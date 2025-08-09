<?php
/**
 * Scheduled Events template.
 *
 * Handles markup for the Scheduled Events meta box.
 *
 * @package WPCV_CGI
 * @since 0.3.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

?>
<!-- <?php echo esc_html( $this->path_template . $this->path_metabox ); ?>metabox-settings-schedule.php -->
<table class="form-table">
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_interval_id ); ?>"><?php esc_html_e( 'Schedule Interval', 'wpcv-civicrm-groups-integration' ); ?></label></th>
		<td>
			<select class="settings-select" name="<?php echo esc_attr( $this->form_interval_id ); ?>" id="<?php echo esc_attr( $this->form_interval_id ); ?>">
				<?php foreach ( $schedules as $key => $value ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $interval, $key ); ?>><?php echo esc_html( $value['display'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Choose how often to synchronize your Synced Groups.', 'wpcv-civicrm-groups-integration' ); ?></p>
		</td>
	</tr>
</table>

<table class="form-table sync-details">
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_direction_id ); ?>"><?php esc_html_e( 'Sync direction', 'wpcv-civicrm-groups-integration' ); ?></label></th>
		<td>
			<select class="settings-select" name="<?php echo esc_attr( $this->form_direction_id ); ?>" id="<?php echo esc_attr( $this->form_direction_id ); ?>">
				<option value="civicrm" <?php selected( $direction, 'civicrm' ); ?>><?php esc_html_e( 'CiviCRM Groups &rarr; "Groups" Groups', 'wpcv-civicrm-groups-integration' ); ?></option>
				<option value="groups" <?php selected( $direction, 'groups' ); ?>><?php esc_html_e( '"Groups" Groups &rarr; CiviCRM Groups', 'wpcv-civicrm-groups-integration' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Choose whether your CiviCRM Groups or "Groups" Groups are the "source of truth".', 'wpcv-civicrm-groups-integration' ); ?></p>
		</td>
	</tr>

	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $this->form_batch_id ); ?>"><?php esc_html_e( 'Batch count', 'wpcv-civicrm-groups-integration' ); ?></label></th>
		<td>
			<input type="number" name="<?php echo esc_attr( $this->form_batch_id ); ?>" id="<?php echo esc_attr( $this->form_batch_id ); ?>" value="<?php echo esc_attr( $batch_count ); ?>" />
			<p class="description"><?php esc_html_e( 'Set the number of items to process each time the schedule runs. Setting "0" will process all Groups in one go. Be aware that this could exceed your PHP timeout limit if you have lots of Groups and Contacts. It would be better to use one of the supplied WP-CLI commands in this situation.', 'wpcv-civicrm-groups-integration' ); ?></p>
		</td>
	</tr>
</table>
