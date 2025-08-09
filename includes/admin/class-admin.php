<?php
/**
 * Admin class.
 *
 * Handles admin functionality.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 *
 * A class that encapsulates admin functionality.
 *
 * @since 0.1
 */
class WPCV_CGI_Admin {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var WPCV_CGI
	 */
	public $plugin;

	/**
	 * Plugin version.
	 *
	 * @since 0.1
	 * @access public
	 * @var string
	 */
	public $plugin_version;

	/**
	 * Plugin settings.
	 *
	 * @since 0.1
	 * @access public
	 * @var array
	 */
	public $settings = [];

	/**
	 * WordPress Schedule object.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var WPCV_CGI_Schedule
	 */
	public $schedule;

	/**
	 * Settings Page object.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var WPCV_CGI_Page_Settings
	 */
	public $page_settings;

	/**
	 * Manual Sync Page object.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var WPCV_CGI_Page_Manual_Sync
	 */
	public $page_manual_sync;

	/**
	 * Class constructor.
	 *
	 * @since 0.1
	 *
	 * @param object $plugin The plugin object.
	 */
	public function __construct( $plugin ) {

		// Store reference to plugin.
		$this->plugin = $plugin;

		// Add action for init.
		add_action( 'wpcv_cgi/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Only do this once.
		static $done;
		if ( isset( $done ) && true === $done ) {
			return;
		}

		// Bootstrap admin.
		$this->include_files();
		$this->setup_objects();
		$this->admin_tasks();

		/**
		 * Fires when admin has loaded.
		 *
		 * Used internally to bootstrap objects.
		 *
		 * @since 0.3.0
		 */
		do_action( 'wpcv_cgi/admin/loaded' );

		// We're done.
		$done = true;

	}

	/**
	 * Includes files.
	 *
	 * @since 0.3.0
	 */
	public function include_files() {

		// Load our class files.
		require WPCV_CGI_PATH . 'includes/admin/class-admin-schedule.php';
		require WPCV_CGI_PATH . 'includes/admin/class-admin-batch.php';
		require WPCV_CGI_PATH . 'includes/admin/class-admin-stepper.php';
		require WPCV_CGI_PATH . 'includes/admin/class-page-settings-base.php';
		require WPCV_CGI_PATH . 'includes/admin/class-page-settings.php';
		require WPCV_CGI_PATH . 'includes/admin/class-page-manual-sync.php';

	}

	/**
	 * Sets up this plugin's objects.
	 *
	 * @since 0.3.0
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->schedule         = new WPCV_CGI_Schedule( $this );
		$this->page_settings    = new WPCV_CGI_Page_Settings( $this );
		$this->page_manual_sync = new WPCV_CGI_Page_Manual_Sync( $this->page_settings );

	}

	/**
	 * Performs plugin admin tasks.
	 *
	 * @since 0.3.0
	 */
	public function admin_tasks() {

		// Load plugin version.
		$this->plugin_version = $this->option_get( 'wpcv_cgi_version', 'none' );

		// Perform any upgrade tasks.
		$this->upgrade_tasks();

		// Upgrade version if needed.
		if ( WPCV_CGI_VERSION !== $this->plugin_version ) {
			$this->store_version();
		}

		// Load settings array.
		$this->settings = $this->option_get( 'wpcv_cgi_settings', $this->settings );

		// Upgrade settings.
		$this->upgrade_settings();

	}

	/**
	 * Perform upgrade tasks.
	 *
	 * @since 0.1
	 */
	public function upgrade_tasks() {

		/*
		// For upgrades by version, use something like the following.
		if ( version_compare( WPCV_CGI_VERSION, '0.3.4', '>=' ) ) {
			// Do something
		}
		*/

		/*
		// Always sync capabilities on plugin upgrade.
		if ( $this->plugin_version != WPCV_CGI_VERSION ) {
			$this->plugin->civicrm->permissions->capabilities_sync_queue();
		}
		*/

	}

	/**
	 * Upgrade settings when required.
	 *
	 * @since 0.1.2
	 */
	public function upgrade_settings() {

		// Don't save by default.
		$save = false;

		/**
		 * Filters the save flag.
		 *
		 * @since 0.3.0
		 *
		 * @param bool $save The save settings flag.
		 */
		$save = apply_filters( 'wpcv_cgi/upgrade_settings', $save );

		// Save settings if need be.
		if ( true === $save ) {
			$this->settings_save();
		}

	}

	/**
	 * Store the plugin version.
	 *
	 * @since 0.1
	 */
	public function store_version() {

		// Store version.
		$this->option_set( 'wpcv_cgi_version', WPCV_CGI_VERSION );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get default settings for this plugin.
	 *
	 * @since 0.1.2
	 *
	 * @return array $settings The default settings for this plugin.
	 */
	public function settings_get_defaults() {

		// Init return.
		$settings = [];

		/**
		 * Filter default settings.
		 *
		 * @since 0.1.2
		 *
		 * @param array $settings The array of default settings.
		 */
		$settings = apply_filters( 'wpcv_cgi/settings_default', $settings );

		// --<
		return $settings;

	}

	/**
	 * Save array as option.
	 *
	 * @since 0.1
	 *
	 * @return bool Success or failure.
	 */
	public function settings_save() {

		// Save array as option.
		return $this->option_set( 'wpcv_cgi_settings', $this->settings );

	}

	/**
	 * Check whether a specified setting exists.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @return bool Whether or not the setting exists.
	 */
	public function setting_exists( $setting_name ) {

		// Get existence of setting in array.
		return array_key_exists( $setting_name, $this->settings );

	}

	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @param mixed  $default The default value if the setting does not exist.
	 * @return mixed The setting or the default.
	 */
	public function setting_get( $setting_name, $default = false ) {

		// Get setting.
		return ( array_key_exists( $setting_name, $this->settings ) ) ? $this->settings[ $setting_name ] : $default;

	}

	/**
	 * Sets a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 * @param mixed  $value The value of the setting.
	 */
	public function setting_set( $setting_name, $value = '' ) {

		// Set setting.
		$this->settings[ $setting_name ] = $value;

	}

	/**
	 * Deletes a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $setting_name The name of the setting.
	 */
	public function setting_delete( $setting_name ) {

		// Unset setting.
		unset( $this->settings[ $setting_name ] );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Test existence of a specified option.
	 *
	 * @since 0.1
	 *
	 * @param string $option_name The name of the option.
	 * @return bool $exists Whether or not the option exists.
	 */
	public function option_exists( $option_name ) {

		// Test by getting option with unlikely default.
		if ( 'fenfgehgefdfdjgrkj' === $this->option_get( $option_name, 'fenfgehgefdfdjgrkj' ) ) {
			return false;
		} else {
			return true;
		}

	}

	/**
	 * Return a value for a specified option.
	 *
	 * @since 0.1
	 *
	 * @param string $option_name The name of the option.
	 * @param string $default The default value of the option if it has no value.
	 * @return mixed $value the value of the option.
	 */
	public function option_get( $option_name, $default = false ) {

		// Get option.
		$value = get_option( $option_name, $default );

		// --<
		return $value;

	}

	/**
	 * Set a value for a specified option.
	 *
	 * @since 0.1
	 *
	 * @param string $option_name The name of the option.
	 * @param mixed  $value The value to set the option to.
	 * @return bool $success True if the value of the option was successfully updated.
	 */
	public function option_set( $option_name, $value = '' ) {

		// Update option.
		return update_option( $option_name, $value );

	}

	/**
	 * Delete a specified option.
	 *
	 * @since 0.1
	 *
	 * @param string $option_name The name of the option.
	 * @return bool $success True if the option was successfully deleted.
	 */
	public function option_delete( $option_name ) {

		// Delete option.
		return delete_option( $option_name );

	}

}
