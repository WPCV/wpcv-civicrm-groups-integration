<?php
/**
 * Job command class.
 *
 * @package WPCV_CGI
 */

// Bail if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Run Integrate CiviCRM with Groups cron jobs.
 *
 * ## EXAMPLES
 *
 *     $ wp cvgrp job sync_to_wp
 *     Success: Executed 'sync_to_wp' job.
 *
 * @since 0.3.0
 *
 * @package WPCV_CGI
 */
class WPCV_CGI_CLI_Command_Job extends WPCV_CGI_CLI_Command {

	/**
	 * Sync CiviCRM Group Contacts to WordPress Groups.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cvgrp job sync_to_wp
	 *     Success: Executed 'sync_to_wp' job.
	 *
	 * @alias sync-to-wp
	 *
	 * @since 0.3.0
	 *
	 * @param array $args The WP-CLI positional arguments.
	 * @param array $assoc_args The WP-CLI associative arguments.
	 */
	public function sync_to_wp( $args, $assoc_args ) {

		// Bootstrap CiviCRM.
		$this->bootstrap_civicrm();

		$plugin = wpcv_cgi();

		// Get all synced CiviCRM Groups.
		$groups = $plugin->civicrm->groups_synced_get();
		if ( $groups instanceof CRM_Core_Exception ) {
			WP_CLI::error( sprintf( 'Could not fetch CiviCRM Groups: %s.', $groups->getMessage() ) );
		}

		foreach ( $groups as $group ) {
			WP_CLI::log( '' );
			WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing CiviCRM Group%n %Y%s%n %y(ID: %d)%n' ), $group['title'], (int) $group['id'] ) );
			$this->group_sync_to_wp( (int) $group['id'] );
		}

		WP_CLI::log( '' );
		WP_CLI::success( "Executed 'sync_to_wp' job." );

	}

