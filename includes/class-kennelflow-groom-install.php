<?php
/**
 * Database: commissions ledger table.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Install
 */
class KennelFlow_Groom_Install {

	const DB_VERSION_OPTION = 'groompress_db_version';

	const SCHEMA_VERSION = '2';

	/**
	 * Table name including prefix.
	 *
	 * @return string
	 */
	public static function commissions_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ltkf_commissions';
	}

	/**
	 * Create or upgrade tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::commissions_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			staff_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
			gross_amount decimal(12,4) NOT NULL DEFAULT 0,
			commission_amount decimal(12,4) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			meta_json longtext NULL,
			created_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_booking (order_id, booking_id),
			KEY staff_user_id (staff_user_id),
			KEY status_created (status, created_gmt)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Run install when schema version bumps.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		self::ensure_commissions_meta_json_column();

		$current = get_option( self::DB_VERSION_OPTION, '' );
		if ( self::SCHEMA_VERSION === $current ) {
			return;
		}

		self::install();
		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Add meta_json to existing installs (demo seed + purge markers).
	 *
	 * @return void
	 */
	public static function ensure_commissions_meta_json_column() {
		if ( ! function_exists( 'ltkf_table_exists' ) || ! function_exists( 'ltkf_db_column_exists' ) ) {
			return;
		}

		$table = self::commissions_table_name();
		if ( ! ltkf_table_exists( $table ) ) {
			return;
		}
		if ( ltkf_db_column_exists( $table, 'meta_json' ) ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL; table from helper.
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `meta_json` longtext NULL AFTER `status`" );
	}

	/**
	 * Whether the commissions table exists.
	 *
	 * @return bool
	 */
	public static function commissions_table_exists() {
		global $wpdb;

		if ( ! function_exists( 'ltkf_table_exists' ) ) {
			return false;
		}

		return ltkf_table_exists( self::commissions_table_name() );
	}
}
