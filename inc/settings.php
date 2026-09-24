<?php 
/**
 * Plugin settings
 */


/**
 * Define Namespaces
 */
namespace Apos37\ClearCache;
use Apos37\ClearCache\Clear;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
	(new Settings())->init();
} );


/**
 * The class
 */
class Settings {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'cceverywhere_nonce';
    

    /**
     * Load on init
     */
    public function init() {
        
		// Submenu
        add_action( 'admin_menu', [ $this, 'submenu' ] );

		// Settings fields
        add_action( 'admin_init', [  $this, 'settings_fields' ] );

        // Admin header for the plugin settings page
        add_action( 'in_admin_header', [ $this, 'admin_header' ] );

        // Admin theme colors
        add_filter( 'cceverywhere_theme_colors', [ $this, 'inherit_ahd_colors' ] );

        // Ajax
        add_action( 'wp_ajax_cceverywhere_save_settings', [ $this, 'ajax_save_settings' ] );

        // Enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


	/**
     * Submenu
     *
     * @return void
     */
    public function submenu() {
        add_submenu_page(
            'tools.php',
            CCEVERYWHERE_NAME . ' — ' . __( 'Settings', 'clear-cache-everywhere' ),
            CCEVERYWHERE_NAME,
            'manage_options',
            CCEVERYWHERE__TEXTDOMAIN,
            [ $this, 'page' ]
        );
    } // End submenu()

    
    /**
     * The page
     *
     * @return void
     */
    public function page() {
        global $current_screen;
        if ( $current_screen->id != CCEVERYWHERE_SETTINGS_SCREEN_ID ) {
            return;
        }

        $last_results = get_option( 'clear_cache_everywhere_last_results', [] );
        $clearing_actions = ( new Clear() )->get_clearing_actions();
        $total_elapsed = 0;

        if ( ! empty( $last_results ) && ! empty( $clearing_actions ) ) {
            foreach ( $clearing_actions as $action ) {
                $key = $action[ 'key' ];
                if ( ! empty( $action[ 'enabled' ] ) && ! empty( $last_results[ $key ][ 'start' ] ) && ! empty( $last_results[ $key ][ 'end' ] ) ) {
                    $total_elapsed += $last_results[ $key ][ 'end' ] - $last_results[ $key ][ 'start' ];
                }
            }
        }
        ?>
        <div class="wrap cceverywhere-wrap">
            <hr class="wp-header-end">
            <div class="cceverywhere-content-wrap">
                <div class="cceverywhere-box">
                    <div class="cceverywhere-box-body">
                        <div id="cce-clear-cache-result">
                            <?php if ( $total_elapsed > 0 ) : ?>
                                <?php
                                echo wp_kses_post( sprintf(
                                    /* translators: %s is total elapsed seconds for clearing actions only */
                                    __( 'Total time for clearing all enabled options the last time they were cleared: <strong>%s seconds</strong>.<br><em>Note: This does not include page reload or any recaching by the site or plugins afterwards.</em>', 'clear-cache-everywhere' ),
                                    number_format( $total_elapsed, 3 )
                                ) );
                                ?>
                            <?php else : ?>
                                <?php esc_html_e( 'No enabled clearing actions have been run. You may clear all using the Clear Cache Now button, or individually using the buttons below.', 'clear-cache-everywhere' ); ?>
                            <?php endif; ?>
                        </div>
                        <h2><?php esc_html_e( 'Choose below which items you want to clear with the Clear Cache Now button and Admin Bar link.', 'clear-cache-everywhere' ); ?></h2>
                        <form id="cceverywhere-settings-form" method="post">
                            <?php do_settings_sections( CCEVERYWHERE_TEXTDOMAIN ); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } // End page()


    /**
     * Store the settings fields here
     *
     * @return array
     */
    public function get_settings_fields( $return_keys_only = false ) {
        // Defaults and Hosting
        $fields = [
            [
                'key'         => 'rewrite_rules',
                'title'       => __( 'Rewrite Rules', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => sprintf(
                    /* translators: %s: permalink settings page URL */
                    __( 'Flushes WordPress rewrite rules cache. Sometimes this doesn’t fully clear; you can also resave the <a href="%s" target="_blank" rel="noopener noreferrer">Permalinks settings</a> page.', 'clear-cache-everywhere' ),
                    esc_url( admin_url( 'options-permalink.php' ) )
                ),
            ],
            [
                'key'         => 'wp_cache_flush',
                'title'       => __( 'WordPress Object Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Flushes the WordPress object cache.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'transients',
                'title'       => __( 'Transients', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears all expired and active transients. This is usually not needed if you are trying to see updates on a page. This may impact site performance temporarily as themes and plugins rebuild their caches.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'opcache_reset',
                'title'       => __( 'OPcache Reset', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Resets PHP OPcache to clear cached scripts. May take a few seconds on large sites.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'apcu',
                'title'       => __( 'APCu Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears the APCu user cache. Separate from OPcache and commonly available on shared and managed hosting.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'varnish',
                'title'       => __( 'Varnish Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges the Varnish cache if detected. Network requests may take a few seconds to propagate.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'redis_memcached',
                'title'       => __( 'Redis/Memcached', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Flushes Redis or Memcached object cache. Large caches may take several seconds.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'fragment_cache',
                'title'       => __( 'Fragment Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears any fragment cache used by themes or plugins. May take a few seconds on large sites.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'rest_api_cache',
                'title'       => __( 'REST API Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears cached REST API responses.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'sessions',
                'title'       => __( 'Sessions', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => FALSE,
                'run_context' => 'page',
                'comments'    => __( 'Clears active user sessions; users may need to log in again.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'cookies',
                'title'       => __( 'Cookies', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'page',
                'comments'    => __( 'Clears browser cookies related to the site.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'browser_cache',
                'title'       => __( 'Browser Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'page',
                'comments'    => __( 'Clears cached resources stored in the user’s browser.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'hosting_cache',
                'title'       => __( 'Hosting Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'hosting',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears caching handled by your hosting provider.', 'clear-cache-everywhere' ),
            ],
            [
                'key'      => 'hosting_purge_url',
                'title'    => __( 'Hosting Purge URL', 'clear-cache-everywhere' ),
                'type'     => 'text',
                'sanitize' => 'sanitize_text_field',
                'section'  => 'hosting',
            ],
            [
                'key'         => 'cloudflare_cache',
                'title'       => __( 'Cloudflare Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'cdn',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears caching handled by Cloudflare.', 'clear-cache-everywhere' ),
            ],
            [
                'key'      => 'cloudflare_zone_id',
                'title'    => __( 'Cloudflare Zone ID', 'clear-cache-everywhere' ),
                'type'     => 'text',
                'sanitize' => 'sanitize_text_field',
                'section'  => 'cdn',
                'comments' => __( 'Required to clear Cloudflare cache. Find this in your Cloudflare dashboard under Overview > API > Zone ID.', 'clear-cache-everywhere' ),
            ],
            [
                'key'      => 'cloudflare_api_token',
                'title'    => __( 'Cloudflare API Token', 'clear-cache-everywhere' ),
                'type'     => 'password',
                'sanitize' => 'sanitize_text_field',
                'section'  => 'cdn',
                'comments' => __( 'Required to clear Cloudflare cache. Find this in your Cloudflare dashboard under Overview > API > Get Your Token > Create a New Token with Permission: Zone, Cache Purge, Purge.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'cdn_generic_cache',
                'title'       => __( 'Generic CDN Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'cdn',
                'default'     => FALSE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges cache via a configured generic CDN endpoint.', 'clear-cache-everywhere' ),
            ],
            [
                'key'      => 'cdn_purge_url',
                'title'    => __( 'CDN Purge Endpoint', 'clear-cache-everywhere' ),
                'type'     => 'text',
                'sanitize' => 'sanitize_text_field',
                'section'  => 'cdn',
                'comments' => __( 'Full REST purge-all endpoint URL for your CDN (e.g. KeyCDN, Bunny, StackPath).', 'clear-cache-everywhere' ),
            ],
            [
                'key'      => 'cdn_api_key',
                'title'    => __( 'CDN API Key', 'clear-cache-everywhere' ),
                'type'     => 'password',
                'sanitize' => 'sanitize_text_field',
                'section'  => 'cdn',
                'comments' => __( 'API key or token sent as a Bearer Authorization header.', 'clear-cache-everywhere' ),
            ],
        ];

        // Integrations
        if ( ( is_plugin_active( 'cornerstone/cornerstone.php' ) && has_action( 'cs_purge_tmp' ) ) || defined( 'CS_VERSION' ) ) {
            $fields[] = [
                'key'         => 'cornerstone',
                'title'       => __( 'Cornerstone Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears the Cornerstone page builder cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'elementor/elementor.php' ) && class_exists( '\Elementor\Plugin' ) ) {
            $fields[] = [
                'key'         => 'elementor',
                'title'       => __( 'Elementor Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Elementor page builder cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'wp-super-cache/wp-cache.php' ) && function_exists( 'wp_cache_clear_cache' ) ) {
            $fields[] = [
                'key'         => 'wp_super_cache',
                'title'       => __( 'WP Super Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears the WP Super Cache plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) && function_exists( 'w3tc_flush_all' ) ) {
            $fields[] = [
                'key'         => 'w3_total_cache',
                'title'       => __( 'W3 Total Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears W3 Total Cache plugin caches.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) && function_exists( 'rocket_clean_domain' ) ) {
            $fields[] = [
                'key'         => 'wp_rocket',
                'title'       => __( 'WP Rocket', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears WP Rocket plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) && has_action( 'litespeed_purge_all' ) ) {
            $fields[] = [
                'key'         => 'litespeed_cache',
                'title'       => __( 'LiteSpeed Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears LiteSpeed Cache plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'sg-cachepress/sg-cachepress.php' ) && class_exists( 'SG_CachePress_Supercacher' ) ) {
            $fields[] = [
                'key'         => 'sg_optimizer',
                'title'       => __( 'SiteGround Optimizer', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears SiteGround Optimizer cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'cloudflare/cloudflare.php' ) && class_exists( 'CF\WordPress\Hooks' ) ) {
            $fields[] = [
                'key'         => 'cloudflare',
                'title'       => __( 'Cloudflare Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges Cloudflare cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'autoptimize/autoptimize.php' ) && class_exists( 'autoptimizeCache' ) ) {
            $fields[] = [
                'key'         => 'autoptimize',
                'title'       => __( 'Autoptimize Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Autoptimize generated CSS/JS cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( ( is_plugin_active( 'swift-performance-lite/performance.php' ) || is_plugin_active( 'swift-performance/performance.php' ) ) && function_exists( 'swift_performance_cache_clear' ) ) {
            $fields[] = [
                'key'         => 'swift_performance',
                'title'       => __( 'Swift Performance Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Swift Performance plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'comet-cache/comet-cache.php' ) && function_exists( 'comet_cache_clear_cache' ) ) {
            $fields[] = [
                'key'         => 'comet_cache',
                'title'       => __( 'Comet Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Comet Cache plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) && function_exists( 'wpfc_clear_cache' ) ) {
            $fields[] = [
                'key'         => 'wp_fastest_cache',
                'title'       => __( 'WP Fastest Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears WP Fastest Cache plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'hummingbird-performance/hummingbird.php' ) && class_exists( 'Hummingbird\Cache' )) {
            $fields[] = [
                'key'         => 'hummingbird_cache',
                'title'       => __( 'Hummingbird Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Hummingbird plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'nginx-helper/nginx-helper.php' ) && has_action( 'rt_nginx_helper_purge_all' ) ) {
            $fields[] = [
                'key'         => 'nginx_helper',
                'title'       => __( 'Nginx Helper', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges cache managed by Nginx Helper plugin.', 'clear-cache-everywhere' ),
            ];
        }
        if ( is_plugin_active( 'wp-optimize/wp-optimize.php' ) && function_exists( 'wp_optimize_clear_cache' ) ) {
            $fields[] = [
                'key'         => 'wp_optimize',
                'title'       => __( 'WP-Optimize Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears WP-Optimize cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'breeze/breeze.php' ) && has_action( 'breeze_clear_all_cache' ) ) {
            $fields[] = [
                'key'         => 'breeze',
                'title'       => __( 'Breeze Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Breeze (Cloudways) plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( defined( 'KINSTAMU_VERSION' ) ) {
            $fields[] = [
                'key'         => 'kinsta',
                'title'       => __( 'Kinsta Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Kinsta server-level cache via the Kinsta MU plugin.', 'clear-cache-everywhere' ),
            ];
        }

        if ( method_exists( 'wpecommon', 'purge_varnish_cache' ) ) {
            $fields[] = [
                'key'         => 'wp_engine',
                'title'       => __( 'WP Engine Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears WP Engine server-level Varnish cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'nitropack-integration/nitropack.php' ) && function_exists( 'nitropack_sdk_purge' ) ) {
            $fields[] = [
                'key'         => 'nitropack',
                'title'       => __( 'NitroPack Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears NitroPack plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'pantheon-advanced-page-cache/pantheon-advanced-page-cache.php' ) && function_exists( 'pantheon_wp_clear_edge_all' ) ) {
            $fields[] = [
                'key'         => 'pantheon',
                'title'       => __( 'Pantheon Edge Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Pantheon edge cache via the Pantheon Advanced Page Cache plugin.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'cache-enabler/cache-enabler.php' ) && function_exists( 'cache_enabler_clear_total_cache' ) ) {
            $fields[] = [
                'key'         => 'cache_enabler',
                'title'       => __( 'Cache Enabler', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Cache Enabler plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( function_exists( 'spinupwp_purge_site_cache' ) ) {
            $fields[] = [
                'key'         => 'spinupwp',
                'title'       => __( 'SpinupWP Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears SpinupWP server-level page cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'cachify/cachify.php' ) && has_action( 'cachify_flush_cache' ) ) {
            $fields[] = [
                'key'         => 'cachify',
                'title'       => __( 'Cachify', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Cachify plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'powered-cache/powered-cache.php' ) && function_exists( 'powered_cache_flush_cache' ) ) {
            $fields[] = [
                'key'         => 'powered_cache',
                'title'       => __( 'Powered Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Powered Cache plugin cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( function_exists( 'rocketnet_purge_cache' ) ) {
            $fields[] = [
                'key'         => 'rocketnet',
                'title'       => __( 'Rocket.net Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Rocket.net server-level cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( class_exists( '\RedisCachePro\Plugin' ) ) {
            $fields[] = [
                'key'         => 'object_cache_pro',
                'title'       => __( 'Object Cache Pro', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Flushes Object Cache Pro Redis cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( is_plugin_active( 'wp-cloudflare-page-cache/wp-cloudflare-super-page-cache.php' ) && class_exists( '\SW_CLOUDFLARE_PAGECACHE' ) ) {
            $fields[] = [
                'key'         => 'wp_cloudflare_super_page_cache',
                'title'       => __( 'WP Cloudflare Super Page Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges WP Cloudflare Super Page Cache.', 'clear-cache-everywhere' ),
            ];
        }

        if ( ( new Clear() )->is_seraphinite_available() ) {
            $fields[] = [
                'key'         => 'seraphinite_accelerator',
                'title'       => __( 'Seraphinite Accelerator', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'integrations',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Clears Seraphinite Accelerator cache. This may take up to a minute to complete.', 'clear-cache-everywhere' ),
            ];
        }

        // Apply filter to allow developers to add custom fields
        $fields = apply_filters( 'cceverywhere_custom_settings', $fields );

        // Return
        if ( $return_keys_only ) {
            $field_keys = [];
            foreach ( $fields as $field ) {
                $field_keys[] = $field[ 'key' ];
            }
            return $field_keys;
        }
        return $fields;
    } // End get_settings_fields()


    /**
     * Settings fields
     *
     * @return void
     */
    public function settings_fields() {
        // Slug
        $slug = CCEVERYWHERE_TEXTDOMAIN;

        // Fields
        $fields = $this->get_settings_fields( false );

        /**
         * Sections
         */
        $settings_sections = [
            [ 'defaults', __( 'Defaults', 'clear-cache-everywhere' ), '' ],
            [ 'hosting', __( 'Hosting', 'clear-cache-everywhere' ), '' ],
            [ 'cdn', __( 'CDN', 'clear-cache-everywhere' ), '' ],
            [ 'integrations', __( 'Integrations', 'clear-cache-everywhere' ), '' ],
            [ 'custom', __( 'Custom', 'clear-cache-everywhere' ), '' ],
        ];

        // Only include sections with fields
        $settings_sections_to_add = [];
        foreach ( $settings_sections as $settings_section ) {
            $section_key = $settings_section[0];
            
            // Check if any fields exist for the section
            $section_fields = array_filter( $fields, function( $field ) use ( $section_key ) {
                return isset( $field[ 'section' ] ) && $field[ 'section' ] === $section_key;
            });

            // If there are fields for this section, add the section to the settings sections
            if ( ! empty( $section_fields ) ) {
                $settings_sections_to_add[] = $settings_section;
            }
        }

        // Iter the filtered sections
        foreach ( $settings_sections_to_add as $settings_section ) {
            add_settings_section(
                $settings_section[0],
                $settings_section[1] . ':',
                $settings_section[2],
                $slug
            );
        }
        
        /**
         * Fields
         */
        // Iter the fields
        foreach ( $fields as $field ) {
            $option_name = CCEVERYWHERE__TEXTDOMAIN.'_'.$field[ 'key' ];
            $callback = 'settings_field_'.$field[ 'type' ];
            $args = [
                'id'    => $option_name,
                'class' => $option_name,
                'name'  => $option_name,
                'key'   => $field[ 'key' ],
            ];

            // Add comments
            if ( isset( $field[ 'comments' ] ) && $field[ 'comments' ] != '' ) {
                $comments = '<br><span class="cceverywhere_action_desc">' . $field[ 'comments' ] . '</span>';
            } else {
                $comments = '';
            }
            
            // Add select options
            if ( isset( $field[ 'options' ] ) ) {
                $args[ 'options' ] = $field[ 'options' ];
            }

            // Add default
            if ( isset( $field[ 'default' ] ) ) {
                $args[ 'default' ] = $field[ 'default' ];
            }

            // Add revert
            if ( isset( $field[ 'revert' ] ) ) {
                $args[ 'revert' ] = $field[ 'revert' ];
            }

            // Add run context
            if ( isset( $field[ 'run_context' ] ) ) {
                $args[ 'run_context' ] = $field[ 'run_context' ];
            }

            // Add the field
            register_setting( $slug, $option_name, sanitize_key( $field[ 'sanitize' ] ) );
            add_settings_field( $option_name, $field[ 'title' ] . wp_kses_post( $comments ), [ $this, $callback ], $slug, $field[ 'section' ], $args );
        }
    } // End settings_fields()
  
    
    /**
     * Custom callback function to print text field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_text( $args ) {
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '43rem';
        $default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        $comments = isset( $args[ 'comments' ] ) ? '<br><p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            '<input type="text" id="%s" name="%s" value="%s" style="width: %s;" />%s',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( $value ),
            esc_attr( $width ),
            wp_kses_post( $comments )
        );
    } // settings_field_text()


    /**
     * Custom callback function to print password field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_password( $args ) {
        $width = isset( $args[ 'width' ] ) ? $args[ 'width' ] : '43rem';
        $default = isset( $args[ 'default' ] )  ? $args[ 'default' ] : '';
        $value = get_option( $args[ 'name' ], $default );
        if ( isset( $args[ 'revert' ] ) && $args[ 'revert' ] == true && trim( $value ) == '' ) {
            $value = $default;
        }
        $comments = isset( $args[ 'comments' ] ) ? '<br><p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            '<input type="password" id="%s" name="%s" value="%s" style="width: %s;" />%s',
            esc_attr( $args[ 'id' ] ),
            esc_attr( $args[ 'name' ] ),
            esc_html( $value ),
            esc_attr( $width ),
            wp_kses_post( $comments )
        );
    } // settings_field_password()


    /**
     * Custom callback function to print checkbox field
     *
     * @param array $args
     * @return void
     */
    public function settings_field_checkbox( $args ) {
        $value = get_option( $args[ 'name' ] );
        if ( false === $value && isset( $args[ 'default' ] ) ) {
            $value = $args[ 'default' ];
        }
        $value = $this->sanitize_checkbox( $value );

        // Output the checkbox
        printf(
            '<div class="cce-action-container"><input type="checkbox" id="%s" name="%s" value="1" %s/>',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            checked( 1, $value, false )
        );

        // Add the "Clear" button and result container for actions with run_context
        if ( isset( $args[ 'run_context' ] ) && isset( $args[ 'key' ] ) ) {
            $key = $args[ 'key' ];

            printf(
                ' <button type="button" class="cceverywhere-button cce-run-action-btn" data-key="%s">%s</button>',
                esc_attr( $key ),
                esc_html__( 'Clear', 'clear-cache-everywhere' )
            );

            // Determine if page query params exist and match this field
            $show_finalizing = false;
            if ( isset( $_GET[ 'cce_run_page' ] ) && $args[ 'run_context' ] === 'page' ) {
                $show_finalizing = true;
            } elseif ( isset( $_GET[ 'cce_run_single_page' ] ) && $_GET[ 'cce_run_single_page' ] === '1' ) {
                if ( isset( $_GET[ 'key' ] ) && $_GET[ 'key' ] === $key ) {
                    $show_finalizing = true;
                }
            }

            // Result text and class
            $result_text = '';
            $result_class = '';

            if ( $show_finalizing ) {
                $result_text = __( 'Finalizing...', 'clear-cache-everywhere' );
            } else {
                $last_results = get_option( 'clear_cache_everywhere_last_results', [] );

                if ( isset( $last_results[ $key ] ) ) {
                    $res = $last_results[ $key ];

                    if ( $res[ 'status' ] === 'running' && empty( $res[ 'end' ] ) ) {
                        $result_class = 'fail';
                        $result_text = __( 'Could not complete. Something went wrong.', 'clear-cache-everywhere' );
                    } elseif ( ! empty( $res[ 'end' ] ) ) {
                        $result_class = $res[ 'status' ] ?? 'fail';
                        $date_format = get_option( 'date_format' );
                        $time_format = get_option( 'time_format' );

                        $end_ts = (int) floor( $res[ 'end' ] );

                        $datetime = wp_date( $date_format . ' \a\t ' . $time_format, $end_ts );

                        $label = $res[ 'status' ] === 'success'
                            ? __( 'Last cleared on %s', 'clear-cache-everywhere' )
                            : __( 'Last attempted on %s', 'clear-cache-everywhere' );

                        $result_text = sprintf( $label, $datetime );

                        // append elapsed seconds if start is available
                        if ( ! empty( $res[ 'start' ] ) ) {
                            $elapsed = $res[ 'end' ] - $res[ 'start' ];
                            $result_text .= ' (' . number_format( $elapsed, 3 ) . 's)';
                        }

                        if ( $res[ 'status' ] !== 'info' ) {
                            $result_text .= ' - ' . strtoupper( $res[ 'status' ] );
                        }

                        if ( ! empty( $res[ 'error_message' ] ) ) {
                            $result_text .= ' - ' . $res[ 'error_message' ];
                        }
                    }
                }
            }

            // Run context class
            $run_context_class = 'run-context-' . esc_attr( $args[ 'run_context' ] );
            $enabled_class = $value ? 'enabled' : 'disabled';

            // Result container
            printf(
                '<div class="cce-action-result %s %s %s" id="cce-action-result-%s">%s</div>',
                esc_attr( $result_class ),
                esc_attr( $run_context_class ),
                esc_attr( $enabled_class ),
                esc_attr( $key ),
                esc_html( $result_text )
            );
        }

        echo '</div>';
    } // End settings_field_checkbox()


    /**
     * Sanitize checkbox
     *
     * @param int $value
     * @return boolean
     */
    public function sanitize_checkbox( $value ) {
        return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    } // End sanitize_checkbox()


    /**
     * Include the admin header for the plugin settings page.
     *
     * @return void
     */
    public function admin_header() {
        $screen = get_current_screen();
        if ( ! isset( $screen->id ) || $screen->id !== CCEVERYWHERE_SETTINGS_SCREEN_ID ) {
            return;
        }
        include CCEVERYWHERE_INCLUDES_ABSPATH . 'header.php';
    } // End admin_header()


    /**
     * Inherit colors from the Admin Help Docs plugin.
     *
     * @param array $colors
     * @return array
     */
    public function inherit_ahd_colors( $colors ) {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! is_plugin_active( 'admin-help-docs/admin-help-docs.php' ) ) {
            return $colors;
        }
        $ahd_colors = get_option( 'helpdocs_colors', [] );
        if ( ! is_array( $ahd_colors ) || empty( $ahd_colors ) ) {
            return $colors;
        }
        $map = [
            'header_bg'    => 'header-bg',
            'header_font'  => 'header-font',
            'button'       => 'button',
            'button_font'  => 'button-font',
            'button_hover' => 'button-hover',
        ];
        foreach ( $map as $ahd_key => $cce_key ) {
            if ( ! empty( $ahd_colors[ $ahd_key ] ) ) {
                $colors[ $cce_key ] = $ahd_colors[ $ahd_key ];
            }
        }
        return $colors;
    } // End inherit_ahd_colors()


    /**
     * Ajax save settings callback.
     *
     * @return void
     */
    public function ajax_save_settings() {
        if ( ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), $this->nonce ) ) {
            wp_send_json_error( [ 'msg' => __( 'Invalid nonce.', 'clear-cache-everywhere' ) ] );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'msg' => __( 'Unauthorized.', 'clear-cache-everywhere' ) ] );
        }

        foreach ( $this->get_settings_fields() as $field ) {
            $option_name = CCEVERYWHERE__TEXTDOMAIN . '_' . $field[ 'key' ];
            $value = isset( $_POST[ $option_name ] ) ? wp_unslash( $_POST[ $option_name ] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            if ( is_string( $value ) ) {
                $value = trim( $value );
            }
            update_option( $option_name, $value );
        }

        $clear = new Clear();

        wp_send_json_success( [
            'msg'                => __( 'Settings saved successfully.', 'clear-cache-everywhere' ),
            'clearing_actions'   => $clear->get_clearing_actions(),
            'cache_cleared_text' => $clear->get_cache_cleared_text(),
        ] );
    } // End ajax_save_settings()


    /**
     * Enqueue javascript
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( $hook !== CCEVERYWHERE_SETTINGS_SCREEN_ID ) {
            return;
        }

        $theme_handle = CCEVERYWHERE_TEXTDOMAIN . '-theme';
        wp_enqueue_style( $theme_handle, CCEVERYWHERE_CSS_PATH . 'theme.css', [], CCEVERYWHERE_SCRIPT_VERSION );

        $declarations = [];
        foreach ( apply_filters( 'cceverywhere_theme_colors', [] ) as $var => $value ) {
            $hex = sanitize_hex_color( $value );
            if ( $hex ) {
                $declarations[] = '--cceverywhere-color-' . sanitize_key( $var ) . ': ' . $hex . ';';
            }
        }
        if ( ! empty( $declarations ) ) {
            wp_add_inline_style( $theme_handle, ':root {' . implode( '', $declarations ) . '}' );
        }

        wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-settings', CCEVERYWHERE_CSS_PATH . 'settings.css', [ $theme_handle ], CCEVERYWHERE_SCRIPT_VERSION );

        $js_handle = CCEVERYWHERE_TEXTDOMAIN . '-settings-js';
        wp_enqueue_script( $js_handle, CCEVERYWHERE_JS_PATH . 'settings.js', [ 'jquery', CCEVERYWHERE_TEXTDOMAIN . '-clear' ], CCEVERYWHERE_SCRIPT_VERSION, true );
        wp_localize_script( $js_handle, 'cceverywhere_settings', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( $this->nonce ),
            'text'     => [
                'saving'       => __( 'Saving...', 'clear-cache-everywhere' ),
                'saved'        => __( 'Settings saved successfully.', 'clear-cache-everywhere' ),
                'error_saving' => __( 'Error saving settings.', 'clear-cache-everywhere' ),
            ],
        ] );
    } // End enqueue_scripts()

}
