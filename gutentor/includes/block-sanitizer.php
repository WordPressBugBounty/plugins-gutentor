<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Block sanitizer: per-attribute input sanitization + lightweight output escaping.
 *
 * Replaces the blanket wp_kses() approach with a two-phase system:
 *   Phase 1 (render_block_data): Sanitize each attribute by its expected type
 *   Phase 2 (render_block):       Strip only known-bad patterns from final HTML
 *
 * @package Gutentor
 */

// ─────────────────────────────────────────────────────────────
// Phase 1: Per-attribute sanitization before block rendering
// ─────────────────────────────────────────────────────────────

add_filter( 'render_block_data', 'gutentor_sanitize_block_attrs', 10, 3 );

/**
 * Sanitize block attributes before rendering.
 *
 * Fires BEFORE rendering — operates on parsed block attrs, not on final HTML.
 * Each attribute gets the right sanitizer based on its name + schema.
 *
 * Pattern ORDER is intentional and tested:
 *   SVG (most restrictive) → URL → Rich Text (schema-backed) → Color → Plain text (default)
 *
 * @param array $parsed_block The parsed block data.
 * @param array $source_block Unmodified source block.
 * @param array $parent_block Parent block (unused).
 * @return array
 */
function gutentor_sanitize_block_attrs( $parsed_block, $source_block, $parent_block ) {
	if ( empty( $parsed_block['blockName'] ) || strpos( $parsed_block['blockName'], 'gutentor' ) !== 0 ) {
		return $parsed_block;
	}

	$schema = array();
	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $parsed_block['blockName'] );
	$has_block_schema = $block_type && ! empty( $block_type->attributes );
	if ( $has_block_schema ) {
		$schema = $block_type->attributes;
	}

	$known_rich_text = gutentor_get_known_rich_text_attrs();

	foreach ( $parsed_block['attrs'] as $key => $value ) {
		$attr_schema = isset( $schema[ $key ] ) ? $schema[ $key ] : array();
		$parsed_block['attrs'][ $key ] = gutentor_sanitize_attr(
			$value,
			$key,
			$attr_schema,
			$known_rich_text,
			$has_block_schema
		);
	}

	return $parsed_block;
}

/**
 * Known rich-text attribute names and dotted paths across all Gutentor blocks.
 *
 * Compiled from every "source": "html" declaration in all 78 block.json files.
 * Top-level entries are attribute names.
 * Nested entries are dotted paths through arrays (e.g. 'blockSortableItems.title').
 *
 * Only consulted for JS-only blocks (no PHP registration).
 * For PHP-registered blocks, schema is authoritative.
 *
 * @return array
 */
function gutentor_get_known_rich_text_attrs() {
	return array(
		// Top-level (standalone string attrs with source: html)
		'e0AT',          // gutentor/e0 (Simple Text)
		'e1AT',          // gutentor/e1 (Advanced Text)
		'e2Text',        // gutentor/e2 (Button)
		'e18Txt',        // gutentor/e18 (Filter Item)
		'e19AllTxt',     // gutentor/e19 (Filter Items)
		'blockComponentTitle', // 27 widget blocks (accordion, pricing, etc.)

		// Nested array paths (blockSortableItems[].{field})
		'blockSortableItems.title',
		'blockSortableItems.desc',
		'blockSortableItems.subTitle',
		'blockSortableItems.price',
		'blockSortableItems.designation',
		'blockSortableItems.number',
		'blockSortableItems.duration',
		'blockSortableItems.heading',
		'blockSortableItems.openingClosingTime',
		'blockSortableItems.blockTabListText',
		'blockSortableItems.blockTabDescText',
		'e7SortableItem.title',
	);
}

/**
 * Sanitize a single attribute value.
 *
 * @param mixed  $value            The attribute value.
 * @param string $key              The attribute name.
 * @param array  $schema           The attribute schema (from WP_Block_Type or empty).
 * @param array  $known_rich_text  Known rich-text attribute names/paths.
 * @param bool   $has_block_schema Whether the block has PHP registration schema.
 * @return mixed
 */
function gutentor_sanitize_attr( $value, $key, $schema, $known_rich_text, $has_block_schema = false ) {
	if ( is_array( $value ) ) {
		return gutentor_sanitize_array_attr( $value, $key, $known_rich_text );
	}

	if ( ! is_string( $value ) ) {
		return $value;
	}

	if ( preg_match( '/cSVG|svg|SVG/i', $key ) ) {
		return gutentor_sanitize_svg( $value );
	}

	if ( preg_match( '/(Url|Link|Src)$/i', $key ) ) {
		return esc_url_raw( $value );
	}

	$has_html_source = isset( $schema['source'] ) && 'html' === $schema['source'];
	$is_known_rich_text = in_array( $key, $known_rich_text, true );
	$is_rich_text = $has_html_source || ( ! $has_block_schema && $is_known_rich_text );
	if ( $is_rich_text ) {
		return wp_kses_post( $value );
	}

	if ( preg_match( '/[Cc]olor|[Bb]g|[Bb][Gg]/', $key ) ) {
		return gutentor_sanitize_color_value( $value );
	}

	return sanitize_text_field( $value );
}

/**
 * Recursively sanitize array/object attributes.
 *
 * Schema is NOT passed down: nested attributes don't carry "source" info,
 * and naming conventions apply identically at every depth.
 *
 * Rich-text detection uses dotted paths (e.g. "blockSortableItems.title")
 * matched against $known_rich_text. Indexed array keys are stripped from
 * the prefix so blockSortableItems[0].title maps to "blockSortableItems.title".
 *
 * @param array  $array           The attribute value (array).
 * @param string $key_prefix      Dotted path prefix from parent attribute.
 * @param array  $known_rich_text Set of known rich-text dotted paths.
 * @return array
 */
