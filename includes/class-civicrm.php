<?php
/**
 * CiviCRM class.
 *
 * Handles CiviCRM-related functionality.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * CiviCRM Class.
 *
 * A class that encapsulates CiviCRM functionality.
 *
 * @since 0.1
 */
class WPCV_CGI_CiviCRM {

	/**
	 * Plugin object.
	 *
	 * @since 0.1
	 * @access public
	 * @var object $plugin The plugin object.
	 */
	public $plugin;

	/**
	 * CiviCRM Permissions object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @var object $permissions The CiviCRM Permissions object.
	 */
	public $permissions;

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

		// Bootstrap this class.
		$this->include_files();
		$this->setup_objects();
		$this->register_hooks();

		/**
		 * Broadcast that this class is now loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wpcv_cgi/civicrm/loaded' );

	}

	/**
	 * Includes files.
	 *
	 * @since 1.0.0
	 */
	public function include_files() {

		// Load our class files.
		require WPCV_CGI_PATH . 'includes/class-civicrm-permissions.php';

	}

	/**
	 * Sets up objects for this class.
	 *
	 * @since 1.0.0
	 */
	public function setup_objects() {

		// Instantiate objects.
		$this->permissions = new WPCV_CGI_CiviCRM_Permissions( $this );

	}

	/**
	 * Register hooks.
	 *
	 * @since 0.1
	 */
	public function register_hooks() {

		// Register template directory for form amends.
		add_action( 'civicrm_config', [ $this, 'register_form_directory' ], 10 );

		// Modify CiviCRM Group Create form.
		add_action( 'civicrm_buildForm', [ $this, 'form_group_create_build' ], 10, 2 );

		// Intercept CiviCRM Group Create form submission process.
		add_action( 'civicrm_postProcess', [ $this, 'form_group_create_process' ], 10, 2 );

		// Intercept before and after CiviCRM creating a Group.
		add_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_created_post' ], 10, 4 );

		// Intercept before and after CiviCRM updated a Group.
		add_action( 'civicrm_pre', [ $this, 'group_updated_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_updated' ], 10, 4 );

		// Intercept CiviCRM's add Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Intercept CiviCRM's delete Contacts from Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Intercept CiviCRM's rejoin Contacts to Group.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_rejoined' ], 10, 4 );

	}

	// -------------------------------------------------------------------------

