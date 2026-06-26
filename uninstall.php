<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Commission rows and role assignments are not removed automatically.
 * Delete options here if you add persistent settings that should be cleared on uninstall.
 *
 * @package KennelFlow_Groom
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
