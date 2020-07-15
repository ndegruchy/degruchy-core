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
 * Version:           1.3.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Nathan DeGruchy
 * Author URI:        https://degruchy.org/
 * Text Domain:       degruchy-core
 * License:           GPL Version 3 or Later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define(
	'CACHELIFETIME',
	3600000
);

/**
 * Add CSPs to header
 *
 * @return bool TRUE This always fires
 */
function degruchy_csp() {
	$_csp_cache = wp_cache_get( 'degruchy-core-csp', 'degruchy-core' );

	if ( FALSE == $_csp_cache ) { // CSP Cache is empty, generate it again
		// Settings matrix
		$csp_options = array(
			"default-src"  => array(
				'\'self\'',
				'https://www.degruchy.org',
			),
			"base-uri"     => array(
				'\'self\'',
				'https://www.degruchy.org',
			),
			"script-src"   => array(
				'\'self\'',
				'\'unsafe-inline\'',
			),
			"style-src"    => array(
				'\'self\'',
				'\'unsafe-inline\'',
			),
			"font-src"     => array(
				'\'self\'',
				'data:',
			),
			"img-src"      => array(
				'\'self\'',
				'data:',
				'https://cdn.shortpixel.ai',
			),
			"prefetch-src" => array(
				'\'self\'',
				'https://www.degruchy.org',
				'https://cdn.shortpixel.ai',
			),
			"report-uri"   => "https://degruchy.report-uri.com/r/d/csp/enforce",
			"report-to"    => "https://degruchy.report-uri.com/r/d/csp/enforce",
			//CSP level 3 https://www.w3.org/TR/CSP/#changes-from-level-2
		);

		if ( empty( array_filter( $csp_options ) ) ) {
			return FALSE;
		}

		$csp_string = 'Content-Security-Policy: '; // Empty by default

		foreach ( $csp_options as $rule => $setting ) {
			// For each item in the top-level array
			if ( is_array( $setting ) ) {
				// If we find the value is another array, loop in
				$csp_string .= $rule; // First part
				foreach ( $setting as $item ) {
					$csp_string .= " {$item}"; // Append setting
				}
			} else {
				// If it's just a simple k=>v, then add it as per normal.
				$csp_string .= "{$rule} {$setting}";
			}
			$csp_string .= "; "; // separator
		}

		$csp_string .= 'upgrade-insecure-requests; block-all-mixed-content;'; // non-value rules
		$csp_string = trim( $csp_string );

		// Caching
		wp_cache_set(
			'degruchy-core-csp',
			$csp_string,
			'degruchy-core',
			CACHELIFETIME
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
	if ( ! is_singular( 'post' ) || in_category( 'garrett-quotes' ) ) {
		return $content;
	}

	$postd = get_the_date( 'U' );
	$today = date( 'U' );
	$oneyr = 60 * 60 * 24 * 365;

	if ( ( ( $today - $postd ) >= $oneyr ) ) { // If we're a year or more old

		$_banner_cache = wp_cache_get(
			'degruchy-core-old-banner',
			'degruchy-core'
		);

		if ( FALSE == $_banner_cache ) {
			// If the banner cache is empty, generate it
			// Add parsedown.
			if ( file_exists( __DIR__ . '/vendor/parsedown/Parsedown.php' ) ) {
				require_once __DIR__ . '/vendor/parsedown/Parsedown.php';
				$Parsedown = new Parsedown;

				// Set some options
				$Parsedown->setSafeMode( TRUE );

				$banner_file = __DIR__ . '/templates/banner.md';
				if ( file_exists( $banner_file ) ) {
					$banner = file_get_contents( $banner_file );
				} else {
					$banner = ''; // banner template is missing, abort!
				}

				$banner = $Parsedown->text( $banner );
				$banner = "<section id=\"old\">{$banner}</section>";

				// Cache the result for an hour
				wp_cache_set(
					'degruchy-core-old-banner',
					$banner,
					'degruchy-core',
					CACHELIFETIME
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
	if ( ! file_exists( __DIR__ . '/styles/tweaks.css' ) ) {
		// Abort if we don't find a tweaks CSS file
		return FALSE;
	}

	wp_enqueue_style(
		'degruchy-core-tweaks',
		plugins_url( '/styles/tweaks.css', __FILE__ ),
		array(),
		NULL,
		'all'
	);

	return TRUE;
}

add_action( 'wp_enqueue_scripts', 'degruchy_css_tweaks', 99 );

add_action( 'wp_enqueue_scripts', 'degruchy_css_tweaks', 99 );

/**
 * Blogroll Shortcode
 *
 * @return string $blogroll A formatted blogroll, suitable for a shortcode
 */
function degruchy_core_sc_blogroll() {
	$blogroll = wp_list_bookmarks(
		array(
			'orderby'      => 'name',
			'echo'         => FALSE,
			'title_li'     => '',
			'title_before' => '',
			'title_after'  => '',
			'show_images'  => '',
			'categorize'   => 0,
		)
	);

	return "<ul>{$blogroll}</ul>";
}

add_shortcode( 'blogroll', 'degruchy_core_sc_blogroll' );
add_filter( 'pre_option_link_manager_enabled', '__return_true' );

/**
 * Images Page Shortcode
 */
function degruchy_core_images() {
	$_images_cache = wp_cache_get( 'degruchy-images', 'degruchy-core' );
	$content       = '<section id="images">';

	if ( empty( $_images_cache ) ) {
		$query_images_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => - 1,
		);

		$query_images = new WP_Query( $query_images_args );

		foreach ( $query_images->posts as $image ) {
			if ( empty( $image ) ) {
				break;
			}

			$meta = wp_get_attachment_metadata( $image->ID );
			$url  = wp_get_attachment_thumb_url( $image->ID );

			// Found: https://wordpress.org/support/topic/how-to-get-the-alt-text-of-an-image/
			// Not going to lie, this is... weird, considering the above meta function should return this
			// as part of the array, but whatever...
			$alt = esc_html( get_post_meta( $image->ID, '_wp_attachment_image_alt', TRUE ) );

			if ( empty( $alt ) ) {
				$alt = 'No alternative text found for this resource.';
			}

			$height = $meta[ 'sizes' ][ 'thumbnail' ][ 'height' ];
			$width  = $meta[ 'sizes' ][ 'thumbnail' ][ 'width' ];
			$bigurl = $meta[ 'file' ];

			$content .= '<figure class="gallery-img">';
			$content .= "<a href=\"/wp-content/uploads/{$bigurl}\">";
			$content .= "<img src=\"{$url}\" height=\"{$height}\" width=\"{$width}\" alt=\"{$alt}\">";
			$content .= '</a>';
			$content .= '</figure>';
		}

		$content .= '</section>';

		wp_cache_set(
			'degruchy-images',
			$content,
			'degruchy-core',
			CACHELIFETIME
		);

		return $content;

	} else {
		return $_images_cache;
	}
}

add_shortcode( 'degruchy-images', 'degruchy_core_images' );

function my_social_networks() {
	return 'pocket|wallabag|reddit|email';
}
add_filter( 'wp_toolbelt_social_networks', 'my_social_networks');
