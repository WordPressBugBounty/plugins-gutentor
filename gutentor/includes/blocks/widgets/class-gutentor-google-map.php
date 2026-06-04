<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_Google_Map' ) ) {

	/**
	 * Functions related to Google Map
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */

	class Gutentor_Google_Map extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 'google-map';

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid artefacts and improves performance.
		 *
		 * @static
		 * @access public
		 * @since 1.0.1
		 * @return object
		 */
		public static function get_instance() {

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
		 * Set register_block_type_args variable on parent
		 * Used for blog template loading
		 *
		 * @since      3.2.6
		 * @package    Gutentor
		 * @author     Gutentor <info@gutentor.com>
		 */
		public function register_block_type_args() {
			$this->register_block_type_args = array(
				'view_script_handles' => array( 'gutentor-google-maps', 'google-maps' ),
			);
		}

		/**
		 * Render Google Map Data
		 *
		 * @since    1.0.1
		 * @access   public
		 *
		 * @param array  $attributes
		 * @param string $content
		 * @return string
		 */
		public function render_callback( $attributes, $content ) {

			$id      = isset( $attributes['id'] ) ? $attributes['id'] : 'gutentor-google-map-' . wp_rand( 10, 100 );
			$blockID = isset( $attributes['blockID'] ) ? $attributes['blockID'] : '';

			$default_class = gutentor_block_add_default_classes( 'gutentor-google-map', $attributes );

			$align = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$tag   = isset( $attributes['blockSectionHtmlTag'] ) ? $attributes['blockSectionHtmlTag'] : 'section';

			$blockComponentAnimation = isset( $attributes['blockComponentAnimation'] ) ? $attributes['blockComponentAnimation'] : '';
			$blockItemsWrapAnimation = isset( $attributes['blockItemsWrapAnimation'] ) ? $attributes['blockItemsWrapAnimation'] : '';

			$tag = gutentor_get_module_tag( $tag );

			$local_attr                      = array();
			$local_attr['id']                = $id;
			$local_attr['location']          = isset( $attributes['location'] ) ? $attributes['location'] : '';
			$local_attr['latitude']          = isset( $attributes['latitude'] ) ? $attributes['latitude'] : '';
			$local_attr['longitude']         = isset( $attributes['longitude'] ) ? $attributes['longitude'] : '';
			$local_attr['zoom']              = isset( $attributes['zoom'] ) ? $attributes['zoom'] : 15;
			$local_attr['type']              = isset( $attributes['type'] ) ? $attributes['type'] : 'roadmap';
			$local_attr['draggable']         = isset( $attributes['draggable'] ) ? $attributes['draggable'] : true;
			$local_attr['mapTypeControl']    = isset( $attributes['mapTypeControl'] ) ? $attributes['mapTypeControl'] : true;
			$local_attr['zoomControl']       = isset( $attributes['zoomControl'] ) ? $attributes['zoomControl'] : true;
			$local_attr['fullscreenControl'] = isset( $attributes['fullscreenControl'] ) ? $attributes['fullscreenControl'] : true;
			$local_attr['streetViewControl'] = isset( $attributes['streetViewControl'] ) ? $attributes['streetViewControl'] : true;
			$local_attr['markers']           = isset( $attributes['markers'] ) ? $attributes['markers'] : array();

			$output  = '<' . esc_attr( $tag ) . ' class="' . esc_attr( apply_filters( 'gutentor_save_section_class', gutentor_concat_space( 'gutentor-section gutentor-google-map', $align, $default_class ), $attributes ) ) . '" id="section-' . esc_attr( $blockID ) . '"   ' . GutentorAnimationOptionsDataAttr( $blockComponentAnimation ) . '>' . "\n";
			$output .= apply_filters( 'gutentor_save_before_container', '', $attributes );
			$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_save_container_class', 'grid-container', $attributes ) ) . "'>";
			$output .= apply_filters( 'gutentor_save_before_block_items', '', $attributes );
			$output .= '<div class="' . esc_attr( apply_filters( 'gutentor_save_grid_row_class', 'gutentor-grid-item-wrap', $attributes ) ) . '" id="' . esc_attr( $id ) . '" ' . GutentorAnimationOptionsDataAttr( $blockItemsWrapAnimation ) . '></div>' . "\n";
			$output .= apply_filters( 'gutentor_save_after_block_items', '', $attributes );
			$output .= '</div>' . "\n";
			$output .= apply_filters( 'gutentor_save_after_container', '', $attributes );
			$output .= '</' . esc_attr( $tag ) . '>' . "\n";

			$map_data = 'if ( ! window.gutentorGoogleMaps ) window.gutentorGoogleMaps = [];' . "\n";
			$map_data .= 'window.gutentorGoogleMaps.push( { container: "' . esc_js( $id ) . '", attributes: ' . wp_json_encode( $local_attr ) . ' } );';
			wp_add_inline_script( 'gutentor-google-maps', $map_data, 'before' );

			return $output;
		}
	}
}
Gutentor_Google_Map::get_instance()->run();
