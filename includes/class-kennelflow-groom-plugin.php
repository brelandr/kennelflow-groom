<?php
/**
 * Main plugin loader.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-activator.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-install.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-admin-calendar.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-calendar-access.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-vet-access.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-admin-settings.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-admin-earnings.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-admin-salon.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-commissions.php';
require_once KENNELFLOW_GROOM_PLUGIN_DIR . 'includes/class-kennelflow-groom-completion-sms.php';

/**
 * Class KennelFlow_Groom_Plugin
 */
class KennelFlow_Groom_Plugin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function init() {
		KennelFlow_Groom_Install::maybe_upgrade();
		KennelFlow_Groom_Activator::ensure_role();
		KennelFlow_Groom_Calendar_Access::init();
		KennelFlow_Groom_Calendar_Access::register_caps();
		KennelFlow_Groom_Admin_Salon::init();
		KennelFlow_Groom_Admin_Calendar::init();
		KennelFlow_Groom_Admin_Settings::init();
		KennelFlow_Groom_Admin_Earnings::init();
		KennelFlow_Groom_Commissions::init();
		KennelFlow_Groom_Completion_Sms::init();
		if ( kennelflow_groom_vet_emr_active() ) {
			KennelFlow_Groom_Vet_Access::init();
		}
		add_filter( 'ltkf_rest_calendar_resources', array( $this, 'filter_calendar_resources' ), 10, 3 );
	}

	/**
	 * Y-axis rows: WordPress users with the Groomer role (plus any resource_id from bookings).
	 *
	 * @param null|array[]    $resources Prior value.
	 * @param array[]         $bookings  Normalized calendar bookings.
	 * @param WP_REST_Request $request   Request.
	 * @return null|array[]
	 */
	public function filter_calendar_resources( $resources, $bookings, $request ) {
		if ( 'grooming' !== sanitize_key( (string) $request->get_param( 'booking_kind' ) ) ) {
			return $resources;
		}

		$by_id = array();

		$user_query = new WP_User_Query(
			array(
				'role'    => KennelFlow_Groom_Activator::ROLE,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => 'all',
			)
		);

		$users = $user_query->get_results();
		if ( is_array( $users ) ) {
			foreach ( $users as $u ) {
				if ( ! $u instanceof WP_User ) {
					continue;
				}
				$uid           = (int) $u->ID;
				$by_id[ $uid ] = array(
					'id'    => $uid,
					'title' => $u->display_name,
				);
			}
		}

		foreach ( (array) $bookings as $b ) {
			$rid = isset( $b['resource_id'] ) ? (int) $b['resource_id'] : 0;
			if ( $rid < 1 ) {
				continue;
			}
			if ( isset( $by_id[ $rid ] ) ) {
				continue;
			}
			$u             = get_userdata( $rid );
			$by_id[ $rid ] = array(
				'id'    => $rid,
				'title' => $u ? $u->display_name : sprintf(
					/* translators: %d: WordPress user id */
					__( 'User %d', 'kennelflow-groom' ),
					$rid
				),
			);
		}

		$needs_unassigned = false;
		foreach ( (array) $bookings as $b ) {
			if ( empty( $b['resource_id'] ) ) {
				$needs_unassigned = true;
				break;
			}
		}
		if ( $needs_unassigned ) {
			$by_id[0] = array(
				'id'    => 0,
				'title' => __( 'Unassigned', 'kennelflow-groom' ),
			);
		}

		$out = array_values( $by_id );
		if ( empty( $out ) ) {
			$out[] = array(
				'id'    => 0,
				'title' => __( 'Unassigned', 'kennelflow-groom' ),
			);
		}

		return $out;
	}
}
