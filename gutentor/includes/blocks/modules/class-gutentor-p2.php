<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_P2' ) ) {

	/**
	 * Functions related to Blog Post
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */

	class Gutentor_P2 extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 'p2';

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
		 * @since      3.0.6
		 * @package    Gutentor
		 * @author     Gutentor <info@gutentor.com>
		 */
		public function register_block_type_args() {
			$this->register_block_type_args = array(
				'view_script_handles' => array( 'magnific-popup' ),
				'style_handles'       => array( 'magnific-popup' ),
			);
		}

		/**
		 * Render Blog Post Data
		 *
		 * @since    1.0.1
		 * @access   public
		 *
		 * @param array  $attributes
		 * @param string $content
		 * @return string
		 */
		public function render_callback( $attributes, $content ) {

			$gID     = isset( $attributes['gID'] ) ? $attributes['gID'] : '';
			$blockID = isset( $attributes['pID'] ) ? $attributes['pID'] : $gID;
			$output  = '';

			$default_class = gutentor_block_add_default_classes( 'gutentor-p2', $attributes );

			$tag                     = isset( $attributes['mTag'] ) ? $attributes['mTag'] : 'section';
			$template                = isset( $attributes['p2Temp'] ) ? $attributes['p2Temp'] : '';
			$post_number             = isset( $attributes['postsToShow'] ) ? $attributes['postsToShow'] : 6;
			$align                   = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$blockComponentAnimation = isset( $attributes['mAnimation'] ) ? $attributes['mAnimation'] : '';

			/*query args*/
			$query_args = array(
				'posts_per_page'      => isset( $attributes['postsToShow'] ) ? $attributes['postsToShow'] : 6,
				'post_type'           => isset( $attributes['pPostType'] ) ? $attributes['pPostType'] : 'post',
				'orderby'             => isset( $attributes['orderBy'] ) ? $attributes['orderBy'] : 'date',
				'order'               => isset( $attributes['order'] ) ? $attributes['order'] : 'desc',
				'paged'               => isset( $attributes['paged'] ) ? $attributes['paged'] : 1,
				'ignore_sticky_posts' => true,
				'post_status'         => 'publish',
			);

			/*Backward compatible*/
			if ( isset( $attributes['categories'] ) && ! empty( $attributes['categories'] ) ) {
				if ( is_array( $attributes['categories'] ) && ! gutentor_is_array_empty( $attributes['categories'] ) ) {
					$query_args['taxonomy'] = 'category';
					$query_args['term']     = $attributes['categories'];
				}
				if ( ! is_array( $attributes['categories'] ) ) {
					$query_args['taxonomy'] = 'category';
					$query_args['term']     = $attributes['categories'];
				}
			}

			if ( isset( $attributes['pTaxType'] ) && ! empty( $attributes['pTaxType'] ) &&
				isset( $attributes['pTaxTerm'] ) && ! empty( $attributes['pTaxTerm'] ) ) {

				$query_args['taxonomy']    = $attributes['pTaxType'];
				$query_args['taxOperator'] = isset( $attributes['pTaxOperator'] ) ? $attributes['pTaxOperator'] : 'IN';

				if ( is_array( $attributes['pTaxTerm'] ) ) {
					$p2_terms = array();
					foreach ( $attributes['pTaxTerm'] as $p2_term ) {
						$p2_terms [] = $p2_term['value'];
					}
					$query_args['term'] = $p2_terms;
				} elseif ( is_string( $attributes['pTaxTerm'] ) || is_numeric( $attributes['pTaxTerm'] ) ) {
					$query_args['term'] = $attributes['pTaxTerm'];
				}
			}

			if ( isset( $attributes['pAuthor'] ) && ! empty( $attributes['pAuthor'] ) ) {
				if ( is_array( $attributes['pAuthor'] ) ) {
					$author_list = array();
					foreach ( $attributes['pAuthor'] as $data ) {
						$author_list[] = $data['value'];
					}
					$query_args['author__in'] = $author_list;
				}
			}

			if ( isset( $attributes['offset'] ) ) {
				$query_args['offset'] = $attributes['offset'];
			}
			if ( isset( $attributes['pIncludePosts'] ) && ! empty( $attributes['pIncludePosts'] ) ) {
				$query_args['post__in'] = $attributes['pIncludePosts'];
			}
			if ( isset( $attributes['pExcludePosts'] ) && ! empty( $attributes['pExcludePosts'] ) ) {
				$query_args['post__not_in'] = $attributes['pExcludePosts'];
			}
			if ( isset( $attributes['pOffsetPosts'] ) ) {
				$query_args['offset'] = $attributes['pOffsetPosts'];
			}

			$gutentor_p2_news_the_query = new WP_Query( gutentor_get_query( $query_args ) );

			if ( $gutentor_p2_news_the_query->have_posts() ) :
				$tag     = gutentor_get_module_tag( $tag );
				$output .= '<' . esc_attr( $tag ) . ' class="' . esc_attr( apply_filters( 'gutentor_post_module_main_wrap_class', gutentor_concat_space( 'section-' . $gID, 'gutentor-post-module', 'gutentor-post-module-p2', 'gutentor-post-' . $post_number, $align, 'gutentor-template-' . $template, $default_class ), $attributes ) ) . '" id="' . esc_attr( $blockID ) . '" ' . GutentorAnimationOptionsDataAttr( $blockComponentAnimation ) . '>' . "\n";
				$output .= apply_filters( 'gutentor_post_module_before_container', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_post_module_container_class', 'grid-container', $attributes ) ) . "'>";
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_post_module_grid_row_class', 'grid-row', $attributes ) ) . "'>";
				$output .= apply_filters( 'gutentor_post_module_before_block_items', '', $attributes );

				/*post query*/
				$output .= apply_filters( 'gutentor_post_module_p2_query_data', '', $gutentor_p2_news_the_query, $attributes );

				$output .= apply_filters( 'gutentor_post_module_after_block_items', '', $attributes );
				$output .= '</div>';/*.grid-row*/
				$output .= '</div>';/*.grid-container*/
				$output .= apply_filters( 'gutentor_post_module_after_container', '', $attributes );
				$output .= '</' . esc_attr( $tag ) . '>';/*.gutentor-blog-post-wrapper*/
			endif;

			// Restore original Post Data.
			wp_reset_postdata();
			return $output;
		}
	}
}
Gutentor_P2::get_instance()->run();
