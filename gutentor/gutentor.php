<?php
/**If this file is called directly, abort.*/
if ( ! defined( 'WPINC' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

/**
 *
 * @link              https://www.gutentor.com/
 * @since             1.0.0
 * @package           Gutentor
 *
 * @wordpress-plugin
 * Plugin Name:       Gutentor - Gutenberg Blocks - Page Builder for Gutenberg Editor
 * Description:       Advanced yet easy, Gutenberg editor page builder blocks. Create a masterpiece, pixel perfect websites using modern WordPress Gutenberg blocks.
 * Version:           4.0.3
 * Author:            Gutentor
 * Author URI:        https://www.gutentor.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gutentor
 * Requires PHP:      7.4
 */

/*Define Constants for this plugin*/
define( 'GUTENTOR_VERSION', '4.0.3' );
define( 'GUTENTOR_PLUGIN_NAME', 'gutentor' );
define( 'GUTENTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'GUTENTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'GUTENTOR_SCRIPT_PREFIX', ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min' );
$GLOBALS['GUTENTOR_GLOBAL'] = array();

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-gutentor-activator.php
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required by register_activation_hook.
function activate_gutentor() {
	require_once GUTENTOR_PATH . 'includes/activator.php';
	Gutentor_Activator::activate();
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require GUTENTOR_PATH . 'includes/init.php';

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-gutentor-deactivator.php
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Required by register_deactivation_hook.
function deactivate_gutentor() {
	require_once GUTENTOR_PATH . 'includes/deactivator.php';
	Gutentor_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_gutentor' );
register_deactivation_hook( __FILE__, 'deactivate_gutentor' );

// comment on post added.
function gutentor_count_comments( $object ) {
	$comments_count = wp_count_comments( $object['id'] );
	return $comments_count->total_comments;
}

/**
 * Create API fields for additional info
 *
 * @since 1.0.9
 */
function gutentor_register_rest_fields() {
	// Add comment info.
	register_rest_field(
		'post',
		'gutentor_comment',
		array(
			'get_callback'    => 'gutentor_count_comments',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}
add_action( 'rest_api_init', 'gutentor_register_rest_fields' );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
	if ( ! function_exists( 'run_gutentor' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy bootstrap function, renaming would break compatibility.
		function run_gutentor() {

		return Gutentor::instance();
	}
	run_gutentor()->run();
}
