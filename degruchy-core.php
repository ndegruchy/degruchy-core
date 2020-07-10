<?php

/**
 * degruchy-core
 *
 * @package           degruchy-core
 * @author            Nathan DeGruchy
 * @copyright         2020 Nathan DeGruchy
 * @license           GPL Version 3 or later
 *
 * @wordpress-plugin
 * Plugin Name:       degruchy-core
 * Plugin URI:        https://git.sr.ht/~ndegruchy/degruchy-core
 * Description:       Core plugin for degruchy.org.
 * Version:           1.2
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Nathan DeGruchy
 * Author URI:        https://degruchy.org/
 * Text Domain:       degruchy-core
 * License:           GPL Version 3 or Later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */


/**
 * Add CSPs to header
 *
 * @return bool TRUE This always fires
 */
function degruchy_csp() {
	$toggle = TRUE; // Set me to false to turn this feature off

	if ( ! $toggle ) {
		return FALSE;
	}

	$_csp_cache = wp_cache_get( "degruchy-core-csp", "degruchy-core" );

	if ( FALSE == $_csp_cache ) { // CSP Cache is empty, generate it again
		// Settings matrix
		$csp_options = array(
			"default-src" => array(
				"'self'",
				"https://www.degruchy.org",
			),
			"base-uri"    => array(
				"'self'",
				"https://www.degruchy.org",
			),
			"script-src"  => array(
				"'self'",
				"'unsafe-inline'",
			),
			"style-src"   => array(
				"'self'",
				"'unsafe-inline'",
			),
			"font-src"    => array(
				"'self'",
				"data:",
			),
			"img-src"     => array(
				"'self'",
				"data:",
				"https://cdn.shortpixel.ai",
			),
			"report-uri"  => "https://degruchy.report-uri.com/r/d/csp/enforce",
		);

		if ( empty( array_filter( $csp_options ) ) ) {
			return FALSE;
		}

		$csp_string = "Content-Security-Policy: "; // Empty by default

		foreach ( $csp_options as $rule => $setting ) {
			// For each item in the top-level array
			if ( is_array( $setting ) ) {
				// If we find the value is another array, loop in
				$csp_string .= $rule; // First part
				foreach ( $setting as $item ) {
					$csp_string .= " " . $item; // Append setting
				}
			} else {
				// If it's just a simple k=>v, then add it as per normal.
				$csp_string .= $rule . " " . $setting;
			}
			$csp_string .= "; "; // separator
		}

		$csp_string .= "upgrade-insecure-requests; block-all-mixed-content;"; // non-value rules
		$csp_string = trim( $csp_string );

		// Caching
		wp_cache_set(
			"degruchy-core-csp",
			$csp_string,
			"degruchy-core",
			3600000
		);

		header( $csp_string );
	} else {
		header( $_csp_cache ); // send!
	}

	return TRUE;
}

add_filter( 'send_headers', 'degruchy_csp' );

/**
 * degruchy_custom_fields_metabox
 *
 * Remove Ancient Custom Fields metabox from post editor
 * because it uses a very slow query meta_key sort query
 * so on sites with large postmeta tables it is super slow
 * and is rarely useful anymore on any site
 *
 * @return TRUE This always happens
 */
function degruchy_custom_fields_metabox() {
	foreach ( get_post_types( '', 'names' ) as $post_type ) {
		remove_meta_box( 'postcustom', $post_type, 'normal' );
	}

	return TRUE;
}

add_action( 'admin_menu', 'degruchy_custom_fields_metabox' );

/**
 * Display Old Post Notification on Content
 *
 * @param string $content The post/page content to maybe modify.
 *
 * @return string $content The maybe modified content
 */
function degruchy_maybe_add_banner( $content ) {
	if ( ! is_singular( 'post' ) || in_category( "garrett-quotes" ) ) {
		return $content;
	}

	$postd = get_the_date( 'U' );
	$today = date( 'U' );
	$oneyr = 60 * 60 * 24 * 365;

	if ( ( ( $today - $postd ) >= $oneyr ) ) { // If we're a year or more old

		$_banner_cache = wp_cache_get(
			"degruchy-core-old-banner",
			"degruchy-core"
		);

		if ( FALSE == $_banner_cache ) {
			// If the banner cache is empty, generate it
			// Add parsedown.
			if ( file_exists( __DIR__ . "/vendor/parsedown/Parsedown.php" ) ) {
				require_once __DIR__ . "/vendor/parsedown/Parsedown.php";
				$Parsedown = new Parsedown;

				// Set some options
				$Parsedown->setSafeMode( TRUE );

				$banner_file = __DIR__ . "/templates/banner.md";
				if ( file_exists( $banner_file ) ) {
					$banner = file_get_contents( $banner_file );
				} else {
					$banner = ''; // banner template is missing, abort!
				}

				$banner = $Parsedown->text( $banner );
				$banner = "<section id=\"old\">" . $banner . "</section>";

				// Cache the result for an hour
				wp_cache_set(
					"degruchy-core-old-banner",
					$banner,
					"degruchy-core",
					3600000
				);
			} else {
				$banner = ''; // parsedown is missing! abort!
			}
		} else {
			$banner = $_banner_cache;
		}

		$content = $banner . $content;

		return $content; // Show the banner
	} else {
		// We're not showing the banner, now
		return $content;
	}

}

add_filter( 'the_content', 'degruchy_maybe_add_banner', 99 );

/**
 * degruchy_mime_types
 *
 * @param array $mimes A system supplied list of safe mimetypes.
 *
 * @return array $mimes An appended list of safe mimetypes for uploads
 */
function degruchy_mime_types( $mimes ) {
	$mimes[ 'svg' ]  = 'image/svg';
	$mimes[ 'webp' ] = 'image/webp';
	$mimes[ 'webm' ] = 'video/webm';
	$mimes[ 'weba' ] = 'audio/weba';

	return $mimes;
}

add_filter( 'upload_mimes', 'degruchy_mime_types', 1, 99 );

/**
 * Add tweaks CSS
 *
 * @return bool TRUE Always returns true
 */
function degruchy_css_tweaks() {
	if ( ! file_exists( __DIR__ . "/styles/tweaks.css" ) ) {
		// Abort if we don't find a tweaks CSS file
		return FALSE;
	}

	wp_enqueue_style(
		'degruchy-core-tweaks',
		plugins_url( "/styles/tweaks.css", __FILE__ ),
		array(),
		NULL,
		"all"
	);

	return TRUE;
}

add_action( 'wp_enqueue_scripts', 'degruchy_css_tweaks', 99 );
