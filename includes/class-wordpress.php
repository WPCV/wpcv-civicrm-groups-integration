<?php
/**
 * WordPress class.
 *
 * Handles WordPress-related functionality.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WordPress Class.
 *
 * A class that encapsulates functionality for interacting with the "Groups"
 * plugin in WordPress.
 *
 * @since 0.1
 */
class WPCV_CGI_WordPress {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

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

		// Initialise when plugin is loaded.
		add_action( 'wpcv_cgi/loaded', [ $this, 'initialise' ] );

	}

	/**
	 * Initialise this object.
	 *
	 * @since 0.1
	 */
	public function initialise() {

		// Register hooks.
		$this->register_hooks();

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Hook into Group creation.
		add_action( 'groups_created_group', [ $this, 'group_created' ], 10 );

		// Hook into Group updates.
		add_action( 'groups_updated_group', [ $this, 'group_updated' ], 10 );

		// Hook into Group deletion.
		add_action( 'groups_deleted_group', [ $this, 'group_deleted' ], 10 );

		// Add option to Group add form.
		add_filter( 'groups_admin_groups_add_form_after_fields', [ $this, 'form_add_filter' ], 10 );

		// Add option to Group edit form.
		add_filter( 'groups_admin_groups_edit_form_after_fields', [ $this, 'form_edit_filter' ], 10, 2 );

		/*
		// Hook into form submission?
		//add_action( 'groups_admin_groups_add_submit_success', [ $this, 'form_submitted' ], 10 );
		//add_action( 'groups_admin_groups_edit_submit_success', [ $this, 'form_submitted' ], 10 );
		*/

		// Hook into User additions to a Group.
		add_action( 'groups_created_user_group', [ $this, 'group_member_added' ], 10, 2 );

		// Hook into User deletions from a Group.
		add_action( 'groups_deleted_user_group', [ $this, 'group_member_deleted' ], 10, 2 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Create a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param array $params The params used to create the Group.
	 * @return int|bool $group_id The ID of the Group, or false on failure.
	 */
	public function group_create( $params ) {

		// Bail if a Group by that name exists.
		if ( ! empty( $params['name'] ) ) {
			$group = Groups_Group::read_by_name( $params['name'] );
			if ( ! empty( $group->group_id ) ) {
				return false;
			}
		}

		// Remove hook.
		remove_action( 'groups_created_group', [ $this, 'group_created' ], 10 );

		// Create the Group.
		$group_id = Groups_Group::create( $params );

		// Reinstate hook.
		add_action( 'groups_created_group', [ $this, 'group_created' ], 10 );

		// --<
		return $group_id;

	}

	/**
	 * Update a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param array $params The params used to update the Group.
	 * @return int|bool $group_id The ID of the Group, or false on failure.
	 */
	public function group_update( $params ) {

		// Remove hook.
		remove_action( 'groups_updated_group', [ $this, 'group_updated' ], 10 );

		// Update the Group.
		$group_id = Groups_Group::update( $params );

		// Reinstate hook.
		add_action( 'groups_updated_group', [ $this, 'group_updated' ], 10 );

		// --<
		return $group_id;

	}

	/**
	 * Delete a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The ID of the Group to delete.
	 * @return int|bool $group_id The ID of the deleted Group, or false on failure.
	 */
	public function group_delete( $group_id ) {

		// Remove hook.
		remove_action( 'groups_deleted_group', [ $this, 'group_deleted' ], 10 );

		// Delete the Group.
		$group_id = Groups_Group::delete( $group_id );

		// Reinstate hook.
		add_action( 'groups_deleted_group', [ $this, 'group_deleted' ], 10 );

		// --<
		return $group_id;

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept when a "Groups" Group is created.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The ID of the new Group.
	 */
	public function group_created( $group_id ) {

		// Bail if our checkbox was not checked.
		if ( ! $this->form_get_sync() ) {
			return;
		}

		// Get full Group data.
		$group = Groups_Group::read( $group_id );

		// Create a synced CiviCRM Group.
		$this->plugin->civicrm->group_create_from_wp_group( $group );

	}

	/**
	 * Intercept when a "Groups" Group is updated.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The ID of the updated Group.
	 */
	public function group_updated( $group_id ) {

		// Get full Group data.
		$group = Groups_Group::read( $group_id );

		// Update the synced CiviCRM Group.
		$this->plugin->civicrm->group_update_from_wp_group( $group );

	}

	/**
	 * Intercept when a "Groups" Group is deleted.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The ID of the deleted Group.
	 */
	public function group_deleted( $group_id ) {

		// Delete the synced CiviCRM Group.
		$this->plugin->civicrm->group_delete_by_wp_id( $group_id );

	}

	// -------------------------------------------------------------------------

	/**
	 * Create a "Groups" Group from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param object|array $civicrm_group The CiviCRM Group data.
	 * @return int|bool $group_id The ID of the "Groups" Group, or false on failure.
	 */
	public function group_create_from_civicrm_group( $civicrm_group ) {

		// Construct minimum "Groups" Group params.
		if ( is_object( $civicrm_group ) ) {
			$params = [
				'name' => isset( $civicrm_group->title ) ? $civicrm_group->title : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group->description ) ? $civicrm_group->description : '',
			];
		} else {
			$params = [
				'name' => isset( $civicrm_group['title'] ) ? $civicrm_group['title'] : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group['description'] ) ? $civicrm_group['description'] : '',
			];
		}

		// Create it.
		$group_id = $this->group_create( $params );

		// --<
		return $group_id;

	}

	/**
	 * Update a "Groups" Group from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param object|array $civicrm_group The CiviCRM Group data.
	 * @return int|bool $group_id The ID of the "Groups" Group, or false on failure.
	 */
	public function group_update_from_civicrm_group( $civicrm_group ) {

		// Construct "Groups" Group params.
		if ( is_object( $civicrm_group ) ) {

			// Init params.
			$params = [
				'name' => isset( $civicrm_group->title ) ? $civicrm_group->title : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group->description ) ? $civicrm_group->description : '',
			];

			// Get source string.
			$source = isset( $civicrm_group->source ) ? $civicrm_group->source : '';

		} else {

			// Init params.
			$params = [
				'name' => isset( $civicrm_group['title'] ) ? $civicrm_group['title'] : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group['description'] ) ? $civicrm_group['description'] : '',
			];

			// Get source string.
			$source = isset( $civicrm_group['source'] ) ? $civicrm_group['source'] : '';

		}

		// Sanity check source.
		if ( empty( $source ) ) {
			return false;
		}

		// Get ID from source string.
		$tmp = explode( 'synced-group-', $source );
		$wp_group_id = isset( $tmp[1] ) ? absint( trim( $tmp[1] ) ) : false;

		// Sanity check.
		if ( empty( $wp_group_id ) ) {
			return false;
		}

		// Add ID to params.
		$params['group_id'] = $wp_group_id;

		// Update the Group.
		$group_id = $this->group_update( $params );

		// --<
		return $group_id;

	}

	/**
	 * Delete a "Groups" Group using a CiviCRM Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civicrm_group_id The ID of the CiviCRM Group.
	 * @return int|bool $group_id The ID of the deleted "Groups" Group, or false on failure.
	 */
	public function group_delete_by_civicrm_group_id( $civicrm_group_id ) {

		// Get the ID of the "Groups" Group.
		$wp_group_id = $this->plugin->civicrm->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( empty( $wp_group_id ) ) {
			return false;
		}

		// Delete the Group.
		$group_id = $this->group_delete( $wp_group_id );

		// --<
		return $group_id;

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a "Groups" Group's admin URL.
	 *
	 * @since 0.1.1
	 *
	 * @param int $group_id The numeric ID of the "Groups" Group.
	 * @return str $group_url The "Groups" Group's admin URL.
	 */
	public function group_get_url( $group_id ) {

		// Get Group admin URL.
		$group_url = admin_url( 'admin.php?page=groups-admin&group_id=' . $group_id . '&action=edit' );

		/**
		 * Filters the URL of the "Groups" Group's admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param str $group_url The existing URL.
		 * @param int $group_id The numeric ID of the CiviCRM Group.
		 */
		$group_url = apply_filters( 'wpcv_cgi/wp/group_url', $group_url, $group_id );

		/**
		 * Filter the URL of the "Groups" Group's admin page.
		 *
		 * @since 0.1.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/page_settings/cap'} filter instead.
		 *
		 * @param str $group_url The existing URL.
		 * @param int $group_id The numeric ID of the CiviCRM Group.
		 */
		return apply_filters_deprecated( 'civicrm_groups_sync_group_get_url_wp', [ $group_url, $group_id ], '1.0.0', 'wpcv_cgi/wp/group_url' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a "Groups" Group using a CiviCRM Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civicrm_group_id The ID of the CiviCRM Group.
	 * @return array|bool $wp_group The "Groups" Group data, or false on failure.
	 */
	public function group_get_by_civicrm_id( $civicrm_group_id ) {

		// Get the ID of the "Groups" Group.
		$wp_group_id = $this->plugin->civicrm->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( empty( $wp_group_id ) ) {
			return false;
		}

		// Get full Group data.
		$wp_group = Groups_Group::read( $wp_group_id );

		// --<
		return $wp_group;

	}

	/**
	 * Get a "Groups" Group ID using a CiviCRM Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $civicrm_group_id The ID of the CiviCRM Group.
	 * @return int|bool $group_id The "Groups" Group ID, or false on failure.
	 */
	public function group_get_wp_id_by_civicrm_id( $civicrm_group_id ) {

		// Get the "Groups" Group.
		$wp_group = $this->group_get_by_civicrm_id( $civicrm_group_id );

		// Sanity check Group.
		if ( empty( $wp_group ) ) {
			return false;
		}

		// Sanity check Group ID.
		if ( empty( $wp_group->group_id ) ) {
			return false;
		}

		// --<
		return $wp_group->group_id;

	}

	// -------------------------------------------------------------------------

	/**
	 * Filter the Add Group form.
	 *
	 * @since 0.1
	 *
	 * @param str $content The existing content to be inserted after the default fields.
	 * @return str $content The modified content to be inserted after the default fields.
	 */
	public function form_add_filter( $content ) {

		// Start buffering.
		ob_start();

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/admin/settings-groups-create.php';

		// Save the output and flush the buffer.
		$field = ob_get_clean();

		// Add field to form.
		$content .= $field;

		// --<
		return $content;

	}

	/**
	 * Filter the Edit Group form.
	 *
	 * @since 0.1
	 *
	 * @param str $content The existing content to be inserted after the default fields.
	 * @param int $group_id The numeric ID of the Group.
	 * @return str $content The modified content to be inserted after the default fields.
	 */
	public function form_edit_filter( $content, $group_id ) {

		// Get existing CiviCRM Group.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if there isn't one.
		if ( $civicrm_group === false ) {
			return $content;
		}

		// Get CiviCRM Group admin URL for template.
		$group_url = $this->plugin->civicrm->group_get_url( $civicrm_group['id'] );

		// Start buffering.
		ob_start();

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/admin/settings-groups-edit.php';

		// Save the output and flush the buffer.
		$field = ob_get_clean();

		// Add field to form.
		$content .= $field;

		// --<
		return $content;

	}

	/**
	 * Get our Group form variable.
	 *
	 * @since 0.1.1
	 *
	 * @return bool $sync True if the Group should be synced, false otherwise.
	 */
	public function form_get_sync() {

		// Do not sync by default.
		$sync = false;

		// Maybe override if our POST variable is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST['civicrm-group-field'] ) && 1 === (int) trim( wp_unslash( $_POST['civicrm-group-field'] ) ) ) {
			$sync = true;
		}

		// --<
		return $sync;

	}

	/**
	 * Intercept successful Group form submission.
	 *
	 * Unfortunately for our purposes, this callback is triggered after the
	 * Group has been created. We therefore have to check for our POST variable
	 * in `group_created`, `group_updated` and `group_deleted` instead.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the Group.
	 */
	public function form_submitted( $group_id ) {

		/*
		$e = new Exception();
		$trace = $e->getTraceAsString();
		error_log( print_r( array(
			'method' => __METHOD__,
			'group_id' => $group_id,
			//'backtrace' => $trace,
		), true ) );
		*/

	}

	// -------------------------------------------------------------------------

	/**
	 * Add a WordPress User to a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The ID of the WordPress User to add to the Group.
	 * @param int $group_id The ID of the "Groups" Group.
	 * @return bool $success True on success, false otherwise.
	 */
	public function group_member_add( $user_id, $group_id ) {

		// Bail if they are already a Group Member.
		if ( Groups_User_Group::read( $user_id, $group_id ) ) {
			return true;
		}

		// Remove hook.
		remove_action( 'groups_created_user_group', [ $this, 'group_member_added' ], 10 );

		// Add User to Group.
		$success = Groups_User_Group::create( [
			'user_id'  => $user_id,
			'group_id' => $group_id,
		] );

		// Reinstate hook.
		add_action( 'groups_created_user_group', [ $this, 'group_member_added' ], 10, 2 );

		// Maybe log on failure?
		if ( ! $success ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'message' => __( 'Could not add User to Group.', 'wpcv-civicrm-groups-integration' ),
				'user_id' => $user_id,
				'group_id' => $group_id,
				'backtrace' => $trace,
			] );
		}

		// --<
		return $success;

	}

	/**
	 * Delete a WordPress User from a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The ID of the WordPress User to delete from the Group.
	 * @param int $group_id The ID of the "Groups" Group.
	 * @return bool $success True on success, false otherwise.
	 */
	public function group_member_delete( $user_id, $group_id ) {

		// Bail if they are not a Group Member.
		if ( ! Groups_User_Group::read( $user_id, $group_id ) ) {
			return true;
		}

		// Remove hook.
		remove_action( 'groups_deleted_user_group', [ $this, 'group_member_deleted' ], 10 );

		// Delete User from Group.
		$success = Groups_User_Group::delete( $user_id, $group_id );

		// Reinstate hook.
		add_action( 'groups_deleted_user_group', [ $this, 'group_member_deleted' ], 10, 2 );

		// Maybe log on failure?
		if ( ! $success ) {
			$e = new Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'message' => __( 'Could not delete User from Group.', 'wpcv-civicrm-groups-integration' ),
				'user_id' => $user_id,
				'group_id' => $group_id,
				'backtrace' => $trace,
			] );
		}

		// --<
		return $success;

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept when a WordPress User is added to a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The ID of the WordPress User added to the Group.
	 * @param int $group_id The ID of the "Groups" Group.
	 */
	public function group_member_added( $user_id, $group_id ) {

		// Get Contact for this User ID.
		$civicrm_contact_id = $this->plugin->civicrm->contact_id_get_by_user_id( $user_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_contact_id ) || $civicrm_contact_id === false ) {
			return;
		}

		// Get CiviCRM Group for this "Groups" Group ID.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_group ) || $civicrm_group === false ) {
			return;
		}

		// Add User to CiviCRM Group.
		$success = $this->plugin->civicrm->group_contact_create( $civicrm_group['id'], $civicrm_contact_id );

	}

	/**
	 * Intercept when a WordPress User is deleted from a "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The ID of the WordPress User added to the Group.
	 * @param int $group_id The ID of the "Groups" Group.
	 */
	public function group_member_deleted( $user_id, $group_id ) {

		// Get Contact for this User ID.
		$civicrm_contact_id = $this->plugin->civicrm->contact_id_get_by_user_id( $user_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_contact_id ) || $civicrm_contact_id === false ) {
			return;
		}

		// Get CiviCRM Group for this "Groups" Group ID.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_group ) || $civicrm_group === false ) {
			return;
		}

		// Remove User from CiviCRM Group.
		$success = $this->plugin->civicrm->group_contact_delete( $civicrm_group['id'], $civicrm_contact_id );

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a WordPress User ID for a given CiviCRM Contact ID.
	 *
	 * @since 0.1
	 *
	 * @param int $contact_id The numeric CiviCRM Contact ID.
	 * @return int|bool $user The WordPress User ID, or false on failure.
	 */
	public function user_id_get_by_contact_id( $contact_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// Search using CiviCRM's logic.
		$user_id = CRM_Core_BAO_UFMatch::getUFId( $contact_id );

		// Cast User ID as boolean if we didn't get one.
		if ( empty( $user_id ) ) {
			$user_id = false;
		}

		/**
		 * Filters the result of the WordPress User lookup.
		 *
		 * You can use this filter to create a WordPress User if none is found.
		 * Return the new WordPress User ID and the Group linkage will be made.
		 *
		 * @since 1.0.0
		 *
		 * @param int|bool $user_id The numeric ID of the WordPress User, or false on failure.
		 * @param int $contact_id The numeric ID of the CiviCRM Contact.
		 */
		$user_id = apply_filters( 'wpcv_cgi/wp/user_id', $user_id, $contact_id );

		/**
		 * Filter the result of the WordPress User lookup.
		 *
		 * You can use this filter to create a WordPress User if none is found.
		 * Return the new WordPress User ID and the Group linkage will be made.
		 *
		 * @since 0.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/wp/user_id'} filter instead.
		 *
		 * @param int|bool $user_id The numeric ID of the WordPress User, or false on failure.
		 * @param int $contact_id The numeric ID of the CiviCRM Contact.
		 */
		$user_id = apply_filters_deprecated( 'civicrm_groups_sync_user_id_get_by_contact_id', [ $user_id, $contact_id ], '1.0.0', 'wpcv_cgi/wp/user_id' );

		// --<
		return $user_id;

	}

	/**
	 * Get a WordPress User object for a given CiviCRM Contact ID.
	 *
	 * @since 0.1
	 *
	 * @param int $contact_id The numeric CiviCRM Contact ID.
	 * @return WP_User|bool $user The WordPress User object, or false on failure.
	 */
	public function user_get_by_contact_id( $contact_id ) {

		// Get WordPress User ID.
		$user_id = $this->user_id_get_by_contact_id( $contact_id );

		// Bail if we didn't get one.
		if ( empty( $user_id ) || $user_id === false ) {
			return false;
		}

		// Get User object.
		$user = new WP_User( $user_id );

		// Bail if we didn't get one.
		if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
			return false;
		}

		// --<
		return $user;

	}

}
