<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Gutentor_Featured_N4_T2' ) ) {

	/**
	 * Gutentor_Featured_N4_T2 Class For Gutentor
	 *
	 * @package Gutentor
	 * @since 2.0.0
	 */
	class Gutentor_Featured_N4_T2 extends Gutentor_Featured {

		/**
		 * Number of items
		 *
		 * @access public
		 * @since 3.0.0
		 * @var integer
		 */
		public $number = 4;

		/**
		 * Number of template
		 *
		 * @access public
		 * @since 3.0.0
		 * @var integer
		 */
		public $template = 2;

		/**
		 * Gets an instance of this object.
		 * Prevents duplicate instances which avoid interface and improves performance.
		 *
		 * @static
		 * @access public
		 * @since 2.0.0
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
		 * Post template
		 *
		 * @param {string} $output
		 * @param {object} $the_query
		 * @param {array}  $attributes
		 *
		 * @return {string}
		 */
		public function post_template( $output, $the_query, $attributes ) {

			if ( ! $this->isP2( $attributes ) ) {
				return $output;
			}
			$index = 0;
			while ( $the_query->have_posts() ) :
				$the_query->the_post();
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_post_module_p2_grid_class', 'grid-lg-6 grid-md-6 grid-12', $attributes ) ) . "'>";
				$output .= $this->featured_post_type_template( get_post(), $attributes, $index );
				$output .= '</div>';
				++$index;
			endwhile;
			return $output;
		}

		/**
		 * Term Template
		 *
		 * @param {string} $output
		 * @param {array}  $terms
		 * @param {array}  $attributes
		 *
		 * @return {string}
		 */
		public function term_template( $output, $terms, $attributes ) {

			if ( ! $this->isT2( $attributes ) ) {
				return $output;
			}
			$index = 0;
			foreach ( $terms as $term ) {
				$output .= "<div class='" . esc_attr( apply_filters( 'gutentor_term_module_t2_grid_class', 'grid-lg-6 grid-md-6 grid-12', $attributes ) ) . "'>";
				$output .= $this->t2_single_article( $term, $attributes, $index );
				$output .= '</div>';
				++$index;
			}
			return $output;
		}
	}
}
Gutentor_Featured_N4_T2::get_instance()->run();
