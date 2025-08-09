<?php
/**
 * Uninstaller.
 *
 * Handles plugin uninstallation.
 *
 * @package WPCV_CGI
 */

// Bail if uninstall not called from WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// TODO: This may need to be done for every site in multisite.

// Delete version.
delete_option( 'wpcv_cgi_version' );

// Delete settings.
delete_option( 'wpcv_cgi_settings' );
