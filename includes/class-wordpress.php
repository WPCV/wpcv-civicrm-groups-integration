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
	 * @var WPCV_CGI
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

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the IDS of the synced "Groups" Groups.
	 *
	 * @since 0.3.0
	 *
	 * @return array $groups The array of synced Groups, or empty on failure.
	 */
	public function groups_synced_get() {

		// Get all synced CiviCRM Groups.
		$groups         = [];
		$civicrm_groups = $this->plugin->civicrm->groups_synced_get();
		if ( $civicrm_groups instanceof CRM_Core_Exception ) {
			return $groups;
		}

		// Extract "Groups" Group IDs.
		foreach ( $civicrm_groups as $group ) {
			$groups[] = (int) str_replace( 'synced-group-', '', $group['source'] );
		}

		// --<
		return $groups;

	}

	/**
	 * Checks if the "Groups" User Group table exists.
	 *
	 * @since 0.3.0
	 *
	 * @return bool $exists The array of WordPress User IDs.
	 */
	public function group_users_table_exists() {

		global $wpdb;

		// Assume it does.
		$exists = true;

		// Check if the actually table exists.
		$user_group_table = _groups_get_tablename( 'user_group' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $user_group_table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $user_group_table ) ) ) {
			$exists = false;
		}

		// --<
		return $exists;

	}

	/**
	 * Gets the User and Group IDs with a given limit and offset.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $limit The numeric limit for the query.
	 * @param integer $offset The numeric offset for the query.
	 * @return array $group_user_ids The array of WordPress User IDs and "Groups" Group IDs.
	 */
	public function group_users_get( $limit = 0, $offset = 0 ) {

		global $wpdb;

		// Bail if table does not exist.
		if ( ! $this->group_users_table_exists() ) {
			return [];
		}

		// Get the synced "Groups" Groups IDs.
		$group_ids = $this->groups_synced_get();
		if ( empty( $group_ids ) ) {
			return [];
		}

		// Build WHERE clause.
		$where_clause = 'WHERE group_id IN (' . implode( ', ', $group_ids ) . ')';

		// If there is no limit, there's no need for an offset either.
		$user_group_table = _groups_get_tablename( 'user_group' );
		if ( 0 === $limit ) {

			// Perform the query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$group_user_ids = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $user_group_table $where_clause ORDER BY group_id",
				ARRAY_A
			);

		} else {

			// Perform the query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$group_user_ids = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM $user_group_table $where_clause ORDER BY group_id LIMIT %d OFFSET %d",
					$limit,
					$offset
				),
				ARRAY_A
			);

		}

		// --<
		return $group_user_ids;

	}

	/**
	 * Gets the User IDs in a given "Groups" Group.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $group_id The numeric ID of the "Groups" Group.
	 * @return array $user_ids The array of WordPress User IDs.
	 */
	public function group_user_ids_get( $group_id ) {

		$user_ids = [];

		// Get all User IDs in the "Groups" Group.
		$group          = new Groups_Group( $group_id );
		$group_user_ids = $group->user_ids;

		// Bail if there are none.
		if ( empty( $group_user_ids ) || ! is_array( $group_user_ids ) ) {
			return $user_ids;
		}

		return $group_user_ids;

	}

	/**
	 * Gets the User IDs for a given set of Contact IDs.
	 *
	 * @since 0.2.1
	 *
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 * @return array $data The array of User IDs keyed by Contact ID.
	 */
	public function group_user_ids_for_contact_ids_get( $contact_ids ) {

		$data = [];

		foreach ( $contact_ids as $contact_id ) {

			// Skip if there is no User ID.
			$user_id = $this->user_id_get_by_contact_id( $contact_id );
			if ( empty( $user_id ) ) {
				$data[ $contact_id ] = 0;
				continue;
			}

			$data[ $contact_id ] = $user_id;

		}

		return $data;

	}

	// -----------------------------------------------------------------------------------

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

	// -----------------------------------------------------------------------------------

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

	// -----------------------------------------------------------------------------------

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
				'name'        => isset( $civicrm_group->title ) ? $civicrm_group->title : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group->description ) ? $civicrm_group->description : '',
			];
		} else {
			$params = [
				'name'        => isset( $civicrm_group['title'] ) ? $civicrm_group['title'] : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
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
				'name'        => isset( $civicrm_group->title ) ? $civicrm_group->title : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
				'description' => isset( $civicrm_group->description ) ? $civicrm_group->description : '',
			];

			// Get source string.
			$source = isset( $civicrm_group->source ) ? $civicrm_group->source : '';

		} else {

			// Init params.
			$params = [
				'name'        => isset( $civicrm_group['title'] ) ? $civicrm_group['title'] : __( 'Untitled', 'wpcv-civicrm-groups-integration' ),
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
		$tmp         = explode( 'synced-group-', $source );
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

	// -----------------------------------------------------------------------------------

	/**
	 * Get a "Groups" Group's admin URL.
	 *
	 * @since 0.1.1
	 *
	 * @param int $group_id The numeric ID of the "Groups" Group.
	 * @return string $group_url The "Groups" Group's admin URL.
	 */
	public function group_get_url( $group_id ) {

		// Get Group admin URL.
		$group_url = admin_url( 'admin.php?page=groups-admin&group_id=' . $group_id . '&action=edit' );

		/**
		 * Filter the URL of the "Groups" Group's admin page.
		 *
		 * @since 0.1.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/page_settings/cap'} filter instead.
		 *
		 * @param string $group_url The existing URL.
		 * @param int    $group_id The numeric ID of the CiviCRM Group.
		 */
		$group_url = apply_filters_deprecated( 'civicrm_groups_sync_group_get_url_wp', [ $group_url, $group_id ], '1.0.0', 'wpcv_cgi/wp/group_url' );

		/**
		 * Filters the URL of the "Groups" Group's admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $group_url The existing URL.
		 * @param int    $group_id The numeric ID of the CiviCRM Group.
		 */
		return apply_filters( 'wpcv_cgi/wp/group_url', $group_url, $group_id );

	}

	// -----------------------------------------------------------------------------------

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

	// -----------------------------------------------------------------------------------

	/**
	 * Filter the Add Group form.
	 *
	 * @since 0.1
	 *
	 * @param string $content The existing content to be inserted after the default fields.
	 * @return string $content The modified content to be inserted after the default fields.
	 */
	public function form_add_filter( $content ) {

		// Start buffering.
		ob_start();

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/wordpress/groups/settings-groups-create.php';

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
	 * @param string $content The existing content to be inserted after the default fields.
	 * @param int    $group_id The numeric ID of the Group.
	 * @return string $content The modified content to be inserted after the default fields.
	 */
	public function form_edit_filter( $content, $group_id ) {

		// Get existing CiviCRM Group.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if there isn't one.
		if ( false === $civicrm_group ) {
			return $content;
		}

		// Get CiviCRM Group admin URL for template.
		$group_url = $this->plugin->civicrm->group_get_url( $civicrm_group['id'] );

		// Start buffering.
		ob_start();

		// Include template.
		include WPCV_CGI_PATH . 'assets/templates/wordpress/groups/settings-groups-edit.php';

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

	// -----------------------------------------------------------------------------------

	/**
	 * Checks if a WordPress User is a member of a "Groups" Group.
	 *
	 * @since 0.3.0
	 *
	 * @param int $user_id The ID of the WordPress User.
	 * @param int $group_id The ID of the "Groups" Group.
	 * @return bool $exists True if User is a Group member, false otherwise.
	 */
	public function group_member_exists( $user_id, $group_id ) {

		// Assume not a Group Member.
		$exists = false;

		// Override if they are a Group Member.
		if ( Groups_User_Group::read( $user_id, $group_id ) ) {
			$exists = true;
		}

		// --<
		return $exists;

	}

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
		if ( $this->group_member_exists( $user_id, $group_id ) ) {
			return true;
		}

		// Remove hook.
		remove_action( 'groups_created_user_group', [ $this, 'group_member_added' ], 10 );

		// Build args.
		$args = [
			'user_id'  => $user_id,
			'group_id' => $group_id,
		];

		// Add User to Group.
		$success = Groups_User_Group::create( $args );

		// Reinstate hook.
		add_action( 'groups_created_user_group', [ $this, 'group_member_added' ], 10, 2 );

		// Maybe log on failure?
		if ( ! $success ) {
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not add User to Group.', 'wpcv-civicrm-groups-integration' ),
				'user_id'   => $user_id,
				'group_id'  => $group_id,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
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
		if ( ! $this->group_member_exists( $user_id, $group_id ) ) {
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
			$e     = new Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'message'   => __( 'Could not delete User from Group.', 'wpcv-civicrm-groups-integration' ),
				'user_id'   => $user_id,
				'group_id'  => $group_id,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $success;

	}

	// -----------------------------------------------------------------------------------

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
		if ( empty( $civicrm_contact_id ) || false === $civicrm_contact_id ) {
			return;
		}

		// Get CiviCRM Group for this "Groups" Group ID.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_group ) || false === $civicrm_group ) {
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
		if ( empty( $civicrm_contact_id ) || false === $civicrm_contact_id ) {
			return;
		}

		// Get CiviCRM Group for this "Groups" Group ID.
		$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );

		// Bail if we don't get one.
		if ( empty( $civicrm_group ) || false === $civicrm_group ) {
			return;
		}

		// Remove User from CiviCRM Group.
		$success = $this->plugin->civicrm->group_contact_delete( $civicrm_group['id'], $civicrm_contact_id );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the User IDs to add to a given "Groups" Group for a given set of Contact IDs.
	 *
	 * @since 0.2.1
	 *
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 * @param int   $group_id The numeric ID of the "Groups" Group.
	 * @return array $result The array of User IDs.
	 */
	public function group_user_ids_to_add( $contact_ids, $group_id ) {

		$result = [
			'no-user-id'  => [],
			'has-user-id' => [],
		];

		foreach ( $contact_ids as $contact_id ) {

			// Skip if there is no User ID.
			$user_id = $this->user_id_get_by_contact_id( $contact_id );
			if ( empty( $user_id ) ) {
				$result['no-user-id'][] = $contact_id;
				continue;
			}

			// Skip if they are already a "Groups" Group Member.
			if ( $this->group_member_exists( $user_id, $group_id ) ) {
				continue;
			}

			$result['has-user-id'][ $contact_id ] = $user_id;

		}

		return $result;

	}

	/**
	 * Bulk adds a set of User IDs to a given "Groups" Group.
	 *
	 * @since 0.2.1
	 *
	 * @param array $user_ids The array of User IDs.
	 * @param int   $group_id The numeric ID of the "Groups" Group.
	 * @return array $result The array of User IDs where adding succeeded or failed.
	 */
	public function group_user_ids_add( $user_ids, $group_id ) {

		$result = [
			'added'  => [],
			'failed' => [],
		];

		// Bail if there are no Users to add to the "Groups" Group.
		if ( empty( $user_ids ) ) {
			return $result;
		}

		foreach ( $user_ids as $contact_id => $user_id ) {
			$success = $this->group_member_add( $user_id, $group_id );
			if ( true === $success ) {
				$result['added'][ $contact_id ] = $user_id;
			} else {
				$result['failed'][ $contact_id ] = $user_id;
			}
		}

		return $result;

	}

	// -----------------------------------------------------------------------------------

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

		// Search using CiviCRM's logic.
		$user_id = CRM_Core_BAO_UFMatch::getUFId( $contact_id );

		// Cast User ID as boolean if we didn't get one.
		if ( empty( $user_id ) ) {
			$user_id = false;
		}

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
		 * @param int      $contact_id The numeric ID of the CiviCRM Contact.
		 */
		$user_id = apply_filters_deprecated( 'civicrm_groups_sync_user_id_get_by_contact_id', [ $user_id, $contact_id ], '1.0.0', 'wpcv_cgi/wp/user_id' );

		/**
		 * Filters the result of the WordPress User lookup.
		 *
		 * You can use this filter to create a WordPress User if none is found.
		 * Return the new WordPress User ID and the Group linkage will be made.
		 *
		 * @since 1.0.0
		 *
		 * @param int|bool $user_id The numeric ID of the WordPress User, or false on failure.
		 * @param int      $contact_id The numeric ID of the CiviCRM Contact.
		 */
		$user_id = apply_filters( 'wpcv_cgi/wp/user_id', $user_id, $contact_id );

		// --<
		return $user_id;

	}

	/**
	 * Get a WordPress User object for a given CiviCRM Contact ID.
	 *
	 * @since 0.1
	 *
	 * @param integer $contact_id The numeric CiviCRM Contact ID.
	 * @return WP_User|bool $user The WordPress User object, or false on failure.
	 */
	public function user_get_by_contact_id( $contact_id ) {

		// Get WordPress User ID.
		$user_id = $this->user_id_get_by_contact_id( $contact_id );

		// Bail if we didn't get one.
		if ( empty( $user_id ) || false === $user_id ) {
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

	// -----------------------------------------------------------------------------------

	/**
	 * Syncs all "Groups" Groups to their corresponding CiviCRM Groups.
	 *
	 * @since 0.3.0
	 */
	public function sync_to_civicrm() {

		// Feedback at the beginning of the process.
		$this->plugin->log_message( '' );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );
		$this->plugin->log_message( __( 'Syncing all "Groups" Groups to their corresponding CiviCRM Groups...', 'wpcv-civicrm-groups-integration' ) );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );

		// Get all the Group Users in the Synced Groups.
		$group_users = $this->group_users_get();
		if ( ! empty( $group_users ) ) {

			// Save some queries by using a pseudo-cache.
			$correspondences = [
				'users'  => [],
				'groups' => [],
			];

			foreach ( $group_users as $group_user ) {

				// Get the CiviCRM Group ID for this "Groups" Group ID.
				$group_id = (int) $group_user['group_id'];

				// Try the pseudo-cache first.
				if ( isset( $correspondences['groups'][ $group_id ] ) ) {
					$civicrm_group_id = $correspondences['groups'][ $group_id ];
				} else {
					// Check the database.
					$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );
					if ( ! empty( $civicrm_group ) ) {
						$civicrm_group_id                       = (int) $civicrm_group['id'];
						$correspondences['groups'][ $group_id ] = $civicrm_group_id;
						$this->plugin->log_message( '' );
						$this->plugin->log_message(
							/* translators: 1: The name of the Group, 2: The ID of the Group. */
							sprintf( __( 'Adding Users from "Groups" Group %1$s (ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $civicrm_group['title'], (int) $group_id )
						);
						$this->plugin->log_message(
							/* translators: %d: The ID of the Group. */
							sprintf( __( 'Syncing with CiviCRM Group (ID: %d)', 'wpcv-civicrm-groups-integration' ), $civicrm_group_id )
						);
					} else {
						$correspondences['groups'][ $group_id ] = false;
					}
				}

				// Skip if there is no CiviCRM Group for this "Groups" Group ID.
				if ( empty( $civicrm_group_id ) ) {
					continue;
				}

				// Get the CiviCRM Contact ID for this User ID.
				$user_id = (int) $group_user['user_id'];

				// Try the pseudo-cache first.
				if ( isset( $correspondences['users'][ $user_id ] ) ) {
					$contact_id = $correspondences['users'][ $user_id ];
				} else {
					// Check the database.
					$contact_id = $this->plugin->civicrm->contact_id_get_by_user_id( $user_id );
					if ( ! empty( $contact_id ) ) {
						$correspondences['users'][ $user_id ] = (int) $contact_id;
					} else {
						$correspondences['users'][ $user_id ] = false;
					}
				}

				// Skip if there is no Contact for this User ID.
				if ( empty( $contact_id ) || false === $contact_id ) {
					$this->plugin->log_message(
						/* translators: %d: The ID of the User. */
						sprintf( __( 'No Contact ID found for User (ID: %d)', 'wpcv-civicrm-groups-integration' ), $user_id )
					);
					continue;
				}

				// Skip if there is an existing GroupContact entry.
				$exists = $this->plugin->civicrm->group_contact_get( $civicrm_group_id, $contact_id );
				if ( ! empty( $exists ) ) {
					continue;
				}

				// Create the CiviCRM Group Contact.
				$success = $this->plugin->civicrm->group_contact_create( $civicrm_group_id, $contact_id );
				if ( false !== $success ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Added Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				} else {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Failed to add Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				}

			}

		}

		// Get all the Group Contacts in the Synced Groups.
		$group_contacts = $this->plugin->civicrm->group_contacts_get();
		if ( ( $group_contacts instanceof CRM_Core_Exception ) ) {
			$group_contacts = [];
		}

		// Delete each Group Contact where the User no longer exists in the "Groups" Group.
		if ( ! empty( $group_contacts ) ) {
			$group_ids = [];
			foreach ( $group_contacts as $group_contact ) {

				$user_id  = (int) $group_contact['uf_match.uf_id'];
				$group_id = (int) str_replace( 'synced-group-', '', $group_contact['group.source'] );

				// Show feedback each time Group changes.
				if ( ! in_array( $group_id, $group_ids, true ) ) {
					$this->plugin->log_message( '' );
					$this->plugin->log_message(
						/* translators: 1: The name of the Group, 2: The ID of the Group. */
						sprintf( __( 'Deleting Contacts not in "Groups" Group %1$s (ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $group_contact['group.title'], $group_id )
					);
					$this->plugin->log_message(
						/* translators: %d: The ID of the Group. */
						sprintf( __( 'Syncing with CiviCRM Group (ID: %d)', 'wpcv-civicrm-groups-integration' ), (int) $group_contact['group_id'] )
					);
					$group_ids[] = $group_id;
				}

				// Skip if the Group User exists.
				if ( $this->group_member_exists( $user_id, $group_id ) ) {
					continue;
				}

				// Delete the Group Contact.
				$contact_id = (int) $group_contact['contact_id'];
				$success    = $this->plugin->civicrm->group_contact_delete( (int) $group_contact['group_id'], $contact_id );
				if ( false !== $success ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Removed Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				} else {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Failed to remove Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				}

			}
		}

	}

	/**
	 * Batch sync "Groups" Groups to CiviCRM Groups.
	 *
	 * @since 0.3.0
	 *
	 * @param string $identifier The batch identifier.
	 */
	public function batch_sync_to_civicrm( $identifier ) {

		// Get the current Batch.
		$batch        = new WPCV_CGI_Admin_Batch( $identifier );
		$batch_number = $batch->initialise();

		// Set batch count for schedules.
		if ( false !== strpos( $identifier, 'cvgrp_cron' ) ) {
			$batch_count = (int) $this->plugin->admin->setting_get( 'batch_count' );
			$batch->stepper->step_count_set( $batch_count );
		}

		// Call the Batches in order.
		switch ( $batch_number ) {
			case 0:
				$this->batch_sync_one( $batch );
				break;
			case 1:
				$this->batch_sync_two( $batch );
				break;
			case 2:
				$this->batch_sync_three( $batch );
				break;
		}

	}

	/**
	 * Batch sync "Groups" Group Users to CiviCRM Group Contacts.
	 *
	 * @since 0.3.0
	 *
	 * @param WPCV_CGI_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_one( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Users for this step.
		$groups_batch = $this->group_users_get( $limit, $offset );

		// Save some queries by using a pseudo-cache.
		$correspondences = [
			'users'  => [],
			'groups' => [],
		];

		// Ensure each Group User has a CiviCRM Group Contact.
		if ( ! empty( $groups_batch ) ) {
			foreach ( $groups_batch as $group_user ) {

				// Get the CiviCRM Group ID for this "Groups" Group ID.
				$group_id = (int) $group_user['group_id'];

				// Try the pseudo-cache first.
				if ( isset( $correspondences['groups'][ $group_id ] ) ) {
					$civicrm_group_id = $correspondences['groups'][ $group_id ];
				} else {
					// Check the database.
					$civicrm_group = $this->plugin->civicrm->group_get_by_wp_id( $group_id );
					if ( ! empty( $civicrm_group ) ) {
						$civicrm_group_id                       = (int) $civicrm_group['id'];
						$correspondences['groups'][ $group_id ] = $civicrm_group_id;
					} else {
						$correspondences['groups'][ $group_id ] = false;
					}
				}

				// Skip if there is no CiviCRM Group for this "Groups" Group ID.
				if ( empty( $civicrm_group_id ) ) {
					continue;
				}

				// Get the CiviCRM Contact ID for this User ID.
				$user_id = (int) $group_user['user_id'];

				// Try the pseudo-cache first.
				if ( isset( $correspondences['users'][ $user_id ] ) ) {
					$contact_id = $correspondences['users'][ $user_id ];
				} else {
					// Check the database.
					$contact_id = $this->plugin->civicrm->contact_id_get_by_user_id( $user_id );
					if ( ! empty( $contact_id ) ) {
						$correspondences['users'][ $user_id ] = (int) $contact_id;
					} else {
						$correspondences['users'][ $user_id ] = false;
					}
				}

				// Skip if there is no Contact for this User ID.
				if ( empty( $contact_id ) || false === $contact_id ) {
					continue;
				}

				// Skip if there is an existing GroupContact entry.
				$exists = $this->plugin->civicrm->group_contact_get( $civicrm_group_id, $contact_id );
				if ( ! empty( $exists ) ) {
					continue;
				}

				// Create the CiviCRM Group Contact.
				$success = $this->plugin->civicrm->group_contact_create( $civicrm_group_id, $contact_id );
				if ( false !== $success ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Added User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				} else {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Failed to add User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				}

			}
		}

		// Get the next batch of Group Users.
		$batch->stepper->next();
		$offset       = $batch->stepper->initialise();
		$groups_batch = $this->group_users_get( $limit, $offset );

		// Move batching onwards.
		if ( empty( $groups_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch delete Group Contacts where the User no longer exists in the "Groups" Group.
	 *
	 * @since 0.3.0
	 *
	 * @param WPCV_CGI_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_two( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Contacts for this step.
		$civicrm_batch = $this->plugin->civicrm->group_contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}

		// Delete each Group Contact where the User no longer exists in the "Groups" Group.
		if ( ! empty( $civicrm_batch ) ) {
			foreach ( $civicrm_batch as $group_contact ) {

				// Skip if the Group User exists.
				$user_id  = (int) $group_contact['uf_match.uf_id'];
				$group_id = (int) str_replace( 'synced-group-', '', $group_contact['group.source'] );
				if ( $this->group_member_exists( $user_id, $group_id ) ) {
					continue;
				}

				// Delete the Group Contact.
				$contact_id = (int) $group_contact['contact_id'];
				$success    = $this->plugin->civicrm->group_contact_delete( (int) $group_contact['group_id'], $contact_id );
				if ( false !== $success ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Removed Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				} else {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Failed to remove Contact (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				}

			}
		}

		// Get the next batch of Group Contacts.
		$batch->stepper->next();
		$offset        = $batch->stepper->initialise();
		$civicrm_batch = $this->plugin->civicrm->group_contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}

		// Move batching onwards.
		if ( empty( $civicrm_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch done.
	 *
	 * @since 0.3.0
	 *
	 * @param WPCV_CGI_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_three( $batch ) {

		// We're finished.
		$batch->delete();
		unset( $batch );

	}

}
