<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Localize images referenced by imported template JSON.
 *
 * Template JSON is fetched server-side and handed to the block editor, which
 * inserts the blocks as they are. Without this pass every media object in the
 * JSON keeps pointing at the source site and carries an attachment ID that is
 * only meaningful there. Images are downloaded into the Media Library and the
 * JSON is rewritten before it reaches the browser.
 *
 * @package Gutentor
 * @since 4.0.7
 */

if ( ! class_exists( 'Gutentor_Import_Media' ) ) {
	/**
	 * Class Gutentor_Import_Media.
	 */
	class Gutentor_Import_Media {

		/**
		 * Attachment meta holding the reuse key of an imported file.
		 *
		 * @var string
		 */
		const SOURCE_META = '_gutentor_import_source_url';

		/**
		 * Attachment meta marking which key format SOURCE_META uses.
		 *
		 * Keys are only comparable within one format; the version is stored next
		 * to the key so a format change can never cause an incorrect reuse.
		 *
		 * @var string
		 */
		const KEY_VERSION_META = '_gutentor_import_source_key_version';

		/**
		 * Current reuse-key format.
		 *
		 * Version 1 (never shipped) lowercased the path, dropped the query and
		 * stripped every -WxH suffix, so it could merge unrelated files. Version 2
		 * preserves path case and query and merges size variants only when the
		 * template declares them as sizes of one media object.
		 *
		 * @var int
		 */
		const KEY_VERSION = 2;

		/**
		 * Attachment meta recording the site an imported file came from.
		 *
		 * Source attachment IDs are only meaningful inside their own origin, so
		 * they are never looked up without it.
		 *
		 * @var string
		 */
		const ORIGIN_META = '_gutentor_import_source_origin';

		/**
		 * Attachment meta recording the source attachment ID.
		 *
		 * @var string
		 */
		const SOURCE_ID_META = '_gutentor_import_source_id';

		/**
		 * Transient prefix used to carry import warnings to the next admin screen.
		 *
		 * @var string
		 */
		const REPORT_TRANSIENT_PREFIX = 'gutentor_import_media_report_';

		/**
		 * Source identity => attachment ID (or WP_Error) for the current request.
		 *
		 * @var array
		 */
		private static $resolved = array();

		/**
		 * Import report for the current request.
		 *
		 * @var array
		 */
		private static $report = array();

		/**
		 * File extensions handled by the localizer.
		 *
		 * SVG is deliberately not supported: WordPress rejects it by default and
		 * the importer must not widen the allowed upload types.
		 *
		 * @var array
		 */
		private static $image_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'avif', 'bmp' );

		/**
		 * Register the warning channel.
		 *
		 * @return void
		 */
		public static function run() {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}

		/**
		 * Localize every image reference found in a decoded template payload.
		 *
		 * The payload is walked in place when it holds objects and copied when it
		 * holds arrays; block structure, block names and inner blocks are never
		 * modified, only the values that reference remote media.
		 *
		 * @param mixed $data Decoded template JSON.
		 * @return array
		 */
		public static function localize( $data ) {
			self::$resolved = array();
			self::$report   = array(
				'localized' => 0,
				'reused'    => 0,
				'failed'    => array(),
			);

			$data   = self::walk( $data, '' );
			$report = self::$report;

			self::$resolved = array();
			self::$report   = array();

			return array(
				'data'   => $data,
				'report' => $report,
			);
		}

		/**
		 * Record import warnings for the current user and fire an action.
		 *
		 * Two channels are kept:
		 * - A request-scoped entry, so the editor can ask for exactly the report
		 *   that belongs to its own import call and show it immediately.
		 * - A per-user entry, read on the next admin screen as a fallback when the
		 *   editor is closed before the immediate notice is rendered.
		 *
		 * @param array  $report     Report returned by localize().
		 * @param string $request_id Caller-supplied id correlating this response.
		 * @return void
		 */
		public static function report( $report, $request_id = '' ) {
			if ( empty( $report['failed'] ) ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( $user_id ) {
				set_transient( self::REPORT_TRANSIENT_PREFIX . $user_id, $report, HOUR_IN_SECONDS );

				$request_id = self::sanitize_request_id( $request_id );
				if ( '' !== $request_id ) {
					set_transient( self::request_key( $user_id, $request_id ), $report, 5 * MINUTE_IN_SECONDS );
				}
			}

			/**
			 * Fires after imported template images were localized.
			 *
			 * @param array $report Import report.
			 */
			do_action( 'gutentor_import_media_report', $report );
		}

		/**
		 * Normalize a caller-supplied request id.
		 *
		 * @param mixed $request_id Raw value.
		 * @return string
		 */
		public static function sanitize_request_id( $request_id ) {
			if ( ! is_string( $request_id ) ) {
				return '';
			}
			$request_id = preg_replace( '/[^A-Za-z0-9_-]/', '', $request_id );
			return substr( (string) $request_id, 0, 64 );
		}

		/**
		 * Transient key for one correlated import request.
		 *
		 * @param int    $user_id    User ID.
		 * @param string $request_id Request id.
		 * @return string
		 */
		private static function request_key( $user_id, $request_id ) {
			return 'gutentor_import_media_req_' . (int) $user_id . '_' . $request_id;
		}

		/**
		 * Fetch and consume the report for one correlated import request.
		 *
		 * @param string $request_id Request id.
		 * @return array
		 */
		public static function take_request_report( $request_id ) {
			$user_id    = get_current_user_id();
			$request_id = self::sanitize_request_id( $request_id );
			if ( ! $user_id || '' === $request_id ) {
				return array();
			}

			$key    = self::request_key( $user_id, $request_id );
			$report = get_transient( $key );
			if ( ! $report ) {
				return array();
			}
			delete_transient( $key );

			return $report;
		}

		/**
		 * Render pending import warnings.
		 *
		 * @return void
		 */
		public static function admin_notice() {
			$user_id = get_current_user_id();
			if ( ! $user_id || ! current_user_can( 'upload_files' ) ) {
				return;
			}

			$key    = self::REPORT_TRANSIENT_PREFIX . $user_id;
			$report = get_transient( $key );
			if ( empty( $report ) ) {
				return;
			}
			delete_transient( $key );

			$remote = $report['failed'];
			if ( empty( $remote ) ) {
				return;
			}

			echo '<div class="notice notice-warning is-dismissible"><p><strong>';
			echo esc_html__( 'Some template images could not be imported.', 'gutentor' );
			echo '</strong> ';
			echo esc_html(
				sprintf(
					/* Translators: %d: number of images left on the source site. */
					_n(
						'%d image is still loaded from the template source site:',
						'%d images are still loaded from the template source site:',
						count( $remote ),
						'gutentor'
					),
					count( $remote )
				)
			);
			echo '</p><ul style="margin-left:1.5em;list-style:disc">';
			foreach ( $remote as $item ) {
				$url     = isset( $item['url'] ) ? $item['url'] : '';
				$message = isset( $item['message'] ) ? $item['message'] : '';
				echo '<li><code>' . esc_html( $url ) . '</code>';
				if ( $message ) {
					echo ' &mdash; ' . esc_html( $message );
				}
				echo '</li>';
			}
			echo '</ul><p>';
			echo esc_html__( 'Upload the missing files to the Media Library and replace them in the editor.', 'gutentor' );
			echo '</p></div>';
		}

		/**
		 * Walk a decoded JSON value and localize media leaves.
		 *
		 * @param mixed  $node Current node.
		 * @param string $key  Key holding the node.
		 * @return mixed
		 */
		private static function walk( $node, $key ) {
			if ( is_string( $node ) ) {
				return self::localize_string( $node, $key );
			}

			if ( is_object( $node ) ) {
				$props = get_object_vars( $node );
				if ( self::is_media_object( $props ) ) {
					return self::localize_media_object( $props );
				}
				foreach ( array_keys( $props ) as $prop ) {
					if ( 'originalContent' === $prop ) {
						continue;
					}
					$node->$prop = self::walk( $node->$prop, $prop );
				}
				return $node;
			}

			if ( is_array( $node ) ) {
				if ( self::is_associative( $node ) && self::is_media_object( $node ) ) {
					return self::localize_media_object( $node );
				}
				foreach ( $node as $child_key => $child ) {
					if ( is_string( $child_key ) && 'originalContent' === $child_key ) {
						continue;
					}
					$node[ $child_key ] = self::walk( $child, is_string( $child_key ) ? $child_key : $key );
				}
				return $node;
			}

			return $node;
		}

		/**
		 * Localize a string leaf.
		 *
		 * @param string $value String value.
		 * @param string $key   Attribute name.
		 * @return string
		 */
		private static function localize_string( $value, $key ) {
			if ( '' === $value ) {
				return $value;
			}

			if ( false !== stripos( $value, '<img' ) || false !== stripos( $value, 'url(' ) ) {
				return self::localize_markup( $value );
			}

			if ( self::is_image_key( $key ) && self::is_remote_image_url( $value ) ) {
				return self::localize_image_url( $value );
			}

			return $value;
		}

		/**
		 * Rewrite image references inside an HTML/CSS string.
		 *
		 * Only <img src>, <img srcset> and url() inside a background declaration
		 * are touched, so links, scripts, fonts and video sources are preserved.
		 * Attribute names are never searched, only markup.
		 *
		 * @param string $value Markup.
		 * @return string
		 */
		private static function localize_markup( $value ) {
			$value = preg_replace_callback( '/<img\b[^>]*>/i', array( __CLASS__, 'localize_img_tag' ), $value );
			$value = preg_replace_callback(
				'/(background(?:-image)?\s*:\s*url\(\s*)([\'"]?)([^\'")]+)(\2\s*\))/i',
				array( __CLASS__, 'localize_css_url' ),
				$value
			);
			return $value;
		}

		/**
		 * Rewrite src/srcset of a single <img> tag.
		 *
		 * A srcset is a declaration that its candidates are variants of one
		 * image, so the set is imported once and every candidate is mapped onto
		 * the closest local sub-size instead of being downloaded separately.
		 *
		 * @param array $matches Regex matches.
		 * @return string
		 */
		private static function localize_img_tag( $matches ) {
			$tag = $matches[0];

			$srcset = '';
			if ( preg_match( '/(\ssrcset\s*=\s*)([\'"])(.*?)\2/i', $tag, $srcset_match ) ) {
				$srcset = $srcset_match[3];
			}

			$set = array(
				'entries'    => array(),
				'attachment' => 0,
				'local'      => array(),
				'map'        => array(),
			);
			if ( '' !== $srcset ) {
				$set = self::localize_srcset_candidates( $srcset );
			}

			/* The src fallback shares the set's attachment when there is one. */
			$src_local = null;
			$tag       = preg_replace_callback(
				'/(\ssrc\s*=\s*)([\'"])(.*?)\2/i',
				function ( $src ) use ( $set, &$src_local ) {
					$key = self::url_key( $src[3] );
					if ( isset( $set['map'][ $key ] ) ) {
						$src_local = self::entry_for_key( $set['entries'], $key );
						return $src[1] . $src[2] . $set['map'][ $key ] . $src[2];
					}
					if ( $set['attachment'] && self::is_remote_image_url( $src[3] ) ) {
						$src_local = self::closest_size(
							$set['local'],
							self::width_from_file_name( $src[3] )
						);
						return $src[1] . $src[2] . $src_local['url'] . $src[2];
					}
					return $src[1] . $src[2] . self::localize_image_url( $src[3] ) . $src[2];
				},
				$tag
			);

			$tag = preg_replace_callback(
				'/(\ssrcset\s*=\s*)([\'"])(.*?)\2/i',
				function ( $srcset_match_inner ) use ( $set, $src_local ) {
					if ( empty( $set['entries'] ) ) {
						$out = array();
						foreach ( explode( ',', $srcset_match_inner[3] ) as $candidate ) {
							$candidate = trim( $candidate );
							if ( '' === $candidate ) {
								continue;
							}
							$parts    = preg_split( '/\s+/', $candidate, 2 );
							$parts[0] = self::localize_image_url( $parts[0] );
							$out[]    = implode( ' ', $parts );
						}
						return $srcset_match_inner[1] . $srcset_match_inner[2] . implode( ', ', $out ) . $srcset_match_inner[2];
					}

					$rendered = array();
					foreach ( $set['entries'] as $entry ) {
						if ( '' === $entry['local_url'] ) {
							continue;
						}

						$descriptor = '';
						if ( 'w' === $entry['type'] ) {
							/* Report the width the local file actually has. */
							$descriptor = $entry['local_width'] > 0
								? $entry['local_width'] . 'w'
								: $entry['source_descriptor'];
						} elseif ( 'x' === $entry['type'] ) {
							$descriptor = self::density_descriptor( $entry, $set['entries'] );
						}

						$candidate = trim( $entry['local_url'] . ' ' . $descriptor );
						if ( ! in_array( $candidate, $rendered, true ) ) {
							$rendered[] = $candidate;
						}
					}

					return $srcset_match_inner[1] . $srcset_match_inner[2] . implode( ', ', $rendered ) . $srcset_match_inner[2];
				},
				$tag
			);

			return $tag;
		}

		/**
		 * Emit a density descriptor relative to the set's smallest local file.
		 *
		 * The `src` attribute points at the smallest resolved file, so that file
		 * defines 1x and every other candidate is expressed against it. Emitting
		 * the quotient of the actual file widths keeps the descriptor true.
		 *
		 * @param array $entry   Entry being rendered.
		 * @param array $entries All entries in the set.
		 * @return string
		 */
		private static function density_descriptor( $entry, $entries ) {
			$base = 0;
			foreach ( $entries as $other ) {
				if ( $other['local_width'] > 0 && ( 0 === $base || $other['local_width'] < $base ) ) {
					$base = $other['local_width'];
				}
			}

			if ( $base <= 0 || $entry['local_width'] <= 0 ) {
				return $entry['source_descriptor'];
			}

			$density = round( $entry['local_width'] / $base, 2 );
			$text    = rtrim( rtrim( number_format( $density, 2, '.', '' ), '0' ), '.' );

			return ( '' === $text ? '1' : $text ) . 'x';
		}

		/**
		 * Read the pixel width encoded in a WordPress style file name.
		 *
		 * @param string $url URL or file name.
		 * @return int
		 */
		private static function width_from_file_name( $url ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( preg_match( '/(?:^|[-_])(\d{2,5})x(\d{2,5})(?=\.[a-z0-9]+$)/i', $path, $m ) ) {
				return (int) $m[1];
			}
			return 0;
		}

		/**
		 * Find the resolved entry for a source URL key.
		 *
		 * @param array  $entries Entries.
		 * @param string $key     Source URL key.
		 * @return array|null
		 */
		private static function entry_for_key( $entries, $key ) {
			foreach ( $entries as $entry ) {
				if ( $entry['key'] === $key ) {
					return array( 'url' => $entry['local_url'], 'width' => $entry['local_width'] );
				}
			}
			return null;
		}

		/**
		 * Import one srcset as a single image and resolve its candidates.
		 *
		 * @param string $srcset Raw srcset attribute value.
		 * @return array
		 */
		private static function localize_srcset_candidates( $srcset ) {
			$candidates = array();

			foreach ( explode( ',', $srcset ) as $candidate ) {
				$candidate = trim( $candidate );
				if ( '' === $candidate ) {
					continue;
				}
				$parts = preg_split( '/\s+/', $candidate, 2 );
				if ( ! self::is_remote_image_url( $parts[0] ) ) {
					continue;
				}

				$descriptor = isset( $parts[1] ) ? strtolower( trim( (string) $parts[1] ) ) : '';
				$type       = '';
				$value      = 0;
				if ( preg_match( '/^(\d+)w$/', $descriptor, $m ) ) {
					$type  = 'w';
					$value = (int) $m[1];
				} elseif ( preg_match( '/^(\d+(?:\.\d+)?)x$/', $descriptor, $m ) ) {
					$type  = 'x';
					$value = (float) $m[1];
				}

				$candidates[ self::url_key( $parts[0] ) ] = array(
					'url'               => $parts[0],
					'type'              => $type,
					'value'             => $value,
					'source_descriptor' => $descriptor,
				);
			}

			$empty = array(
				'entries'    => array(),
				'attachment' => 0,
				'local'      => array(),
				'map'        => array(),
			);

			if ( empty( $candidates ) ) {
				return $empty;
			}

			/*
			 * A srcset must not mix descriptor types; if the source does, treat
			 * the set as widths, which is what every current catalogue uses.
			 */
			$types = array_unique( array_filter( array_column( $candidates, 'type' ) ) );
			if ( count( $types ) > 1 ) {
				foreach ( $candidates as $key => $candidate ) {
					$candidates[ $key ]['type']              = 'w';
					$candidates[ $key ]['value']             = self::width_from_file_name( $candidate['url'] );
					$candidates[ $key ]['source_descriptor'] = $candidates[ $key ]['value'] ? $candidates[ $key ]['value'] . 'w' : '';
				}
			}

			/* Largest declared candidate is the best local source. */
			$best      = '';
			$best_rank = -1;
			foreach ( $candidates as $candidate ) {
				$rank = 'w' === $candidate['type'] && $candidate['value'] > 0
					? (int) $candidate['value']
					: (int) round( $candidate['value'] * 1000 );
				if ( $rank > $best_rank ) {
					$best_rank = $rank;
					$best      = $candidate['url'];
				}
			}

			$attachment_id = self::get_attachment_id( $best, '', '', array_keys( $candidates ) );
			if ( is_wp_error( $attachment_id ) ) {
				return $empty;
			}

			$local = wp_prepare_attachment_for_js( $attachment_id );
			if ( ! $local || ! is_array( $local ) ) {
				$url = wp_get_attachment_url( $attachment_id );
				return array(
					'entries'    => array(),
					'attachment' => (int) $attachment_id,
					'local'      => array( 'url' => $url, 'width' => 0, 'sizes' => array() ),
					'map'        => array(),
				);
			}

			$entries = array();
			$map     = array();

			/*
			 * Density descriptors are relative, not absolute: the src is the 1x
			 * image and 2x must resolve to roughly twice that. Absolute widths are
			 * unknown for density sets, so candidates are ranked against each
			 * other and the largest density receives the largest local file.
			 */
			$types       = array_values( array_unique( array_filter( array_column( $candidates, 'type' ) ) ) );
			$density_set = ( 1 === count( $types ) && 'x' === $types[0] );
			$max_density = 1;
			if ( $density_set ) {
				foreach ( $candidates as $candidate ) {
					$max_density = max( $max_density, (float) $candidate['value'] );
				}
			}
			$full_width = self::file_width( isset( $local['url'] ) ? $local['url'] : '' );
			if ( ! $full_width && isset( $local['width'] ) ) {
				$full_width = (int) $local['width'];
			}

			foreach ( $candidates as $key => $candidate ) {
				$want = 0;
				if ( 'w' === $candidate['type'] && $candidate['value'] > 0 ) {
					$want = (int) $candidate['value'];
				} elseif ( $density_set && $candidate['value'] > 0 && $full_width > 0 ) {
					$want = (int) round( $full_width * ( (float) $candidate['value'] / $max_density ) );
				}

				$resolved = self::closest_size( $local, $want );

				$entries[] = array(
					'key'               => $key,
					'type'              => $candidate['type'],
					'source_descriptor' => $candidate['source_descriptor'],
					'local_url'         => $resolved['url'],
					'local_width'       => $resolved['width'],
				);
				$map[ $key ] = $resolved['url'];
			}

			return array(
				'entries'    => $entries,
				'attachment' => (int) $attachment_id,
				'local'      => $local,
				'map'        => $map,
			);
		}

		/**
		 * Pick the local size closest to a wanted pixel width.
		 *
		 * `wp_prepare_attachment_for_js()` caps `sizes.large` at the site's
		 * large_size_w, so its reported width can be smaller than the file it
		 * points at. Descriptors are emitted for the file actually served, so the
		 * real dimensions are read from disk instead.
		 *
		 * @param array $local Local attachment data.
		 * @param int   $want  Wanted width in pixels.
		 * @return array { url, width }
		 */
		private static function closest_size( $local, $want ) {
			$full = array(
				'url'   => isset( $local['url'] ) ? $local['url'] : '',
				'width' => self::file_width( isset( $local['url'] ) ? $local['url'] : '' ),
			);
			if ( ! $full['width'] && isset( $local['width'] ) ) {
				$full['width'] = (int) $local['width'];
			}

			$sizes = ( isset( $local['sizes'] ) && is_array( $local['sizes'] ) ) ? $local['sizes'] : array();
			if ( empty( $sizes ) || ! $want ) {
				return $full;
			}

			$best       = $full;
			$best_delta = abs( $full['width'] - $want );
			foreach ( $sizes as $size ) {
				if ( empty( $size['url'] ) ) {
					continue;
				}
				$width = self::file_width( $size['url'] );
				if ( ! $width ) {
					continue;
				}
				$delta = abs( $width - $want );
				if ( $delta < $best_delta ) {
					$best_delta = $delta;
					$best       = array( 'url' => $size['url'], 'width' => $width );
				}
			}

			return $best;
		}

		/**
		 * Real pixel width of a local uploaded image.
		 *
		 * @param string $url Attachment or sub-size URL.
		 * @return int
		 */
		private static function file_width( $url ) {
			if ( '' === $url ) {
				return 0;
			}

			$uploads = wp_get_upload_dir();
			if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
				return 0;
			}

			$path = strtok( $url, '?' );
			$path = str_replace( $uploads['baseurl'], $uploads['basedir'], $path );
			if ( ! file_exists( $path ) ) {
				return 0;
			}

			$size = wp_getimagesize( $path );
			return ( $size && isset( $size[0] ) ) ? (int) $size[0] : 0;
		}

		/**
		 * Rewrite the URL of a background-image declaration.
		 *
		 * @param array $matches Regex matches.
		 * @return string
		 */
		private static function localize_css_url( $matches ) {
			return $matches[1] . $matches[2] . self::localize_image_url( $matches[3] ) . $matches[4];
		}

		/**
		 * Localize one image URL, keeping the original on failure.
		 *
		 * @param string $url Image URL.
		 * @return string
		 */
		private static function localize_image_url( $url ) {
			$url = trim( (string) $url );
			if ( ! self::is_remote_image_url( $url ) ) {
				return $url;
			}

			$attachment_id = self::get_attachment_id( $url );
			if ( is_wp_error( $attachment_id ) ) {
				return $url;
			}

			$local_url = wp_get_attachment_url( $attachment_id );
			return $local_url ? $local_url : $url;
		}

		/**
		 * Whether an attribute name is expected to hold an image URL.
		 *
		 * @param string $key Attribute name.
		 * @return bool
		 */
		private static function is_image_key( $key ) {
			if ( '' === $key ) {
				return false;
			}
			if ( preg_match( '/(link|href|video|audio|iframe|script|font|icon)/i', $key ) ) {
				return false;
			}
			return (bool) preg_match( '/(img|image|photo|thumb|logo|avatar|background|bg|media)/i', $key );
		}

		/**
		 * Whether a decoded node looks like a WordPress media/attachment object.
		 *
		 * @param array $props Node properties.
		 * @return bool
		 */
		private static function is_media_object( $props ) {
			if ( empty( $props['url'] ) || ! is_string( $props['url'] ) ) {
				return false;
			}

			if ( isset( $props['mime'] ) && is_string( $props['mime'] ) ) {
				if ( 0 === stripos( $props['mime'], 'video/' ) || 0 === stripos( $props['mime'], 'audio/' ) ) {
					return false;
				}
			}
			if ( isset( $props['type'] ) && is_string( $props['type'] ) && in_array( $props['type'], array( 'video', 'audio' ), true ) ) {
				return false;
			}

			if ( ! self::is_remote_image_url( $props['url'] ) ) {
				return false;
			}

			if ( isset( $props['sizes'] ) && ! empty( self::to_array( $props['sizes'] ) ) ) {
				return true;
			}

			foreach ( array( 'id', 'mime', 'filename', 'subtype' ) as $marker ) {
				if ( isset( $props[ $marker ] ) ) {
					return true;
				}
			}

			/* Reduced objects such as {url} or {url, alt}. */
			return count( $props ) <= 3;
		}

		/**
		 * Rewrite a media object so it describes a local attachment.
		 *
		 * A media object that declares `sizes` states, as part of the source
		 * contract, which URLs are variants of one source attachment. That
		 * relationship — and the source attachment ID when present — is used to
		 * resolve the object, never a guess made from the file name.
		 *
		 * @param array $props Node properties.
		 * @return array
		 */
		private static function localize_media_object( $props ) {
			$source        = $props['url'];
			$alt           = isset( $props['alt'] ) && is_string( $props['alt'] ) ? $props['alt'] : '';
			$title         = isset( $props['title'] ) && is_string( $props['title'] ) ? $props['title'] : '';
			$is_rich       = isset( $props['sizes'] ) || isset( $props['mime'] ) || isset( $props['id'] ) || isset( $props['filename'] );

			$variants      = self::declared_variants( $props );
			$download_url  = self::largest_source_url( $variants );
			if ( '' === $download_url ) {
				$download_url = $source;
			}

			$keys = self::identity_keys( $props, $variants );
			$source_id = isset( $props['id'] ) ? absint( $props['id'] ) : 0;
			$attachment_id = self::get_attachment_id( $download_url, $alt, $title, $keys, $source_id );

			if ( is_wp_error( $attachment_id ) ) {
				/*
				 * Keep the remote URL so the block still renders, but never keep
				 * the source attachment ID: it may belong to an unrelated local
				 * attachment.
				 */
				if ( isset( $props['id'] ) ) {
					$props['id'] = 0;
				}
				return $props;
			}

			if ( ! $is_rich ) {
				$local_url = wp_get_attachment_url( $attachment_id );
				if ( $local_url ) {
					$props['url'] = $local_url;
					$props['id']  = (int) $attachment_id;
				}
				return $props;
			}

			$local = wp_prepare_attachment_for_js( $attachment_id );
			if ( ! $local || ! is_array( $local ) ) {
				return $props;
			}

			$result = array_merge( $props, $local );

			$source_sizes    = isset( $props['sizes'] ) ? self::to_array( $props['sizes'] ) : array();
			$local_sizes     = isset( $local['sizes'] ) && is_array( $local['sizes'] ) ? $local['sizes'] : array();
			$result['sizes'] = self::merge_sizes( $source_sizes, $local_sizes );

			if ( '' !== $alt ) {
				$result['alt'] = $alt;
			}

			/* Stale administration links to the source site. */
			unset( $result['uploadedToLink'], $result['uploadedToTitle'] );

			return $result;
		}

		/**
		 * Collect the URLs a media object declares as variants of one file.
		 *
		 * Only declared `sizes` entries are collected: two URLs whose names
		 * happen to differ by a dimension suffix are not related unless the
		 * template says so.
		 *
		 * @param array $props Media object properties.
		 * @return array List of array( 'url', 'width', 'height' ).
		 */
		private static function declared_variants( $props ) {
			$variants = array();

			if ( isset( $props['url'] ) && is_string( $props['url'] ) ) {
				$variants[] = array(
					'url'    => $props['url'],
					'width'  => isset( $props['width'] ) ? (int) $props['width'] : 0,
					'height' => isset( $props['height'] ) ? (int) $props['height'] : 0,
				);
			}

			foreach ( self::to_array( isset( $props['sizes'] ) ? $props['sizes'] : array() ) as $size ) {
				$size = self::to_array( $size );
				if ( isset( $size['url'] ) && is_string( $size['url'] ) ) {
					$variants[] = array(
						'url'    => $size['url'],
						'width'  => isset( $size['width'] ) ? (int) $size['width'] : 0,
						'height' => isset( $size['height'] ) ? (int) $size['height'] : 0,
					);
				}
			}

			return $variants;
		}

		/**
		 * Pick the highest-resolution URL the object declares.
		 *
		 * The first URL encountered may be a thumbnail, and a thumbnail must never
		 * become the only local copy for full/large uses. When dimensions are
		 * absent the object's own `url` is kept, which is its main image.
		 *
		 * @param array $variants Declared variants.
		 * @return string
		 */
		private static function largest_source_url( $variants ) {
			$best_url  = '';
			$best_area = -1;

			foreach ( $variants as $variant ) {
				if ( ! self::is_remote_image_url( $variant['url'] ) ) {
					continue;
				}
				$area = $variant['width'] * $variant['height'];
				if ( $area > $best_area ) {
					$best_area = $area;
					$best_url  = $variant['url'];
				}
			}

			return $best_url;
		}

		/**
		 * Build every reuse key a media object is known by.
		 *
		 * The relation key exists only when the template names a source
		 * attachment ID, and always carries the origin, because a source
		 * attachment ID is only meaningful inside its own site.
		 *
		 * @param array $props    Media object properties.
		 * @param array $variants Declared variants.
		 * @return array
		 */
		private static function identity_keys( $props, $variants ) {
			$keys = array();

			$source_id = isset( $props['id'] ) ? absint( $props['id'] ) : 0;
			$origin    = '';
			if ( isset( $props['url'] ) && is_string( $props['url'] ) ) {
				$origin = self::source_origin( $props['url'] );
			}

			if ( $source_id && '' !== $origin ) {
				$keys[] = self::relation_key( $origin, $source_id );
			}

			foreach ( $variants as $variant ) {
				$keys[] = self::url_key( $variant['url'] );
			}

			return array_values( array_unique( array_filter( $keys ) ) );
		}

		/**
		 * Reuse key for an exact source URL.
		 *
		 * The path keeps its case and the query is preserved: both select a
		 * different file. Only the host is normalized, because host names are
		 * case-insensitive.
		 *
		 * @param string $url Source URL.
		 * @return string
		 */
		private static function url_key( $url ) {
			$parts = wp_parse_url( trim( (string) $url ) );
			if ( empty( $parts['host'] ) ) {
				return '';
			}

			$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
			$origin = strtolower( $parts['host'] );
			if ( ! empty( $parts['port'] ) ) {
				$origin .= ':' . $parts['port'];
			}

			$path  = isset( $parts['path'] ) ? $parts['path'] : '';
			$query = isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '';

			return self::key( 'url', $scheme . '://' . $origin . $path . $query );
		}

		/**
		 * Reuse key for a source attachment ID inside one origin.
		 *
		 * @param string $origin    Source origin.
		 * @param int    $source_id Source attachment ID.
		 * @return string
		 */
		private static function relation_key( $origin, $source_id ) {
			return self::key( 'src', $origin . '#' . absint( $source_id ) );
		}

		/**
		 * Prefix a key with the current key format.
		 *
		 * Keys only compare equal within one format, so a stored key from an
		 * older format can never be mistaken for a current one.
		 *
		 * @param string $kind Key kind.
		 * @param string $body Key body.
		 * @return string
		 */
		private static function key( $kind, $body ) {
			return self::KEY_VERSION . ':' . $kind . ':' . $body;
		}

		/**
		 * Origin of a source URL: scheme-less host and port.
		 *
		 * @param string $url Source URL.
		 * @return string
		 */
		private static function source_origin( $url ) {
			$parts = wp_parse_url( trim( (string) $url ) );
			if ( empty( $parts['host'] ) ) {
				return '';
			}

			$origin = strtolower( $parts['host'] );
			if ( ! empty( $parts['port'] ) ) {
				$origin .= ':' . $parts['port'];
			}

			return $origin;
		}

		/**
		 * Preserve the size keys the block expects, using local files.
		 *
		 * A block may request any size that existed on the source site, so every
		 * original key must still resolve. Missing local sizes fall back to full.
		 *
		 * @param array $source_sizes Sizes from the template.
		 * @param array $local_sizes  Sizes of the local attachment.
		 * @return array
		 */
		private static function merge_sizes( $source_sizes, $local_sizes ) {
			$merged = array();
			foreach ( $source_sizes as $name => $size ) {
				if ( isset( $local_sizes[ $name ] ) ) {
					$merged[ $name ] = $local_sizes[ $name ];
				} elseif ( isset( $local_sizes['full'] ) ) {
					$merged[ $name ] = $local_sizes['full'];
				}
			}
			if ( ! isset( $merged['full'] ) && isset( $local_sizes['full'] ) ) {
				$merged['full'] = $local_sizes['full'];
			}
			return $merged;
		}

		/**
		 * Resolve a remote image to a local attachment, downloading it once.
		 *
		 * @param string $url   URL to download.
		 * @param string $alt   Alt text supplied by the template.
		 * @param string $title Title supplied by the template.
		 * @param array  $keys  Every reuse key this source is known by.
		 * @param int    $source_id Source attachment ID declared by the template.
		 * @return int|\WP_Error
		 */
		private static function get_attachment_id( $url, $alt = '', $title = '', $keys = array(), $source_id = 0 ) {
			if ( empty( $keys ) ) {
				$keys = array( self::url_key( $url ) );
			}
			$keys = array_values( array_unique( array_filter( $keys ) ) );
			sort( $keys );
			$cache_key = md5( implode( '|', $keys ) );

			if ( isset( self::$resolved[ $cache_key ] ) ) {
				$cached = self::$resolved[ $cache_key ];
				if ( ! is_wp_error( $cached ) ) {
					++self::$report['reused'];
				}
				return $cached;
			}

			$existing = self::find_existing_attachment( $keys );
			if ( $existing ) {
				self::$resolved[ $cache_key ] = $existing;
				++self::$report['reused'];
				return $existing;
			}

			$result                       = self::download_attachment( $url, $alt, $title, $keys, $source_id );
			self::$resolved[ $cache_key ] = $result;

			return $result;
		}

		/**
		 * Find an attachment previously imported from any of these keys.
		 *
		 * The key format is part of the lookup, so a mapping written by an older
		 * format can never be matched by a current key.
		 *
		 * @param array $keys Reuse keys.
		 * @return int
		 */
		private static function find_existing_attachment( $keys ) {
			if ( empty( $keys ) ) {
				return 0;
			}

			$found = get_posts(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'suppress_filters'       => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'     => self::KEY_VERSION_META,
							'value'   => self::KEY_VERSION,
							'compare' => '=',
						),
						array(
							'key'     => self::SOURCE_META,
							'value'   => $keys,
							'compare' => 'IN',
						),
					),
				)
			);

			if ( empty( $found ) ) {
				return 0;
			}

			$attachment_id = (int) $found[0];

			/* The mapping outlived the file: drop it and import again. */
			$file = get_attached_file( $attachment_id );
			if ( ! $file || ! file_exists( $file ) ) {
				self::forget_mapping( $attachment_id );
				return 0;
			}

			return $attachment_id;
		}

		/**
		 * Drop the mapping of one attachment when its stored key cannot be reused.
		 *
		 * Scoped to the attachment in hand: no site-wide metadata sweep runs on
		 * init, because the earlier key format was never shipped, so there is no
		 * legacy population to migrate.
		 *
		 * @param int $attachment_id Attachment ID.
		 * @return void
		 */
		private static function forget_mapping( $attachment_id ) {
			delete_post_meta( $attachment_id, self::SOURCE_META );
			delete_post_meta( $attachment_id, self::KEY_VERSION_META );
		}

		/**
		 * Download a remote image and create the attachment.
		 *
		 * @param string $url   URL to download.
		 * @param string $alt   Alt text supplied by the template.
		 * @param string $title Title supplied by the template.
		 * @param array  $keys  Reuse keys to record.
		 * @param int    $source_id Source attachment ID declared by the template.
		 * @return int|\WP_Error
		 */
		private static function download_attachment( $url, $alt, $title, $keys, $source_id = 0 ) {
			$url = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( empty( $url ) || ! wp_http_validate_url( $url ) ) {
				return self::fail( $url, __( 'Invalid image URL.', 'gutentor' ) );
			}

			/*
			 * Importing a template is an edit_posts action, but writing to the
			 * Media Library requires upload_files, and wp_handle_sideload()
			 * enforces neither. Capability is checked here so an import can never
			 * widen the caller's privileges.
			 */
			if ( ! current_user_can( 'upload_files' ) ) {
				return self::fail( $url, __( 'You are not allowed to upload media.', 'gutentor' ) );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$max = (int) apply_filters( 'gutentor_import_media_max_bytes', 15 * MB_IN_BYTES );

			/*
			 * download_url() takes no size argument, so the ceiling is applied on
			 * the HTTP request itself. WP_Http_Curl and WP_Http_Streams both stop
			 * writing at limit_response_size, which bounds the transfer instead of
			 * rejecting the file only after it has been fully downloaded.
			 */
			$limit = function ( $args, $url ) use ( $max ) {
				if ( $max > 0 ) {
					$args['limit_response_size'] = $max;
				}
				return $args;
			};
			add_filter( 'http_request_args', $limit, 10, 2 );

			/* wp_safe_remote_get() is used inside download_url(). */
			$tmp = download_url( $url, 30 );

			remove_filter( 'http_request_args', $limit, 10 );

			if ( is_wp_error( $tmp ) ) {
				return self::fail( $url, $tmp->get_error_message() );
			}

			$size = filesize( $tmp );

			/*
			 * A truncated transfer means the source exceeded the cap; report that
			 * rather than a generic empty-file error.
			 */
			if ( $max > 0 && ( $size <= 0 || $size >= $max ) ) {
				wp_delete_file( $tmp );
				return self::fail(
					$url,
					sprintf(
						/* Translators: %s: maximum allowed file size. */
						__( 'The file is larger than the %s import limit.', 'gutentor' ),
						size_format( $max )
					)
				);
			}
			if ( $size <= 0 ) {
				wp_delete_file( $tmp );
				return self::fail( $url, __( 'The downloaded file is empty.', 'gutentor' ) );
			}

			$mime = wp_get_image_mime( $tmp );
			if ( ! $mime || 0 !== strpos( $mime, 'image/' ) ) {
				wp_delete_file( $tmp );
				return self::fail( $url, __( 'The downloaded file is not an image.', 'gutentor' ) );
			}
			if ( ! in_array( $mime, get_allowed_mime_types(), true ) ) {
				wp_delete_file( $tmp );
				return self::fail( $url, __( 'This image type is not allowed in the Media Library.', 'gutentor' ) );
			}

			$file_array = array(
				'name'     => self::file_name( $url, $mime ),
				'tmp_name' => $tmp,
			);

			/* An empty description would blank the attachment title. */
			$description = '' !== $title ? sanitize_text_field( $title ) : null;

			$attachment_id = media_handle_sideload( $file_array, 0, $description );
			if ( is_wp_error( $attachment_id ) ) {
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				return self::fail( $url, $attachment_id->get_error_message() );
			}

			update_post_meta( $attachment_id, self::SOURCE_META, $keys[0] );
			update_post_meta( $attachment_id, self::KEY_VERSION_META, self::KEY_VERSION );
			update_post_meta( $attachment_id, self::ORIGIN_META, self::source_origin( $url ) );
			if ( $source_id ) {
				update_post_meta( $attachment_id, self::SOURCE_ID_META, absint( $source_id ) );
			}
			if ( '' !== $alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
			}

			/* Every alias of a multi-size source is recorded, so any of them reuses it. */
			foreach ( array_slice( $keys, 1 ) as $alias ) {
				add_post_meta( $attachment_id, self::SOURCE_META, $alias );
			}

			++self::$report['localized'];

			return (int) $attachment_id;
		}

		/**
		 * Build a safe file name with the extension matching the real MIME type.
		 *
		 * @param string $url  Source URL.
		 * @param string $mime Verified MIME type.
		 * @return string
		 */
		private static function file_name( $url, $mime ) {
			$extensions = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
				'image/avif' => 'avif',
				'image/bmp'  => 'bmp',
			);
			$extension  = isset( $extensions[ $mime ] ) ? $extensions[ $mime ] : 'jpg';

			$path = wp_parse_url( $url, PHP_URL_PATH );
			$name = sanitize_file_name( wp_basename( (string) $path ) );
			$name = pathinfo( $name, PATHINFO_FILENAME );

			if ( '' === $name ) {
				$name = 'imported-image';
			}

			return $name . '.' . $extension;
		}

		/**
		 * Whether a URL points at an image file that is not already local.
		 *
		 * @param string $url URL.
		 * @return bool
		 */
		private static function is_remote_image_url( $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				return false;
			}

			$url = trim( $url );
			if ( 0 === strpos( $url, '//' ) ) {
				$url = set_url_scheme( $url );
			}
			if ( 0 !== stripos( $url, 'http://' ) && 0 !== stripos( $url, 'https://' ) ) {
				return false;
			}

			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( empty( $host ) ) {
				return false;
			}

			$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $home_host && strtolower( $host ) === strtolower( $home_host ) ) {
				return false;
			}

			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

			return in_array( $ext, self::$image_extensions, true );
		}

		/**
		 * Normalize a decoded JSON value to an array.
		 *
		 * Gutentor decodes template JSON without the associative flag, so nested
		 * objects reach this class as stdClass instances.
		 *
		 * @param mixed $value Decoded value.
		 * @return array
		 */
		private static function to_array( $value ) {
			if ( is_array( $value ) ) {
				return $value;
			}
			if ( is_object( $value ) ) {
				return get_object_vars( $value );
			}
			return array();
		}

		/**
		 * Whether an array uses string keys.
		 *
		 * @param array $value Array.
		 * @return bool
		 */
		private static function is_associative( $value ) {
			if ( ! is_array( $value ) || empty( $value ) ) {
				return false;
			}
			return array_keys( $value ) !== range( 0, count( $value ) - 1 );
		}

		/**
		 * Record a failed download.
		 *
		 * @param string $url     Source URL.
		 * @param string $message Reason.
		 * @return \WP_Error
		 */
		private static function fail( $url, $message ) {
			self::$report['failed'][] = array(
				'url'     => $url,
				'message' => $message,
			);

			return new WP_Error( 'gutentor_import_media_failed', $message, array( 'url' => $url ) );
		}
	}
}

Gutentor_Import_Media::run();
