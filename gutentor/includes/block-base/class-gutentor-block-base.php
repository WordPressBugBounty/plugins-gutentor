<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_Block_Base' ) ) {

	/**
	 * Base Class For Gutentor for common functions
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */
	class Gutentor_Block_Base {

		/**
		 * Add attributes to register_block_type
		 *
		 * @access protected
		 * @since 3.2.6
		 * @var array
		 */
		protected $register_block_type_args = array();

		/**
		 * Name of the block handled by the concrete subclass.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = '';

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid artefacts and improves performance.
		 *
		 * @static
		 * @access public
		 * @return object
		 * @since 1.0.1
		 */
		public static function get_base_instance() {
			// Store the instance locally to avoid private static replication.

			static $instance = null;

			// Only run these methods if they haven't been ran previously.
			if ( null === $instance ) {
				$instance = new self();
			}

			// Always return the instance.
			return $instance;
		}

		/**
		 * Run Block
		 *
		 * @access public
		 * @return void
		 * @since 1.0.1
		 */
		public function run() {
			if ( method_exists( $this, 'load_dependencies' ) ) {
				$this->load_dependencies();
			}
			if ( method_exists( $this, 'register_block_type_args' ) ) {
				$this->register_block_type_args();
			}
			add_action( 'init', array( $this, 'register_and_render' ) );
		}

		/**
		 * Register this Block
		 * Callback will aut called by this function register_block_type
		 *
		 * @access public
		 * @return void
		 * @since 1.0.1
		 */
		/**
		 * Find the block.json path for this block.
		 * Searches src/ directories based on block_name prefix.
		 *
		 * @return string|null Full path to block.json or null if not found.
		 */
		public function get_block_json_path() {
			$name = $this->block_name;
			// Check dist/src first (for production/WordPress.org), fall back to src/
			$dist_src = GUTENTOR_PATH . 'dist/src';
			$src = GUTENTOR_PATH . 'src';
			$use_src = is_dir( $dist_src ) ? $dist_src : $src;

			// Element blocks: e0, e1, e2... search src/element/ for matching directory
			if ( preg_match( '/^e\d+/', $name ) ) {
				$dirs = glob( $use_src . '/element/' . $name . '-*' );
				if ( ! empty( $dirs ) && file_exists( $dirs[0] . '/block.json' ) ) {
					return $dirs[0] . '/block.json';
				}
			}

			// Module blocks: m0, m4, m5, m10 + child blocks m0-col, m4-col, m6-item, m7-tab
			if ( preg_match( '/^m\d+/', $name ) ) {
				// Child blocks: m6-item → m6-accordion/innerItem, m7-tab → m7-tabs/tab
				$child_map = array(
					'm6-item' => 'm6-accordion/innerItem',
					'm7-tab'  => 'm7-tabs/tab',
				);
				if ( isset( $child_map[ $name ] ) ) {
					$path = $use_src . '/module/' . $child_map[ $name ] . '/block.json';
					if ( file_exists( $path ) ) {
						return $path;
					}
				}

				// Parent blocks: m0 → m0-carousel, m4 → m4-advanced-columns, etc.
				$dirs = glob( $use_src . '/module/' . $name . '-*' );
				if ( ! empty( $dirs ) && file_exists( $dirs[0] . '/block.json' ) ) {
					return $dirs[0] . '/block.json';
				}

				// Some modules have exact name (m8, m9, m11, m12, m13)
				$path = $use_src . '/module/' . $name . '/block.json';
				if ( file_exists( $path ) ) {
					return $path;
				}
			}

			// Post blocks: p1 → p1-blog, p2 → p2, etc.
			if ( preg_match( '/^p\d+/', $name ) ) {
				$dirs = glob( $use_src . '/post/' . $name . '-*' );
				if ( ! empty( $dirs ) && file_exists( $dirs[0] . '/block.json' ) ) {
					return $dirs[0] . '/block.json';
				}
				$path = $use_src . '/post/' . $name . '/block.json';
				if ( file_exists( $path ) ) {
					return $path;
				}
			}

			// Term blocks: t1, t2, t3
			if ( preg_match( '/^t\d+/', $name ) ) {
				$dirs = glob( $use_src . '/term/' . $name . '-*' );
				if ( ! empty( $dirs ) && file_exists( $dirs[0] . '/block.json' ) ) {
					return $dirs[0] . '/block.json';
				}
				$path = $use_src . '/term/' . $name . '/block.json';
				if ( file_exists( $path ) ) {
					return $path;
				}
			}

			// Widget blocks: blog-post, counter-box, gallery, etc.
			// Handle known name mismatches (block_name vs directory name)
			$widget_name_map = array(
				'counter-box' => 'counter',
			);
			$dir_name = isset( $widget_name_map[ $name ] ) ? $widget_name_map[ $name ] : $name;
			$path = $use_src . '/block/' . $dir_name . '/block.json';
			if ( file_exists( $path ) ) {
				return $path;
			}

			return null;
		}

		/**
		 * Register this Block
		 * Uses register_block_type_from_metadata() to read from block.json.
		 *
		 * @access public
		 * @return void
		 * @since 1.0.1
		 */
		public function register_and_render() {
			$block_json_path = $this->get_block_json_path();

			if ( ! $block_json_path ) {
				return; // No block.json found — block registered by JS only
			}

			$args = array();

			if ( method_exists( $this, 'render_callback' ) ) {
				$args['render_callback'] = array( $this, 'render_callback' );
			}

			if ( $this->register_block_type_args ) {
				$args = array_merge( $args, $this->register_block_type_args );
			}

			register_block_type_from_metadata( $block_json_path, $args );
		}

	}
}

/**
 * Return instance of  Gutentor_Block_Base class
 *
 * @since    1.0.0
 */
if ( ! function_exists( 'gutentor_block_base' ) ) {

	function gutentor_block_base() {
		return Gutentor_Block_Base::get_base_instance();
	}
}
