<?php
/**
 * Admin: Grooming Schedule screen (KennelFlow Core calendar bundle).
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Admin_Calendar
 */
class KennelFlow_Groom_Admin_Calendar {

	const PAGE_SLUG = 'groompress-schedule';

	/**
	 * Same handle as KennelFlow Core admin calendar (shared React bundle).
	 */
	const SCRIPT_HANDLE = 'kf-hub-admin-calendar';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_scripts' ) );
	}

	/**
	 * Capability for viewing the grooming calendar.
	 *
	 * @return string
	 */
	public static function required_cap() {
		return apply_filters( 'groompress_admin_calendar_capability', 'edit_posts' );
	}

	/**
	 * Submenu under KennelFlow pets (kf_pet).
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		$parent = function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=' . ltkf_get_pet_post_type();
		add_submenu_page(
			$parent,
			__( 'Grooming Schedule', 'kennelflow-groom' ),
			__( 'Grooming Schedule', 'kennelflow-groom' ),
			self::required_cap(),
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Enqueue KennelFlow Core calendar bundle on this screen only.
	 *
	 * @param string $hook_suffix Current admin page hook (preferred; matches `load-$hook` / `do_action( 'admin_enqueue_scripts', $hook )`).
	 * @return void
	 */
	public static function maybe_enqueue_scripts( $hook_suffix = '' ) {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) || ! defined( 'LTKF_PLUGIN_DIR' ) || ! defined( 'LTKF_PLUGIN_URL' ) || ! defined( 'LTKF_CORE_VERSION' ) ) {
			return;
		}

		$pt = ltkf_get_pet_post_type();
		// Hub submenu: `kennelflow-hub_page_groompress-schedule` (typical). Fall back if menu parent is the pet CPT.
		$expected_ids = array();
		if ( function_exists( 'ltkf_get_hub_page_hook_suffix' ) ) {
			$expected_ids[] = ltkf_get_hub_page_hook_suffix( self::PAGE_SLUG );
		}
		$expected_ids[] = $pt . '_page_' . self::PAGE_SLUG;
		$expected_ids   = array_unique( array_map( 'strval', $expected_ids ) );

		$current_hook = (string) $hook_suffix;
		$ok           = in_array( $current_hook, $expected_ids, true );
		if ( ! $ok && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && in_array( (string) $screen->id, $expected_ids, true ) ) {
				$ok = true;
			}
		}

		// Failsafes: some environments pass an unexpected $hook_suffix or a late screen; `admin.php?page=groompress-schedule` is reliable.
		if ( ! $ok && is_admin() && ! wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page_get = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( self::PAGE_SLUG === $page_get ) {
				$ok = true;
			} elseif ( '' !== (string) $hook_suffix && false !== strpos( (string) $hook_suffix, 'groompress-schedule' ) ) {
				$ok = true;
			}
		}

		/**
		 * Whether the current admin view is the Grooming Schedule screen (scripts should load).
		 *
		 * @since 0.2.0
		 *
		 * @param bool   $ok            Whether we matched hook / screen.
		 * @param string $hook_suffix   Hook passed to `admin_enqueue_scripts`.
		 * @param string $page_slug     This screen’s page slug.
		 * @param string[] $expected_ids Candidate screen / hook ids.
		 */
		$ok = (bool) apply_filters( 'kennelflow_groom_is_admin_calendar_screen', $ok, (string) $hook_suffix, self::PAGE_SLUG, $expected_ids );
		if ( ! $ok ) {
			return;
		}

		$index_js = LTKF_PLUGIN_DIR . 'build/index.js';
		if ( ! is_readable( $index_js ) ) {
			if ( is_admin() && function_exists( 'add_action' ) ) {
				$page_slug = self::PAGE_SLUG;
				add_action(
					'admin_notices',
					static function () use ( $page_slug ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						if ( ! isset( $_GET['page'] ) || $page_slug !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
							return;
						}
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}
						printf(
							'<div class="notice notice-error"><p>%s</p></div>',
							esc_html__( 'Grooming Schedule uses the KennelFlow Hub calendar bundle. The compiled files are missing. From the kennelflow-core plugin folder, run: npm install && npm run build (then upload the build/ folder: index.js, index.css, index.asset.php).', 'kennelflow-groom' )
						);
					}
				);
			}
			return;
		}

		$asset_file = LTKF_PLUGIN_DIR . 'build/index.asset.php';
		$asset      = array(
			'dependencies' => array(),
			'version'      => LTKF_CORE_VERSION,
		);
		if ( is_readable( $asset_file ) ) {
			$loaded = require $asset_file;
			if ( is_array( $loaded ) ) {
				$asset = array_merge( $asset, $loaded );
			}
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			LTKF_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$localized = function_exists( 'ltkf_get_calendar_localized_settings' )
			? ltkf_get_calendar_localized_settings()
			: array(
				'rest_url' => esc_url_raw( rest_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'kfCalendarSettings',
			$localized
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'kennelflow-core', LTKF_PLUGIN_DIR . 'languages' );

		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			LTKF_PLUGIN_URL . 'build/index.css',
			array(),
			$asset['version']
		);
	}

	/**
	 * Current UTC week (Mon–Sun) as Y-m-d for the React shell.
	 *
	 * @return array{0:string,1:string} start_date, end_date
	 */
	protected static function current_week_range_utc() {
		$utc = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$day_of_week = (int) $utc->format( 'N' );
		$week_start  = $utc->modify( '-' . ( $day_of_week - 1 ) . ' days' )->setTime( 0, 0, 0 );
		$week_end    = $week_start->modify( '+6 days' );

		return array( $week_start->format( 'Y-m-d' ), $week_end->format( 'Y-m-d' ) );
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::required_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kennelflow-groom' ) );
		}

		list( $start_date, $end_date ) = self::current_week_range_utc();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description"><?php esc_html_e( 'Grooming appointments by staff member. Times are stored in UTC. Pets use the KennelFlow Hub (kf_pet).', 'kennelflow-groom' ); ?></p>
			<div
				id="groompress-admin-calendar-root"
				class="groompress-admin-calendar-root"
				data-start-date="<?php echo esc_attr( $start_date ); ?>"
				data-end-date="<?php echo esc_attr( $end_date ); ?>"
				data-booking-kind="grooming"
				data-corner-label="<?php echo esc_attr( __( 'Groomer', 'kennelflow-groom' ) ); ?>"
			></div>
		</div>
		<?php
	}
}
