<?php
/**
 * Plugin Name: Integrate CiviCRM with Groups
 * Plugin URI: https://github.com/WPCV/wpcv-civicrm-groups-integration
 * GitHub Plugin URI: https://github.com/WPCV/wpcv-civicrm-groups-integration
 * Description: Integrates CiviCRM Groups with Groups provided by the Groups plugin.
 * Author: Christian Wach
 * Version: 1.0.0a
 * Author URI: https://haystack.co.uk
 * Text Domain: wpcv-civicrm-groups-integration
 * Domain Path: /languages
 * Depends: CiviCRM
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



// Set our version here.
define( 'WPCV_CGI_VERSION', '1.0.0a' );

// Store reference to this file.
if ( ! defined( 'WPCV_CGI_FILE' ) ) {
	define( 'WPCV_CGI_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'WPCV_CGI_URL' ) ) {
	define( 'WPCV_CGI_URL', plugin_dir_url( WPCV_CGI_FILE ) );
}
// Store PATH to this plugin's directory.
if ( ! defined( 'WPCV_CGI_PATH' ) ) {
	define( 'WPCV_CGI_PATH', plugin_dir_path( WPCV_CGI_FILE ) );
}

// Set debug flag.
if ( ! defined( 'WPCV_CGI_DEBUG' ) ) {
	define( 'WPCV_CGI_DEBUG', false );
}



/**
 * Plugin class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 0.1
 */
class WPCV_CGI {

	/**
	 * Admin utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $admin The Admin utilities object.
	 */
	public $admin;

	/**
	 * CiviCRM utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $civicrm The CiviCRM utilities object.
	 */
	public $civicrm;

	/**
	 * WordPress utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $wordpress The WordPress utilities object.
	 */
	public $wordpress;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Bail if dependencies fail.
		if ( ! $this->dependencies() ) {
			return;
		}

		// Initialise this plugin.
		$this->initialise();

		/**
		 * Broadcast that this plugin is now loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'wpcv_cgi/loaded' );

	}

	/**
	 * Checks the dependencies for this plugin.
	 *
	 * @since 0.1.2
	 */
	public function dependencies() {

		// Defer to CiviCRM Groups Sync if present.
		if ( defined( 'CIVICRM_GROUPS_SYNC_VERSION' ) ) {
			return false;
		}

		// Init only when CiviCRM is fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}
		if ( ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Bail if CiviCRM plugin is not present.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Bail if we don't have the "Groups" plugin.
		if ( ! defined( 'GROUPS_CORE_VERSION' ) ) {
			return false;
		}

		// We're good.
		return true;

	}

	/**
	 * Initialises the plugin.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && $done === true ) {
			return;
		}

		// Bootstrap plugin.
		$this->enable_translation();
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		// We're done.
		$done = true;

	}

	/**
	 * Includes files.
	 *
	 * @since 0.1
	 */
	public function include_files() {

		// Load our class files.
		require WPCV_CGI_PATH . 'includes/class-civicrm.php';
		require WPCV_CGI_PATH . 'includes/class-wordpress.php';
		require WPCV_CGI_PATH . 'includes/class-admin.php';

	}

	/**
	 * Sets up this plugin's objects.
	 *
	 * @since 0.1
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->civicrm = new WPCV_CGI_CiviCRM( $this );
		$this->wordpress = new WPCV_CGI_WordPress( $this );
		$this->admin = new WPCV_CGI_Admin( $this );

	}

	/**
	 * Registers hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// If global-scope hooks are needed, add them here.

	}

	/**
	 * Loads translation files.
	 *
	 * @since 0.1
	 */
	public function enable_translation() {

		// Enable translation.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'wpcv-civicrm-groups-integration', // Unique name.
			false, // Deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // Relative path to files.
		);

	}

	// -------------------------------------------------------------------------

	/**
	 * Checks if this plugin is network activated.
	 *
	 * @since 0.1
	 *
	 * @return bool $is_network_active True if network activated, false otherwise.
	 */
	public function is_network_activated() {

		// Only need to test once.
		static $is_network_active;

		// Have we done this already?
		if ( isset( $is_network_active ) ) {
			return $is_network_active;
		}

		// If not multisite, it cannot be.
		if ( ! is_multisite() ) {
			$is_network_active = false;
			return $is_network_active;
		}

		// Make sure plugin file is included when outside admin.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get path from 'plugins' directory to this plugin.
		$this_plugin = plugin_basename( WPCV_CGI_FILE );

		// Test if network active.
		$is_network_active = is_plugin_active_for_network( $this_plugin );

		// --<
		return $is_network_active;

	}

	/**
	 * Checks if CiviCRM is initialised.
	 *
	 * @since 0.1
	 *
	 * @return bool True if CiviCRM initialised, false otherwise.
	 */
	public function is_civicrm_initialised() {

		// Bail if CiviCRM is not fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) || ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Bail if no CiviCRM init function.
		if ( ! function_exists( 'civi_wp' ) ) {
			return false;
		}

		// Try and initialise CiviCRM.
		return civi_wp()->initialize();

	}

	/**
	 * Write to the error log.
	 *
	 * @since 0.1.2
	 *
	 * @param array $data The data to write to the log file.
	 */
	public function log_error( $data = [] ) {

		// Skip if not debugging.
		if ( WPCV_CGI_DEBUG === false ) {
			return;
		}

		// Skip if empty.
		if ( empty( $data ) ) {
			return;
		}

		// Format data.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$error = print_r( $data, true );

		// Write to log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $error );

	}

}



/**
 * Utility to get a reference to this plugin.
 *
 * @since 0.1
 *
 * @return object WPCV_CGI The plugin reference.
 */
function wpcv_cgi() {

	// Store instance in static variable.
	static $plugin = false;

	// Maybe return instance.
	if ( false === $plugin ) {
		$plugin = new WPCV_CGI();
	}

	// --<
	return $plugin;

}

// Initialise plugin when plugins have loaded.
add_action( 'plugins_loaded', 'wpcv_cgi' );

/*
 * Uninstall uses the 'uninstall.php' method.
 *
 * @see https://codex.wordpress.org/Function_Reference/register_uninstall_hook
 */



/**
 * Utility to add link to settings page.
 *
 * @since 0.1
 *
 * @param array $links The existing links array.
 * @param str $file The name of the plugin file.
 * @return array $links The modified links array.
 */
function wpcv_cgi_action_links( $links, $file ) {

	// Add links only when CiviCRM is fully installed.
	if ( ! defined( 'CIVICRM_INSTALLED' ) || ! CIVICRM_INSTALLED ) {
		return $links;
	}

	// Bail if CiviCRM plugin is not present.
	if ( ! function_exists( 'civi_wp' ) ) {
		return $links;
	}

	// Bail if we don't have the "Groups" plugin.
	if ( ! defined( 'GROUPS_CORE_VERSION' ) ) {
		return $links;
	}

	// Add settings link.
	if ( $file === plugin_basename( dirname( __FILE__ ) . '/wpcv-civicrm-groups-integration.php' ) ) {

		/*
		// Add settings link if not network activated and not viewing network admin.
		$link = add_query_arg( [ 'page' => 'wpcv_cgi_parent' ], admin_url( 'options-general.php' ) );
		$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'wpcv-civicrm-groups-integration' ) . '</a>';
		*/

		// Always add Paypal link.
		$paypal = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . $paypal . '" target="_blank">' . __( 'Donate!', 'wpcv-civicrm-groups-integration' ) . '</a>';

	}

	// --<
	return $links;

}

// Add filter for the above.
add_filter( 'plugin_action_links', 'wpcv_cgi_action_links', 10, 2 );
