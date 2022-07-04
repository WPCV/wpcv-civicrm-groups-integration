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
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * Plugin version.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $plugin_version The plugin version.
	 */
	public $plugin_version;

	/**
	 * Plugin settings.
	 *
	 * @since 0.1
	 * @access public
	 * @var array $settings The plugin settings.
	 */
	public $settings = [];

	/**
	 * Parent Page.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $parent_page The parent page.
	 */
	public $parent_page;

	/**
	 * Settings Page.
	 *
	 * @since 0.1
	 * @access public
	 * @var str $settings_page The settings page.
	 */
	public $settings_page;

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

		// Load plugin version.
		$this->plugin_version = $this->option_get( 'wpcv_cgi_version', 'none' );

		// Perform any upgrade tasks.
		$this->upgrade_tasks();

		// Upgrade version if needed.
		if ( $this->plugin_version != WPCV_CGI_VERSION ) {
			$this->store_version();
		}

		// Load settings array.
		$this->settings = $this->option_get( 'wpcv_cgi_settings', $this->settings );

		// Upgrade settings.
		$this->upgrade_settings();

		// Register hooks.
		$this->register_hooks();

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

		// Always sync capabilities on plugin upgrade.
		if ( $this->plugin_version != WPCV_CGI_VERSION ) {
			$this->plugin->civicrm->permissions->capabilities_sync_queue();
		}

	}

	/**
	 * Upgrade settings when required.
	 *
	 * @since 0.1.2
	 */
	public function upgrade_settings() {

		// Don't save by default.
		$save = false;

		/*
		// Some setting may not exist.
		if ( ! $this->setting_exists( 'some_setting' ) ) {

			// Add it from defaults.
			$settings = $this->settings_get_defaults();
			$this->setting_set( 'some_setting', $settings['some_setting'] );
			$save = true;

		}
		*/

		// Save settings if need be.
		if ( $save === true ) {
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

	/**
	 * Register hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		/*
		// Add admin page to Settings menu.
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		*/

	}

	// -------------------------------------------------------------------------

	/**
	 * Add admin menu items for this plugin.
	 *
	 * @since 0.1
	 */
	public function admin_menu() {

		/**
		 * Filters the default capability for accessing the admin menu.
		 *
		 * @since 1.0.0
		 *
		 * @param str $capability The default capability for access to settings page.
		 */
		$capability = apply_filters( 'wpcv_cgi/page_settings/cap', 'manage_options' );

		// Check User permissions.
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// Add the admin page to the Settings menu.
		$this->parent_page = add_options_page(
			__( 'Integrate CiviCRM with Groups: Settings', 'wpcv-civicrm-groups-integration' ),
			__( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' ),
			$capability,
			'wpcv_cgi_parent',
			[ $this, 'page_settings' ]
		);

		// Add help text.
		add_action( 'admin_head-' . $this->parent_page, [ $this, 'admin_head' ], 50 );

		/*
		// Add scripts and styles.
		add_action( 'admin_print_styles-' . $this->parent_page, array( $this, 'admin_css' ) );
		*/

		// Add settings page.
		$this->settings_page = add_submenu_page(
			'wpcv_cgi_parent', // Parent slug.
			__( 'Integrate CiviCRM with Groups: Settings', 'wpcv-civicrm-groups-integration' ), // Page title.
			__( 'Settings', 'wpcv-civicrm-groups-integration' ), // Menu title.
			$capability, // Required caps.
			'wpcv_cgi_settings', // Slug name.
			[ $this, 'page_settings' ] // Callback.
		);

		// Ensure correct menu item is highlighted.
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_menu_highlight' ], 50 );

		// Add help text.
		add_action( 'admin_head-' . $this->settings_page, [ $this, 'admin_head' ], 50 );

		/*
		// Add scripts and styles.
		add_action( 'admin_print_styles-' . $this->settings_page, array( $this, 'admin_css' ) );
		*/

	}

	/**
	 * Highlight the plugin's parent menu item.
	 *
	 * Regardless of the actual admin screen we are on, we need the parent menu
	 * item to be highlighted so that the appropriate menu is open by default
	 * when the subpage is viewed.
	 *
	 * @since 0.1
	 *
	 * @global string $plugin_page The current plugin page.
	 * @global string $submenu_file The current submenu.
	 */
	public function admin_menu_highlight() {

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		global $plugin_page, $submenu_file;

		// Define subpages.
		$subpages = [
			'wpcv_cgi_settings',
		];

		/**
		 * Filter the list of subpages.
		 *
		 * @since 0.1
		 *
		 * @param array $subpages The existing list of subpages.
		 */
		$subpages = apply_filters( 'wpcv_cgi/subpages', $subpages );

		// This tweaks the Settings subnav menu to show only one menu item.
		if ( in_array( $plugin_page, $subpages ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$plugin_page = 'wpcv_cgi_parent';
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu_file = 'wpcv_cgi_parent';
		}

	}

	/**
	 * Initialise plugin help.
	 *
	 * @since 0.1
	 */
	public function admin_head() {

		// Get screen object.
		$screen = get_current_screen();

		// Pass to method in this class.
		$this->admin_help( $screen );

	}

	/**
	 * Adds help copy to admin page.
	 *
	 * @since 0.1
	 *
	 * @param object $screen The existing WordPress screen object.
	 * @return object $screen The amended WordPress screen object.
	 */
	public function admin_help( $screen ) {

		// Init page IDs.
		$pages = [
			$this->parent_page,
			$this->settings_page,
		];

		// Bail if not our screen.
		if ( ! in_array( $screen->id, $pages ) ) {
			return $screen;
		}

		// Add a tab - we can add more later.
		$screen->add_help_tab( [
			'id'      => 'wpcv_cgi_settings',
			'title'   => __( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' ),
			'content' => $this->admin_help_get(),
		] );

		// --<
		return $screen;

	}

	/**
	 * Get help text.
	 *
	 * @since 0.1
	 *
	 * @return string $help The help text formatted as HTML.
	 */
	public function admin_help_get() {

		// Start buffering.
		ob_start();

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/help/settings-help.php';

		// Save the output and flush the buffer.
		$help = ob_get_clean();

		// --<
		return $help;

	}

	// -------------------------------------------------------------------------

	/**
	 * Show our settings page.
	 *
	 * @since 0.1
	 */
	public function page_settings() {

		/**
		 * Filters the default capability for accessing the settings page.
		 *
		 * @since 1.0.0
		 *
		 * @param str $capability The default capability for access to settings page.
		 */
		$capability = apply_filters( 'wpcv_cgi/page_settings/cap', 'manage_options' );

		// Check User permissions.
		if ( ! current_user_can( $capability ) ) {
			return;
		}

		// Get admin page URLs.
		$urls = $this->page_get_urls();

		/**
		 * Do not show tabs by default but allow overrides.
		 *
		 * @since 0.1
		 *
		 * @param bool False by default - do not show tabs.
		 */
		$show_tabs = apply_filters( 'wpcv_cgi/show_tabs', false );

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/admin/settings-page.php';

	}

	/**
	 * Get admin page URLs.
	 *
	 * @since 0.1
	 *
	 * @return array $urls The array of admin page URLs.
	 */
	public function page_get_urls() {

		// Only calculate once.
		if ( isset( $this->urls ) ) {
			return $this->urls;
		}

		// Init return.
		$this->urls = [];

		// Get admin page URLs.
		$this->urls['settings'] = menu_page_url( 'wpcv_cgi_settings', false );

		/**
		 * Filter the list of URLs.
		 *
		 * @since 0.1
		 *
		 * @param array $urls The existing list of URLs.
		 */
		$this->urls = apply_filters( 'wpcv_cgi/page_urls', $this->urls );

		// --<
		return $this->urls;

	}

	/**
	 * Get the URL for the form action.
	 *
	 * @since 0.1
	 *
	 * @return string $target_url The URL for the admin form action.
	 */
	public function page_submit_url_get() {

		// Sanitise admin page URL.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! empty( $target_url ) ) {
			$url_array = explode( '&', $target_url );
			if ( ! empty( $url_array ) ) {
				$url_raw = str_replace( '&amp;updated=true', '', $url_array[0] );
				$target_url = htmlentities( $url_raw . '&updated=true' );
			}
		}

		// --<
		return $target_url;

	}

	// -------------------------------------------------------------------------

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
		 * Filters the default settings.
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
	 * @param string $name The name of the setting.
	 * @return bool Whether or not the setting exists.
	 */
	public function setting_exists( $name = '' ) {

		// Get existence of setting in array.
		return array_key_exists( $name, $this->settings );

	}

	/**
	 * Return a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $name The name of the setting.
	 * @param mixed $default The default value if the setting does not exist.
	 * @return mixed The setting or the default.
	 */
	public function setting_get( $name = '', $default = false ) {

		// Get setting.
		return ( array_key_exists( $name, $this->settings ) ) ? $this->settings[ $name ] : $default;

	}

	/**
	 * Sets a value for a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $name The name of the setting.
	 * @param mixed $value The value of the setting.
	 */
	public function setting_set( $name = '', $value = '' ) {

		// Set setting.
		if ( ! empty( $name ) ) {
			$this->settings[ $name ] = $value;
		}

	}

	/**
	 * Deletes a specified setting.
	 *
	 * @since 0.1
	 *
	 * @param string $name The name of the setting.
	 */
	public function setting_delete( $name = '' ) {

		// Unset setting.
		if ( isset( $this->settings[ $name ] ) ) {
			unset( $this->settings[ $name ] );
		}

	}

	// -------------------------------------------------------------------------

	/**
	 * Test existence of a specified option.
	 *
	 * @since 0.1
	 *
	 * @param str $name The name of the option.
	 * @return bool $exists Whether or not the option exists.
	 */
	public function option_exists( $name ) {

		// Empty options cannot exist.
		if ( empty( $name ) ) {
			return false;
		}

		// Test by getting option with unlikely default.
		if ( $this->option_get( $name, 'fenfgehgefdfdjgrkj' ) == 'fenfgehgefdfdjgrkj' ) {
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
	 * @param str $name The name of the option.
	 * @param str $default The default value of the option if it has no value.
	 * @return mixed $value The value of the option.
	 */
	public function option_get( $name, $default = false ) {

		// Get option.
		$value = get_option( $name, $default );

		// --<
		return $value;

	}

	/**
	 * Set a value for a specified option.
	 *
	 * @since 0.1
	 *
	 * @param str $name The name of the option.
	 * @param mixed $value The value to set the option to.
	 * @return bool $success True if the value of the option was successfully updated.
	 */
	public function option_set( $name, $value = '' ) {

		// Update option.
		return update_option( $name, $value );

	}

	/**
	 * Delete a specified option.
	 *
	 * @since 0.1
	 *
	 * @param str $name The name of the option.
	 * @return bool $success True if the option was successfully deleted.
	 */
	public function option_delete( $name ) {

		// Delete option.
		return delete_option( $name );

	}

}
