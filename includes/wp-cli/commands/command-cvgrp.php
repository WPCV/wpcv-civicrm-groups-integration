<?php
/**
 * Command class.
 *
 * @package WPCV_CGI
 */

// Bail if WP-CLI is not present.
if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Manage Integrate CiviCRM with Groups through the command-line.
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
class WPCV_CGI_CLI_Command extends WPCV_CGI_CLI_Command_Base {

	/**
	 * Adds our description and sub-commands.
	 *
	 * @since 0.3.0
	 *
	 * @param object $command The command.
	 * @return array $info The array of information about the command.
	 */
	private function command_to_array( $command ) {

		$info = [
			'name'        => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc'    => $command->get_longdesc(),
		];

		foreach ( $command->get_subcommands() as $subcommand ) {
			$info['subcommands'][] = $this->command_to_array( $subcommand );
		}

		if ( empty( $info['subcommands'] ) ) {
			$info['synopsis'] = (string) $command->get_synopsis();
		}

		return $info;

	}

}
