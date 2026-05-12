<?php
/**
 * Commission ledger: WooCommerce order completed → grooming line items.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Commissions
 */
class KennelFlow_Groom_Commissions {

	/**
	 * Order item meta key for booking post id (KennelFlow Core).
	 */
	const ORDER_ITEM_META_BOOKING_ID = 'kf_booking_id';

	/**
	 * Nonce action for marking pending commissions paid (admin-post).
	 */
	const NONCE_MARK_PAID = 'groompress_mark_paid';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_groompress_mark_paid', array( __CLASS__, 'handle_mark_paid' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_completed' ), 10, 2 );
		}
	}

	/**
	 * Admin-post: set all pending commission rows for a groomer to paid.
	 *
	 * @return void
	 */
	public static function handle_mark_paid() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_MARK_PAID ) ) {
			wp_die(
				esc_html__( 'Invalid security token.', 'kennelflow-groom' ),
				esc_html__( 'Error', 'kennelflow-groom' ),
				array( 'response' => 403 )
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to do that.', 'kennelflow-groom' ),
				esc_html__( 'Error', 'kennelflow-groom' ),
				array( 'response' => 403 )
			);
		}

		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			wp_die(
				esc_html__( 'KennelFlow is not available.', 'kennelflow-groom' ),
				esc_html__( 'Error', 'kennelflow-groom' ),
				array( 'response' => 503 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$groomer_id = isset( $_POST['groomer_id'] ) ? absint( wp_unslash( $_POST['groomer_id'] ) ) : 0;

		$redirect_base = admin_url( 'admin.php?page=' . KennelFlow_Groom_Admin_Earnings::PAGE_SLUG );

		if ( $groomer_id < 1 ) {
			wp_safe_redirect(
				add_query_arg( 'payout_error', 'invalid_groomer', $redirect_base )
			);
			exit;
		}

		if ( ! KennelFlow_Groom_Install::commissions_table_exists() ) {
			wp_safe_redirect(
				add_query_arg( 'payout_error', 'no_table', $redirect_base )
			);
			exit;
		}

		global $wpdb;

		$table = KennelFlow_Groom_Install::commissions_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Bulk payout update; table from helper; values sanitized.
		$wpdb->update(
			$table,
			array( 'status' => 'paid' ),
			array(
				'staff_user_id' => $groomer_id,
				'status'        => 'pending',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		wp_safe_redirect(
			add_query_arg( 'payout_success', '1', $redirect_base )
		);
		exit;
	}

	/**
	 * Default commission percentage (0–100).
	 *
	 * @return float
	 */
	public static function get_default_commission_percent() {
		$raw = get_option( 'groompress_commission_percent_default', 50 );
		$pct = is_numeric( $raw ) ? (float) $raw : 50.0;
		if ( $pct < 0 ) {
			$pct = 0.0;
		}
		if ( $pct > 100 ) {
			$pct = 100.0;
		}
		return $pct;
	}

	/**
	 * Whether the product is in the Grooming product category.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function product_is_grooming_category( $product_id ) {
		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return false;
		}

		if ( class_exists( \Landtech\KennelFlow\Core\Woocommerce::class ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && (string) $product->get_meta( \Landtech\KennelFlow\Core\Woocommerce::PRODUCT_META_SERVICE_KEY, true ) === \Landtech\KennelFlow\Core\Woocommerce::KEY_GROOMING ) {
				return true;
			}
		}

		$slug = apply_filters( 'groompress_grooming_product_category_slug', 'grooming' );
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return false;
		}

		return has_term( $slug, 'product_cat', $product_id );
	}

	/**
	 * Load kf_bookings row for a booking post ID.
	 *
	 * @param int $booking_post_id Booking post ID.
	 * @return object|null
	 */
	protected static function get_booking_index_row( $booking_post_id ) {
		global $wpdb;

		if ( ! function_exists( 'ltkf_bookings_table_name' ) || ! function_exists( 'ltkf_table_exists' ) ) {
			return null;
		}

		$table = ltkf_bookings_table_name();
		if ( ! ltkf_table_exists( $table ) ) {
			return null;
		}

		$booking_post_id = absint( $booking_post_id );
		if ( $booking_post_id < 1 ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Single-row lookup; table from KennelFlow helper.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, post_id, booking_kind, kennel_id FROM `{$table}` WHERE post_id = %d LIMIT 1",
				$booking_post_id
			)
		);

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Order reached completed status.
	 *
	 * @param int   $order_id Order ID.
	 * @param mixed $order    Optional order object.
	 * @return void
	 */
	public static function on_order_completed( $order_id, $order = null ) {
		unset( $order );

		if ( ! KennelFlow_Groom_Install::commissions_table_exists() ) {
			return;
		}

		$order_id = absint( $order_id );
		if ( $order_id < 1 ) {
			return;
		}

		$wc_order = wc_get_order( $order_id );
		if ( ! $wc_order instanceof WC_Order ) {
			return;
		}

		$pct = self::get_default_commission_percent();
		if ( $pct <= 0 ) {
			return;
		}

		/**
		 * Aggregate gross per kf_bookings.id so multiple grooming lines for one booking
		 * become a single ledger row (unique order_id + booking_id).
		 *
		 * @var array<int, array{staff:int, gross:float}>
		 */
		$per_booking = array();

		foreach ( $wc_order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product_id = absint( $item->get_product_id() );
			if ( ! self::product_is_grooming_category( $product_id ) ) {
				continue;
			}

			$booking_post_id = absint( $item->get_meta( self::ORDER_ITEM_META_BOOKING_ID, true ) );
			if ( $booking_post_id < 1 ) {
				continue;
			}

			$row = self::get_booking_index_row( $booking_post_id );
			if ( ! $row || ! isset( $row->id ) ) {
				continue;
			}

			$kind = isset( $row->booking_kind ) ? sanitize_key( (string) $row->booking_kind ) : '';
			if ( 'grooming' !== $kind ) {
				continue;
			}

			$staff_id = isset( $row->kennel_id ) ? absint( $row->kennel_id ) : 0;
			if ( $staff_id < 1 ) {
				continue;
			}

			$booking_row_id = absint( $row->id );

			$line_gross = (float) wc_format_decimal( (float) $item->get_total() + (float) $item->get_total_tax() );
			if ( $line_gross <= 0 ) {
				continue;
			}

			if ( ! isset( $per_booking[ $booking_row_id ] ) ) {
				$per_booking[ $booking_row_id ] = array(
					'staff' => $staff_id,
					'gross' => 0.0,
				);
			}

			$per_booking[ $booking_row_id ]['gross'] += $line_gross;
		}

		foreach ( $per_booking as $booking_row_id => $agg ) {
			$gross = (float) wc_format_decimal( $agg['gross'] );
			if ( $gross <= 0 ) {
				continue;
			}
			$commission = (float) wc_format_decimal( $gross * $pct / 100.0 );
			self::insert_commission_row( $order_id, $booking_row_id, $agg['staff'], $gross, $commission );
		}
	}

	/**
	 * Insert a pending commission row if not already present.
	 *
	 * @param int   $order_id         WooCommerce order ID.
	 * @param int   $booking_row_id   kf_bookings.id.
	 * @param int   $staff_user_id    Groomer user ID.
	 * @param float $gross_amount     Line gross.
	 * @param float $commission_amount Commission owed.
	 * @return void
	 */
	protected static function insert_commission_row( $order_id, $booking_row_id, $staff_user_id, $gross_amount, $commission_amount ) {
		global $wpdb;

		$table = KennelFlow_Groom_Install::commissions_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Idempotency check; table from helper.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE order_id = %d AND booking_id = %d LIMIT 1",
				$order_id,
				$booking_row_id
			)
		);

		if ( $exists ) {
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Ledger insert.
		$wpdb->insert(
			$table,
			array(
				'staff_user_id'     => $staff_user_id,
				'order_id'          => $order_id,
				'booking_id'        => $booking_row_id,
				'gross_amount'      => $gross_amount,
				'commission_amount' => $commission_amount,
				'status'            => 'pending',
				'created_gmt'       => $now,
			),
			array( '%d', '%d', '%d', '%f', '%f', '%s', '%s' )
		);
	}
}
