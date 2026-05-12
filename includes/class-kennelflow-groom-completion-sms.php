<?php
/**
 * SMS when a grooming booking is marked completed (KennelPress status + KennelFlow Twilio).
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Completion_Sms
 */
class KennelFlow_Groom_Completion_Sms {

	/**
	 * Prior booking status captured before update_post_meta runs (per post ID).
	 *
	 * @var array<int,string>
	 */
	protected static $prev_booking_status = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'KennelPress_Post_Meta' ) ) {
			return;
		}

		add_filter( 'update_post_metadata', array( __CLASS__, 'capture_prev_booking_status' ), 10, 5 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_booking_status_updated' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_booking_status_added' ), 10, 4 );
	}

	/**
	 * Remember previous status so updated_post_meta can detect transitions.
	 *
	 * @param null|bool $check      Pass-through.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value New value (unused).
	 * @param mixed     $prev_value Previous value.
	 * @return null|bool
	 */
	public static function capture_prev_booking_status( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		unset( $meta_value );

		if ( KennelPress_Post_Meta::BOOKING_STATUS !== $meta_key ) {
			return $check;
		}

		$object_id = (int) $object_id;
		if ( $object_id < 1 || 'kennelpress_booking' !== get_post_type( $object_id ) ) {
			return $check;
		}

		self::$prev_booking_status[ $object_id ] = (string) $prev_value;

		return $check;
	}

	/**
	 * Fires after status meta is updated.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value New value.
	 * @return void
	 */
	public static function on_booking_status_updated( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );

		if ( KennelPress_Post_Meta::BOOKING_STATUS !== $meta_key ) {
			return;
		}

		$post_id = (int) $object_id;
		if ( $post_id < 1 ) {
			return;
		}

		$new = sanitize_key( (string) $meta_value );
		if ( 'completed' !== $new ) {
			return;
		}

		$old = '';
		if ( isset( self::$prev_booking_status[ $post_id ] ) ) {
			$old = (string) self::$prev_booking_status[ $post_id ];
			unset( self::$prev_booking_status[ $post_id ] );
		}
		$old = sanitize_key( $old );
		if ( 'completed' === $old ) {
			return;
		}

		self::maybe_send_pickup_sms( $post_id );
	}

	/**
	 * First-time status meta (e.g. new booking already completed).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Key.
	 * @param mixed  $meta_value New value.
	 * @return void
	 */
	public static function on_booking_status_added( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id );

		if ( KennelPress_Post_Meta::BOOKING_STATUS !== $meta_key ) {
			return;
		}

		if ( 'completed' !== sanitize_key( (string) $meta_value ) ) {
			return;
		}

		$post_id = (int) $object_id;
		if ( $post_id < 1 ) {
			return;
		}

		self::maybe_send_pickup_sms( $post_id );
	}

	/**
	 * Send Twilio SMS to the pet owner when grooming is marked completed.
	 *
	 * @param int $booking_post_id kennelpress_booking post ID.
	 * @return void
	 */
	protected static function maybe_send_pickup_sms( $booking_post_id ) {
		if ( ! class_exists( \Landtech\KennelFlow\Core\TwilioService::class ) || ! function_exists( 'ltkf_get_pet_owner_user_id' ) || ! function_exists( 'ltkf_get_user_phone_for_sms' ) ) {
			return;
		}

		$booking_post_id = absint( $booking_post_id );
		if ( $booking_post_id < 1 || 'kennelpress_booking' !== get_post_type( $booking_post_id ) ) {
			return;
		}

		$kind = (string) get_post_meta( $booking_post_id, KennelPress_Post_Meta::BOOKING_KIND, true );
		if ( 'grooming' !== sanitize_key( $kind ) ) {
			return;
		}

		$pet_id = (int) get_post_meta( $booking_post_id, KennelPress_Post_Meta::BOOKING_PET_ID, true );
		if ( $pet_id < 1 ) {
			return;
		}

		$owner_id = ltkf_get_pet_owner_user_id( $pet_id );
		if ( $owner_id < 1 ) {
			return;
		}

		$phone = ltkf_get_user_phone_for_sms( $owner_id );
		if ( '' === $phone ) {
			return;
		}

		$pet_name = get_the_title( $pet_id );
		if ( '' === $pet_name ) {
			$pet_name = __( 'your pet', 'kennelflow-groom' );
		}

		\Landtech\KennelFlow\Core\TwilioService::send_sms(
			$phone,
			'Great news! ' . $pet_name . ' is all finished with their grooming and ready for pickup.'
		);
	}
}
