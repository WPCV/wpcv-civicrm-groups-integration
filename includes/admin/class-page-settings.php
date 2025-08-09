<?php
/**
 * Settings Page class.
 *
 * Handles Settings Page functionality.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Settings Page class.
 *
 * A class that encapsulates Settings Page functionality.
 *
 * @since 0.3.0
 */
class WPCV_CGI_Page_Settings extends WPCV_CGI_Page_Settings_Base {

	/**
	 * Plugin object.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var WPCV_CGI
	 */
	public $plugin;

	/**
	 * Admin object.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var WPCV_CGI_Admin
	 */
	public $admin;

	/**
	 * Form interval ID.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var string
	 */
	public $form_interval_id = 'interval_id';

	/**
	 * Form sync direction ID.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var string
	 */
	public $form_direction_id = 'direction_id';

	/**
	 * Form batch ID.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var string
	 */
	public $form_batch_id = 'batch_id';

	/**
	 * Class constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param object $admin The admin object.
	 */
	public function __construct( $admin ) {

		// Store references to objects.
		$this->plugin = $admin->plugin;
		$this->admin  = $admin;

		// Set a unique prefix for all Pages.
		$this->hook_prefix_common = 'cvgrp_admin';

		// Set a unique prefix.
		$this->hook_prefix = 'cvgrp_settings';

		// Assign page slugs.
		$this->page_slug = 'cvgrp_settings';

		/*
		// Assign page layout.
		$this->page_layout = 'dashboard';
		*/

		// Assign path to plugin directory.
		$this->path_plugin = WPCV_CGI_PATH;

		// Assign form IDs.
		$this->form_interval_id  = $this->hook_prefix . '_' . $this->form_interval_id;
		$this->form_direction_id = $this->hook_prefix . '_' . $this->form_direction_id;
		$this->form_batch_id     = $this->hook_prefix . '_' . $this->form_batch_id;

		// Bootstrap parent.
		parent::__construct();

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.3.0
	 */
	public function initialise() {

		// Assign translated strings.
		$this->plugin_name          = __( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );
		$this->page_title           = __( 'Settings for Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );
		$this->page_tab_label       = __( 'Settings', 'wpcv-civicrm-groups-integration' );
		$this->page_menu_label      = __( 'Groups Sync', 'wpcv-civicrm-groups-integration' );
		$this->page_help_label      = __( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );
		$this->metabox_submit_title = __( 'Settings', 'wpcv-civicrm-groups-integration' );

	}

	/**
	 * Adds styles.
	 *
	 * @since 0.3.0
	 */
	public function admin_styles() {

		// Enqueue our "Settings Page" stylesheet.
		wp_enqueue_style(
			$this->hook_prefix . '-css',
			plugins_url( 'assets/css/page-settings.css', WPCV_CGI_FILE ),
			false,
			WPCV_CGI_VERSION, // Version.
			'all' // Media.
		);

	}

	/**
	 * Adds scripts.
	 *
	 * @since 0.3.0
	 */
	public function admin_scripts() {

		// Enqueue our "Settings Page" script.
		wp_enqueue_script(
			$this->hook_prefix . '-js',
			plugins_url( 'assets/js/page-settings.js', WPCV_CGI_FILE ),
			[ 'jquery' ],
			WPCV_CGI_VERSION, // Version.
			true
		);

	}

	/**
	 * Registers meta boxes.
	 *
	 * @since 0.3.0
	 *
	 * @param string $screen_id The Settings Page Screen ID.
	 * @param array  $data The array of metabox data.
	 */
	public function meta_boxes_register( $screen_id, $data ) {

		// Bail if not the Screen ID we want.
		if ( $screen_id !== $this->page_context . $this->page_slug ) {
			return;
		}

		// Check User permissions.
		if ( ! $this->page_capability() ) {
			return;
		}

		// Define a handle for the following metabox.
		$handle = $this->hook_prefix . '_settings_schedule';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'Recurring Schedules', 'wpcv-civicrm-groups-integration' ),
			[ $this, 'meta_box_schedule_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		/*
		// Make this metabox closed by default.
		add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		/**
		 * Broadcast that the metaboxes have been added.
		 *
		 * @since 0.3.0
		 *
		 * @param string $screen_id The Screen indentifier.
		 * @param array $vars The array of metabox data.
		 */
		do_action( $this->hook_prefix . '_settings_page_meta_boxes_added', $screen_id, $data );

	}

	/**
	 * Renders "Recurring Schedules" meta box on Settings screen.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_schedule_render( $unused, $metabox ) {

		// Get our settings.
		$interval    = $this->admin->setting_get( 'interval' );
		$direction   = $this->admin->setting_get( 'direction' );
		$batch_count = (int) $this->admin->setting_get( 'batch_count' );

		// First item.
		$first = [
			'off' => [
				'interval' => 0,
				'display'  => __( 'Off', 'wpcv-civicrm-groups-integration' ),
			],
		];

		// Build schedules.
		$schedules = $this->admin->schedule->intervals_get();
		$schedules = $first + $schedules;

		// Include template file.
		include $this->path_plugin . $this->path_template . $this->path_metabox . 'metabox-settings-schedule.php';

	}

	/**
	 * Performs save actions when the form has been submitted.
	 *
	 * @since 0.3.0
	 *
	 * @param string $submit_id The Settings Page form submit ID.
	 */
	public function form_save( $submit_id ) {

		// Check that we trust the source of the data.
		check_admin_referer( $this->form_nonce_action, $this->form_nonce_field );

		// Get existing interval.
		$existing_interval = $this->admin->setting_get( 'interval' );

		// Set new interval.
		$interval     = 'off';
		$interval_raw = filter_input( INPUT_POST, $this->form_interval_id );
		if ( ! empty( $interval_raw ) ) {
			$interval = sanitize_text_field( wp_unslash( $interval_raw ) );
		}
		$this->admin->setting_set( 'interval', esc_sql( $interval ) );

		// Set new sync direction.
		$direction     = 'civicrm';
		$direction_raw = filter_input( INPUT_POST, $this->form_direction_id );
		if ( ! empty( $direction_raw ) ) {
			$direction = sanitize_text_field( wp_unslash( $direction_raw ) );
		}
		$this->admin->setting_set( 'direction', esc_sql( $direction ) );

		// Set new batch count.
		$batch_count_raw = filter_input( INPUT_POST, $this->form_batch_id );
		$batch_count     = (int) sanitize_text_field( wp_unslash( $batch_count_raw ) );
		$this->admin->setting_set( 'batch_count', esc_sql( $batch_count ) );

		// Clear current scheduled event if the schedule is being deactivated.
		if ( 'off' !== $existing_interval && 'off' === $interval ) {
			$this->admin->schedule->unschedule();
		}

		/*
		 * Clear current scheduled event and add new scheduled event
		 * if the schedule is active and the interval has changed.
		 */
		if ( 'off' !== $interval && $interval !== $existing_interval ) {
			$this->admin->schedule->unschedule();
			$this->admin->schedule->schedule( $interval );
		}

		// Save settings.
		$this->admin->settings_save();

	}

}
