<?php
/**
 * CiviCRM Permissions class.
 *
 * Handles CiviCRM Permissions-related functionality.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Permissions Class.
 *
 * A class that encapsulates CiviCRM Permissions functionality.
 *
 * @since 1.0.0
 */
class WPCV_CGI_CiviCRM_Permissions {

	/**
	 * Plugin object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var object $civicrm The CiviCRM object.
	 */
	public $civicrm;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param object $civicrm The CiviCRM object.
	 */
	public function __construct( $civicrm ) {

		// Store reference to plugin.
		$this->plugin  = $civicrm->plugin;
		$this->civicrm = $civicrm;

		// Initialise when CiviCRM class is loaded.
		add_action( 'wpcv_cgi/civicrm/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialises this object.
	 *
	 * @since 1.0.0
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {

		// Sync when CiviCRM has refreshed the WordPress capabilities.
		add_action( 'civicrm_capabilities_refreshed', [ $this, 'capabilities_sync' ], 20 );

		// Sync when a CiviCRM Extension's status changes from uninstalled to enabled.
		add_action( 'civicrm_install', [ $this, 'capabilities_sync' ], 20 );

		// Sync when a CiviCRM Extension's status changes from disabled to enabled.
		add_action( 'civicrm_enable', [ $this, 'capabilities_sync' ], 20 );

		// Sync when a CiviCRM Extension's status changes from enabled to disabled.
		add_action( 'civicrm_disable', [ $this, 'capabilities_sync' ], 20 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets all CiviCRM permissions converted to WordPress capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @return array $capabilities The array of CiviCRM permissions converted to WordPress capabilities.
	 */
	public function capabilities_get_all() {

		// Init return.
		$capabilities = [];

		// Bail if CiviCRM not initialised.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $capabilities;
		}

		// Get all CiviCRM permissions, excluding disabled components and descriptions.
		$permissions = CRM_Core_Permission::basicPermissions( false, false );

		// Convert to WordPress capabilities.
		foreach ( $permissions as $permission => $title ) {
			$capabilities[] = CRM_Utils_String::munge( strtolower( $permission ) );
		}

		/**
		 * Filters the complete set of CiviCRM capabilities.
		 *
		 * @since 1.0.0
		 *
		 * @param array $capabilities The complete set of CiviCRM capabilities.
		 */
		$capabilities = apply_filters( 'wpcv_cgi/civicrm/capabilities', $capabilities );

		// --<
		return $capabilities;

	}

	/**
	 * Queues syncing capabilities to "Groups" plugin when CiviCRM is available.
	 *
	 * @since 1.0.0
	 */
	public function capabilities_sync_queue() {

		// Wait until "init" hook.
		add_action( 'init', [ $this, 'capabilities_sync' ] );

	}

	/**
	 * Syncs capabilities to the "Groups" plugin.
	 *
	 * @since 1.0.0
	 */
	public function capabilities_sync() {

		// Get all CiviCRM permissions.
		$capabilities = $this->capabilities_get_all();

		// Add the capabilities if not already added.
		foreach ( $capabilities as $capability ) {
			if ( ! Groups_Capability::read_by_capability( $capability ) ) {
				Groups_Capability::create( [ 'capability' => $capability ] );
			}
		}

		// Delete capabilities that no longer exist.
		$this->capabilities_delete_missing( $capabilities );

	}

	/**
	 * Deletes CiviCRM capabilities when they no longer exist.
	 *
	 * This can happen when an Extension which had previously added permissions
	 * is disabled or uninstalled, for example.
	 *
	 * @since 1.0.0
	 *
	 * @param array $capabilities The complete set of CiviCRM capabilities.
	 */
	public function capabilities_delete_missing( $capabilities ) {

		/**
		 * Filters whether capabilities should be deleted.
		 *
		 * To enable deletion of capabilities, return boolean true.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $allow_delete False (disabled) by default.
		 */
		$allow_delete = apply_filters( 'wpcv_cgi/civicrm/capabilities/delete_missing', false );
		if ( false === $allow_delete ) {
			return;
		}

		// Read the stored CiviCRM permissions array.
		$stored = $this->permissions_get_stored();

		// Save and bail if we don't have any stored.
		if ( empty( $stored ) ) {
			$this->permissions_store( $capabilities );
			return;
		}

		// Find the capabilities that are missing in the current CiviCRM data.
		$not_in_current = array_diff( $stored, $capabilities );

		// Delete the capabilities if not already deleted.
		foreach ( $capabilities as $capability ) {
			$groups_cap = Groups_Capability::read_by_capability( $capability );
			if ( ! empty( $groups_cap->capability_id ) ) {
				Groups_Capability::delete( $groups_cap->capability_id );
			}
		}

		// Overwrite the current permissions array.
		$this->permissions_store( $capabilities );

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets the array of stored CiviCRM permissions.
	 *
	 * Only used when deleting CiviCRM capabilities is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return array $permissions The array of stored permissions.
	 */
	public function permissions_get_stored() {

		// Get from option.
		$permissions = get_option( 'wpcv_cgi_stored_permissions', 'false' );

		// If no option exists, cast return as array.
		if ( 'false' === $permissions ) {
			$permissions = [];
		}

		// --<
		return $permissions;

	}

	/**
	 * Stores the array of CiviCRM permissions.
	 *
	 * Only used when deleting CiviCRM capabilities is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param array $permissions The array of permissions to store.
	 */
	public function permissions_store( $permissions ) {

		// Set the option.
		update_option( 'wpcv_cgi_stored_permissions', $permissions );

	}

}