	/**
	 * Syncs a given CiviCRM Group to a "Groups" Group.
	 *
	 * @since 0.3.0
	 *
	 * @param integer $civicrm_group_id The numeric CiviCRM Group ID.
	 */
	private function group_sync_to_wp( $civicrm_group_id ) {

		// Avoid nonsense requests.
		if ( empty( $civicrm_group_id ) ) {
			return;
		}

		$plugin = wpcv_cgi();

		// Bail if there is no "Groups" Group ID.
		$wp_group_id = $plugin->civicrm->group_get_wp_id_by_civicrm_id( $civicrm_group_id );
		if ( false === $wp_group_id ) {
			return;
		}

		WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with "Groups" Group%n %y(ID: %d)%n' ), (int) $wp_group_id ) );

		// Get the list of Contact IDs in the Group.
		$civicrm_group_contact_ids = $plugin->civicrm->group_contact_ids_get( $civicrm_group_id );
		if ( $civicrm_group_contact_ids instanceof CRM_Core_Exception ) {
			WP_CLI::log( sprintf( WP_CLI::colorize( '%rCould not fetch Contact IDs:%n %s' ), $civicrm_group_contact_ids->getMessage() ) );
			return;
		}

		// Get all User IDs in the "Groups" Group.
		$wp_group_user_ids = $plugin->wordpress->group_user_ids_get( $wp_group_id );

		// Set a feedback flag.
		$did_sync = false;

		// Add Contacts to the Group if they are missing.
		if ( ! empty( $civicrm_group_contact_ids ) ) {

			// Get the Users to add to the "Groups" Group.
			$data = $plugin->wordpress->group_user_ids_to_add( $civicrm_group_contact_ids, $wp_group_id );
			if ( ! empty( $data['has-user-id'] ) ) {

				WP_CLI::log( WP_CLI::colorize( '%gAdding Contacts from CiviCRM Group...%n' ) );

				$feedback = $plugin->wordpress->group_user_ids_add( $data['has-user-id'], $wp_group_id );

				if ( ! empty( $feedback['added'] ) ) {
					foreach ( $feedback['added'] as $contact_id => $user_id ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gAdded User%n %y(Contact ID: %d) (User ID: %d)%n' ), (int) $contact_id, (int) $user_id ) );
					}
				}
				if ( ! empty( $feedback['failed'] ) ) {
					foreach ( $feedback['failed'] as $contact_id => $user_id ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to add User%n %y(Contact ID: %d) (User ID: %d)%n' ), (int) $contact_id, (int) $user_id ) );
					}
				}
				if ( ! empty( $data['no-user-id'] ) ) {
					foreach ( $data['no-user-id'] as $contact_id ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%bNo User ID found for Contact%n %y(ID: %d)%n' ), (int) $contact_id ) );
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
			$wp_group_contact_ids = $plugin->civicrm->group_contact_ids_for_user_ids_get( $wp_group_user_ids );

			// Get all Contact IDs to remove from the "Groups" Group.
			$contact_ids_to_remove = array_diff( $wp_group_contact_ids, $civicrm_group_contact_ids );

			if ( ! empty( $contact_ids_to_remove ) ) {

				WP_CLI::log( WP_CLI::colorize( '%gDeleting Contacts not in CiviCRM Group...%n' ) );

				// Process Contact IDs.
				foreach ( $contact_ids_to_remove as $contact_id ) {

					// Find the corresponding User ID.
					$user_id = $plugin->wordpress->user_id_get_by_contact_id( $contact_id );
					if ( false === $user_id ) {
						continue;
					}

					// Remove User from "Groups" Group.
					$success = $plugin->wordpress->group_member_delete( $user_id, $wp_group_id );
					if ( true === $success ) {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gRemoved User%n %y(Contact ID: %d) (User ID: %d)%n' ), (int) $contact_id, (int) $user_id ) );
					} else {
						WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to remove User%n %y(Contact ID: %d) (User ID: %d)%n' ), (int) $contact_id, (int) $user_id ) );
					}

				}

				$did_sync = true;

			}

		}

		// Show feedback when no sync has taken place.
		if ( false === $did_sync ) {
			WP_CLI::log( WP_CLI::colorize( '%gGroups are already in sync.%n' ) );
		}

	}

	/**
	 * Sync WordPress "Groups" Group Users to CiviCRM Groups.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cvgrp job sync_to_civicrm
	 *     Success: Executed 'sync_to_civicrm' job.
	 *
	 * @alias sync-to-civicrm
	 *
	 * @since 0.3.0
	 *
	 * @param array $args The WP-CLI positional arguments.
	 * @param array $assoc_args The WP-CLI associative arguments.
	 */
	public function sync_to_civicrm( $args, $assoc_args ) {

		// Bootstrap CiviCRM.
		$this->bootstrap_civicrm();

		$plugin = wpcv_cgi();

		// Get all the Group Users in the Synced Groups.
		$group_users = $plugin->wordpress->group_users_get();
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
					$civicrm_group = $plugin->civicrm->group_get_by_wp_id( $group_id );
					if ( ! empty( $civicrm_group ) ) {
						$civicrm_group_id                       = (int) $civicrm_group['id'];
						$correspondences['groups'][ $group_id ] = $civicrm_group_id;
						WP_CLI::log( '' );
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gAdding Users from "Groups" Group%n %Y%s%n %y(ID: %d)%n' ), $civicrm_group['title'], (int) $group_id ) );
						WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM Group%n %y(ID: %d)%n' ), $civicrm_group_id ) );
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
					$contact_id = $plugin->civicrm->contact_id_get_by_user_id( $user_id );
					if ( ! empty( $contact_id ) ) {
						$correspondences['users'][ $user_id ] = (int) $contact_id;
					} else {
						$correspondences['users'][ $user_id ] = false;
					}
				}

				// Skip if there is no Contact for this User ID.
				if ( empty( $contact_id ) || false === $contact_id ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%bNo Contact ID found for User%n %y(ID: %d)%n' ), $user_id ) );
					continue;
				}

				// Skip if there is an existing GroupContact entry.
				$exists = $plugin->civicrm->group_contact_get( $civicrm_group_id, $contact_id );
				if ( ! empty( $exists ) ) {
					continue;
				}

				// Create the CiviCRM Group Contact.
				$success = $plugin->civicrm->group_contact_create( $civicrm_group_id, $contact_id );
				if ( false !== $success ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gAdded User%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
				} else {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to add User%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
				}

			}

		}

		// Get all the Group Contacts in the Synced Groups.
		$group_contacts = $plugin->civicrm->group_contacts_get();
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
					WP_CLI::log( '' );
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gDeleting Contacts not in "Groups" Group%n %Y%s%n %y(ID: %d)%n' ), $group_contact['group.title'], $group_id ) );
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gSyncing with CiviCRM Group%n %y(ID: %d)%n' ), (int) $group_contact['group_id'] ) );
					$group_ids[] = $group_id;
				}

				// Skip if the Group User exists.
				if ( $plugin->wordpress->group_member_exists( $user_id, $group_id ) ) {
					continue;
				}

				// Delete the Group Contact.
				$contact_id = (int) $group_contact['contact_id'];
				$success    = $plugin->civicrm->group_contact_delete( (int) $group_contact['group_id'], $contact_id );
				if ( false !== $success ) {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%gRemoved Contact%n %y(Contact ID: %d) (User ID: %d)%n' ), $contact_id, $user_id ) );
				} else {
					WP_CLI::log( sprintf( WP_CLI::colorize( '%rFailed to remove Contact%n %y(Contact ID: %d) (User ID: %d)%n' ), (int) $contact_id, (int) $user_id ) );
				}

			}
		}

		WP_CLI::log( '' );
		WP_CLI::success( "Executed 'sync_to_civicrm' job." );

	}

}
