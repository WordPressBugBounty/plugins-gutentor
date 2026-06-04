<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_P5' ) ) {

	/**
	 * Functions related to Blog Post
	 *
	 * @package Gutentor
	 * @since 1.0.1
	 */
	class Gutentor_P5 extends Gutentor_Block_Base {

		/**
		 * Name of the block.
		 *
		 * @access protected
		 * @since 1.0.1
		 * @var string
		 */
		protected $block_name = 'p5';

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid artefacts and improves performance.
		 *
		 * @static
		 * @access public
		 * @return object
		 * @since 1.0.1
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
		 * @since      1.0.1
		 * @package    Gutentor
		 * @author     Gutentor <info@gutentor.com>
		 */
		public function register_block_type_args() {
			$this->register_block_type_args = array(
				'view_script_handles' => array( 'acmeticker' ),
			);
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
			require_once GUTENTOR_PATH . 'includes/block-templates/ticker/class-ticker-p5-templates.php';
		}

		/**
		 * Render Blog Post Data
		 *
		 * @param array  $attributes
		 * @param string $content
		 * @return string
		 * @since    1.0.1
		 * @access   public
		 */
		public function render_callback( $attributes, $content ) {
			$gID     = isset( $attributes['gID'] ) ? $attributes['gID'] : '';
			$blockID = isset( $attributes['pID'] ) ? $attributes['pID'] : $gID;
			$output  = '';

			$default_class = gutentor_block_add_default_classes( 'gutentor-p5', $attributes );

			// the query
			$args = array(
				'posts_per_page' => isset( $attributes['postsToShow'] ) ? $attributes['postsToShow'] : 6,
				'post_type'      => isset( $attributes['pPostType'] ) ? $attributes['pPostType'] : 'post',
				'orderby'        => isset( $attributes['orderBy'] ) ? $attributes['orderBy'] : 'date',
				'order'          => isset( $attributes['order'] ) ? $attributes['order'] : 'desc',
				'cat'            => isset( $attributes['categories'] ) ? $attributes['categories'] : '',
				'paged'          => isset( $attributes['paged'] ) ? $attributes['paged'] : 1,
			);

			if ( isset( $attributes['pTaxType'] ) && ! empty( $attributes['pTaxType'] ) &&
				isset( $attributes['pTaxTerm'] ) && ! empty( $attributes['pTaxTerm'] ) ) {

				$args['taxonomy']    = $attributes['pTaxType'];
				$args['taxOperator'] = isset( $attributes['pTaxOperator'] ) ? $attributes['pTaxOperator'] : 'IN';
				if ( is_array( $attributes['pTaxTerm'] ) ) {
					$p1_terms = array();
					foreach ( $attributes['pTaxTerm'] as $p1_term ) {
						$p1_terms [] = $p1_term['value'];
					}
					$args['term'] = $p1_terms;
				} elseif ( is_string( $attributes['pTaxTerm'] ) || is_numeric( $attributes['pTaxTerm'] ) ) {
					$args['term'] = $attributes['pTaxTerm'];
				}
			}
			if ( isset( $attributes['pAuthor'] ) && ! empty( $attributes['pAuthor'] ) ) {
				if ( is_array( $attributes['pAuthor'] ) ) {
					$author_list = array();
					foreach ( $attributes['pAuthor'] as $data ) {
						$author_list[] = $data['value'];
					}
					$args['author__in'] = $author_list;
				}
			}

			if ( isset( $attributes['pIncludePosts'] ) && ! empty( $attributes['pIncludePosts'] ) ) {
				$args['post__in'] = $attributes['pIncludePosts'];
			}
			if ( isset( $attributes['pExcludePosts'] ) && ! empty( $attributes['pExcludePosts'] ) ) {
				$args['post__not_in'] = $attributes['pExcludePosts'];
			}
			if ( isset( $attributes['pOffsetPosts'] ) ) {
				$args['offset'] = $attributes['pOffsetPosts'];
			}
			$tag                     = isset( $attributes['mTag'] ) ? $attributes['mTag'] : 'div';
			$news_ticker_header      = isset( $attributes['p5NewsTxt'] ) ? $attributes['p5NewsTxt'] : '';
			$template                = isset( $attributes['p5Temp'] ) ? $attributes['p5Temp'] : '';
			$align                   = isset( $attributes['align'] ) ? 'align' . $attributes['align'] : '';
			$blockComponentAnimation = isset( $attributes['mAnimation'] ) ? $attributes['mAnimation'] : '';

			$the_query = new WP_Query( gutentor_get_query( $args ) );
			$p5OnNewsTxt = isset( $attributes['p5OnNewsTxt'] ) ? $attributes['p5OnNewsTxt'] : true;
			$p5Type      = isset( $attributes['p5Type'] ) ? $attributes['p5Type'] : 'marquee';
			$p5OnControl = isset( $attributes['p5OnControl'] ) ? $attributes['p5OnControl'] : true;

			if ( $the_query->have_posts() ) :
				$tag     = gutentor_get_module_tag( $tag );
				$output .= '<' . esc_attr( $tag ) . ' class="' . esc_attr( apply_filters( 'gutentor_post_module_main_wrap_class', gutentor_concat_space( 'gutentor-post-module', 'gutentor-post-module-p5', 'section-' . $gID, $template, $align, $default_class ), $attributes ) ) . '" id="' . esc_attr( $blockID ) . '" data-gbid="' . esc_attr( $gID ) . '" ' . GutentorAnimationOptionsDataAttr( $blockComponentAnimation ) . '' . gutentor_get_html_attr( apply_filters( 'gutentor_edit_news_ticker_data_attr', array(), $attributes ) ) . '>' . "\n";
				$output .= apply_filters( 'gutentor_post_module_before_container', '', $attributes );
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_post_module_p5_newsticker_wrap_class', 'gutentor-news-ticker', $attributes ) ) . "'>";
				$output .= apply_filters( 'gutentor_post_module_before_block_items', '', $attributes );
				if ( $p5OnNewsTxt ) {
					$output .= "<div class='gutentor-news-ticker-label'>" . esc_html( $news_ticker_header ) . '</div>';/*.ul*/
				}
				$output .= "<div class='gutentor-news-ticker-box'>";
				$output .= "<div class='gutentor-news-ticker-wrap'>";
				$output .= "<ul class='gutentor-news-ticker-data'>";
				while ( $the_query->have_posts() ) :
					$the_query->the_post();
					$output .= '<li>';
					$output .= apply_filters( 'gutentor_post_module_p5_query_data', '', get_post(), $attributes );
					$output .= '</li>';/*.li*/
				endwhile;
				$output .= '</ul>';/*.ul*/
				$output .= '</div>';/*.gutentor-news-ticker-wrap*/
				$output .= '</div>';/*.gutentor-news-ticker-box*/

				if ( 'vertical' === $p5Type ) {
					$hor = ' gutentor-news-ticker-vertical-controls';
				} else {
					$hor = ' gutentor-news-ticker-horizontal-controls';
				}

				if ( $p5OnControl ) {
					$output .= "<div class='gutentor-news-ticker-controls" . $hor . "'>";/*.ul*/
					if ( $p5Type !== 'marquee' ) {
						$output .= '<Button type="button" class="gutentor-news-ticker-arrow gutentor-news-ticker-prev"></Button>';
					}
					$output .= '<Button type="button" class="gutentor-news-ticker-action gutentor-news-ticker-pause"></Button>';
					if ( $p5Type !== 'marquee' ) {
						$output .= '<Button type="button" class="gutentor-news-ticker-arrow gutentor-news-ticker-next"></Button>';
					}
					$output .= '</div>';/*.gutentor-news-ticker-controls*/
				}

				$output .= apply_filters( 'gutentor_post_module_after_block_items', '', $attributes );
				$output .= '</div>';/*.grid-container*/
				$output .= apply_filters( 'gutentor_post_module_after_container', '', $attributes );
				$output .= '</' . esc_attr( $tag ) . '>';/*.gutentor-blog-post-wrapper*/
			endif;

			// Restore original Post Data
			wp_reset_postdata();
			return $output;
		}
	}
}
Gutentor_P5::get_instance()->run();
