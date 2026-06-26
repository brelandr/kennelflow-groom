<?php
/**
 * GroomPress settings: default commission percentage.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Admin_Settings
 */
class KennelFlow_Groom_Admin_Settings {

	const PAGE_SLUG = 'groompress-settings';

	const OPTION_PERCENT = 'groompress_commission_percent_default';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 15 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Capability.
	 *
	 * @return string
	 */
	public static function required_cap() {
		return apply_filters( 'groompress_settings_capability', 'manage_options' );
	}

	/**
	 * Submenu under KennelFlow pets.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		$parents = array();
		if ( function_exists( 'groompress_get_salon_menu_slug' ) ) {
			$parents[] = groompress_get_salon_menu_slug();
		}
		if ( function_exists( 'ltkf_get_hub_menu_slug' ) ) {
			$parents[] = ltkf_get_hub_menu_slug();
		}
		if ( empty( $parents ) ) {
			$parents[] = 'edit.php?post_type=' . ltkf_get_pet_post_type();
		}
		$parents = array_unique( array_map( 'strval', $parents ) );

		foreach ( $parents as $parent ) {
			add_submenu_page(
				$parent,
				__( 'GroomPress Settings', 'kennelflow-groom' ),
				__( 'GroomPress Settings', 'kennelflow-groom' ),
				self::required_cap(),
				self::PAGE_SLUG,
				array( __CLASS__, 'render_page' )
			);
		}
	}

	/**
	 * Register option.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'groompress_settings',
			self::OPTION_PERCENT,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_percent' ),
				'default'           => '50',
			)
		);

		register_setting(
			'groompress_settings',
			KennelFlow_Groom_Vet_Access::OPTION_FULL_MEDICAL,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitize checkbox option.
	 *
	 * @param mixed $value Raw.
	 * @return bool
	 */
	public static function sanitize_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			$s = strtolower( trim( $value ) );
			if ( in_array( $s, array( '0', 'false', 'no', 'off', '' ), true ) ) {
				return false;
			}
			return in_array( $s, array( '1', 'true', 'yes', 'on' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Sanitize commission percent 0–100.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_percent( $value ) {
		if ( null === $value || is_array( $value ) ) {
			return '50';
		}
		$n = (float) round( (float) $value, 2 );
		if ( $n < 0 ) {
			$n = 0.0;
		}
		if ( $n > 100 ) {
			$n = 100.0;
		}
		return (string) $n;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::required_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kennelflow-groom' ) );
		}

		$has_wc  = class_exists( 'WooCommerce' );
		$has_vet = kennelflow_groom_vet_emr_active();

		$current_percent = get_option( self::OPTION_PERCENT, '50' );
		$full_medical    = KennelFlow_Groom_Vet_Access::allow_full_medical_access();

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php settings_fields( 'groompress_settings' ); ?>
				<table class="form-table" role="presentation">
					<?php if ( $has_vet ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Groomer medical access', 'kennelflow-groom' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( KennelFlow_Groom_Vet_Access::OPTION_FULL_MEDICAL ); ?>" value="0" />
								<label for="groompress_allow_full_medical_access">
									<input
										type="checkbox"
										name="<?php echo esc_attr( KennelFlow_Groom_Vet_Access::OPTION_FULL_MEDICAL ); ?>"
										id="groompress_allow_full_medical_access"
										value="1"
										<?php checked( $full_medical ); ?>
									/>
									<?php esc_html_e( 'Allow groomers to view full KennelFlow Vet medical records (EMR, SOAP, clinical data).', 'kennelflow-groom' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When unchecked, groomers only see the Grooming log (grooming-specific notes) on pet profiles and cannot access the rest of the EMR — unless a role is granted access below or under KennelFlow → Staff Permissions.', 'kennelflow-groom' ); ?>
								</p>
								<p class="description">
									<?php
									$sp_url   = admin_url( 'admin.php?page=kf-staff-permissions' );
									$sp_link  = '<a href="' . esc_url( $sp_url ) . '">' . esc_html__( 'KennelFlow → Staff Permissions', 'kennelflow-groom' ) . '</a>';
									$sp_blurb = sprintf(
										/* translators: %s: link to the Staff Permissions screen. */
										__( 'To grant only some roles full EMR (recommended), use %s. Enable “Groomer medical access” and/or “Edit Medical Records (EMR)” for the Groomer (or other) role. The checkbox above applies the same access to all users with the Groomer role at once.', 'kennelflow-groom' ),
										$sp_link
									);
									echo wp_kses(
										$sp_blurb,
										array(
											'a' => array( 'href' => array() ),
										)
									);
									?>
								</p>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ( $has_wc ) : ?>
						<tr>
							<th scope="row">
								<label for="groompress_commission_percent_default"><?php esc_html_e( 'Default Commission Percentage', 'kennelflow-groom' ); ?></label>
							</th>
							<td>
								<p class="description">
									<?php esc_html_e( 'When an order is completed, grooming line items (products in the Grooming product category) create commission rows for the groomer assigned on the booking.', 'kennelflow-groom' ); ?>
								</p>
								<input
									name="<?php echo esc_attr( self::OPTION_PERCENT ); ?>"
									id="groompress_commission_percent_default"
									type="number"
									step="0.01"
									min="0"
									max="100"
									class="small-text"
									value="<?php echo esc_attr( (string) $current_percent ); ?>"
								/>
								<p class="description">
									<?php esc_html_e( 'Applied to the gross line amount (including tax) for qualifying grooming products.', 'kennelflow-groom' ); ?>
								</p>
								<p class="description">
									<?php
									printf(
										/* translators: %s: product category slug */
										esc_html__( 'Map grooming services to WooCommerce products in the product category with slug %s (or change via the groompress_grooming_product_category_slug filter).', 'kennelflow-groom' ),
										'<code>grooming</code>'
									);
									?>
								</p>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<?php if ( ! $has_vet && ! $has_wc ) : ?>
					<p class="description">
						<?php esc_html_e( 'Install KennelFlow Vet for groomer EMR access options and WooCommerce for commission settings.', 'kennelflow-groom' ); ?>
					</p>
				<?php endif; ?>

				<?php if ( $has_vet || $has_wc ) : ?>
					<?php submit_button(); ?>
				<?php endif; ?>
			</form>

			<?php if ( ! $has_wc ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'WooCommerce is required for commission recording and grooming product categories.', 'kennelflow-groom' ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( ! $has_vet ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'KennelFlow Vet is required for groomer medical access controls.', 'kennelflow-groom' ); ?>
				</p></div>
			<?php endif; ?>
		</div>
		<?php
	}
}
