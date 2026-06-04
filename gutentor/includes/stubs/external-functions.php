<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'WC' ) ) {
	if ( ! class_exists( 'Gutentor_WC_Stub' ) ) {
		class Gutentor_WC_Stub {
			public function plugin_url() {
				return '';
			}
		}
	}

	/**
	 * Fallback WooCommerce accessor for static analysis when WooCommerce is absent.
	 *
	 * @return object
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function WC() {
		return new Gutentor_WC_Stub();
	}
}

if ( ! class_exists( 'Gutentor_WC_Product_Stub' ) ) {
	class Gutentor_WC_Product_Stub {
		public function get_average_rating() {
			return 0;
		}

		public function get_rating_count() {
			return 0;
		}

		public function get_price_html() {
			return '';
		}

		public function get_price() {
			return '';
		}

		public function get_date_created() {
			return '';
		}

		public function get_regular_price() {
			return '';
		}

		public function get_price_suffix() {
			return '';
		}
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_get_product( $product_id ) {
		return new Gutentor_WC_Product_Stub();
	}
}

if ( ! function_exists( 'wc_get_rating_html' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_get_rating_html( $rating, $count = 0 ) {
		return '';
	}
}

if ( ! function_exists( 'wc_price' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_price( $price ) {
		return '';
	}
}

if ( ! function_exists( 'wc_format_sale_price' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_format_sale_price( $regular_price, $sale_price ) {
		return '';
	}
}

if ( ! function_exists( 'wc_get_price_to_display' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_get_price_to_display( $product, $args = array() ) {
		return 0;
	}
}

if ( ! function_exists( 'wc_implode_html_attributes' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function wc_implode_html_attributes( $attributes ) {
		return '';
	}
}

if ( ! function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function woocommerce_template_loop_add_to_cart( $args = array() ) {
		return null;
	}
}

if ( ! function_exists( 'woocommerce_get_product_thumbnail' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function woocommerce_get_product_thumbnail( $size = 'woocommerce_thumbnail' ) {
		return '';
	}
}

if ( ! function_exists( 'edd_get_download' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_get_download( $download_id ) {
		return null;
	}
}

if ( ! function_exists( 'edd_favorites_load_link' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_favorites_load_link( $download_id ) {
		return '';
	}
}

if ( ! function_exists( 'edd_wl_wish_list_link' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_wl_wish_list_link( $args = array() ) {
		return '';
	}
}

if ( ! function_exists( 'edd_has_variable_prices' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_has_variable_prices( $download_id ) {
		return false;
	}
}

if ( ! function_exists( 'edd_price_range' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_price_range( $download_id ) {
		return '';
	}
}

if ( ! function_exists( 'edd_get_download_price' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_get_download_price( $download_id ) {
		return 0;
	}
}

if ( ! function_exists( 'edd_price' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_price( $download_id, $echo = true ) {
		return '';
	}
}

if ( ! function_exists( 'edd_get_purchase_link' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_get_purchase_link( $args = array() ) {
		return '';
	}
}

if ( ! function_exists( 'edd_purchase_variable_pricing' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_purchase_variable_pricing( $download_id ) {
		return '';
	}
}

if ( ! function_exists( 'edd_get_option' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_get_option( $key, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'edd_reviews' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- External plugin stub for static analysis.
	function edd_reviews() {
		return null;
	}
}

if ( ! function_exists( 'gutentor_custom_edd_review' ) ) {
	function gutentor_custom_edd_review( $download_id ) {
		return '';
	}
}
