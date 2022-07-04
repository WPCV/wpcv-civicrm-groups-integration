<?php
/**
 * Edit Group template.
 *
 * Handles markup for the Edit Group screen.
 *
 * @package WPCV_CGI
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?><!-- assets/templates/admin/settings-groups-edit.php -->
<div class="field">
	<p>
	<?php

	echo sprintf(
		/* translators: %s: The URL of the Group. */
		__( 'There is <a href="%s">an existing CiviCRM Group</a> that is linked to this Group. The Group Members will exist in both Groups.', 'wpcv-civicrm-groups-integration' ),
		$group_url
	);

	?>
	</p>
</div>
