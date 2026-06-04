<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_Blog_Post' ) ) {

	/**
	 * Functions related to Blog Post
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */

	class Gutentor_Blog_Post extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 'blog-post';

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

			require_once GUTENTOR_PATH . 'includes/block-templates/widgets/class-widget-blog-post-templates.php';
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

			$blockID = isset( $attributes['blockID'] ) ? $attributes['blockID'] : '';
			$output  = '';

			$default_class = gutentor_block_add_default_classes( 'gutentor-blog-post', $attributes );

			// the query
			$args = array(
				'posts_per_page'      => isset( $attributes['postsToShow'] ) ? $attributes['postsToShow'] : 6,
				'orderby'             => isset( $attributes['orderBy'] ) ? $attributes['orderBy'] : 'date',
				'order'               => isset( $attributes['order'] ) ? $attributes['order'] : 'desc',
				'cat'                 => isset( $attributes['categories'] ) ? $attributes['categories'] : '',
				'ignore_sticky_posts' => 1,
			);

			$tag                     = isset( $attributes['blockSectionHtmlTag'] ) ? $attributes['blockSectionHtmlTag'] : 'section';
			$template                = isset( $attributes['blockBlogTemplate'] ) ? $attributes['blockBlogTemplate'] : '';
			$align                   = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$blockComponentAnimation = isset( $attributes['blockComponentAnimation'] ) ? $attributes['blockComponentAnimation'] : '';
			$blockItemsWrapAnimation = isset( $attributes['blockItemsWrapAnimation'] ) ? $attributes['blockItemsWrapAnimation'] : '';

			$the_query = new WP_Query( gutentor_get_query( $args ) );

			if ( $the_query->have_posts() ) :
				$tag = gutentor_get_module_tag( $tag );

				$output .= '<' . esc_attr( $tag ) . ' class="' . esc_attr( apply_filters( 'gutentor_save_section_class', 'gutentor-section gutentor-blog-post-wrapper ' . gutentor_concat_space( $template, $align, $default_class ) . '', $attributes ) ) . '" id="section-' . esc_attr( $blockID ) . '" ' . GutentorAnimationOptionsDataAttr( $blockComponentAnimation ) . '>' . "\n";
				$output .= apply_filters( 'gutentor_save_before_container', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_save_container_class', 'grid-container', $attributes ) ) . "'>";
				$output .= apply_filters( 'gutentor_save_before_block_items', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_save_grid_item_wrap_class', 'gutentor-grid-item-wrap', $attributes ) ) . "' " . esc_attr( apply_filters( 'gutentor_save_grid_item_wrap_attr', '', $attributes ) ) . ' ' . GutentorAnimationOptionsDataAttr( $blockItemsWrapAnimation ) . '>';
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_save_grid_row_class', 'grid-row', $attributes ) ) . "' " . esc_attr( apply_filters( 'gutentor_save_grid_row_attr', '', $attributes ) ) . '>';
				while ( $the_query->have_posts() ) :
					$the_query->the_post();
					$thumb_class = has_post_thumbnail() ? 'gutentor-post-has-thumb' : 'gutentor-post-no-thumb';
					$output     .= "<article class='" . esc_attr( apply_filters( 'gutentor_save_grid_column_class', $thumb_class, $attributes ) ) . "'>";
					$output     .= '<div class="gutentor-single-item">';
					$output     .= apply_filters( 'gutentor_save_blog_post_block_template_data', '', get_post(), $attributes );
					$output     .= '</div>';/*.gutentor-single-item*/
					$output     .= '</article>';/*.article*/
				endwhile;
				$output .= '</div>';/*.grid-row*/
				$output .= '</div>';/*.grid-row-item-wrap*/
				$output .= apply_filters( 'gutentor_save_after_block_items', '', $attributes );
				$output .= '</div>';/*.grid-container*/
				$output .= apply_filters( 'gutentor_save_after_container', '', $attributes );
				$output .= '</' . esc_attr( $tag ) . '>';/*.gutentor-blog-post-wrapper*/
			endif;

			// Restore original Post Data.
			wp_reset_postdata();
			return $output;
		}
	}
}
Gutentor_Blog_Post::get_instance()->run();
