<?php
/**
 * Edit Group template.
 *
 * Handles markup for the Edit Group screen.
 *
 * @package WPCV_CGI
 * @since 0.1
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/wordpress/groups/settings-groups-edit.php -->
<div class="field">
	<p>
	<?php

	echo sprintf(
		/* translators: 1: The opening anchor tag, 2: The closing anchor tag. */
		esc_html__( 'There is %1$san existing CiviCRM Group%2$s that is linked to this Group. The Group Members will exist in both Groups.', 'wpcv-civicrm-groups-integration' ),
		'<a href="' . esc_url( $group_url ) . '">',
		'</a>'
	);

	?>
	</p>
</div>
