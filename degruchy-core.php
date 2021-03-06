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
 * Plugin Name:       DeGruchy Core Plugin
 * Plugin URI:        https://git.sr.ht/~ndegruchy/degruchy-core
 * Description:       Core functionality plugin for WordPress on DeGruchy.org.
 * Version:           1.3.5
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Nathan DeGruchy
 * Author URI:        https://degruchy.org/
 * Text Domain:       degruchy-core
 * License:           GPL Version 3 or Later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: ndegruchy/degruchy-core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CACHELIFETIME', 3600 );
define( 'DEGRUCHY_CORE_VERSION', '1.3.5' );

/**
 * Disable plugin/theme editor
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', TRUE );
}

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
	$_css_files = array(
		10 => array(
			'name' => 'old-banner',
			'file' => '/styles/old-banner.css',
		),
		20 => array(
			'name' => 'pictures',
			'file' => '/styles/pictures.css',
		),
		99 => array(
			'name' => 'tweaks',
			'file' => '/styles/tweaks.css',
		),
	);

	// sort the array on key
	ksort( $_css_files );

	foreach ( $_css_files as $order => $data ) {
		if ( ! file_exists( __DIR__ . $data[ 'file' ] ) ) {
			// Abort if we don't find a tweaks CSS file
			break;
		}

		wp_register_style(
			"degruchy-core-css-{$data['name']}",
			plugins_url( $data[ 'file' ], __FILE__ ),
			array(),
			NULL,
			'screen'
		);

		wp_enqueue_style(
			"degruchy-core-css-{$data['name']}",
			plugins_url( $data[ 'file' ], __FILE__ ),
			array(),
			NULL,
			'screen'
		);
	}

	return TRUE;
}

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


// Remove wlwmanifest.xml (needed to support windows live writer).
remove_action( 'wp_head', 'wlwmanifest_link' );

// Remove generator tag from RSS feeds.
remove_action( 'atom_head', 'the_generator' );
remove_action( 'comments_atom_head', 'the_generator' );
remove_action( 'rss_head', 'the_generator' );
remove_action( 'rss2_head', 'the_generator' );
remove_action( 'commentsrss2_head', 'the_generator' );
remove_action( 'rdf_header', 'the_generator' );
remove_action( 'opml_head', 'the_generator' );
remove_action( 'app_head', 'the_generator' );
add_filter( 'the_generator', '__return_false' );

// Remove WordPress generator version.
remove_action( 'wp_head', 'wp_generator' );

// Remove emoji styles and script from header.
if ( is_admin() ) {

	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

	add_filter(
		'tiny_mce_plugins',
		function ( $plugins ) {
			if ( is_array( $plugins ) ) {
				return array_diff( $plugins, array( 'wpemoji' ) );
			}

			return array();
		}
	);

} else {

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	add_filter( 'emoji_svg_url', '__return_false' );

}

// Remove jQuery Migrate.
add_action(
	'wp_default_scripts',
	function ( $scripts ) {

		if ( ! is_admin() && ! empty( $scripts->registered[ 'jquery' ] ) ) {
			$jquery_dependencies                   = $scripts->registered[ 'jquery' ]->deps;
			$scripts->registered[ 'jquery' ]->deps = array_diff( $jquery_dependencies, array( 'jquery-migrate' ) );
		}

	}
);

function degruchy_social_sharing_filter() {
    if("feed" === get_post_type())
		return false;
    // Show for all other posts.
    return true;
}
add_filter( 'toolbelt_display_social_sharing', 'degruchy_social_sharing_filter' );
