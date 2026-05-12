<?php
/**
 * Admin: earnings report by groomer and date range.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Admin_Earnings
 */
class KennelFlow_Groom_Admin_Earnings {

	const PAGE_SLUG = 'groompress-earnings';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Capability.
	 *
	 * @return string
	 */
	public static function required_cap() {
		$default = class_exists( 'KennelFlow_Groom_Activator' ) ? KennelFlow_Groom_Activator::CAP_VIEW_COMMISSIONS : 'groompress_view_commissions';
		return apply_filters( 'groompress_earnings_capability', $default );
	}

	/**
	 * Submenu under GroomPress Salon.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		if ( ! function_exists( 'groompress_get_salon_menu_slug' ) ) {
			return;
		}

		$parent = groompress_get_salon_menu_slug();
		add_submenu_page(
			$parent,
			__( 'Groomer Earnings', 'kennelflow-groom' ),
			__( 'Groomer Earnings', 'kennelflow-groom' ),
			self::required_cap(),
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Validate Y-m-d.
	 *
	 * @param string $ymd Date string.
	 * @return bool
	 */
	protected static function is_ymd( $ymd ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return false;
		}
		$ts = strtotime( $ymd . ' 00:00:00 UTC' );
		return false !== $ts && gmdate( 'Y-m-d', $ts ) === $ymd;
	}

	/**
	 * Aggregated rows for date range (UTC).
	 *
	 * @param string $start_gmt Inclusive Y-m-d H:i:s UTC.
	 * @param string $end_gmt   Inclusive end-of-day Y-m-d H:i:s UTC.
	 * @return array<int, array{staff_user_id:int,pending_total:float,paid_total:float,all_total:float}>
	 */
	protected static function query_aggregates( $start_gmt, $end_gmt ) {
		global $wpdb;

		$table = KennelFlow_Groom_Install::commissions_table_name();
		if ( ! KennelFlow_Groom_Install::commissions_table_exists() ) {
			return array();
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Aggregated report; table from helper.
		$sql = $wpdb->prepare(
			"
			SELECT
				staff_user_id,
				SUM( CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END ) AS pending_total,
				SUM( CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END ) AS paid_total,
				SUM( commission_amount ) AS all_total
			FROM `{$table}`
			WHERE created_gmt >= %s
			AND created_gmt <= %s
			GROUP BY staff_user_id
			ORDER BY staff_user_id ASC
			",
			$start_gmt,
			$end_gmt
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$uid   = isset( $row['staff_user_id'] ) ? absint( $row['staff_user_id'] ) : 0;
			$out[] = array(
				'staff_user_id' => $uid,
				'pending_total' => isset( $row['pending_total'] ) ? (float) $row['pending_total'] : 0.0,
				'paid_total'    => isset( $row['paid_total'] ) ? (float) $row['paid_total'] : 0.0,
				'all_total'     => isset( $row['all_total'] ) ? (float) $row['all_total'] : 0.0,
			);
		}

		return $out;
	}

	/**
	 * Format money for display.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	protected static function format_money( $amount ) {
		$amount = (float) $amount;
		if ( function_exists( 'wc_price' ) ) {
			return wp_kses_post( wc_price( $amount ) );
		}
		return esc_html( number_format_i18n( $amount, 2 ) );
	}

	/**
	 * Render report page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::required_cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kennelflow-groom' ) );
		}

		$utc_now       = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$default_end   = $utc_now->format( 'Y-m-d' );
		$default_start = $utc_now->modify( '-30 days' )->format( 'Y-m-d' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter form.
		$start_in = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $default_start;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$end_in = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $default_end;

		if ( ! self::is_ymd( $start_in ) ) {
			$start_in = $default_start;
		}
		if ( ! self::is_ymd( $end_in ) ) {
			$end_in = $default_end;
		}

		if ( strcmp( $end_in, $start_in ) < 0 ) {
			$tmp      = $start_in;
			$start_in = $end_in;
			$end_in   = $tmp;
		}

		$start_gmt = $start_in . ' 00:00:00';
		$end_gmt   = $end_in . ' 23:59:59';

		$rows = self::query_aggregates( $start_gmt, $end_gmt );

		$sum_pending = 0.0;
		$sum_paid    = 0.0;
		$sum_all     = 0.0;
		foreach ( $rows as $r ) {
			$sum_pending += $r['pending_total'];
			$sum_paid    += $r['paid_total'];
			$sum_all     += $r['all_total'];
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only payout redirect query args.
			if ( isset( $_GET['payout_success'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['payout_success'] ) ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'Groomer commissions successfully marked as paid.', 'kennelflow-groom' )
				);
			}

			if ( isset( $_GET['payout_error'] ) ) {
				$err = sanitize_key( wp_unslash( $_GET['payout_error'] ) );
				$msg = '';
				if ( 'invalid_groomer' === $err ) {
					$msg = __( 'Invalid groomer. No changes were saved.', 'kennelflow-groom' );
				} elseif ( 'no_table' === $err ) {
					$msg = __( 'The commissions table is not available.', 'kennelflow-groom' );
				}
				if ( '' !== $msg ) {
					printf(
						'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
						esc_html( $msg )
					);
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			?>

			<p class="description">
				<?php esc_html_e( 'Commission totals from the ledger (UTC dates), grouped by staff member.', 'kennelflow-groom' ); ?>
			</p>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="groompress-earnings-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<p>
					<label for="groompress-earnings-start"><?php esc_html_e( 'Start date (UTC)', 'kennelflow-groom' ); ?></label>
					<input type="date" id="groompress-earnings-start" name="start_date" value="<?php echo esc_attr( $start_in ); ?>" />
					<label for="groompress-earnings-end"><?php esc_html_e( 'End date (UTC)', 'kennelflow-groom' ); ?></label>
					<input type="date" id="groompress-earnings-end" name="end_date" value="<?php echo esc_attr( $end_in ); ?>" />
					<?php submit_button( __( 'Apply', 'kennelflow-groom' ), 'secondary', '', false ); ?>
				</p>
			</form>

			<?php if ( ! KennelFlow_Groom_Install::commissions_table_exists() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'The commissions table is not installed yet. Reactivate GroomPress or check the database.', 'kennelflow-groom' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Groomer', 'kennelflow-groom' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Pending (owed)', 'kennelflow-groom' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Paid', 'kennelflow-groom' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total', 'kennelflow-groom' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'No commission rows in this range.', 'kennelflow-groom' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $rows as $r ) : ?>
								<?php
								$user = get_userdata( $r['staff_user_id'] );
								$name = $user ? $user->display_name : sprintf(
									/* translators: %d: user id */
									__( 'User #%d', 'kennelflow-groom' ),
									$r['staff_user_id']
								);
								?>
								<tr>
									<td><?php echo esc_html( $name ); ?></td>
									<td>
										<?php
										$pending_amt = isset( $r['pending_total'] ) ? (float) $r['pending_total'] : 0.0;
										echo '<span class="groompress-pending-amount">' . wp_kses_post( self::format_money( $pending_amt ) ) . '</span>';
										if ( $pending_amt > 0 && current_user_can( 'manage_options' ) ) {
											$confirm_payout = __(
												'Are you sure you want to mark all pending commissions for this groomer as paid? This cannot be undone.',
												'kennelflow-groom'
											);
											?>
											<form
												class="groompress-mark-paid-form"
												method="post"
												action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
												style="display:inline-block;margin-left:8px;vertical-align:middle;"
												onsubmit="return confirm( <?php echo wp_json_encode( $confirm_payout ); ?> );"
											>
												<?php wp_nonce_field( KennelFlow_Groom_Commissions::NONCE_MARK_PAID ); ?>
												<input type="hidden" name="action" value="groompress_mark_paid" />
												<input type="hidden" name="groomer_id" value="<?php echo esc_attr( (string) $r['staff_user_id'] ); ?>" />
												<?php
												submit_button(
													__( 'Mark All as Paid', 'kennelflow-groom' ),
													'small secondary',
													'groompress_mark_paid_submit',
													false,
													array(
														'style' => 'vertical-align:middle;',
													)
												);
												?>
											</form>
											<?php
										}
										?>
									</td>
									<td><?php echo wp_kses_post( self::format_money( $r['paid_total'] ) ); ?></td>
									<td><?php echo wp_kses_post( self::format_money( $r['all_total'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<?php if ( ! empty( $rows ) ) : ?>
						<tfoot>
							<tr>
								<th scope="row"><?php esc_html_e( 'Totals', 'kennelflow-groom' ); ?></th>
								<th><?php echo wp_kses_post( self::format_money( $sum_pending ) ); ?></th>
								<th><?php echo wp_kses_post( self::format_money( $sum_paid ) ); ?></th>
								<th><?php echo wp_kses_post( self::format_money( $sum_all ) ); ?></th>
							</tr>
						</tfoot>
					<?php endif; ?>
				</table>
				<p class="description">
					<?php
					printf(
						/* translators: 1: start Y-m-d, 2: end Y-m-d */
						esc_html__( 'Range: %1$s–%2$s UTC.', 'kennelflow-groom' ),
						esc_html( $start_in ),
						esc_html( $end_in )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
