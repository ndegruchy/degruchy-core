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
 * Version:           1.0.0
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

	$csp_string = ""; // Empty by default

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

	$csp_string .= " upgrade-insecure-requests; block-all-mixed-content;"; // non-value rules

	header( "Content-Security-Policy: $csp_string" ); // send!

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
	if ( ! is_singular( 'post' ) ) {
		return $content;
	}

	$postd  = get_the_date( 'U' );
	$today  = date( 'U' );
	$oneyr  = 60 * 60 * 24 * 365;
	$cats   = get_categories();
	$show   = 0;
	$banner = '<section id="old"><p><em><strong>Please Note:</strong> This post is a year old or older. That means the content may have changed, <a href="https://degruchy.org/notes/evolving-thoughts/">be out of date with current thinking or just plain wrong</a>. If you have any questions, comments or issues regarding this content, please <a href="mailto:nathan@degruchy.org?subject=Issue%20With%20Your%20Site%3A&body=Hello%2C%0D%0A%0D%0AI%20am%20having%20issues%20with%20this%20page%3A%0D%0A%0D%0APlease%20help.%0D%0A%0D%0AThanks!">send me an email</a> with the link and your message.</em></p><p><em>Thank you.</em></p><p><em>&mdash; Nathan</em></p></section>';

	foreach ( $cats as $category ) { // Loop through the assigned categories
		if ( $category == 'garrett-quotes' ) { // If we are a garrett quote
			$show = 0; // hide the bar
		} else {
			$show = 1;
		}
	}

	if ( ( ( $today - $postd ) >= $oneyr ) && $show == 1 ) { // If we're a year or more old
		$content = $banner . $content;

		return $content; // Show the banner
	} else {
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
	// $mimes[ 'svg'  ] = 'image/svg+xml'; // doesn't seem to work...
	$mimes[ 'svg' ]  = 'image/svg';
	$mimes[ 'webp' ] = 'image/webp';
	$mimes[ 'webm' ] = 'video/webm';
	$mimes[ 'weba' ] = 'audio/weba';

	return $mimes;
}

add_filter( 'upload_mimes', 'degruchy_mime_types', 1, 99 );

