CiviCRM Groups Sync
===================

**Contributors:** [needle](https://profiles.wordpress.org/needle/) [kcristiano](https://profiles.wordpress.org/kcristiano/)<br/>
**Donate link:** https://www.paypal.me/interactivist<br/>
**Tags:** civicrm, groups, sync<br/>
**Requires at least:** 4.9<br/>
**Tested up to:** 6.0<br/>
**Stable tag:** 1.0.0a<br/>
**License:** GPLv2 or later<br/>
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Keeps Contacts in CiviCRM Groups in sync with WordPress Users in Groups provided by the Groups plugin



## Description

Please note: this is the development repository for *CiviCRM Groups Sync*.

*CiviCRM Groups Sync* is a WordPress plugin that keeps Contacts in CiviCRM Groups in sync with WordPress Users in Groups provided by the [Groups](https://wordpress.org/plugins/groups/) plugin.

### Requirements

This plugin requires a minimum of *WordPress 4.9*, *Groups 2.5* and *CiviCRM 5.8*.



## Installation

There are two ways to install from GitLab:

### ZIP Download

If you have downloaded *CiviCRM Groups Sync* as a ZIP file from the git repository, do the following to install and activate the plugin:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/civicrm-groups-sync`
2. Activate the plugin (in multisite, network activate)
3. You are done!

### git clone

If you have cloned the code from the git repository, it is assumed that you know what you're doing.



## Notes

### WordPress Users

By default, this plugin does not create a WordPress User when a CiviCRM Contact is added to a CiviCRM Group which is synced to a *Groups* Group. If you wish to do so, use a callback from the `civicrm_groups_sync_user_id_get_by_contact_id` filter to create a new WordPress User and return the User ID.

### CiviCRM Contacts

By default, this plugin does not create a CiviCRM Contact when a WordPress User is added to a *Groups* Group which is synced to a CiviCRM Group. If you wish to do so, use a callback from the `civicrm_groups_sync_contact_id_get_by_user_id` filter to create a new CiviCRM Contact and return the Contact ID.

### BuddyPress compatibility

If you are using both *BuddyPress* and *Groups* then you will also need [this plugin](https://github.com/itthinx/groups-buddypress-compatibility) in order to prevent conflicts between the two identically-named actions in these plugins.

### Permissions Sync

It may be useful to sync the capabilities/permissions using [CiviCRM Permissions Sync](https://develop.tadpole.cc/plugins/civicrm-permissions-sync). Details can be found in the CiviCRM Permissions Sync [README.md file](https://develop.tadpole.cc/plugins/civicrm-permissions-sync/-/blob/master/README.md).



## Usage

### WordPress Groups

When you add a Group in WordPress, you'll be able to select an option that also creates a Group in CiviCRM. This allows you to update a User in a WordPress Group and it will update that Contact in CiviCRM and add them to the CiviCRM Group.

![CGS add Group in WordPress](https://develop.tadpole.cc/danaskallman/civicrm-groups-sync/raw/docs/docs/images/cgs-wp-add-group.png)

With the Groups in WordPress, you'll be also be able to assign capabilities or ACLs so that Users in that Groups can access the parts of CiviCRM they can work on.

![CGS for capability/ACL management](https://develop.tadpole.cc/danaskallman/civicrm-groups-sync/raw/docs/docs/images/cgs-capabilities-mgmt.png)

Tip: Add Users as WordPress using the default User Roles, like Subscriber or Author. Then add them to the appropriate Group to get the ACLs needed to access CiviCRM.

### CiviCRM Groups

When adding a Group in CiviCRM, there will be an option to also create a Group in WordPress. These Groups are all Access Control Groups, since capabilities can be assigned to the Groups, as referenced above.

![CGS add Group in CiviCRM](https://develop.tadpole.cc/danaskallman/civicrm-groups-sync/raw/docs/docs/images/cgs-add-group-civicrm.png)
