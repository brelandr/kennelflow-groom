<?php
/**
 * Activation: Groomer role.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Activator
 */
class KennelFlow_Groom_Activator {

	const ROLE = 'groomer';

	/**
	 * View GroomPress commissions / Salon menu (groomers and admins).
	 */
	const CAP_VIEW_COMMISSIONS = 'groompress_view_commissions';

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( function_exists( 'groompress_load_textdomain' ) ) {
			groompress_load_textdomain();
		}

		self::ensure_role();
		require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-install.php';
		KennelFlow_Groom_Install::install();
	}

	/**
	 * Plugin deactivation (role is left registered so existing users keep the role).
	 *
	 * @return void
	 */
	public static function deactivate() {
	}

	/**
	 * Register or update the Groomer role.
	 *
	 * @return void
	 */
	public static function ensure_role() {
		$display = __( 'Groomer', 'kennelflow-groom' );

		$caps = array(
			'read'                     => true,
			'edit_posts'               => true,
			self::CAP_VIEW_COMMISSIONS => true,
		);

		$role = get_role( self::ROLE );
		if ( $role ) {
			foreach ( $caps as $cap => $grant ) {
				$role->add_cap( $cap, (bool) $grant );
			}
		} else {
			add_role( self::ROLE, $display, $caps );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_VIEW_COMMISSIONS );
		}
	}
}
