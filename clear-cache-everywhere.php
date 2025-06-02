<?php
/**
 * Plugin Name:         Clear Cache Everywhere
 * Plugin URI:          https://pluginrx.com/plugin/clear-cache-everywhere/
 * Description:         Instantly clear all cache sources including WP cache, hosting cache, transients, sessions, and browser cache.
 * Version:             1.1.0
 * Requires at least:   5.9
 * Tested up to:        6.8
 * Requires PHP:        7.4
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         clear-cache-everywhere
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Created on:          March 19, 2025
 */


/**
 * Define Namespace
 */
namespace Apos37\ClearCache;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Defines
 */
$plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'version'      => 'Version',
    'plugin_uri'   => 'Plugin URI',
    'requires_php' => 'Requires PHP',
    'textdomain'   => 'Text Domain',
    'author'       => 'Author',
    'author_uri'   => 'Author URI',
    'discord_uri'  => 'Discord URI'
] );

// Versions
define( 'CCEVERYWHERE_VERSION', $plugin_data[ 'version' ] );
define( 'CCEVERYWHERE_SCRIPT_VERSION', CCEVERYWHERE_VERSION );                                                // REPLACE WITH time() DURING TESTING
define( 'CCEVERYWHERE_MIN_PHP_VERSION', $plugin_data[ 'requires_php' ] );

// Names
define( 'CCEVERYWHERE_NAME', $plugin_data[ 'name' ] );
define( 'CCEVERYWHERE_TEXTDOMAIN', $plugin_data[ 'textdomain' ] );
define( 'CCEVERYWHERE__TEXTDOMAIN', str_replace( '-', '_', CCEVERYWHERE_TEXTDOMAIN ) );
define( 'CCEVERYWHERE_AUTHOR', $plugin_data[ 'author' ] );
define( 'CCEVERYWHERE_AUTHOR_URI', $plugin_data[ 'author_uri' ] );
define( 'CCEVERYWHERE_PLUGIN_URI', $plugin_data[ 'plugin_uri' ] );
define( 'CCEVERYWHERE_GUIDE_URL', CCEVERYWHERE_AUTHOR_URI . 'guide/plugin/' . CCEVERYWHERE_TEXTDOMAIN . '/' );
define( 'CCEVERYWHERE_DOCS_URL', CCEVERYWHERE_AUTHOR_URI . 'docs/plugin/' . CCEVERYWHERE_TEXTDOMAIN . '/' );
define( 'CCEVERYWHERE_SUPPORT_URL', CCEVERYWHERE_AUTHOR_URI . 'support/plugin/' . CCEVERYWHERE_TEXTDOMAIN . '/' );
define( 'CCEVERYWHERE_DISCORD_URL', $plugin_data[ 'discord_uri' ] );

// Paths
define( 'CCEVERYWHERE_BASENAME', plugin_basename( __FILE__ ) );                                               //: text-domain/text-domain.php
define( 'CCEVERYWHERE_ABSPATH', plugin_dir_path( __FILE__ ) );                                                //: /home/.../public_html/wp-content/plugins/text-domain/
define( 'CCEVERYWHERE_DIR', plugins_url( '/' . CCEVERYWHERE_TEXTDOMAIN . '/' ) );                             //: https://domain.com/wp-content/plugins/text-domain/
define( 'CCEVERYWHERE_INCLUDES_ABSPATH', CCEVERYWHERE_ABSPATH . 'inc/' );                                     //: /home/.../public_html/wp-content/plugins/text-domain/includes/
define( 'CCEVERYWHERE_INCLUDES_DIR', CCEVERYWHERE_DIR . 'inc/' );                                             //: https://domain.com/wp-content/plugins/text-domain/includes/
define( 'CCEVERYWHERE_JS_PATH', CCEVERYWHERE_INCLUDES_DIR . 'js/' );                                          //: https://domain.com/wp-content/plugins/text-domain/includes/js/
define( 'CCEVERYWHERE_CSS_PATH', CCEVERYWHERE_INCLUDES_DIR . 'css/' );                                        //: https://domain.com/wp-content/plugins/text-domain/includes/css/
define( 'CCEVERYWHERE_IMG_PATH', CCEVERYWHERE_INCLUDES_DIR . 'img/' );                                        //: https://domain.com/wp-content/plugins/text-domain/includes/img/
define( 'CCEVERYWHERE_SETTINGS_PATH', admin_url( 'edit.php?post_type=cceverywhere-files&page=settings' ) );   //: https://domain.com/wp-admin/?page=text-domain

// Screen IDs
define( 'CCEVERYWHERE_SETTINGS_SCREEN_ID', 'tools_page_' . CCEVERYWHERE__TEXTDOMAIN );


/**
 * Includes
 */
require_once CCEVERYWHERE_INCLUDES_ABSPATH . 'common.php';
require_once CCEVERYWHERE_INCLUDES_ABSPATH . 'admin-bar.php';
require_once CCEVERYWHERE_INCLUDES_ABSPATH . 'clear-cache.php';
require_once CCEVERYWHERE_INCLUDES_ABSPATH . 'settings.php';


/**
 * Helper function for developers
 *
 * Use this function to clear all caches from within your code,
 * such as from a scheduled event or an admin action.
 *
 * @param bool $log_results Whether to log the results of cache clearing to the debug log. Default is false.
 * 
 * @return array The results of the cache clearing process.
 */
if ( !function_exists( 'cceverywhere_clear_all' ) ) {
    function cceverywhere_clear_all( $log_results = false ) {
        return (new \Apos37\ClearCache\Clear())->clear_all( true, $log_results );
    } // End cceverywhere_clear_all()
}