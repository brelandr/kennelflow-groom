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

global $wpdb;

// Remove plugin options.
delete_option( 'groompress_db_version' );
delete_option( 'groompress_commission_percent_default' );
delete_option( 'groompress_allow_full_medical_access' );

// Drop custom table.
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ltkf_commissions`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is an internal constant, not user input.
