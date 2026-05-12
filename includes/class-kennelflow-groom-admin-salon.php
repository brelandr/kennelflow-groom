<?php
/**
 * GroomPress Salon: top-level admin menu (commissions / earnings home).
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Admin_Salon
 */
class KennelFlow_Groom_Admin_Salon {

	/**
	 * Capability to see the Salon top-level menu (default: view commissions).
	 *
	 * @return string
	 */
	public static function required_cap() {
		$default = class_exists( 'KennelFlow_Groom_Activator' ) ? KennelFlow_Groom_Activator::CAP_VIEW_COMMISSIONS : 'groompress_view_commissions';
		return apply_filters( 'groompress_salon_menu_capability', $default );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 5 );
	}

	/**
	 * Register Salon before commissions submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		add_menu_page(
			__( 'GroomPress Salon', 'kennelflow-groom' ),
			__( 'GroomPress', 'kennelflow-groom' ),
			self::required_cap(),
			groompress_get_salon_menu_slug(),
			array( __CLASS__, 'render_page' ),
			'dashicons-scissors',
			57
		);
	}

	/**
	 * Landing screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::required_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kennelflow-groom' ) );
		}

		$earnings_url = admin_url( 'admin.php?page=' . KennelFlow_Groom_Admin_Earnings::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Grooming commissions and earnings. Use Grooming Schedule under KennelFlow for today’s calendar.', 'kennelflow-groom' ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $earnings_url ); ?>">
					<?php esc_html_e( 'Groomer Earnings', 'kennelflow-groom' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
