<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_E4' ) ) {

	/**
	 * Functions related to Google Map
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */

	class Gutentor_E4 extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 'e4';

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
			$blockID = isset( $attributes['gID'] ) ? $attributes['gID'] : '';
			$class   = 'gutentor-google-map';

			$default_class = gutentor_block_add_default_classes( 'gutentor-e4', $attributes );
			$class        .= ' ' . $default_class;

			$align = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$tag   = 'div';

			$local_attr                      = array();
			$local_attr['id']                = $id;
			$local_attr['location']          = isset( $attributes['e4Loc'] ) ? $attributes['e4Loc'] : 'La Sagrada Familia, Barcelona, Spain';
			$local_attr['latitude']          = isset( $attributes['e4Lat'] ) ? $attributes['e4Lat'] : '41.4036299';
			$local_attr['longitude']         = isset( $attributes['e4Lon'] ) ? $attributes['e4Lon'] : '2.1743558000000576';
			$local_attr['zoom']              = isset( $attributes['e4Zoom'] ) ? $attributes['e4Zoom'] : 15;
			$local_attr['type']              = isset( $attributes['e4Type'] ) ? $attributes['e4Type'] : 'roadmap';
			$local_attr['draggable']         = isset( $attributes['e4Draggable'] ) ? $attributes['e4Draggable'] : true;
			$local_attr['mapTypeControl']    = isset( $attributes['e4TypeCtrl'] ) ? $attributes['e4TypeCtrl'] : true;
			$local_attr['zoomControl']       = isset( $attributes['e4ZoomCtrl'] ) ? $attributes['e4ZoomCtrl'] : true;
			$local_attr['fullscreenControl'] = isset( $attributes['e4FullScrCtrl'] ) ? $attributes['e4FullScrCtrl'] : true;
			$local_attr['streetViewControl'] = isset( $attributes['e4StreetViewCtrl'] ) ? $attributes['e4StreetViewCtrl'] : true;
			$local_attr['markers']           = isset( $attributes['e4Markers'] ) ? $attributes['e4Markers'] : array();

			$block_animation_attrs = isset( $attributes['eAnimation'] ) ? $attributes['eAnimation'] : '';

			$map_section_class = gutentor_concat_space( 'gutentor-element g-el-gmap', $align );
			$map_section_id    = 'section-' . $blockID;
			$map_section_class = gutentor_concat_space( $map_section_class, $map_section_id );
			$class             = gutentor_concat_space( $class, $id );

			$output  = '<' . $tag . ' class="' . esc_attr( apply_filters( 'gutentor_save_element_class', $map_section_class, $attributes ) ) . '" id="section-' . esc_attr( $blockID ) . '"   ' . GutentorAnimationOptionsDataAttr( $block_animation_attrs ) . '>' . "\n";
			$output .= '<div class="' . esc_attr( apply_filters( 'gutentor_save_grid_row_class', gutentor_concat_space( esc_attr( $class ), 'gutentor-grid-item-wrap' ), $attributes ) ) . '" id="' . esc_attr( $id ) . '"></div>' . "\n";
			$output .= '</' . $tag . '>' . "\n";

			$map_data = 'if ( ! window.gutentorGoogleMaps ) window.gutentorGoogleMaps = [];' . "\n";
			$map_data .= 'window.gutentorGoogleMaps.push( { container: "' . esc_js( $id ) . '", attributes: ' . wp_json_encode( $local_attr ) . ' } );';
			wp_add_inline_script( 'gutentor-google-maps', $map_data, 'before' );

			return $output;
		}
	}
}
Gutentor_E4::get_instance()->run();
