<?php
/**
 * GroomPress public helpers.
 *
 * @package GroomPress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Top-level GroomPress Salon admin menu slug (`add_menu_page` parent).
 *
 * @return string
 */
function groompress_get_salon_menu_slug() {
	return apply_filters( 'groompress_salon_menu_slug', 'groompress-salon' );
}

/**
 * Whether KennelFlow Vet EMR capabilities are available (groomer medical access integration).
 *
 * @return bool
 */
function kennelflow_groom_vet_emr_active() {
	return class_exists( 'KennelFlow_Vet_Capabilities' );
}
