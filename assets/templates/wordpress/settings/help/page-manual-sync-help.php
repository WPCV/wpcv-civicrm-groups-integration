<?php
/**
 * Settings Help template.
 *
 * Handles markup for Settings Help.
 *
 * @package WPCV_CGI
 * @since 0.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_help ); ?>page-settings-help.php -->
<p><?php esc_html_e( 'Choose your sync direction depending on whether your CiviCRM Groups or your "Groups" Groups are the "source of truth".', 'wpcv-civicrm-groups-integration' ); ?></p>

<p><?php esc_html_e( 'The procedure in both directions is as follows:', 'wpcv-civicrm-groups-integration' ); ?></p>

<ol>
	<li><?php esc_html_e( 'Group members in the source Group will be added to the target Group if they are missing.', 'wpcv-civicrm-groups-integration' ); ?></li>
	<li><?php esc_html_e( 'Group members in the target Group will be deleted if they are no longer members of the source Group.', 'wpcv-civicrm-groups-integration' ); ?></li>
</ol>
