<?php
/**
 * Back-compat wrapper: dependency slug mapping lives in KennelFlow Core.
 *
 * @package KennelFlow_Groom
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Landtech\KennelFlow\Core\Plugin_Dependencies', false ) ) {
	return;
}

/**
 * Class KennelFlow_Groom_Dependencies
 */
class KennelFlow_Groom_Dependencies {

	/**
	 * @return void
	 */
	public static function register() {
		// Core registers the filter when active.
	}

	/**
	 * @param string $slug Canonical slug.
	 * @return bool
	 */
	public static function is_dependency_active( $slug ) {
		return false;
	}

	/**
	 * @param string $slug Canonical slug.
	 * @return string
	 */
	public static function dependency_label( $slug ) {
		return $slug;
	}
}
