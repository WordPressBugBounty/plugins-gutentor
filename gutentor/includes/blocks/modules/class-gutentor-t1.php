<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_T1' ) ) {

	/**
	 * Functions related to Terms
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */

	class Gutentor_T1 extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 't1';

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
		 * Load Dependencies
		 * Used for blog template loading
		 *
		 * @since      1.0.1
		 * @package    Gutentor
		 * @author     Gutentor <info@gutentor.com>
		 */
		public function load_dependencies() {

			require_once GUTENTOR_PATH . 'includes/block-templates/normal/class-normal-t1-templates.php';
		}

		/**
		 * Render Term Data
		 *
		 * @since    1.0.1
		 * @access   public
		 *
		 * @param array  $attributes
		 * @param string $content
		 * @return string
		 */
		public function render_callback( $attributes, $content ) {

			$gID            = isset( $attributes['gID'] ) ? $attributes['gID'] : '';
			$blockID        = isset( $attributes['mID'] ) ? $attributes['mID'] : $gID;
			$tTypeTermQuery = isset( $attributes['tTypeTermQuery'] ) ? $attributes['tTypeTermQuery'] : 'default';
			$output         = '';

			$default_class = gutentor_block_add_default_classes( 'gutentor-t1', $attributes );

			$tag                     = isset( $attributes['mTag'] ) ? $attributes['mTag'] : 'section';
			$template                = isset( $attributes['t1Temp'] ) ? $attributes['t1Temp'] : '';
			$termStyle               = isset( $attributes['termStyle'] ) ? $attributes['termStyle'] : '';
			$tRevCont                = isset( $attributes['tRevCont'] ) ? $attributes['tRevCont'] : false;
			$tRevContClass           = ( $termStyle === 'gtf-list' && $tRevCont ) ? 'gtf-reverse-list' : '';
			$tOnImgW                 = isset( $attributes['tOnImgW'] ) && $attributes['tOnImgW'];
			$tImgW                   = isset( $attributes['tImgW'] ) && $attributes['tImgW'];
			$enable_featured_img     = isset( $attributes['tOnFImg'] ) && $attributes['tOnFImg'];
			$enabledWidth            = ( $template !== 'gutentor_t1_template2' && $enable_featured_img && $tOnImgW && $tImgW ) ? 'gutentor-enabled-width' : '';
			$align                   = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$blockComponentAnimation = isset( $attributes['mAnimation'] ) ? $attributes['mAnimation'] : '';

			/*
			Query
			*/
			$term_query_args = array(
				'taxonomy' => isset( $attributes['t1Taxonomy'] ) ? $attributes['t1Taxonomy'] : 'category',
			);
			if ( $tTypeTermQuery === 'default' ) {
				/*query args*/
				$term_query_args = array(
					'taxonomy'   => isset( $attributes['t1Taxonomy'] ) ? $attributes['t1Taxonomy'] : 'category',
					'orderby'    => isset( $attributes['t1OrderBy'] ) ? $attributes['t1OrderBy'] : 'date',
					'order'      => isset( $attributes['t1Order'] ) ? $attributes['t1Order'] : 'desc',
					'hide_empty' => isset( $attributes['t1HideEmpty'] ) ? $attributes['t1HideEmpty'] : true,
					'number'     => isset( $attributes['t1Number'] ) ? $attributes['t1Number'] : 6,
				);
				if ( isset( $attributes['t1IncludeTerms'] ) && ! empty( $attributes['t1IncludeTerms'] ) ) {
					$term_query_args['include'] = explode( ',', $attributes['t1IncludeTerms'] );
				}
				if ( isset( $attributes['t1ExcludeTerms'] ) && ! empty( $attributes['t1ExcludeTerms'] ) ) {
					$term_query_args['exclude'] = explode( ',', $attributes['t1ExcludeTerms'] );
				}
			}
			if ( $tTypeTermQuery === 'custom' ) {
				$tTermQueryJson  = isset( $attributes['tTermQuery'] ) ? $attributes['tTermQuery'] : false;
				$tTermQueryData  = json_decode( $tTermQueryJson, true );
				$term_query_args = array_merge( $term_query_args, $tTermQueryData );
			}

			$terms = get_terms( gutentor_get_term_query( $term_query_args ) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$tag = gutentor_get_module_tag( $tag );

				$output .= '<' . esc_attr( $tag ) . ' id="' . esc_attr( $blockID ) . '" class="' . esc_attr( apply_filters( 'gutentor_term_module_main_wrap_class', gutentor_concat_space( 'section-' . $gID, 'gutentor-module', 'gtf-module', 'gutentor-term-module', 'gutentor-term-module-t1', $align, $termStyle, $tRevContClass, $enabledWidth, $template, $default_class ), $attributes ) ) . '" ' . GutentorAnimationOptionsDataAttr( $blockComponentAnimation ) . '>' . "\n";
				$output .= apply_filters( 'gutentor_term_module_before_container', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_term_module_container_class', 'grid-container', $attributes ) ) . "'>";
				$output .= apply_filters( 'gutentor_term_module_before_block_items', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_term_module_grid_row_class', 'grid-row', $attributes ) ) . "' " . gutentor_get_html_attr( apply_filters( 'gutentor_term_module_attr', array(), $attributes ) ) . '>';
				$output .= apply_filters( 'gutentor_term_module_before_block_items', '', $attributes );

				$index = 0;
				foreach ( $terms as $term ) {
					/*term query*/
					$output .= apply_filters( 'gutentor_term_module_t1_query_data', '', $term, $attributes, $index );
					++$index;
				}
				$output .= '</div>';/*.grid-row*/
				$output .= apply_filters( 'gutentor_term_module_after_block_items', '', $attributes );
				$output .= '</div>';/*.grid-container*/
				$output .= apply_filters( 'gutentor_term_module_after_container', '', $attributes );
				$output .= '</' . esc_attr( $tag ) . '>';/*.gutentor-blog-term-wrapper*/
			}

			return $output;
		}
	}
}
Gutentor_T1::get_instance()->run();
