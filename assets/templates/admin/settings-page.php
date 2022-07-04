<?php
/**
 * Settings Page template.
 *
 * Handles markup for the Settings Page.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/admin/settings-page.php -->
<div class="wrap">

	<h1><?php esc_html_e( 'Integrate CiviCRM with Groups', 'wpcv-civicrm-groups-integration' ); ?></h1>

	<?php if ( $show_tabs ) : ?>
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo $urls['settings']; ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'wpcv-civicrm-groups-integration' ); ?></a>
			<?php

			/**
			 * Fires when tabs can be added.
			 *
			 * @since 0.1
			 *
			 * @param array $urls The array of subpage URLs.
			 * @param str The key of the active tab in the subpage URLs array.
			 */
			do_action( 'wpcv_cgi/settings_nav_tabs', $urls, 'settings' );

			?>
		</h2>
	<?php else : ?>
		<hr />
	<?php endif; ?>

	<form method="post" id="wpcv_cgi_settings_form" action="<?php echo $this->page_submit_url_get(); ?>">

		<?php wp_nonce_field( 'wpcv_cgi_settings_action', 'wpcv_cgi_settings_nonce' ); ?>

		<p><?php esc_html_e( 'Settings to go here.', 'wpcv-civicrm-groups-integration' ); ?></p>

		<hr />

		<p class="submit">
			<input class="button-primary" type="submit" id="wpcv_cgi_settings_submit" name="wpcv_cgi_settings_submit" value="<?php esc_attr_e( 'Save Changes', 'wpcv-civicrm-groups-integration' ); ?>" />
		</p>

	</form>

</div><!-- /.wrap -->
