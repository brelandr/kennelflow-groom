<?php
/**
 * Front-end and REST access to the KennelFlow Hub calendar for groomers.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KennelFlow_Groom_Calendar_Access
 */
class KennelFlow_Groom_Calendar_Access {

	/**
	 * View Hub / grooming calendar (staff calendar shortcode and REST reads).
	 */
	const CAP_VIEW_CALENDAR = 'groompress_view_calendar';

	/**
	 * Read / edit Hub kf_pet records for grooming desk.
	 */
	const CAP_EDIT_HUB_PETS = 'groompress_edit_hub_pets';

	/**
	 * Create / edit grooming bookings from the staff calendar modal.
	 */
	const CAP_CREATE_BOOKINGS = 'groompress_create_bookings';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'ltkf_user_can_view_hub_calendar', array( __CLASS__, 'filter_user_can_view_hub_calendar' ), 10, 2 );
		add_filter( 'ltkf_admin_calendar_capability', array( __CLASS__, 'filter_calendar_capability' ) );
		add_filter( 'ltkf_hub_calendar_shortcode_cap', array( __CLASS__, 'filter_calendar_capability' ) );
		add_filter( 'groompress_admin_calendar_capability', array( __CLASS__, 'filter_calendar_capability' ) );
		add_filter( 'ltkf_hub_menu_capability', array( __CLASS__, 'filter_hub_menu_capability' ), 20 );
		add_filter( 'ltkf_hub_calendar_shortcode_atts', array( __CLASS__, 'filter_shortcode_atts' ) );
		add_filter( 'ltkf_admin_calendar_localized_settings', array( __CLASS__, 'filter_calendar_localized_settings' ), 15 );
		add_filter( 'ltkf_permissions_managed_capabilities', array( __CLASS__, 'register_permissions_matrix_rows' ) );
		add_filter( 'ltkf_hub_dashboard_sections', array( __CLASS__, 'filter_hub_dashboard_sections' ), 10, 3 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 15, 4 );
		add_action( 'admin_menu', array( __CLASS__, 'trim_hub_submenus_for_groomers' ), 999 );
		add_action( 'admin_menu', array( __CLASS__, 'register_pets_submenu' ), 15 );
	}

	/**
	 * Grant caps to groomer role (idempotent).
	 *
	 * @return void
	 */
	public static function register_caps() {
		$roles = array( 'administrator', 'editor', KennelFlow_Groom_Activator::ROLE );

		/**
		 * Roles that receive groompress_view_calendar and groompress_edit_hub_pets.
		 *
		 * @since 0.2.2
		 *
		 * @param string[] $roles Role slugs.
		 */
		$roles = apply_filters( 'groompress_roles_with_calendar_cap', $roles );

		$caps = array(
			self::CAP_VIEW_CALENDAR,
			self::CAP_CREATE_BOOKINGS,
			self::CAP_EDIT_HUB_PETS,
			KennelFlow_Groom_Activator::CAP_VIEW_COMMISSIONS,
			'edit_posts',
			'read',
			'read_private_posts',
		);

		if ( class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			$caps[] = KennelFlow_Boarding_Capabilities::CAP_EDIT_HUB_PETS;
		}

		foreach ( (array) $roles as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
			if ( defined( 'KENNELFLOW_GROOM_PRO_VERSION' ) && ! $role->has_cap( 'upload_files' ) ) {
				$role->add_cap( 'upload_files' );
			}
		}
	}

	/**
	 * Whether a user may view the Hub calendar UI.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_view( $user_id = 0 ) {
		self::register_caps();

		$user_id = absint( $user_id );
		$caps    = array(
			self::CAP_VIEW_CALENDAR,
			KennelFlow_Groom_Activator::CAP_VIEW_COMMISSIONS,
			'edit_posts',
		);

		foreach ( $caps as $cap ) {
			if ( $user_id > 0 ) {
				if ( user_can( $user_id, $cap ) ) {
					return true;
				}
			} elseif ( current_user_can( $cap ) ) {
				return true;
			}
		}

		if ( class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			if ( $user_id > 0 ) {
				return KennelFlow_Boarding_Capabilities::user_can_edit_hub_pets( $user_id );
			}
			return KennelFlow_Boarding_Capabilities::user_can_edit_hub_pets();
		}

		return false;
	}

	/**
	 * Whether the user may create bookings from the Hub calendar modal.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_create_bookings( $user_id = 0 ) {
		self::register_caps();

		$user_id = absint( $user_id );
		$caps    = array(
			self::CAP_CREATE_BOOKINGS,
			'edit_posts',
			'manage_options',
		);

		if ( class_exists( 'KennelFlow_Boarding_Capabilities' ) ) {
			if ( $user_id > 0 ) {
				if ( KennelFlow_Boarding_Capabilities::user_can_edit_bookings( $user_id ) ) {
					return true;
				}
			} elseif ( KennelFlow_Boarding_Capabilities::user_can_edit_bookings() ) {
				return true;
			}
		}

		foreach ( $caps as $cap ) {
			if ( $user_id > 0 ) {
				if ( user_can( $user_id, $cap ) ) {
					return true;
				}
			} elseif ( current_user_can( $cap ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the user may edit Hub pet posts.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_can_edit_hub_pets( $user_id = 0 ) {
		self::register_caps();

		$user_id = absint( $user_id );
		if ( $user_id > 0 ) {
			return user_can( $user_id, self::CAP_EDIT_HUB_PETS ) || user_can( $user_id, 'manage_options' );
		}
		return current_user_can( self::CAP_EDIT_HUB_PETS ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether the user has the Groomer role.
	 *
	 * @param int $user_id User ID (0 = current).
	 * @return bool
	 */
	public static function user_is_groomer( $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id < 1 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return false;
		}
		return in_array( KennelFlow_Groom_Activator::ROLE, (array) $user->roles, true );
	}

	/**
	 * @param bool $can     Prior result.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public static function filter_user_can_view_hub_calendar( $can, $user_id ) {
		if ( $can ) {
			return true;
		}
		if ( ! self::user_is_groomer( $user_id ) ) {
			return $can;
		}
		return self::user_can_view( $user_id );
	}

	/**
	 * GroomPress admin screens only — do not replace Hub calendar cap for vet/boarding staff.
	 *
	 * @param string $cap Default capability.
	 * @return string
	 */
	public static function filter_calendar_capability( $cap ) {
		if ( self::user_is_groomer() && ! current_user_can( 'manage_options' ) ) {
			return self::CAP_VIEW_CALENDAR;
		}
		return $cap;
	}

	/**
	 * KennelFlow Hub top-level menu for groomers.
	 *
	 * @param string $cap Default capability.
	 * @return string
	 */
	public static function filter_hub_menu_capability( $cap ) {
		if ( self::user_is_groomer() && ! current_user_can( 'manage_options' ) ) {
			return self::CAP_VIEW_CALENDAR;
		}
		return $cap;
	}

	/**
	 * Default grooming rows for groomer sessions on the staff calendar page.
	 *
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return array<string, string>
	 */
	public static function filter_shortcode_atts( $atts ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		if ( ! self::user_is_groomer() ) {
			return $atts;
		}
		if ( empty( $atts['booking_kind'] ) ) {
			$atts['booking_kind'] = 'grooming';
		}
		if ( empty( $atts['corner_label'] ) ) {
			$atts['corner_label'] = __( 'Groomer', 'kennelflow-groom' );
		}
		return $atts;
	}

	/**
	 * Calendar script settings for groomer sessions (default booking kind, create flag).
	 *
	 * @param array<string, mixed> $settings Localized settings.
	 * @return array<string, mixed>
	 */
	public static function filter_calendar_localized_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( defined( 'KENNELFLOW_GROOM_PRO_VERSION' ) && class_exists( 'KennelFlow_Groom_Pro_REST' ) ) {
			$can_upload = current_user_can( 'upload_files' ) && current_user_can( 'edit_posts' );
			$can_upload = (bool) apply_filters( 'kennelflow_groom_pro_calendar_session_photos_enabled', $can_upload, $settings );
			$settings['groompro_session_photos'] = array(
				'active'     => true,
				'enabled'    => $can_upload,
				'can_upload' => $can_upload,
				'rest_ns'    => KennelFlow_Groom_Pro_REST::NS,
				'can_edit'   => current_user_can( 'edit_posts' ),
			);
			if ( ! isset( $settings['booking_session_photos'] ) || ! is_array( $settings['booking_session_photos'] ) ) {
				$settings['booking_session_photos'] = array();
			}
			$settings['booking_session_photos']['grooming'] = array(
				'active'      => true,
				'enabled'     => $can_upload,
				'can_upload'  => $can_upload,
				'rest_ns'     => KennelFlow_Groom_Pro_REST::NS,
				'heading'     => __( 'Session photos', 'kennelflow-groom' ),
				'media_kinds' => array(
					array(
						'key'         => 'before',
						'label'       => __( 'Before', 'kennelflow-groom' ),
						'takeLabel'   => __( 'Take before photo', 'kennelflow-groom' ),
						'chooseLabel' => __( 'Choose before photo', 'kennelflow-groom' ),
					),
					array(
						'key'         => 'after',
						'label'       => __( 'After', 'kennelflow-groom' ),
						'takeLabel'   => __( 'Take after photo', 'kennelflow-groom' ),
						'chooseLabel' => __( 'Choose after photo', 'kennelflow-groom' ),
					),
				),
			);
		}

		if ( ! self::user_can_create_bookings() ) {
			return $settings;
		}

		$settings['can_create_bookings'] = true;

		if ( self::user_is_groomer() ) {
			$settings['default_booking_kind'] = 'grooming';
		}

		return $settings;
	}

	/**
	 * Hub dashboard: daily links groomers can use (calendar + pets).
	 *
	 * @param array  $sections Sections.
	 * @param string $pet_pt   Pet post type.
	 * @param string $loc_pt   Location post type.
	 * @return array
	 */
	public static function filter_hub_dashboard_sections( $sections, $pet_pt, $loc_pt ) {
		unset( $loc_pt );
		if ( ! self::user_is_groomer() || current_user_can( 'manage_options' ) ) {
			return $sections;
		}

		$pet_pt = function_exists( 'ltkf_get_pet_post_type' ) ? ltkf_get_pet_post_type() : 'kf_pet';

		return array(
			array(
				'title' => __( 'Grooming desk', 'kennelflow-groom' ),
				'items' => array(
					array(
						'url'   => admin_url( 'admin.php?page=' . KennelFlow_Groom_Admin_Calendar::PAGE_SLUG ),
						'label' => __( 'Grooming schedule', 'kennelflow-groom' ),
						'icon'  => 'calendar-alt',
					),
					array(
						'url'   => add_query_arg( 'post_type', $pet_pt, admin_url( 'edit.php' ) ),
						'label' => __( 'Pets', 'kennelflow-groom' ),
						'icon'  => 'admin-users',
					),
					array(
						'url'   => admin_url( 'admin.php?page=' . KennelFlow_Groom_Admin_Earnings::PAGE_SLUG ),
						'label' => __( 'Groomer earnings', 'kennelflow-groom' ),
						'icon'  => 'money-alt',
					),
				),
			),
		);
	}

	/**
	 * Allow desk staff to open Hub pets they did not author.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Requested cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Cap args.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( self::user_has_cap( $user_id, 'manage_options' ) ) {
			return $caps;
		}

		if ( self::is_kennelflow_boarding_booking_cap( $cap ) ) {
			if ( self::user_has_cap( $user_id, self::CAP_CREATE_BOOKINGS ) ) {
				if ( self::is_delete_booking_cap( $cap ) ) {
					return array( 'do_not_allow' );
				}
				return array( self::CAP_CREATE_BOOKINGS );
			}
			return $caps;
		}

		if ( ! in_array( $cap, array( 'read_post', 'edit_post', 'delete_post' ), true ) ) {
			return $caps;
		}

		$post_id = 0;
		if ( ! empty( $args[0] ) && is_numeric( (string) $args[0] ) ) {
			$post_id = absint( $args[0] );
		}

		if ( $post_id < 1 ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $caps;
		}

		if ( 'kennelpress_booking' === $post->post_type && self::user_has_cap( $user_id, self::CAP_CREATE_BOOKINGS ) ) {
			if ( 'delete_post' === $cap ) {
				return array( 'do_not_allow' );
			}
			return array( self::CAP_CREATE_BOOKINGS );
		}

		$pet_pt = function_exists( 'ltkf_get_pet_post_type' ) ? ltkf_get_pet_post_type() : 'kf_pet';
		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';

		if ( $loc_pt === $post->post_type && 'read_post' === $cap && self::user_has_cap( $user_id, self::CAP_VIEW_CALENDAR ) ) {
			return array( self::CAP_VIEW_CALENDAR );
		}

		if ( $pet_pt !== $post->post_type ) {
			return $caps;
		}

		if ( ! self::user_has_cap( $user_id, self::CAP_EDIT_HUB_PETS ) ) {
			return $caps;
		}

		if ( 'delete_post' === $cap ) {
			return array( 'do_not_allow' );
		}

		return array( self::CAP_EDIT_HUB_PETS );
	}

	/**
	 * @param string $cap Primitive or meta capability name.
	 * @return bool
	 */
	protected static function is_kennelflow_boarding_booking_cap( $cap ) {
		$cap = (string) $cap;
		if ( '' === $cap ) {
			return false;
		}
		return false !== strpos( $cap, 'kennelflow_boarding_booking' );
	}

	/**
	 * @param string $cap Capability name.
	 * @return bool
	 */
	protected static function is_delete_booking_cap( $cap ) {
		return false !== strpos( (string) $cap, 'delete_kennelflow_boarding_booking' );
	}

	/**
	 * Hide vet/boarding admin screens groomers should not use.
	 *
	 * @return void
	 */
	public static function trim_hub_submenus_for_groomers() {
		if ( ! self::user_is_groomer() || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'ltkf_get_hub_menu_slug' ) ) {
			return;
		}

		$parent = ltkf_get_hub_menu_slug();
		$loc_pt = function_exists( 'ltkf_get_location_post_type' ) ? ltkf_get_location_post_type() : 'kf_location';

		remove_submenu_page( $parent, 'edit.php?post_type=kfvet_room' );
		remove_submenu_page( $parent, 'edit.php?post_type=' . $loc_pt );
		remove_submenu_page( $parent, 'edit-tags.php?taxonomy=kfvet_location&post_type=kfvet_room' );
	}

	/**
	 * Hub pets under GroomPress for groomers.
	 *
	 * @return void
	 */
	public static function register_pets_submenu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) || ! function_exists( 'groompress_get_salon_menu_slug' ) ) {
			return;
		}

		add_submenu_page(
			groompress_get_salon_menu_slug(),
			__( 'Pets', 'kennelflow-groom' ),
			__( 'Pets', 'kennelflow-groom' ),
			self::CAP_EDIT_HUB_PETS,
			'edit.php?post_type=' . ltkf_get_pet_post_type()
		);
	}

	/**
	 * @param array<string, string> $defs Capability definitions.
	 * @return array<string, string>
	 */
	public static function register_permissions_matrix_rows( $defs ) {
		if ( ! is_array( $defs ) ) {
			$defs = array();
		}
		$defs[ self::CAP_VIEW_CALENDAR ]   = __( 'View staff calendar', 'kennelflow-groom' );
		$defs[ self::CAP_CREATE_BOOKINGS ] = __( 'Create grooming bookings (calendar)', 'kennelflow-groom' );
		$defs[ self::CAP_EDIT_HUB_PETS ]   = __( 'Edit Hub pets (grooming)', 'kennelflow-groom' );
		return $defs;
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $cap     Capability.
	 * @return bool
	 */
	protected static function user_has_cap( $user_id, $cap ) {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! is_array( $user->allcaps ) ) {
			return false;
		}
		return ! empty( $user->allcaps[ $cap ] ) || ! empty( $user->allcaps['manage_options'] );
	}
}
