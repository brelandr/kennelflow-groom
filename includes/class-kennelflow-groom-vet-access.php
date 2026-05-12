<?php
/**
 * Groomer access to KennelFlow Vet EMR vs grooming-only notes.
 *
 * KennelFlow Vet resolves read_post/edit_post via map_meta_cap() to primitives such as kennelflow_vet_read_emr;
 * those primitives are granted or denied here using user_has_cap (see KennelFlow_Vet_Capabilities::map_meta_cap).
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Vet_Access
 */
class KennelFlow_Groom_Vet_Access {

	const OPTION_FULL_MEDICAL = 'groompress_allow_full_medical_access';

	/**
	 * Staff Permissions matrix: grant full EMR/SOAP to groomers for this role (KennelFlow → Staff Permissions).
	 * Distinct from {@see KennelFlow_Vet_Capabilities::CAP_EDIT_EMR} (matrix row "Edit Medical Records (EMR)") so the UI can
	 * name groomer access explicitly; either matrix cap enables full access for the groomer role.
	 */
	const CAP_STAFF_FULL_MEDICAL = 'groompress_full_vet_emr';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'KennelFlow_Vet_Capabilities' ) ) {
			return;
		}

		add_filter( 'ltkf_permissions_managed_capabilities', array( __CLASS__, 'register_staff_permissions_matrix_row' ) );
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_user_has_cap' ), 20, 4 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'filter_map_meta_cap' ), 15, 4 );
	}

	/**
	 * Add "Groomer medical access" to KennelFlow → Staff Permissions (per role).
	 *
	 * @param array<string,string> $defs Capability slug => label.
	 * @return array<string,string>
	 */
	public static function register_staff_permissions_matrix_row( $defs ) {
		if ( ! is_array( $defs ) || ! kennelflow_groom_vet_emr_active() ) {
			return $defs;
		}

		$defs[ self::CAP_STAFF_FULL_MEDICAL ] = __(
			'Groomer medical access (full KennelFlow Vet medical records, EMR, SOAP, clinical data)',
			'kennelflow-groom'
		);

		return $defs;
	}

	/**
	 * Whether the user has the Groomer role (avoid recursive role checks via cap).
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	protected static function user_is_groomer( $user ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		return in_array( KennelFlow_Groom_Activator::ROLE, (array) $user->roles, true );
	}

	/**
	 * Site option: groomers may use full KennelFlow Vet EMR (read SOAP, vaccinations, etc.).
	 *
	 * @return bool
	 */
	public static function allow_full_medical_access() {
		return (bool) get_option( self::OPTION_FULL_MEDICAL, false );
	}

	/**
	 * Adjust KennelFlow Vet primitive capabilities for groomers.
	 * KennelFlow_Vet_Capabilities::map_meta_cap() resolves read_post/edit_post to these primitives.
	 *
	 * @param bool[]   $allcaps All capabilities for user.
	 * @param string[] $caps    Requested caps.
	 * @param array    $args    Extra context.
	 * @param WP_User  $user    User object.
	 * @return bool[]
	 */
	public static function filter_user_has_cap( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args );

		if ( ! is_array( $allcaps ) || ! $user instanceof WP_User ) {
			return $allcaps;
		}

		if ( ! empty( $allcaps['manage_options'] ) ) {
			return $allcaps;
		}

		if ( ! self::user_is_groomer( $user ) ) {
			return $allcaps;
		}

		// Site option (all groomers) OR per-role via Staff Permissions: groompress_full_vet_emr, or "Edit Medical Records (EMR)" (kennelflow_vet_edit_emr).
		$from_option = self::allow_full_medical_access();
		$from_matrix = ! empty( $allcaps[ self::CAP_STAFF_FULL_MEDICAL ] );
		if ( ! $from_matrix && class_exists( 'KennelFlow_Vet_Capabilities' ) ) {
			$from_matrix = ! empty( $allcaps[ KennelFlow_Vet_Capabilities::CAP_EDIT_EMR ] );
		}
		$full = $from_option || $from_matrix;
		$full = (bool) apply_filters( 'groompress_groomer_full_medical_access', $full, $user );

		if ( $full ) {
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_READ_EMR ]            = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_EDIT_EMR ]            = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_READ_SOAP ]           = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_EDIT_SOAP ]           = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_GROOMING_NOTES_READ ] = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_GROOMING_NOTES_EDIT ] = true;
		} else {
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_READ_EMR ]            = false;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_EDIT_EMR ]            = false;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_READ_SOAP ]           = false;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_EDIT_SOAP ]           = false;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_GROOMING_NOTES_READ ] = true;
			$allcaps[ KennelFlow_Vet_Capabilities::CAP_GROOMING_NOTES_EDIT ] = true;
		}

		return $allcaps;
	}

	/**
	 * Hook point for GroomPress on map_meta_cap (primitives are set in filter_user_has_cap).
	 *
	 * @param string[] $caps    Required caps.
	 * @param string   $cap     Requested cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Args.
	 * @return string[]
	 */
	public static function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
		unset( $cap, $user_id, $args );
		return $caps;
	}
}
