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
 */
function degruchy_csp()
{
	header("Content-Security-Policy: default-src 'self' www.degruchy.org; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self' data:; img-src 'self' data: https://cdn.shortpixel.ai; upgrade-insecure-requests; block-all-mixed-content; base-uri 'self'; report-uri https://degruchy.report-uri.com/r/d/csp/enforce");
	return true;
}

add_filter( 'send_headers', 'degruchy_csp' );

/**
 * Remove Ancient Custom Fields metabox from post editor
 * because it uses a very slow query meta_key sort query
 * so on sites with large postmeta tables it is super slow
 * and is rarely useful anymore on any site
 */
function s9_remove_post_custom_fields_metabox()
{
    foreach ( get_post_types( '', 'names' ) as $post_type )
	{
         remove_meta_box( 'postcustom' , $post_type , 'normal' );   
    }
}
add_action( 'admin_menu' , 's9_remove_post_custom_fields_metabox' ); 

/**
 * Display Old Post Notification on Content
 */
function display_old_post_notification_on_content( $content )
{
	if( !is_singular( 'post' ) )
		return $content;
	
	$postd  = get_the_date( 'U' );
	$today  = date( 'U' );
	$oneyr  = 60 * 60 * 24 * 365;
	$cats   = get_categories();
	$show   = 0;
	$banner = '<section id="old"> <p> <em><strong>Please Note:</strong> This post is a year old or older. That means the content may have changed, <a href="https://degruchy.org/notes/evolving-thoughts/">be out of date with current thinking or just plain wrong</a>. If you have any questions, comments or issues regarding this content, please <a href="https://degruchy.org/about-me/">send me an email</a> with the link and your message.</em> </p> <p> <em>Thank you.</em> </p> <p> <em>-- Nathan</em> </p> </section>';

	foreach ( $cats as $category )
	{ // Loop through the assigned categories
		if ( $category == 'garrett-quotes' )
		{ // If we are a garrett quote
			$show = 0; // hide the bar
		}
		else
		{
			$show = 1;
		}
	}

	if ( ( ( $today - $postd ) >= $oneyr ) && $show == 1 )
	{ // If we're a year or more old
		$content = $banner . $content;
		return $content; // Show the banner
	}
	else
	{
		return $content;
	}
}

add_filter( 'the_content', 'display_old_post_notification_on_content', 99 );


/* Remove jQuery -- Might not be smart on *all* sites.
 * add_filter( 'wp_default_scripts', 'remove_jquery' );
 * 
 * function remove_jquery( &$scripts )
 * {
 * 	if( !is_admin() )
 * 	{
 *         $scripts->remove( 'jquery' );
 *     }
 * } */

function degruchy_mime_types( $mimes )
{
 	// $mimes[ 'svg'  ] = 'image/svg+xml';
	$mimes[ 'svg'  ] = 'image/svg';
	$mimes[ 'webp' ] = 'image/webp';
	$mimes[ 'webm' ] = 'video/webm';
	$mimes[ 'weba' ] = 'audio/weba';

	return $mimes;
}

add_filter( 'upload_mimes', 'degruchy_mime_types', 1, 99 );

?>
