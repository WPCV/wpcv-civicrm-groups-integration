<?php
/**
 * Settings screen "Submit" metabox template.
 *
 * Handles markup for the Settings screen "Submit" metabox.
 *
 * @package WPCV_CGI
 * @since 0.2.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_metabox ); ?>metabox-settings-submit.php -->
<div class="submitbox">
	<div id="minor-publishing">
		<div id="misc-publishing-actions">
			<div class="misc-pub-section">
				<?php

				// Default section text.
				$misc_pub_section = esc_html__( 'Save your settings here.', 'wpcv-civicrm-groups-integration' );

				/**
				 * Filters the "misc publishing actions" section.
				 *
				 * Make sure that whatever is returned has been properly escaped.
				 *
				 * @since 0.2.1
				 *
				 * @param string $misc_pub_section The text for the "misc publishing actions" section.
				 */
				$misc_pub_section = apply_filters( $this->hook_prefix . '/settings/page/metabox/submit/misc_pub', $misc_pub_section );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $misc_pub_section;

				?>
			</div>
		</div>
		<div class="clear"></div>
	</div>

	<div id="major-publishing-actions">
		<div id="publishing-action">
			<?php submit_button( esc_html__( 'Update', 'wpcv-civicrm-groups-integration' ), 'primary', $this->form_submit_id, false ); ?>
			<input type="hidden" name="action" value="update" />
		</div>
		<div class="clear"></div>
	</div>
</div>
