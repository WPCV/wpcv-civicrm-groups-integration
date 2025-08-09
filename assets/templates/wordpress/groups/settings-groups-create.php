<?php
/**
 * Create a CiviCRM Group template.
 *
 * Handles markup for the Create Group screen.
 *
 * @package WPCV_CGI
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/wordpress/groups/settings-groups-create.php -->
<div class="field">
	<label for="civicrm-group-field" class="field-label civicrm-group-field"><input type="checkbox" id="civicrm-group-field" name="civicrm-group-field" value="1" /> <?php esc_html_e( 'Create a CiviCRM Group', 'wpcv-civicrm-groups-integration' ); ?></label>
	<p class="description"><?php esc_html_e( 'Checking this will create a CiviCRM Group that is linked to this Group. The Group Members will exist in both Groups.', 'wpcv-civicrm-groups-integration' ); ?></p>
</div>
