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
	 * View KennelFlow Hub / grooming calendar (front-end staff calendar and REST reads).
	 */
	const CAP_VIEW_CALENDAR = 'groompress_view_calendar';

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
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
			'read_private_posts'       => true,
			self::CAP_VIEW_COMMISSIONS => true,
			self::CAP_VIEW_CALENDAR    => true,
		);

		if ( class_exists( 'KennelFlow_Groom_Calendar_Access' ) ) {
			$caps[ KennelFlow_Groom_Calendar_Access::CAP_CREATE_BOOKINGS ] = true;
			$caps[ KennelFlow_Groom_Calendar_Access::CAP_EDIT_HUB_PETS ]   = true;
		}

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
			$admin->add_cap( self::CAP_VIEW_CALENDAR );
			if ( class_exists( 'KennelFlow_Groom_Calendar_Access' ) ) {
				$admin->add_cap( KennelFlow_Groom_Calendar_Access::CAP_EDIT_HUB_PETS );
			}
		}

		if ( class_exists( 'KennelFlow_Groom_Calendar_Access' ) ) {
			KennelFlow_Groom_Calendar_Access::register_caps();
		}
	}
}
