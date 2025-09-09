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
	 * @var WPCV_CGI
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

		// Check for sync when CiviCRM creates a User.
		add_action( 'civicrm_post_create_user', [ $this, 'user_created_post' ], 20, 3 );

		// Legacy check for sync when CiviCRM creates a User.
		add_action( 'civicrm_pre_create_user', [ $this, 'user_create_pre' ], 20, 3 );

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Register directory that CiviCRM searches in for our form template file.
	 *
	 * @since 0.1
	 *
	 * @param object $config The CiviCRM config object.
	 */
	public function register_form_directory( &$config ) {

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
	 * @param string $form_name The CiviCRM form name.
	 * @param object $form The CiviCRM form object.
	 */
	public function form_group_create_build( $form_name, &$form ) {

		// Is this the Group Edit form?
		if ( 'CRM_Group_Form_Edit' !== $form_name ) {
			return;
		}

		// Get CiviCRM Group.
		$civicrm_group = $form->getVar( '_group' );

		// Assign template depending on whether we have a Group.
		if ( ! empty( $civicrm_group ) ) {

			// It's the Edit Group form.

			// Get the Groups Group ID.
			$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group->id );

			// Bail if there isn't one.
			if ( false === $wp_group_id ) {
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
			$form->assign( 'wpcv_cgi_edit_description', $description );

			// Insert template block into the page.
			CRM_Core_Region::instance( 'page-body' )->add( [ 'template' => 'groups/wpcv-cgi-edit.tpl' ] );

		} else {

			// It's the New Group form.

			// Add the field element to the form.
			$form->add( 'checkbox', 'wpcv_cgi_create', __( 'Create Synced Group', 'wpcv-civicrm-groups-integration' ) );

			// Add static content.
			$form->assign( 'wpcv_cgi_create_description', __( 'If you are creating a Synced Group, you only need to fill out the "Title" field (and optionally the "Description" field) above. The Group Type will be set to "Access Control" automatically.', 'wpcv-civicrm-groups-integration' ) );

			// Insert template block into the page.
			CRM_Core_Region::instance( 'page-body' )->add( [ 'template' => 'groups/wpcv-cgi-create.tpl' ] );

		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets the synced CiviCRM Groups.
	 *
	 * @since 0.2.1
	 *
	 * @return object|CRM_Core_Exception $result The array of synced CiviCRM Groups, or Exception on failure.
	 * @throws CRM_Core_Exception The Exception object.
	 */
	public function groups_synced_get() {

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			try {
				throw new CRM_Core_Exception( __( 'Could not initialize CiviCRM', 'wpcv-civicrm-groups-integration' ) );
			} catch ( CRM_Core_Exception $e ) {
				return $e;
			}
		}

		try {

			// Get the list of Groups that should be synced.
			$result = \Civi\Api4\Group::get( false )
				->addWhere( 'source', 'LIKE', '%synced-group%' )
				->execute()
				->indexBy( 'id' );

		} catch ( CRM_Core_Exception $e ) {
			return $e;
		}

		// --<
		return $result;

	}

	/**
	 * Gets the synced CiviCRM Group IDs for a given Contact ID.
	 *
	 * @since 0.1.1
	 *
	 * @param integer $contact_id The ID of the CiviCRM Contact.
	 * @return array|CRM_Core_Exception $result The array of synced CiviCRM Group IDs, or Exception on failure.
	 * @throws CRM_Core_Exception The Exception object.
	 */
	public function group_ids_get_for_contact( $contact_id ) {

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			try {
				throw new CRM_Core_Exception( __( 'Could not initialize CiviCRM', 'wpcv-civicrm-groups-integration' ) );
			} catch ( CRM_Core_Exception $e ) {
				return $e;
			}
		}

		// Init return.
		$group_ids = [];

		// Get all synced CiviCRM Groups.
		$civicrm_groups = $this->plugin->civicrm->groups_synced_get();
		if ( $civicrm_groups instanceof CRM_Core_Exception ) {
			return $group_ids;
		}

		// We just need the IDs.
		$civicrm_group_ids = array_keys( $civicrm_groups->getArrayCopy() );

		try {

			// Get the list of synced Group IDs.
			$result = \Civi\Api4\GroupContact::get( false )
				->addSelect( 'group_id' )
				->addWhere( 'contact_id', '=', $contact_id )
				->addWhere( 'group_id', 'IN', $civicrm_group_ids )
				->addOrderBy( 'group_id', 'ASC' )
				->execute()
				->indexBy( 'group_id' );

		} catch ( CRM_Core_Exception $e ) {
			return $e;
		}

		// Bail if there are none.
		if ( $result->count() === 0 ) {
			return $group_ids;
		}

		// We only need the keys of the ArrayObject.
		$group_ids = array_keys( $result->getArrayCopy() );

		// --<
		return $group_ids;

	}

	/**
	 * Gets Group Contacts with a given limit and offset.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $limit The numeric limit for the query.
	 * @param integer $offset The numeric offset for the query.
	 * @return array|CRM_Core_Exception $group_contacts The array of CiviCRM Group Contacts, or Exception on failure.
	 * @throws CRM_Core_Exception The Exception object.
	 */
	public function group_contacts_get( $limit = 0, $offset = 0 ) {

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			try {
				throw new CRM_Core_Exception( __( 'Could not initialize CiviCRM', 'wpcv-civicrm-groups-integration' ) );
			} catch ( CRM_Core_Exception $e ) {
				return $e;
			}
		}

		$group_contacts = [];

		try {

			// Get the list of GroupContacts that should be synced.
			$result = \Civi\Api4\GroupContact::get( false )
				->addSelect( '*', 'group.title', 'group.source', 'uf_match.uf_id' )
				->addJoin( 'Group AS group', 'LEFT', [ 'group_id', '=', 'group.id' ] )
				->addJoin( 'UFMatch AS uf_match', 'LEFT', [ 'contact_id', '=', 'uf_match.contact_id' ] )
				->addWhere( 'status:name', '=', 'Added' )
				->addWhere( 'group.source', 'LIKE', '%synced-group%' )
				->addWhere( 'uf_match.uf_id', 'IS NOT EMPTY' )
				->addOrderBy( 'group_id', 'ASC' )
				->setLimit( $limit )
				->setOffset( $offset )
				->execute();

		} catch ( CRM_Core_Exception $e ) {
			return $e;
		}

		// Bail if there are none.
		if ( $result->count() === 0 ) {
			return $group_contacts;
		}

		// Convert the ArrayObject to a simple array.
		$group_contacts = array_values( $result->getArrayCopy() );

		// --<
		return $group_contacts;

	}

	/**
	 * Gets the Contact IDs in a given CiviCRM Group.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $group_id The numeric ID of the CiviCRM Group.
	 * @return array|CRM_Core_Exception $contact_ids The array of CiviCRM Contact IDs, or Exception on failure.
	 * @throws CRM_Core_Exception The Exception object.
	 */
	public function group_contact_ids_get( $group_id ) {

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			try {
				throw new CRM_Core_Exception( __( 'Could not initialize CiviCRM', 'wpcv-civicrm-groups-integration' ) );
			} catch ( CRM_Core_Exception $e ) {
				return $e;
			}
		}

		$contact_ids = [];

		try {

			// Get all Contact IDs in the Group.
			$result = \Civi\Api4\GroupContact::get( false )
				->addWhere( 'group_id', '=', $group_id )
				->addWhere( 'status:name', '=', 'Added' )
				->execute()
				->indexBy( 'contact_id' );

		} catch ( CRM_Core_Exception $e ) {
			return $e;
		}

		// Bail if there are none.
		if ( $result->count() === 0 ) {
			return $contact_ids;
		}

		// The ArrayObject is keyed by Contact ID.
		$contact_ids = array_keys( $result->getArrayCopy() );

		// --<
		return $contact_ids;

	}

	/**
	 * Gets the Contact IDs for a given set of User IDs.
	 *
	 * @since 0.2.1
	 *
	 * @param array $user_ids The array of WordPress User IDs.
	 * @return array $data The array of Contact IDs keyed by User ID.
	 */
	public function group_contact_ids_for_user_ids_get( $user_ids ) {

		$data = [];

		foreach ( $user_ids as $user_id ) {

			// Skip if there is no Contact ID.
			$contact_id = $this->contact_id_get_by_user_id( $user_id );
			if ( empty( $contact_id ) ) {
				$data[ $user_id ] = 0;
				continue;
			}

			$data[ $user_id ] = $contact_id;

		}

		return $data;

	}

	// -----------------------------------------------------------------------------------

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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_created_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( 'create' !== $op ) {
			return;
		}

		// Bail if this isn't the type of object we're after.
		if ( 'Group' !== $object_name ) {
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_created_post( $op, $object_name, $civicrm_group_id, $civicrm_group ) {

		// Target our operation.
		if ( 'create' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Group' !== $object_name ) {
			return;
		}

		// Make sure we have a Group.
		if ( ! ( $civicrm_group instanceof CRM_Contact_BAO_Group ) ) {
			return;
		}

		// Bail if the "source" field is not set.
		if ( empty( $civicrm_group->source ) || 'synced-group' !== $civicrm_group->source ) {
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
			'id'      => $civicrm_group->id,
			'source'  => 'synced-group-' . $wp_group_id,
		];

		// Update the "source" field to include the ID of the WordPress Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_created_post' ], 10, 4 );

		// Log error on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'      => __METHOD__,
				'op'          => $op,
				'object_name' => $object_name,
				'objectId'    => $civicrm_group_id,
				'objectRef'   => $civicrm_group,
				'params'      => $params,
				'result'      => $result,
				'backtrace'   => $trace,
			];
			$this->plugin->log_error( $log );
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_updated_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( 'edit' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Group' !== $object_name ) {
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_updated( $op, $object_name, $civicrm_group_id, $civicrm_group ) {

		// Target our operation.
		if ( 'edit' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Group' !== $object_name ) {
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $civicrm_group The array of CiviCRM Group data.
	 */
	public function group_deleted_pre( $op, $object_name, $civicrm_group_id, &$civicrm_group ) {

		// Target our operation.
		if ( 'delete' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'Group' !== $object_name ) {
			return;
		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Get a CiviCRM Group's admin URL.
	 *
	 * @since 0.1.1
	 *
	 * @param int $group_id The numeric ID of the CiviCRM Group.
	 * @return string $group_url The CiviCRM Group's admin URL.
	 */
	public function group_get_url( $group_id ) {

		// Bail if no CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return '';
		}

		// Get Group URL.
		$group_url = CRM_Utils_System::url( 'civicrm/group', 'reset=1&action=update&id=' . $group_id );

		/**
		 * Filter the URL of the CiviCRM Group's admin page.
		 *
		 * @since 0.1.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/civicrm/group_url'} filter instead.
		 *
		 * @param string $group_url The existing URL.
		 * @param int    $group_id The numeric ID of the CiviCRM Group.
		 */
		$group_url = apply_filters_deprecated( 'civicrm_groups_sync_group_get_url_civi', [ $group_url, $group_id ], '1.0.0', 'wpcv_cgi/civicrm/group_url' );

		/**
		 * Filters the URL of the CiviCRM Group's admin page.
		 *
		 * @since 1.0.0
		 *
		 * @param string $group_url The existing URL.
		 * @param int    $group_id The numeric ID of the CiviCRM Group.
		 */
		return apply_filters( 'wpcv_cgi/civicrm/group_url', $group_url, $group_id );

	}

	// -----------------------------------------------------------------------------------

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
			'id'      => $group_id,
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
			'source'  => 'synced-group-' . $wp_group_id,
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
			'id'      => $group_id,
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
		$tmp         = explode( 'synced-group-', $civicrm_group['source'] );
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
			'version'     => 3,
			'name'        => wp_unslash( $wp_group->name ),
			'title'       => wp_unslash( $wp_group->name ),
			'description' => isset( $wp_group->description ) ? wp_unslash( $wp_group->description ) : '',
			'group_type'  => [ 1 => 1 ],
			'source'      => 'synced-group-' . $wp_group->group_id,
		];

		// Create the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_created_pre' ], 10, 4 );
		add_action( 'civicrm_post', [ $this, 'group_created_post' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
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
		if ( false === $civicrm_group || empty( $civicrm_group['id'] ) ) {
			return false;
		}

		// Remove hook.
		remove_action( 'civicrm_post', [ $this, 'group_updated' ], 10 );

		// Init params.
		$params = [
			'version'     => 3,
			'id'          => $civicrm_group['id'],
			'name'        => wp_unslash( $wp_group->name ),
			'title'       => wp_unslash( $wp_group->name ),
			'description' => isset( $wp_group->description ) ? wp_unslash( $wp_group->description ) : '',
		];

		// Update the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'create', $params );

		// Reinstate hook.
		add_action( 'civicrm_post', [ $this, 'group_updated' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return (int) $civicrm_group['id'];

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
		if ( false === $civicrm_group || empty( $civicrm_group['id'] ) ) {
			return false;
		}

		// Init params.
		$params = [
			'version' => 3,
			'id'      => $civicrm_group['id'],
		];

		// Delete the synced CiviCRM Group.
		$result = civicrm_api( 'Group', 'delete', $params );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'        => __METHOD__,
				'wp_group_id'   => $wp_group_id,
				'civicrm_group' => $civicrm_group,
				'params'        => $params,
				'result'        => $result,
				'backtrace'     => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return (int) $civicrm_group['id'];

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Gets a CiviCRM Group Contact.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array   $civicrm_contact_id The numeric ID of a CiviCRM Contact.
	 * @return array|bool $group_contact The array of GroupContact data, or false on failure.
	 */
	public function group_contact_get( $civicrm_group_id, $civicrm_contact_id ) {

		// Init return.
		$group_contact = false;

		// Try and init CiviCRM.
		if ( ! $this->plugin->is_civicrm_initialised() ) {
			return $group_contact;
		}

		// Init params.
		$params = [
			'version'    => 3,
			'group_id'   => $civicrm_group_id,
			'contact_id' => $civicrm_contact_id,
			'options'    => [
				'limit' => 1,
			],
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'get', $params );

		// Bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			return $group_contact;
		}

		// Bail if there are no results.
		if ( empty( $result['values'] ) ) {
			return $group_contact;
		}

		// The result set should contain only one item.
		$group_contact = array_pop( $result['values'] );

		// Return Group.
		return $group_contact;

	}

	/**
	 * Add a CiviCRM Contact to a CiviCRM Group.
	 *
	 * @since 0.1
	 *
	 * @param int   $civicrm_group_id The ID of the CiviCRM Group.
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
			'version'    => 3,
			'group_id'   => $civicrm_group_id,
			'contact_id' => $civicrm_contact_id,
			'status'     => 'Added',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hook.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_added' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
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
	 * @param int   $civicrm_group_id The ID of the CiviCRM Group.
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
			'version'    => 3,
			'group_id'   => $civicrm_group_id,
			'contact_id' => $civicrm_contact_id,
			'status'     => 'Removed',
		];

		// Call API.
		$result = civicrm_api( 'GroupContact', 'create', $params );

		// Reinstate hooks.
		add_action( 'civicrm_pre', [ $this, 'group_contacts_deleted' ], 10, 4 );

		// Log error and bail on failure.
		if ( isset( $result['is_error'] ) && 1 === (int) $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			$log   = [
				'method'    => __METHOD__,
				'params'    => $params,
				'result'    => $result,
				'backtrace' => $trace,
			];
			$this->plugin->log_error( $log );
			return false;
		}

		// --<
		return $result;

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Intercept when a CiviCRM Contact is added to a Group.
	 *
	 * @since 0.1
	 *
	 * @param string $op The type of database operation.
	 * @param string $object_name The type of object.
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $contact_ids The array of CiviCRM Contact IDs.
	 */
	public function group_contacts_added( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( 'create' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'GroupContact' !== $object_name ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( false === $wp_group_id ) {
			return;
		}

		// Loop through added Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Add User to "Groups" Group.
				if ( false !== $user_id ) {
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_deleted( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( 'delete' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'GroupContact' !== $object_name ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( false === $wp_group_id ) {
			return;
		}

		// Loop through deleted Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Delete User from "Groups" Group.
				if ( false !== $user_id ) {
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
	 * @param int    $civicrm_group_id The ID of the CiviCRM Group.
	 * @param array  $contact_ids Array of CiviCRM Contact IDs.
	 */
	public function group_contacts_rejoined( $op, $object_name, $civicrm_group_id, $contact_ids ) {

		// Target our operation.
		if ( 'edit' !== $op ) {
			return;
		}

		// Target our object type.
		if ( 'GroupContact' !== $object_name ) {
			return;
		}

		// Get "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );

		// Sanity check.
		if ( false === $wp_group_id ) {
			return;
		}

		// Loop through added Contacts.
		if ( count( $contact_ids ) > 0 ) {
			foreach ( $contact_ids as $contact_id ) {

				// Get WordPress User ID.
				$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );

				// Add User to "Groups" Group.
				if ( false !== $user_id ) {
					$this->plugin->wordpress->group_member_add( $user_id, $wp_group_id );
				}

			}
		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Fires after CiviCRM has tried to create a WordPress User.
	 *
	 * The params are due to be added to CiviCRM 5.71 so defaults are needed.
	 *
	 * @since 0.1.1
	 *
	 * @param int|WP_Error $user_id The ID of the new WordPress User, or WP_Error on failure.
	 * @param array        $params The array of source Contact data.
	 * @param bool         $logged_in TRUE when the User has been auto-logged-in, FALSE otherwise.
	 */
	public function user_created_post( $user_id = 0, $params = [], $logged_in = null ) {

		// If there's no User ID, we have the old hook signature.
		if ( empty( $user_id ) ) {
			return;
		}

		// Bail if there's no Contact ID.
		if ( empty( $params['contactID'] ) ) {
			return;
		}

		// Get the synced Group IDs for this Contact ID.
		$group_ids = $this->group_ids_get_for_contact( (int) $params['contactID'] );
		if ( $group_ids instanceof CRM_Core_Exception || empty( $group_ids ) ) {
			return;
		}

		// Add User to all their synced "Groups" Groups.
		foreach ( $group_ids as $civicrm_group_id ) {

			// Skip if there's no "Groups" Group ID.
			$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );
			if ( false === $wp_group_id ) {
				continue;
			}

			// Add User to "Groups" Group.
			$this->plugin->wordpress->group_member_add( $user_id, $wp_group_id );

		}

	}

	/**
	 * Fires when CiviCRM is about to create a WordPress User.
	 *
	 * The params are due to be added to CiviCRM 5.71 so defaults are needed.
	 *
	 * @since 0.1.1
	 *
	 * @param array  $params The array of source Contact data.
	 * @param string $mail_param The name of the param which contains the email address.
	 * @param array  $user_data The array of data to create the WordPress User with.
	 */
	public function user_create_pre( $params = [], $mail_param = '', $user_data = [] ) {

		/*
		 * If there are params, we have the new hook signature which is
		 * handled by self::user_create_post()
		 */
		if ( ! empty( $params ) ) {
			return;
		}

		// We need to listen to "user_register" for the User ID.
		add_action( 'user_register', [ $this, 'user_created' ] );

	}

	/**
	 * Fires when a WordPress User has been created.
	 *
	 * @since 0.1.1
	 *
	 * @param int $user_id The ID of the WordPress User.
	 */
	public function user_created( $user_id ) {

		// Sanity check.
		if ( empty( $user_id ) ) {
			return;
		}

		// Grab the current Contact ID.
		$contact_id = CRM_Core_Session::singleton()->get( 'transaction.userID' );
		if ( empty( $contact_id ) ) {
			return;
		}

		// Get the synced Group IDs for this Contact ID.
		$group_ids = $this->group_ids_get_for_contact( (int) $contact_id );
		if ( $group_ids instanceof CRM_Core_Exception || empty( $group_ids ) ) {
			return;
		}

		// Add User to all their synced "Groups" Groups.
		foreach ( $group_ids as $civicrm_group_id ) {

			// Skip if there's no "Groups" Group ID.
			$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );
			if ( false === $wp_group_id ) {
				continue;
			}

			// Add User to "Groups" Group.
			$this->plugin->wordpress->group_member_add( $user_id, $wp_group_id );

		}

		// Remove our callback.
		remove_action( 'user_register', [ $this, 'user_created' ] );

	}

	// -----------------------------------------------------------------------------------

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

		// Search using CiviCRM's logic.
		$contact_id = CRM_Core_BAO_UFMatch::getContactId( $user_id );

		// Cast Contact ID as boolean if we didn't get one.
		if ( empty( $contact_id ) ) {
			$contact_id = false;
		}

		/**
		 * Filter the result of the CiviCRM Contact lookup.
		 *
		 * @since 0.1
		 * @deprecated 1.0.0 Use the {@see 'wpcv_cgi/civicrm/contact_id'} filter instead.
		 *
		 * @param int|bool $contact_id The numeric ID of the CiviCRM Contact, or false on failure.
		 * @param int      $user_id The numeric ID of the WordPress User.
		 */
		$contact_id = apply_filters_deprecated( 'civicrm_groups_sync_contact_id_get_by_user_id', [ $contact_id, $user_id ], '1.0.0', 'wpcv_cgi/civicrm/contact_id' );

		/**
		 * Filters the result of the CiviCRM Contact lookup.
		 *
		 * You can use this filter to create a CiviCRM Contact if none is found.
		 * Return the new CiviCRM Contact ID and the Group linkage will be made.
		 *
		 * @since 1.0.0
		 *
		 * @param int|bool $contact_id The numeric ID of the CiviCRM Contact, or false on failure.
		 * @param int      $user_id The numeric ID of the WordPress User.
		 */
		$contact_id = apply_filters( 'wpcv_cgi/civicrm/contact_id', $contact_id, $user_id );

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
			'id'      => $contact_id,
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

	// -----------------------------------------------------------------------------------

	/**
	 * Syncs all CiviCRM Groups to their corresponding "Groups" Groups.
	 *
	 * @since 0.3.0
	 */
	public function sync_to_wp() {

		// Feedback at the beginning of the process.
		$this->plugin->log_message( '' );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );
		$this->plugin->log_message( __( 'Syncing all CiviCRM Groups to their corresponding "Groups" Groups...', 'wpcv-civicrm-groups-integration' ) );
		$this->plugin->log_message( '--------------------------------------------------------------------------------' );

		// Get all synced CiviCRM Groups.
		$groups = $this->groups_synced_get();

		// Trap errors.
		if ( $groups instanceof CRM_Core_Exception ) {
			$data = [
				'method'    => __METHOD__,
				'message'   => $groups->getMessage(),
				'backtrace' => $groups->getTraceAsString(),
			];
			$this->plugin->log_error( $data );
			return;
		}

		foreach ( $groups as $group ) {
			$this->plugin->log_message( '' );
			$this->plugin->log_message(
				/* translators: 1: The name of the Group, 2: The ID of the Group. */
				sprintf( __( 'Syncing CiviCRM Group %1$s (ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $group['title'], (int) $group['id'] )
			);
			$this->group_sync_to_wp( (int) $group['id'] );
		}

		$this->plugin->log_message( '' );

	}

	/**
	 * Syncs a given CiviCRM Group to a "Groups" Group.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $civicrm_group_id The numeric ID of the CiviCRM Group.
	 */
	public function group_sync_to_wp( $civicrm_group_id ) {

		// Avoid nonsense requests.
		if ( empty( $civicrm_group_id ) ) {
			return;
		}

		// Bail if there is no "Groups" Group ID.
		$wp_group_id = $this->group_get_wp_id_by_civicrm_id( $civicrm_group_id );
		if ( false === $wp_group_id ) {
			return;
		}

		$this->plugin->log_message(
			/* translators: %d: The ID of the Group. */
			sprintf( __( 'Syncing with "Groups" Group (ID: %d)', 'wpcv-civicrm-groups-integration' ), (int) $wp_group_id )
		);

		// Get the list of Contact IDs in the Group.
		$civicrm_group_contact_ids = $this->group_contact_ids_get( $civicrm_group_id );

		if ( $civicrm_group_contact_ids instanceof CRM_Core_Exception ) {
			$this->plugin->log_message(
				/* translators: %s: The error message. */
				sprintf( __( 'Could not fetch Contact IDs: %s', 'wpcv-civicrm-groups-integration' ), $civicrm_group_contact_ids->getMessage() )
			);
			return;
		}

		// Get all User IDs in the "Groups" Group.
		$wp_group_user_ids = $this->plugin->wordpress->group_user_ids_get( $wp_group_id );

		// Set a feedback flag.
		$did_sync = false;

		// Add Contacts to the Group if they are missing.
		if ( ! empty( $civicrm_group_contact_ids ) ) {

			// Get the Users to add to the "Groups" Group.
			$data = $this->plugin->wordpress->group_user_ids_to_add( $civicrm_group_contact_ids, $wp_group_id );
			if ( ! empty( $data['has-user-id'] ) ) {

				$this->plugin->log_message( __( 'Adding Contacts from CiviCRM Group...', 'wpcv-civicrm-groups-integration' ) );

				$feedback = $this->plugin->wordpress->group_user_ids_add( $data['has-user-id'], $wp_group_id );

				if ( ! empty( $feedback['added'] ) ) {
					foreach ( $feedback['added'] as $contact_id => $user_id ) {
						$this->plugin->log_message(
							/* translators: 1: The ID of the Contact, 2: The ID of the User. */
							sprintf( __( 'Added User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), (int) $contact_id, (int) $user_id )
						);
					}
				}
				if ( ! empty( $feedback['failed'] ) ) {
					foreach ( $feedback['failed'] as $contact_id => $user_id ) {
						$this->plugin->log_message(
							/* translators: 1: The ID of the Contact, 2: The ID of the User. */
							sprintf( __( 'Failed to add User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), (int) $contact_id, (int) $user_id )
						);
					}
				}
				if ( ! empty( $data['no-user-id'] ) ) {
					foreach ( $data['no-user-id'] as $contact_id ) {
						$this->plugin->log_message(
							/* translators: %d: The ID of the Contact. */
							sprintf( __( 'No User ID found for Contact (ID: %d)', 'wpcv-civicrm-groups-integration' ), (int) $contact_id )
						);
					}
				}

				$did_sync = true;

			}

		}

		/*
		 * Delete any Users from the "Groups" Group that are not in the CiviCRM Group.
		 *
		 * To allow the deletion of an entry after a User has been deleted, we don't
		 * check if the User exists.
		 */
		if ( ! empty( $wp_group_user_ids ) ) {

			// Get all Contact IDs in the Group.
			$wp_group_contact_ids = $this->group_contact_ids_for_user_ids_get( $wp_group_user_ids );

			// Get all Contact IDs to remove from the "Groups" Group.
			$contact_ids_to_remove = array_diff( $wp_group_contact_ids, $civicrm_group_contact_ids );

			if ( ! empty( $contact_ids_to_remove ) ) {

				$this->plugin->log_message( __( 'Deleting Contacts not in CiviCRM Group...', 'wpcv-civicrm-groups-integration' ) );

				// Process Contact IDs.
				foreach ( $contact_ids_to_remove as $contact_id ) {

					// Find the corresponding User ID.
					$user_id = $this->plugin->wordpress->user_id_get_by_contact_id( $contact_id );
					if ( false === $user_id ) {
						continue;
					}

					// Remove User from "Groups" Group.
					$success = $this->plugin->wordpress->group_member_delete( $user_id, $wp_group_id );
					if ( true === $success ) {
						$this->plugin->log_message(
							/* translators: 1: The ID of the Contact, 2: The ID of the User. */
							sprintf( __( 'Removed User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), (int) $contact_id, (int) $user_id )
						);
					} else {
						$this->plugin->log_message(
							/* translators: 1: The ID of the Contact, 2: The ID of the User. */
							sprintf( __( 'Failed to remove User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), (int) $contact_id, (int) $user_id )
						);
					}

				}

				$did_sync = true;

			}

		}

		// Show feedback when no sync has taken place.
		if ( false === $did_sync ) {
			$this->plugin->log_message( __( 'Groups are already in sync.', 'wpcv-civicrm-groups-integration' ) );
		}

	}

	// -----------------------------------------------------------------------------------

	/**
	 * Batch sync CiviCRM Groups to "Groups" Groups.
	 *
	 * @since 0.3.0
	 *
	 * @param string $identifier The batch identifier.
	 */
	public function batch_sync_to_wp( $identifier ) {

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
	 * Batch sync CiviCRM Group Contacts to "Groups" Group Users.
	 *
	 * @since 0.3.0
	 *
	 * @param WPCV_CGI_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_one( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Contacts for this step.
		$civicrm_batch = $this->group_contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}

		// Ensure each Group Contact exists in the "Groups" Group.
		if ( ! empty( $civicrm_batch ) ) {
			foreach ( $civicrm_batch as $group_contact ) {

				// Skip if there is an existing Group member.
				$user_id  = (int) $group_contact['uf_match.uf_id'];
				$group_id = (int) str_replace( 'synced-group-', '', $group_contact['group.source'] );
				$exists   = $this->plugin->wordpress->group_member_exists( $user_id, $group_id );
				if ( false !== $exists ) {
					continue;
				}

				// Finally add the "Groups" Group membership.
				$contact_id = (int) $group_contact['contact_id'];
				$success    = $this->plugin->wordpress->group_member_add( $user_id, $group_id );
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

		// Get the next batch of Group Contacts.
		$batch->stepper->next();
		$offset        = $batch->stepper->initialise();
		$civicrm_batch = $this->group_contacts_get( $limit, $offset );
		if ( ( $civicrm_batch instanceof CRM_Core_Exception ) ) {
			$civicrm_batch = [];
		}

		// Move batching onwards.
		if ( empty( $civicrm_batch ) ) {
			$batch->next();
		}

	}

	/**
	 * Batch delete Group Users where the Contact no longer exists in the CiviCRM Group.
	 *
	 * @since 0.3.0
	 *
	 * @param WPCV_CGI_Admin_Batch $batch The batch object.
	 */
	public function batch_sync_two( $batch ) {

		$limit  = $batch->stepper->step_count_get();
		$offset = $batch->stepper->initialise();

		// Get the batch of Group Users for this step.
		$groups_batch = $this->plugin->wordpress->group_users_get( $limit, $offset );

		// Save some queries by using a pseudo-cache.
		$correspondences = [
			'users'  => [],
			'groups' => [],
		];

		// Delete each Group User where the Contact no longer exists in the CiviCRM Group.
		if ( ! empty( $groups_batch ) ) {
			foreach ( $groups_batch as $group_user ) {

				// Get the CiviCRM Group ID for this "Groups" Group ID.
				$group_id = (int) $group_user['group_id'];

				// Try the pseudo-cache first.
				if ( isset( $correspondences['groups'][ $group_id ] ) ) {
					$civicrm_group_id = $correspondences['groups'][ $group_id ];
				} else {
					// Check the database.
					$civicrm_group = $this->group_get_by_wp_id( $group_id );
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
					$contact_id = $this->contact_id_get_by_user_id( $user_id );
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
				$exists = $this->group_contact_get( $civicrm_group_id, $contact_id );
				if ( ! empty( $exists ) ) {
					continue;
				}

				// Finally delete the "Groups" Group membership.
				$success = $this->plugin->wordpress->group_member_delete( $user_id, $group_id );
				if ( ! empty( $success ) ) {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Removed User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				} else {
					$this->plugin->log_message(
						/* translators: 1: The ID of the Contact, 2: The ID of the User. */
						sprintf( __( 'Failed to remove User (Contact ID: %1$d) (User ID: %2$d)', 'wpcv-civicrm-groups-integration' ), $contact_id, $user_id )
					);
				}

			}
		}

		// Get the next batch of Group Users.
		$batch->stepper->next();
		$offset       = $batch->stepper->initialise();
		$groups_batch = $this->plugin->wordpress->group_users_get( $limit, $offset );

		// Move batching onwards.
		if ( empty( $groups_batch ) ) {
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
