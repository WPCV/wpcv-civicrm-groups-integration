<?php
/**
 * Integrate CiviCRM with Groups
 *
 * Plugin Name:       Integrate CiviCRM with Groups
 * Description:       Integrates CiviCRM Groups with Groups provided by the Groups plugin.
 * Plugin URI:        https://github.com/WPCV/wpcv-civicrm-groups-integration
 * GitHub Plugin URI: https://github.com/WPCV/wpcv-civicrm-groups-integration
 * Version:           1.0.0a
 * Author:            WPCV
 * Author URI:        https://github.com/WPCV
 * Text Domain:       wpcv-civicrm-groups-integration
 * Domain Path:       /languages
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
	 * @var WPCV_CGI_Admin
	 */
	public $admin;

	/**
	 * CiviCRM utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var WPCV_CGI_CiviCRM
	 */
	public $civicrm;

	/**
	 * WordPress utilities object.
	 *
	 * @since 0.1
	 * @access public
	 * @var WPCV_CGI_WordPress
	 */
	public $wordpress;

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 */
	public function __construct() {

		// Always include WP-CLI command.
		require_once WPCV_CGI_PATH . 'includes/wp-cli/wp-cli-cvgrp.php';

		// Initialise this plugin.
		add_action( 'plugins_loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialises the plugin.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Bail if dependencies fail.
		if ( ! $this->dependencies() ) {
			return;
		}

		// Only do this once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Bootstrap plugin.
		add_action( 'init', [ $this, 'enable_translation' ] );
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this plugin is now loaded.
		 *
		 * @since 0.1
		 */
		do_action( 'wpcv_cgi/loaded' );

		// We're done.
		$done = true;

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
		if ( ! defined( 'CIVICRM_INSTALLED' ) || ! CIVICRM_INSTALLED ) {
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
	 * Includes files.
	 *
	 * @since 0.1
	 */
	public function include_files() {

		// Load our class files.
		require WPCV_CGI_PATH . 'includes/admin/class-admin.php';
		require WPCV_CGI_PATH . 'includes/class-civicrm.php';
		require WPCV_CGI_PATH . 'includes/class-wordpress.php';

	}

	/**
	 * Sets up this plugin's objects.
	 *
	 * @since 0.1
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->admin     = new WPCV_CGI_Admin( $this );
		$this->civicrm   = new WPCV_CGI_CiviCRM( $this );
		$this->wordpress = new WPCV_CGI_WordPress( $this );

	}

	/**
	 * Registers hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Add action links.
		add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );

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

	// -----------------------------------------------------------------------------------

	/**
	 * Performs plugin activation tasks.
	 *
	 * @since 0.3.0
	 */
	public function activate() {

	}

	/**
	 * Performs plugin deactivation tasks.
	 *
	 * @since 0.3.0
	 */
	public function deactivate() {

		// Remove scheduled hook.
		if ( ! empty( $this->schedule ) ) {
			$this->schedule->unschedule();
		}

	}

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

	/**
	 * Write a message to the log file.
	 *
	 * @since 0.1.2
	 *
	 * @param string $message The message to write to the log file.
	 */
	public function log_message( $message = '' ) {

		// Skip if not debugging.
		if ( WPCV_CGI_DEBUG === false ) {
			return;
		}

		// Write to log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );

	}

	/**
	 * Adds utility links to settings page.
	 *
	 * @since 0.1
	 * @since 0.3.2 Moved into plugin class.
	 *
	 * @param array  $links The existing links array.
	 * @param string $file The name of the plugin file.
	 * @return array $links The modified links array.
	 */
	public function action_links( $links, $file ) {

		// Bail if not this plugin.
		if ( plugin_basename( dirname( __FILE__ ) . '/wpcv-civicrm-groups-integration.php' ) !== $file ) {
			return $links;
		}

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

		// Add settings link if not network activated and not viewing network admin.
		$link    = add_query_arg( [ 'page' => 'cvgrp_settings' ], admin_url( 'admin.php' ) );
		$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Settings', 'wpcv-civicrm-groups-integration' ) . '</a>';

		// Always add Paypal link.
		$paypal  = 'https://www.paypal.me/interactivist';
		$links[] = '<a href="' . esc_url( $paypal ) . '" target="_blank">' . __( 'Donate!', 'wpcv-civicrm-groups-integration' ) . '</a>';

		// --<
		return $links;

	}

}

/**
 * Utility to get a reference to this plugin.
 *
 * @since 0.1
 *
 * @return WPCV_CGI $plugin The plugin reference.
 */
function wpcv_cgi() {

	// Maybe instantiate plugin.
	static $plugin = false;
	if ( false === $plugin ) {
		$plugin = new WPCV_CGI();
	}

	// --<
	return $plugin;

}

// Bootstrap plugin.
wpcv_cgi();

// Plugin activation.
register_activation_hook( __FILE__, [ wpcv_cgi(), 'activate' ] );

// Plugin deactivation.
register_deactivation_hook( __FILE__, [ wpcv_cgi(), 'deactivate' ] );

/*
 * Uninstall uses the 'uninstall.php' method.
 *
 * @see https://codex.wordpress.org/Function_Reference/register_uninstall_hook
 */