function gutentor_sanitize_array_attr( $array, $key_prefix, $known_rich_text = array() ) {
	$result          = array();
	$this_is_indexed = ! empty( $array )
		&& array_keys( $array ) === range( 0, count( $array ) - 1 );

	foreach ( $array as $k => $v ) {
		$child_key = $this_is_indexed ? $key_prefix : $key_prefix . '.' . $k;

		if ( is_string( $v ) ) {
			if ( in_array( $child_key, $known_rich_text, true ) ) {
				$result[ $k ] = wp_kses_post( $v );
			} elseif ( preg_match( '/[Cc]olor|[Bb]g|[Bb][Gg]/', $k ) ) {
				$result[ $k ] = gutentor_sanitize_color_value( $v );
			} elseif ( preg_match( '/(Url|Link|Src)$/i', $k ) ) {
				$result[ $k ] = esc_url_raw( $v );
			} elseif ( preg_match( '/cSVG|svg|SVG/i', $k ) ) {
				$result[ $k ] = gutentor_sanitize_svg( $v );
			} else {
				$result[ $k ] = sanitize_text_field( $v );
			}
		} elseif ( is_array( $v ) ) {
			$result[ $k ] = gutentor_sanitize_array_attr( $v, $child_key, $known_rich_text );
		} else {
			$result[ $k ] = $v;
		}
	}

	return $result;
}

/**
 * Sanitize a color value.
 *
 * Accepts: hex (#xxx), rgb/rgba, hsl/hsla, var(--...), named CSS colors.
 * Non-matching values safely fall through to sanitize_text_field().
 *
 * @param string $color Color value.
 * @return string
 */
function gutentor_sanitize_color_value( $color ) {
	if ( empty( $color ) ) {
		return '';
	}
	$color = trim( $color );

	if ( strpos( $color, 'var(' ) === 0 ) {
		if ( preg_match( '/^var\(--[\w-]+(?:,\s*(?:[^()]*|\([^()]*\))*)?\)$/', $color ) ) {
			return $color;
		}
		return '';
	}

	if ( strpos( $color, '#' ) === 0 ) {
		$sanitized = sanitize_hex_color( $color );
		return $sanitized ?: '';
	}

	if ( preg_match( '/^rgba?\s*\(/', $color ) ) {
		if ( preg_match( '/^rgba?\s*\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*(?:\d+\.?\d*|\.\d+)\s*)?\)$/', $color ) ) {
			return $color;
		}
		return '';
	}

	if ( preg_match( '/^hsla?\s*\(/', $color ) ) {
		if ( preg_match( '/^hsla?\s*\(\s*\d{1,3}\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%\s*(?:,\s*(?:\d+\.?\d*|\.\d+)\s*)?\)$/', $color ) ) {
			return $color;
		}
		return '';
	}

	$allowed = array( 'transparent', 'currentColor', 'inherit', 'initial' );
	if ( in_array( $color, $allowed, true ) ) {
		return $color;
	}

	return sanitize_text_field( $color );
}

/**
 * SVG-safe sanitization using the unified Gutentor whitelist.
 *
 * @param string $svg SVG markup.
 * @return string
 */
function gutentor_sanitize_svg( $svg ) {
	if ( empty( $svg ) ) {
		return '';
	}
	return wp_kses( $svg, gutentor_get_allowed_html() );
}

// ─────────────────────────────────────────────────────────────
// Phase 2: Lightweight output escaping after block rendering
// ─────────────────────────────────────────────────────────────

add_filter( 'render_block', 'gutentor_escape_block_output', 10, 2 );

/**
 * Lightweight output escaping on rendered block HTML.
 *
 * Instead of wp_kses() which strips everything not whitelisted,
 * this strips only known-bad patterns. Legitimate HTML (forms,
 * SVGs, tables, etc.) passes through untouched.
 *
 * Skips blocks with inner blocks to avoid re-sanitizing nested
 * core/html content.
 *
 * @param string $block_content The rendered block HTML.
 * @param array  $block         The block data.
 * @return string
 */
function gutentor_escape_block_output( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || strpos( $block['blockName'], 'gutentor' ) !== 0 ) {
		return $block_content;
	}

	if ( ! empty( $block['innerBlocks'] ) ) {
		return $block_content;
	}

	return gutentor_strip_dangerous_html( $block_content, $block['blockName'] );
}

/**
 * Strip only known-dangerous patterns from HTML.
 *
 * Preserves ALL legitimate HTML elements and attributes.
 *
 * @param string $html       Rendered block HTML.
 * @param string $block_name Block name (e.g. 'gutentor/e4').
 * @return string
 */
function gutentor_strip_dangerous_html( $html, $block_name ) {
	if ( empty( $html ) ) {
		return '';
	}

	$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
	$html = str_ireplace( '</script>', '', $html );
	$html = preg_replace( '/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html );
	$html = preg_replace( '/href\s*=\s*["\']\s*javascript\s*:/i', 'href="#"', $html );
	$html = preg_replace( '/src\s*=\s*["\']\s*javascript\s*:/i', 'src=""', $html );
	$html = preg_replace( '/href\s*=\s*["\']\s*data\s*:/i', 'href="#"', $html );
	$html = preg_replace( '/src\s*=\s*["\']\s*data\s*:/i', 'src=""', $html );

	$iframe_allowed = array( 'gutentor/e4', 'gutentor/e11' );
	if ( ! in_array( $block_name, $iframe_allowed, true ) ) {
		if ( false !== stripos( $html, '<iframe' ) ) {
			$html = preg_replace( '/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html );
			$html = preg_replace( '/<iframe\b[^>]*\/?>/i', '', $html );
		}
	}

	return $html;
}
