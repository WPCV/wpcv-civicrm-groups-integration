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
class WPCV_CGI_Page_Manual_Sync extends WPCV_CGI_Page_Settings_Base {

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
	 * Submit IDs.
	 *
	 * @since 0.3.0
	 * @access public
	 * @var array
	 */
	public $submit_ids = [];

	/**
	 * Class constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param object $parent The parent Settings Page object.
	 */
	public function __construct( $parent ) {

		// Store references to objects.
		$this->plugin = $parent->plugin;
		$this->admin  = $parent->admin;

		// Declare this to be a Sub-page.
		$this->page_parent = $parent;

		// Set a unique prefix.
		$this->hook_prefix = 'cvgrp_manual_sync';

		// Assign page slugs.
		$this->page_slug = 'cvgrp_manual_sync';

		// Assign page layout.
		$this->page_layout = 'dashboard';

		// Assign path to plugin directory.
		$this->path_plugin = WPCV_CGI_PATH;

		// Bootstrap parent.
		parent::__construct();

	}

	/**
	 * Registers hooks.
	 *
	 * @since 0.3.0
	 */
	public function register_hooks() {

		// Add AJAX handlers.
		add_action( 'wp_ajax_' . $this->hook_prefix . '_civicrm_to_wp', [ $this, 'batch_sync_to_wp' ] );
		add_action( 'wp_ajax_' . $this->hook_prefix . '_wp_to_civicrm', [ $this, 'batch_sync_to_civicrm' ] );

		// Filter the allowed Submit IDs.
		add_filter( $this->hook_prefix . '/settings/form/submit_id', [ $this, 'form_buttons_allow' ] );

		// Add some copy to the Page.
		add_filter( $this->hook_prefix . '/settings/page/form/before', [ $this, 'form_description' ] );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 0.3.0
	 */
	public function initialise() {

		// Assign translated strings.
		$this->plugin_name     = __( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );
		$this->page_title      = __( 'Manual Sync for Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );
		$this->page_tab_label  = __( 'Manual Sync', 'wpcv-civicrm-groups-integration' );
		$this->page_menu_label = __( 'Groups Sync', 'wpcv-civicrm-groups-integration' );
		$this->page_help_label = __( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' );

		// Define our button IDs.
		$this->submit_ids = [
			'civicrm_to_wp'      => $this->hook_prefix . '_civicrm_to_wp',
			'civicrm_to_wp_stop' => $this->hook_prefix . '_civicrm_to_wp_stop',
			'wp_to_civicrm'      => $this->hook_prefix . '_wp_to_civicrm',
			'wp_to_civicrm_stop' => $this->hook_prefix . '_wp_to_civicrm_stop',
		];

	}

	/**
	 * Adds styles.
	 *
	 * @since 0.3.0
	 */
	public function admin_styles() {

		// Enqueue our "Manual Sync" Page stylesheet.
		wp_enqueue_style(
			$this->hook_prefix . '-css',
			plugins_url( 'assets/css/page-manual-sync.css', WPCV_CGI_FILE ),
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

		// Enqueue our "Manual Sync" Page script.
		wp_enqueue_script(
			$this->hook_prefix . '-js',
			plugins_url( 'assets/js/page-manual-sync.js', WPCV_CGI_FILE ),
			[ 'jquery', 'jquery-ui-core', 'jquery-ui-progressbar' ],
			WPCV_CGI_VERSION, // Version.
			true
		);

		// Get all the Group Users in the Synced Groups.
		$group_users = $this->plugin->wordpress->group_users_get();

		// Get all the Group Contacts in the Synced Groups.
		$group_contacts = $this->plugin->civicrm->group_contacts_get();
		if ( ( $group_contacts instanceof CRM_Core_Exception ) ) {
			$group_contacts = [];
		}

		// Get the default step count.
		$batch      = new WPCV_CGI_Admin_Batch( $this->hook_prefix . '_wp_to_civicrm' );
		$step_count = $batch->stepper->step_count_get();

		// Init settings.
		$settings = [
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'wp_to_civicrm' => [
				'key'        => 'wp_to_civicrm',
				'submit_id'  => $this->hook_prefix . '_wp_to_civicrm',
				'count'      => count( $group_users ) + count( $group_contacts ),
				'step_count' => $step_count,
			],
			'civicrm_to_wp' => [
				'key'        => 'civicrm_to_wp',
				'submit_id'  => $this->hook_prefix . '_civicrm_to_wp',
				'count'      => count( $group_contacts ) + count( $group_users ),
				'step_count' => $step_count,
			],
		];

		// Init localisation.
		$localisation = [];

		// Add "Groups" Groups localisation.
		$localisation['wp_to_civicrm'] = [
			'total'    => __( 'Group members to sync: {{total}}', 'wpcv-civicrm-groups-integration' ),
			'current'  => __( 'Processing batch {{batch}} of group members {{from}} to {{to}}', 'wpcv-civicrm-groups-integration' ),
			'complete' => __( 'Processing batch {{batch}} of group members {{from}} to {{to}} complete', 'wpcv-civicrm-groups-integration' ),
		];

		// Add CiviCRM Groups localisation.
		$localisation['civicrm_to_wp'] = [
			'total'    => __( 'Group members to sync: {{total}}', 'wpcv-civicrm-groups-integration' ),
			'current'  => __( 'Processing batch {{batch}} of group members {{from}} to {{to}}', 'wpcv-civicrm-groups-integration' ),
			'complete' => __( 'Processing batch {{batch}} of group members {{from}} to {{to}} complete', 'wpcv-civicrm-groups-integration' ),
		];

		// Add common localisation.
		$localisation['common'] = [
			'done' => __( 'All done!', 'wpcv-civicrm-groups-integration' ),
		];

		// Localisation array.
		$vars = [
			'settings'     => $settings,
			'localisation' => $localisation,
		];

		// Localise the WordPress way.
		wp_localize_script(
			$this->hook_prefix . '-js',
			'WPCV_CGI_Manual_Sync_Vars',
			$vars
		);
	}

	/**
	 * Gets the help text.
	 *
	 * @since 1.0.2
	 *
	 * @return string $help The help text formatted as HTML.
	 */
	protected function admin_help_get() {

		// Define path to template.
		$template = $this->path_plugin . $this->path_template . $this->path_help . 'page-manual-sync-help.php';

		// Use contents of help template.
		ob_start();
		require_once $template;
		$help = ob_get_clean();

		// --<
		return $help;

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
		$handle = $this->hook_prefix . '_sync_civicrm_to_wp';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( 'CiviCRM Groups &rarr; "Groups" Groups', 'wpcv-civicrm-groups-integration' ),
			[ $this, 'meta_box_civicrm_to_wp_render' ], // Callback.
			$screen_id, // Screen ID.
			'normal', // Column: options are 'normal' and 'side'.
			'core', // Vertical placement: options are 'core', 'high', 'low'.
			$data
		);

		/*
		// Make this metabox closed by default.
		add_filter( "postbox_classes_{$screen_id}_{$handle}", [ $this, 'meta_box_closed' ] );
		*/

		// Define a handle for the following metabox.
		$handle = $this->hook_prefix . '_sync_wp_to_civicrm';

		// Add the metabox.
		add_meta_box(
			$handle,
			__( '"Groups" Groups &rarr; CiviCRM Groups', 'wpcv-civicrm-groups-integration' ),
			[ $this, 'meta_box_wp_to_civicrm_render' ], // Callback.
			$screen_id, // Screen ID.
			'side', // Column: options are 'normal' and 'side'.
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
	 * Renders "CiviCRM to WordPress" meta box on Settings screen.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_civicrm_to_wp_render( $unused, $metabox ) {

		// Build submit ID and stop ID.
		$submit_id         = $this->hook_prefix . '_civicrm_to_wp';
		$submit_attributes = [
			'data-security' => esc_attr( wp_create_nonce( $submit_id ) ),
		];
		$stop_id           = $this->hook_prefix . '_civicrm_to_wp_stop';

		// Get the current Batch.
		$batch = new WPCV_CGI_Admin_Batch( $submit_id );

		// Meta box description.
		$description = __( 'Synchronize CiviCRM Group Contacts to "Groups" Group Users.', 'wpcv-civicrm-groups-integration' );

		// Button labels.
		$stop_value   = __( 'Stop Sync', 'wpcv-civicrm-groups-integration' );
		$submit_value = __( 'Sync Now', 'wpcv-civicrm-groups-integration' );
		if ( $batch->exists() ) {
			$submit_value = __( 'Continue Sync', 'wpcv-civicrm-groups-integration' );
		}

		// Stop button visibility.
		$stop_visibility = ' hidden';
		if ( $batch->exists() ) {
			$stop_visibility = '';
		}

		// Scrap the Batch.
		unset( $batch );

		// Include template file.
		include $this->path_plugin . $this->path_template . $this->path_metabox . 'metabox-manual-sync.php';

	}

	/**
	 * Renders "WordPress to CiviCRM" meta box on Settings screen.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $unused Unused param.
	 * @param array $metabox Array containing id, title, callback, and args elements.
	 */
	public function meta_box_wp_to_civicrm_render( $unused, $metabox ) {

		// Build submit ID and stop ID.
		$submit_id         = $this->hook_prefix . '_wp_to_civicrm';
		$submit_attributes = [
			'data-security' => esc_attr( wp_create_nonce( $submit_id ) ),
		];
		$stop_id           = $this->hook_prefix . '_wp_to_civicrm_stop';

		// Get the current Batch.
		$batch = new WPCV_CGI_Admin_Batch( $submit_id );

		// Meta box description.
		$description = __( 'Synchronize "Groups" Group Users to CiviCRM Group Contacts.', 'wpcv-civicrm-groups-integration' );

		// Button labels.
		$stop_value   = __( 'Stop Sync', 'wpcv-civicrm-groups-integration' );
		$submit_value = __( 'Sync Now', 'wpcv-civicrm-groups-integration' );
		if ( $batch->exists() ) {
			$submit_value = __( 'Continue Sync', 'wpcv-civicrm-groups-integration' );
		}

		// Stop button visibility.
		$stop_visibility = ' hidden';
		if ( $batch->exists() ) {
			$stop_visibility = '';
		}

		// Scrap the Batch.
		unset( $batch );

		// Include template file.
		include $this->path_plugin . $this->path_template . $this->path_metabox . 'metabox-manual-sync.php';

	}

	/**
	 * Adds a Page description.
	 *
	 * @since 0.3.0
	 */
	public function form_description() {

		// Advice paragraph.
		echo sprintf(
			'<p>%s</p>',
			esc_html__( 'Choose your sync direction depending on whether your CiviCRM Groups or your "Groups" Groups are the "source of truth".', 'wpcv-civicrm-groups-integration' )
		);

		// Procedure paragraph.
		echo sprintf(
			'<p>%s</p>',
			esc_html__( 'The procedure in both directions is as follows:', 'wpcv-civicrm-groups-integration' )
		);

		// Procedure list.
		echo '<ol>';
		echo sprintf(
			'<li>%s</li>',
			esc_html__( 'Group members in the source Group will be added to the target Group if they are missing.', 'wpcv-civicrm-groups-integration' )
		);
		echo sprintf(
			'<li>%s</li>',
			esc_html__( 'Group members in the target Group will be deleted if they are no longer members of the source Group.', 'wpcv-civicrm-groups-integration' )
		);
		echo '</ol>';

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

		// Delete the batch and bail if this is a stop button.
		if ( $this->form_button_is_stop( $submit_id ) ) {
			$batch_id = $this->form_button_submit_for_stop_get( $submit_id );
			$batch    = new WPCV_CGI_Admin_Batch( $batch_id );
			$batch->delete();
			unset( $batch );
			return;
		}

		// Find the current sync type.
		$sync_type = '';
		foreach ( $this->submit_ids as $type => $value ) {
			if ( $submit_id === $value ) {
				$sync_type = $type;
				break;
			}
		}

		// Bail if sync type is not discovered.
		if ( empty( $sync_type ) ) {
			return;
		}

		// Was a CiviCRM Groups to "Groups" Groups button pressed?
		if ( 'civicrm_to_wp' === $sync_type ) {
			$this->plugin->civicrm->batch_sync_to_wp( $submit_id );
		}

		// Was a "Groups" Groups to CiviCRM Groups button pressed?
		if ( 'wp_to_civicrm' === $sync_type ) {
			$this->plugin->wordpress->batch_sync_to_civicrm( $submit_id );
		}

	}

	/**
	 * Allow stepped buttons.
	 *
	 * @since 0.3.0
	 *
	 * @param string $submit_id The Settings Page form submit ID.
	 */
	public function form_buttons_allow( $submit_id ) {

		// Allow form_save() to run.
		foreach ( $this->submit_ids as $value ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST[ $value ] ) ) {
				$submit_id = $value;
				break;
			}
		}

		// --<
		return $submit_id;

	}

	/**
	 * Checks if a given button ID is for a stop button.
	 *
	 * @since 0.3.0
	 *
	 * @param string $button_id The form button ID.
	 * @return bool $is_stop The form button ID.
	 */
	public function form_button_is_stop( $button_id ) {

		// Check for "stop" in the ID.
		if ( false === strpos( $button_id, '_stop' ) ) {
			$is_stop = false;
		} else {
			$is_stop = true;
		}

		// --<
		return $is_stop;

	}

	/**
	 * Gets the submit button ID for a given stop button.
	 *
	 * @since 0.3.0
	 *
	 * @param string $button_id The stop button ID.
	 * @return string $submit_id The corresponding submit button ID.
	 */
	public function form_button_submit_for_stop_get( $button_id ) {

		// Skip if it's not a stop button.
		if ( ! $this->form_button_is_stop( $button_id ) ) {
			return $button_id;
		}

		// Remove the suffix.
		$submit_id = str_replace( '_stop', '', $button_id );

		// --<
		return $submit_id;

	}

	/**
	 * Batch sync "Groups" Groups to CiviCRM Groups.
	 *
	 * @since 0.3.0
	 */
	public function batch_sync_to_civicrm() {

		$data = [];

		// Build submit ID.
		$identifier = $this->hook_prefix . '_wp_to_civicrm';

		// Since this is an AJAX request, check security.
		$result = check_ajax_referer( $identifier, false, false );
		if ( false === $result ) {
			$data['finished'] = 'true';
			wp_send_json( $data );
		}

		// Trigger batch process.
		$this->plugin->wordpress->batch_sync_to_civicrm( $identifier );

		// Get the current Batch.
		$batch = new WPCV_CGI_Admin_Batch( $identifier );
		if ( $batch->exists() ) {

			// Set from and to flags.
			$data['finished'] = 'false';
			$data['batch']    = $batch->get();
			$data['from']     = $batch->stepper->get();
			$data['to']       = $batch->stepper->next_get();

		} else {
			$data['finished'] = 'true';
		}

		// Send data to browser.
		wp_send_json( $data );

	}

	/**
	 * Batch sync CiviCRM Groups to "Groups" Groups.
	 *
	 * @since 0.3.0
	 */
	public function batch_sync_to_wp() {

		$data = [];

		// Build identifier.
		$identifier = $this->hook_prefix . '_civicrm_to_wp';

		// Since this is an AJAX request, check security.
		$result = check_ajax_referer( $identifier, false, false );
		if ( false === $result ) {
			$data['finished'] = 'true';
			wp_send_json( $data );
		}

		// Trigger batch process.
		$this->plugin->civicrm->batch_sync_to_wp( $identifier );

		// Get the current Batch.
		$batch = new WPCV_CGI_Admin_Batch( $identifier );
		if ( $batch->exists() ) {

			// Set from and to flags.
			$data['finished'] = 'false';
			$data['batch']    = $batch->get();
			$data['from']     = $batch->stepper->get();
			$data['to']       = $batch->stepper->next_get();

		} else {
			$data['finished'] = 'true';
		}

		// Send data to browser.
		wp_send_json( $data );

	}

}