	/**
	 * Register directory that CiviCRM searches in for our form template file.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_form_directory( &$config ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return;
		}

		// Get template instance.
		$template = CRM_Core_Smarty::singleton();

		// Define our custom path.
		$custom_path = WPCV_CGI_PATH . 'assets/templates/civicrm';

		// Add our custom template directory.
		$template->addTemplateDir( $custom_path );

		// Register template directory.
		$template_include_path = $custom_path . PATH_SEPARATOR . get_include_path();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_include_path
		set_include_path( $template_include_path );

	}

	/**
	 * Enable a Groups Group to be created when creating a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_group_create_build( $formName, &$form ) {

		// Is this the Group Edit form?
		if ( $formName != 'CRM_Group_Form_Edit' ) {
			return;
		}

		// Get CiviCRM Group.
		$civicrm_group = $form->getVar( '_group' );

		// Assign template depending on whether we have a Group.
		if ( isset( $civicrm_group ) && ! empty( $civicrm_group ) ) {

			// It's the Edit Group form.

			// Get the Groups Group ID.
			$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group->id );

			// Bail if there isn't one.
			if ( $wp_group_id === false ) {
				return;
			}

			// Get the URL.
			$group_url = $this->plugin->wordpress->group_get_url( $wp_group_id );

			// Build description.
			$description = sprintf(
				/* translators: 1: The opening anchor tag, 2: The closing anchor tag. */
				__( 'This group is a Synced Group and already has an associated group in WordPress: %1$sGroup Settings%2$s', 'wpcv-civicrm-groups-integration' ),
				'<a href="' . $group_url . '">',
				'</a>'
			);

			// Add static content.
			$form->assign( 'wpcv_cgi_edit_label', __( 'Existing Synced Group', 'wpcv-civicrm-groups-integration' ) );
			$form->assign( 'wpcv_cgi_edit_url', $group_url );
			$form->assign( 'wpcv_cgi_edit_description', $description );

			// Insert template block into the page.
			CRM_Core_Region::instance( 'page-body' )->add( [
				'template' => 'wpcv-cgi-edit.tpl',
			] );

		} else {

			// It's the New Group form.

			// Add the field element to the form.
			$form->add( 'checkbox', 'wpcv_cgi_create', __( 'Create Synced Group', 'wpcv-civicrm-groups-integration' ) );

			// Add static content.
			$form->assign( 'wpcv_cgi_create_label', __( 'Create Synced Group', 'wpcv-civicrm-groups-integration' ) );
			$form->assign( 'wpcv_cgi_create_description', __( 'If you are creating a Synced Group, you only need to fill out the "Title" field (and optionally the "Description" field) above. The Group Type will be set to "Access Control" automatically.', 'wpcv-civicrm-groups-integration' ) );

			// Insert template block into the page.
			CRM_Core_Region::instance( 'page-body' )->add( [
				'template' => 'wpcv-cgi-create.tpl',
			] );

		}

	}

	/**
	 * Callback for the Add Group form's postProcess hook.
	 *
	 * Curious (to me at least) that "civicrm_pre" and "civicrm_post" fire
	 * before this hook. Moreover, our checkbox value (when present) is passed
	 * to "civicrm_pre" as part of the "objectRef" array. Which means I'm not
	 * quite sure what to do here - or what the postProcess hook is useful for!
	 *
	 * @since 0.1
	 *
	 * @param string $formName The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_group_create_process( $formName, &$form ) {

		// Bail if not Edit Group form.
		if ( ! ( $form instanceof CRM_Group_Form_Edit ) ) {
			return;
		}

		// Inspect submitted values.
		$values = $form->getVar( '_submitValues' );

		// Was our checkbox ticked?
		if ( ! isset( $values['wpcv_cgi_create'] ) ) {
			return;
		}
		if ( $values['wpcv_cgi_create'] != '1' ) {
			return;
		}

		// What now?

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept when a CiviCRM Group is about to be created.
	 *
	 * We update the params by which the CiviCRM Group is created if our form
	 * element has been checked.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_created_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

		// Was our checkbox ticked?
		if ( ! isset( $civicrm_group['wpcv_cgi_create'] ) ) {
			return;
		}
		if ( 1 !== (int) $civicrm_group['wpcv_cgi_create'] ) {
			return;
		}

		// Always make the Group of type "Access Control".
		if ( isset( $civicrm_group['group_type'] ) && is_array( $civicrm_group['group_type'] ) ) {
			$civicrm_group['group_type'][1] = 1;
		} else {
			$civicrm_group['group_type'] = [ 1 => 1 ];
		}

		// Use the "source" field to denote a "Synced Group".
		$civicrm_group['source'] = 'synced-group';

		// Set flag to trigger sync after database insert is complete.
		$this->sync_please = true;

	}

	/**
	 * Intercept after a CiviCRM Group has been created.
	 *
	 * We create the "Groups" Group and update the "source" field for the
	 * CiviCRM Group with the ID of the "Groups" Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_created_post( $op, $object_name, $civicrm_group_id, $civicrm_group ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

		// Make sure we have a Group.
		if ( ! ( $civicrm_group instanceof CRM_Contact_BAO_Group ) ) {
			return;
		}

		// Check for our flag.
		if ( ! isset( $this->sync_please ) ) {
			return;
		}
		if ( $this->sync_please !== true ) {
			return;
		}

		// Bail if the "source" field is not set.
		if ( ! isset( $civicrm_group->source ) ) {
			return;
		}
		if ( 'synced-group' !== $civicrm_group->source ) {
			return;
		}

		// Create a "Groups" Group from CiviCRM Group data.
		$wp_group_id = $this->plugin->wordpress->group_create_from_civicrm_group( $civicrm_group );

		// Remove hooks.
		remove_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'group_created_post' ], 10 );

		// Init params.
		$params = [
			'version' => 3,
			'id' => $civicrm_group->id,
			'source' => 'synced-group-' . $wp_group_id,
		];

		// Update the "source" field to include the ID of the WordPress Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_created_post' ], 10, 4 );

		// Log error on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'op' => $op,
				'object_name' => $object_name,
				'objectId' => $civicrm_group_id,
				'objectRef' => $civicrm_group,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
		}

	}

	/**
	 * Intercept when a CiviCRM Group is about to be updated.
	 *
	 * We need to make sure that the CiviCRM Group remains of type "Access Control".
	 *
	 * @since 0.1.2
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_updated_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

		// Get the full CiviCRM Group.
		$civicrm_group_data = $this->group_get_by_id( $civicrm_group_id );
		if ( empty( $civicrm_group_data ) ) {
			return;
		}

		// Bail if the "source" field is not set.
		if ( empty( $civicrm_group_data['source'] ) ) {
			return;
		}

		// Bail if the "source" field is not for a synced Group.
		if ( false === strpos( $civicrm_group_data['source'], 'synced-group' ) ) {
			return;
		}

		// Always make the Group of type "Access Control".
		if ( isset( $civicrm_group['group_type'] ) && is_array( $civicrm_group['group_type'] ) ) {
			$civicrm_group['group_type'][1] = 1;
		} else {
			$civicrm_group['group_type'] = [ 1 => 1 ];
		}

	}

	/**
	 * Intercept when a CiviCRM Group has been updated.
	 *
	 * There seems to be a bug in CiviCRM such that "source" is not included in
	 * the $civicrm_group data that is passed to this callback. I assume it's
	 * because the "source" field can only be updated via code or the API, so
	 * it's excluded from the database update query.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_updated( $op, $object_name, $civicrm_group_id, $civicrm_group ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

		// Make sure we have a Group.
		if ( ! ( $civicrm_group instanceof CRM_Contact_BAO_Group ) ) {
			return;
		}

		// Get the full CiviCRM Group.
		$civicrm_group_data = $this->group_get_by_id( $civicrm_group_id );
		if ( empty( $civicrm_group_data ) ) {
			return;
		}

		// Bail if the "source" field is not set.
		if ( empty( $civicrm_group_data['source'] ) ) {
			return;
		}

		// Bail if the "source" field is not for a synced Group.
		if ( false === strpos( $civicrm_group_data['source'], 'synced-group' ) ) {
			return;
		}

		// Update the "Groups" Group from CiviCRM Group data.
		$wp_group_id = $this->plugin->wordpress->group_update_from_civicrm_group( $civicrm_group_data );

	}

	/**
	 * Intercept a CiviCRM Group prior to it being deleted.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_deleted_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'Group' ) {
			return;
		}

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a CiviCRM Group's admin URL.
	 *
	 * @since 0.1.1
	 *
	 * @param int $group_id The numeric ID of the CiviCRM Group.
	 * @return str $group_url The CiviCRM Group's admin URL.
	 */
	public function group_get_url( $group_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return '';
		}

		// Get Group URL.
		$group_url = CRM_Utils_System::url( 'civicrm/group', 'reset=1&action=update&id=' . $group_id );

		/**
		 * Filters the URL of the CiviCRM Group's admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param str $group_url The existing URL.
		 * @param int $group_id The numeric ID of the CiviCRM Group.
		 */
		$group_url = apply_filters( 'wpcv_cgi/civicrm/group_url', $group_url, $group_id );

		/**
		 * Filter the URL of the CiviCRM Group's admin page.
		 *
		 * @since 0.1.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/civicrm/group_url'} filter instead.
		 *
		 * @param str $group_url The existing URL.
		 * @param int $group_id The numeric ID of the CiviCRM Group.
		 */
		return apply_filters_deprecated( 'civicrm_groups_sync_group_get_url_civi', [ $group_url, $group_id ], '1.0.0', 'wpcv_cgi/civicrm/group_url' );

	}

	// -------------------------------------------------------------------------

	/**
	 * Gets a CiviCRM Group by its ID.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the Group.
	 * @return array|bool $group The array of CiviCRM Group data, or false on failure.
	 */
	public function group_get_by_id( $group_id ) {

		// Init return.
		$group = false;

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $group;
		}

		// Init params.
		$params = [
			'version' => 3,
			'id' => $group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $group;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// The result set should contain only one item.
		$group = array_pop( $result['values'] );

		// Return Group.
		return $group;

	}

	/**
	 * Get a CiviCRM Group using a "Groups" Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $wp_group_id The numeric ID of the "Groups" Group.
	 * @return array|bool $group The array of CiviCRM Group data, or false on failure.
	 */
	public function group_get_by_wp_id( $wp_group_id ) {

		// Init return.
		$group = false;

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $group;
		}

		// Init params.
		$params = [
			'version' => 3,
			'source' => 'synced-group-' . $wp_group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $group;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group;
		}

		// The result set should contain only one item.
		$group = array_pop( $result['values'] );

		// Return Group.
		return $group;

	}

	/**
	 * Get the "Groups" Group ID using a CiviCRM Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The numeric ID of the CiviCRM Group.
	 * @return int|bool $wp_group_id The ID of the "Groups" Group, or false on failure.
	 */
	public function group_get_wp_id_by_civicrm_id( $group_id ) {

		// Init return.
		$wp_group_id = false;

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $wp_group_id;
		}

		// Init params.
		$params = [
			'version' => 3,
			'id' => $group_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Group', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $wp_group_id;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $wp_group_id;
		}

		// The result set should contain only one item.
		$civicrm_group = array_pop( $result['values'] );

		// Bail if there's no "source" field.
		if ( empty( $civicrm_group['source'] ) ) {
			return $wp_group_id;
		}

		// Get ID from source string.
		$tmp = explode( 'synced-group-', $civicrm_group['source'] );
		$wp_group_id = isset( $tmp[1] ) ? (int) trim( $tmp[1] ) : false;

		// Return the ID of the "Groups" Group.
		return $wp_group_id;

	}

	/**
	 * Create a CiviCRM Group using a "Groups" Group object.
	 *
	 * @since 0.1
	 *
	 * @param object $wp_group The "Groups" Group object.
	 * @return int|bool $group_id The ID of the Group, or false on failure.
	 */
	public function group_create_from_wp_group( $wp_group ) {

		// Sanity check.
		if ( ! is_object( $wp_group ) ) {
			return false;
		}

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Remove hooks.
		remove_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10 );
		remove_action( 'civicrm_post', [ $this, 'group_created_post' ], 10 );

		// Init params.
		$params = [
			'version' => 3,
			'name' => wp_unslash( $wp_group->name ),
			'title' => wp_unslash( $wp_group->name ),
			'description' => isset( $wp_group->description ) ? wp_unslash( $wp_group->description ) : '',
			'group_type' => [ 1 => 1 ],
			'source' => 'synced-group-' . $wp_group->group_id,
		];

		// Create the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_created_post' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
			return false;
		}

		// Return new Group ID.
		return absint( $result['id'] );

	}

	/**
	 * Update a CiviCRM Group using a "Groups" Group object.
	 *
	 * @since 0.1
	 *
	 * @param object $wp_group The "Groups" Group object.
	 * @return int|bool $group_id The ID of the Group, or false on failure.
	 */
	public function group_update_from_wp_group( $wp_group ) {

		// Sanity check.
		if ( ! is_object( $wp_group ) ) {
			return false;
		}

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Get the synced CiviCRM Group.
		$civicrm_group = $this->group_get_by_wp_id( $wp_group->group_id );

		// Sanity check.
		if ( $civicrm_group === false || empty( $civicrm_group['id'] ) ) {
			return false;
		}

		// Remove hook.
		remove_action( 'civicrm_post', [ $this, 'group_updated' ], 10 );

		// Init params.
		$params = [
			'version' => 3,
			'id' => $civicrm_group['id'],
			'name' => wp_unslash( $wp_group->name ),
			'title' => wp_unslash( $wp_group->name ),
			'description' => isset( $wp_group->description ) ? wp_unslash( $wp_group->description ) : '',
		];

		// Update the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hook.
		add_action( 'civicrm_post', [ $this, 'group_updated' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
			return false;
		}

		// --<
		return absint( $civicrm_group['id'] );

	}

	/**
	 * Delete a CiviCRM Group using a "Groups" Group ID.
	 *
	 * @since 0.1
	 *
	 * @param int $wp_group_id The numeric ID of the "Groups" Group.
	 * @return int|bool $group_id The ID of the Group, or false on failure.
	 */
	public function group_delete_by_wp_id( $wp_group_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Get the synced CiviCRM Group.
		$civicrm_group = $this->group_get_by_wp_id( $wp_group_id );

		// Sanity check.
		if ( $civicrm_group === false || empty( $civicrm_group['id'] ) ) {
			return false;
		}

		// Init params.
		$params = [
			'version' => 3,
			'id' => $civicrm_group['id'],
		];

		// Delete the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'delete', $params );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'wp_group_id' => $wp_group_id,
				'civicrm_group' => $civicrm_group,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
			return false;
		}

		// --<
		return absint( $civicrm_group['id'] );

	}

	// -------------------------------------------------------------------------

	/**
	 * Add a CiviCRM Contact to a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $result The array of GroupContact data, or false on failure.
	 */
	public function group_contact_create( $civicrm_group_id, $civicrm_contact_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Remove hook.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10 );

		// Init params.
		$params = [
			'version' => 3,
			'group_id' => $civicrm_group_id,
			'contact_id' => $civicrm_contact_id,
			'status' => 'Added',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hook.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
			return false;
		}

		// --<
		return $result;

	}

	/**
	 * Delete a CiviCRM Contact from a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $civicrm_contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $result The array of GroupContact data, or false on failure.
	 */
	public function group_contact_delete( $civicrm_group_id, $civicrm_contact_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Remove hook.
		remove_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10 );

		// Init params.
		$params = [
			'version' => 3,
			'group_id' => $civicrm_group_id,
			'contact_id' => $civicrm_contact_id,
			'status' => 'Removed',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e = new \Exception();
			$trace = $e->getTraceAsString();
			$this->plugin->log_error( [
				'method' => __METHOD__,
				'params' => $params,
				'result' => $result,
				'backtrace' => $trace,
			] );
			return false;
		}

		// --<
		return $result;

	}

	// -------------------------------------------------------------------------

	/**
	 * Intercept when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids The array of CiviCRM Contact IDs.
	 */
	public function group_contacts_added( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'create' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( $wp_group_id === false ) {
			return;
		}

		// Loop through added Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Add User to "Groups" Group.
				if ( $user_id !== false ) {
					$this->plugin->wordpress->group_member_add( $user_id, $wp_group_id );
				}

			}
		}

	}

	/**
	 * Intercept when a CiviCRM Contact is deleted (or removed) from a Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_deleted( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'delete' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( $wp_group_id === false ) {
			return;
		}

		// Loop through deleted Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Delete User from "Groups" Group.
				if ( $user_id !== false ) {
					$this->plugin->wordpress->group_member_delete( $user_id, $wp_group_id );
				}

			}
		}

	}

	/**
	 * Intercept when a CiviCRM Contact is re-added to a Group.
	 *
	 * The issue here is that CiviCRM fires 'civicrm_pre' with $op = 'delete' regardless
	 * of whether the Contact is being removed or deleted. If a Contact is later re-added
	 * to the Group, then $op != 'create', so we need to intercept $op = 'edit'.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_rejoined( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( $op != 'edit' ) {
			return;
		}

		// Target our object type.
		if ( $object_name != 'GroupContact' ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( $wp_group_id === false ) {
			return;
		}

		// Loop through added Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Add User to "Groups" Group.
				if ( $user_id !== false ) {
					$this->plugin->wordpress->group_member_add( $user_id, $wp_group_id );
				}

			}
		}

	}

	// -------------------------------------------------------------------------

	/**
	 * Get a CiviCRM Contact ID for a given WordPress User ID.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric WordPress User ID.
	 * @return int|bool $contact_id The CiviCRM Contact ID, or false on failure.
	 */
	public function contact_id_get_by_user_id( $user_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return false;
		}

		// Make sure CiviCRM file is included.
		require_once 'CRM/Core/BAO/UFMatch.php';

		// Search using CiviCRM's logic.
		$contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );

		// Cast Contact ID as boolean if we didn't get one.
		if ( empty( $contact_id ) ) {
			$contact_id = false;
		}

		/**
		 * Filters the result of the CiviCRM Contact lookup.
		 *
		 * You can use this filter to create a CiviCRM Contact if none is found.
		 * Return the new CiviCRM Contact ID and the Group linkage will be made.
		 *
		 * @since 1.0.0
		 *
		 * @param int|bool $contact_id The numeric ID of the CiviCRM Contact, or false on failure.
		 * @param int $user_id The numeric ID of the WordPress User.
		 */
		$contact_id = apply_filters( 'wpcv_cgi/civicrm/contact_id', $contact_id, $user_id );

		/**
		 * Filter the result of the CiviCRM Contact lookup.
		 *
		 * @since 0.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/civicrm/contact_id'} filter instead.
		 *
		 * @param int|bool $contact_id The numeric ID of the CiviCRM Contact, or false on failure.
		 * @param int $user_id The numeric ID of the WordPress User.
		 */
		$contact_id = apply_filters_deprecated( 'civicrm_groups_sync_contact_id_get_by_user_id', [ $contact_id, $user_id ], '1.0.0', 'wpcv_cgi/civicrm/contact_id' );

		// --<
		return $contact_id;

	}

	/**
	 * Get a CiviCRM Contact for a given WordPress User ID.
	 *
	 * @since 0.1
	 *
	 * @param int $user_id The numeric WordPress User ID.
	 * @return array|bool $contact The CiviCRM Contact data, or false on failure.
	 */
	public function contact_get_by_user_id( $user_id ) {

		// Init return.
		$contact = false;

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $contact;
		}

		// Get the Contact ID.
		$contact_id = $this->contact_id_get_by_user_id( $user_id );
		if ( empty( $contact_id ) ) {
			return $contact;
		}

		// Init params.
		$params = [
			'version' => 3,
			'id' => $contact_id,
		];

		// Call the CiviCRM API.
		$result = civicrm_api( 'Contact', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $contact;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $contact;
		}

		// The result set should contain only one item.
		$contact = array_pop( $result['values'] );

		// --<
		return $contact;

	}

}
