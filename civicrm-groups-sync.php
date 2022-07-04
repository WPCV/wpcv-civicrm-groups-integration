<?php
/**
 * Plugin Name: CiviCRM Groups Sync
 * Plugin URI: https://develop.tadpole.cc/plugins/civicrm-groups-sync
 * Description: Keeps Contacts in CiviCRM Groups in sync with WordPress Users in Groups provided by the Groups plugin.
 * Author: Christian Wach
 * Version: 1.0.0a
 * Author URI: http://haystack.co.uk
 * Text Domain: civicrm-groups-sync
 * Domain Path: /languages
 * Depends: CiviCRM
 *
 * @package CiviCRM_Groups_Sync
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;



// Set our version here.
define( 'CIVICRM_GROUPS_SYNC_VERSION', '1.0.0a' );

// Store reference to this file.
if ( ! defined( 'CIVICRM_GROUPS_SYNC_FILE' ) ) {
	define( 'CIVICRM_GROUPS_SYNC_FILE', __FILE__ );
}

// Store URL to this plugin's directory.
if ( ! defined( 'CIVICRM_GROUPS_SYNC_URL' ) ) {
	define( 'CIVICRM_GROUPS_SYNC_URL', plugin_dir_url( CIVICRM_GROUPS_SYNC_FILE ) );
}
// Store PATH to this plugin's directory.
if ( ! defined( 'CIVICRM_GROUPS_SYNC_PATH' ) ) {
	define( 'CIVICRM_GROUPS_SYNC_PATH', plugin_dir_path( CIVICRM_GROUPS_SYNC_FILE ) );
}

// Set debug flag.
if ( ! defined( 'CIVICRM_GROUPS_SYNC_DEBUG' ) ) {
	define( 'WPCV_TAX_FIELD_SYNC_DEBUG', false );
}



/**
 * Plugin class.
 *
 * A class that encapsulates plugin functionality.
 *
 * @since 0.1
 */
class CiviCRM_Groups_Sync {

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
		do_action( 'civicrm_groups_sync_loaded' );

	}

	/**
	 * Checks the dependencies for this plugin.
	 *
	 * @since 0.1.2
	 */
	public function dependencies() {

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
		require CIVICRM_GROUPS_SYNC_PATH . 'includes/class-admin.php';
		require CIVICRM_GROUPS_SYNC_PATH . 'includes/class-civicrm.php';
		require CIVICRM_GROUPS_SYNC_PATH . 'includes/class-wordpress.php';

	}

	/**
	 * Sets up this plugin's objects.
	 *
	 * @since 0.1
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->admin = new CiviCRM_Groups_Sync_Admin( $this );
		$this->civicrm = new CiviCRM_Groups_Sync_CiviCRM( $this );
		$this->wordpress = new CiviCRM_Groups_Sync_WordPress( $this );

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
			'civicrm-groups-sync', // Unique name.
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
		$this_plugin = plugin_basename( CIVICRM_GROUPS_SYNC_FILE );

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
		if ( WPCV_TAX_FIELD_SYNC_DEBUG === false ) {
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
 * @return object CiviCRM_Groups_Sync The plugin reference.
 */
function civicrm_groups_sync() {

	// Store instance in static variable.
	static $civicrm_groups_sync = false;

	// Maybe return instance.
	if ( false === $civicrm_groups_sync ) {
		$civicrm_groups_sync = new CiviCRM_Groups_Sync();
	}

	// --<
	return $civicrm_groups_sync;

}

// Initialise plugin when plugins have loaded.
add_action( 'plugins_loaded', 'civicrm_groups_sync' );

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
function civicrm_groups_sync_action_links( $links, $file ) {

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
	if ( $file == plugin_basename( dirname( __FILE__ ) . '/civicrm-groups-sync.php' ) ) {

		// Add settings link if not network activated and not viewing network admin.
		$link = add_query_arg( [ 'page' => 'civicrm_groups_sync_parent' ], admin_url( 'options-general.php' ) );
		//$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'civicrm-groups-sync' ) . '</a>';

		// Always add Paypal link.
		$paypal = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . $paypal . '" target="_blank">' . __( 'Donate!', 'civicrm-groups-sync' ) . '</a>';

	}

	// --<
	return $links;

}

// Add filter for the above.
add_filter( 'plugin_action_links', 'civicrm_groups_sync_action_links', 10, 2 );
