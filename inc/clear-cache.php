<?php 
/**
 * Plugin settings
 */


/**
 * Define Namespaces
 */
namespace Apos37\ClearCache;
use Apos37\ClearCache\Settings;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
    (new Clear())->init();
} );


/**
 * The class
 */
class Clear {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'cceverywhere_nonce';


    /**
     * The query string parameter to call the action for clearing the cache
     *
     * @var string
     */
    public $action_param = 'clear-cache-now';


    /**
     * Load on init
     */
    public function init() {

        // Flush rewrite rules
        $this->clear_all();

        // On wp_loaded
        add_action( 'wp_loaded', [ $this, 'clear_all' ] );

        // Admin notices
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );

        // Front-end notices
        add_action( 'wp_footer', [ $this, 'front_notices' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


	/**
     * Clear all caches.
     *
     * Fires before and after hooks so developers can add custom cache-clearing logic.
     *
     * @return array Results of each cache-clearing action.
     */
    public function clear_all( $force = false, $log_results = false ) {
        if ( $force || 
            ( isset( $_GET[ $this->action_param ] ) && absint( wp_unslash( $_GET[ $this->action_param ] ) ) === 1 &&
              isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ '_wpnonce' ] ) ), $this->nonce ) ) ) {

            // Store results here
            $results = [];
        
            // Get the labels and set up the results
            $fields = (new Settings())->get_settings_fields();
            foreach ( $fields as $field ) {
                if ( $field[ 'key' ] == 'hosting_purge_url' ) {
                    continue;
                }
        
                $results[ $field[ 'key' ] ] = [
                    'title'  => $field[ 'title' ],
                    'result' => null
                ];
            }
        
            /**
             * Fires before cache clearing.
             */
            do_action( 'cceverywhere_before_clear_cache' );
        
            /**
             * Retrieve option names.
             * Each option is stored as CCEVERYWHERE_TEXTDOMAIN - key.
             */
            $prefix = CCEVERYWHERE__TEXTDOMAIN . '_';

            // Flush rewrite rules
            if ( get_option( $prefix . 'rewrite_rules', true ) ) {
                if ( delete_option( 'rewrite_rules' ) ) {
                    flush_rewrite_rules();
                    $results[ 'rewrite_rules' ][ 'result' ] = 'success';
                } else {
                    $results[ 'rewrite_rules' ][ 'result' ] = 'fail';
                }
            }
        
            // Clear WordPress Object Cache if enabled.
            if ( get_option( $prefix . 'wp_cache_flush', true ) ) {
                if ( wp_cache_flush() ) {
                    $results[ 'wp_cache_flush' ][ 'result' ] = 'success';
                } else {
                    $results[ 'wp_cache_flush' ][ 'result' ] = 'fail';
                }
            }
        
            // Clear transients if enabled.
            if ( get_option( $prefix . 'transients', true ) ) {
                global $wpdb;
                $transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ); // phpcs:ignore 

                if ( !empty( $transients ) ) {
                    foreach ( $transients as $transient ) {
                        $name = str_replace( '_transient_', '', $transient );
                        delete_transient( $name );
                    }
                    $results[ 'transients' ][ 'result' ] = 'success';
                } else {
                    $results[ 'transients' ][ 'result' ] = 'fail';
                }
            }
        
            // Destroy PHP sessions if enabled.
            if ( get_option( $prefix . 'sessions', true ) ) {
                if ( session_status() !== PHP_SESSION_NONE ) {
                    session_unset(); // Clear all session variables
                    session_destroy(); // Destroy the session
                    $_SESSION = []; // Ensure $_SESSION is empty

                    if ( empty( $_SESSION ) ) {
                        $results[ 'sessions' ][ 'result' ] = 'success';
                    } else {
                        $results[ 'sessions' ][ 'result' ] = 'fail';
                    }
                } else {
                    $results[ 'sessions' ][ 'result' ] = 'success';
                }
            }

            // Clear cookies
            if ( get_option( $prefix . 'cookies', true ) ) {
                if ( isset( $_COOKIE ) ) {
                    $sanitized_cookies = filter_var_array( $_COOKIE, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
                    
                    foreach ( $sanitized_cookies as $cookie_name => $cookie_value ) {
                        // Sanitize cookie name
                        $cookie_name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $cookie_name );
            
                        // Skip cookies related to logged-in user
                        if ( strpos( $cookie_name, 'wordpress_logged_in_' ) !== false ) {
                            continue;
                        }
            
                        setcookie( $cookie_name, '', time() - 3600, '/' );
                        unset( $_COOKIE[ $cookie_name ] );
                    }
            
                    $results[ 'cookies' ][ 'result' ] = 'success';
                } else {
                    $results[ 'cookies' ][ 'result' ] = 'fail';
                }
            }            
        
            // Force browser cache invalidation if enabled.
            if ( get_option( $prefix . 'browser_cache', true ) ) {
                if ( !headers_sent() ) {
                    @header( 'Cache-Control: no-cache, no-store, must-revalidate', true );
                    @header( 'Pragma: no-cache', true );
                    @header( 'Expires: 0', true );
                    $results[ 'browser_cache' ][ 'result' ] = 'success';
                } else {
                    $results[ 'browser_cache' ][ 'result' ] = 'fail';
                }
            }
        
            // Clear hosting-level cache if enabled and a purge URL is provided.
            if ( get_option( $prefix . 'hosting_cache', false ) ) {
                $purge_url = get_option( $prefix . 'hosting_purge_url' );
                if ( !empty( $purge_url ) ) {
                    $response = wp_remote_get( sanitize_url( $purge_url ) );
                    if ( !is_wp_error( $response ) ) {
                        $results[ 'hosting_cache' ][ 'result' ] = 'success';
                    } else {
                        $results[ 'hosting_cache' ][ 'result' ] = 'fail';
                    }
                }
            }
        
            /**
             * Integrations
             */
        
            if ( get_option( $prefix . 'cornerstone', true ) && is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
                do_action( 'cs_purge_tmp' );
                $results[ 'cornerstone' ][ 'result' ] = 'success';
            }

            if ( get_option( $prefix . 'elementor', true ) && is_plugin_active( 'elementor/elementor.php' ) ) {
                if ( class_exists( '\Elementor\Plugin' ) ) {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                    $results[ 'elementor' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'wp_super_cache', true ) && is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
                if ( function_exists( 'wp_cache_clear_cache' ) ) {
                    wp_cache_clear_cache();
                    $results[ 'wp_super_cache' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'w3_total_cache', true ) && is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
                if ( function_exists( 'w3tc_flush_all' ) ) {
                    w3tc_flush_all();
                    $results[ 'w3_total_cache' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'wp_rocket', true ) && is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
                if ( function_exists( 'rocket_clean_domain' ) ) {
                    rocket_clean_domain();
                    $results[ 'wp_rocket' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'litespeed_cache', true ) && is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
                do_action( 'litespeed_purge_all' );
                $results[ 'litespeed_cache' ][ 'result' ] = 'success';
            }

            if ( get_option( $prefix . 'sg_optimizer', true ) && is_plugin_active( 'sg-cachepress/sg-cachepress.php' ) ) {
                if ( class_exists( 'SG_CachePress_Supercacher' ) ) {
                    \SG_CachePress_Supercacher::purge_cache();
                    $results[ 'sg_optimizer' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'cloudflare', true ) && is_plugin_active( 'cloudflare/cloudflare.php' ) ) {
                if ( class_exists( 'CF\WordPress\Hooks' ) ) {
                    \CF\WordPress\Hooks::purgeCacheEverything();
                    $results[ 'cloudflare' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'autoptimize', true ) && is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
                if ( class_exists( 'autoptimizeCache' ) ) {
                    $autoptimize_cache = new autoptimizeCache();
                    $autoptimize_cache->clearall();
                    $results[ 'autoptimize' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'swift_performance', true ) && 
                ( is_plugin_active( 'swift-performance-lite/performance.php' ) || is_plugin_active( 'swift-performance/performance.php' ) ) ) {
                if ( function_exists( 'swift_performance_cache_clear' ) ) {
                    swift_performance_cache_clear();
                    $results[ 'swift_performance' ][ 'result' ] = 'success';
                }
            }
            
            if ( get_option( $prefix . 'comet_cache', true ) && is_plugin_active( 'comet-cache/comet-cache.php' ) ) {
                if ( function_exists( 'comet_cache_clear_cache' ) ) {
                    comet_cache_clear_cache();
                    $results[ 'comet_cache' ][ 'result' ] = 'success';
                }
            }
            
            if ( get_option( $prefix . 'wp_fastest_cache', true ) && is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
                if ( function_exists( 'wpfc_clear_cache' ) ) {
                    wpfc_clear_cache();
                    $results[ 'wp_fastest_cache' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'hummingbird_cache', true ) && is_plugin_active( 'hummingbird-performance/hummingbird.php' ) ) {
                if ( function_exists( 'Hummingbird\Cache::clear_all_cache' ) ) {
                    \Hummingbird\Cache::clear_all_cache();
                    $results[ 'hummingbird_cache' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'nginx_helper', true ) && is_plugin_active( 'nginx-helper/nginx-helper.php' ) ) {
                if ( function_exists( 'nginx_helper_flush_cache' ) ) {
                    nginx_helper_flush_cache();
                    $results[ 'nginx_helper' ][ 'result' ] = 'success';
                }
            }

            if ( get_option( $prefix . 'wp_optimize', true ) && is_plugin_active( 'wp-optimize/wp-optimize.php' ) ) {
                if ( function_exists( 'wp_optimize_clear_cache' ) ) {
                    wp_optimize_clear_cache();
                    $results[ 'wp_optimize' ][ 'result' ] = 'success';
                }
            }
            
            /**
             * Fires after cache clearing.
             */
            do_action( 'cceverywhere_after_clear_cache' );

            /**
             * What to do with results
             */
            if ( $force ) {
                if ( $log_results ) {
                    $this->log_notices( $results );
                }
                return $results;
            } else {
                set_transient( 'cce_clear_cache_results', $results, 60 );
                wp_safe_redirect( remove_query_arg( [ $this->action_param, '_wpnonce' ] ) );
                exit;
            }
        }
    } // End clear_all()


    /**
     * Allow hook to show skipped notice
     *
     * @return boolean
     */
    public function show_skipped_notice() {
        return filter_var( apply_filters( 'cceverywhere_show_skipped_notice', FALSE ), FILTER_VALIDATE_BOOLEAN );
    } // End show_skipped_notice()


    /**
     * Parse the results into separate arrays
     *
     * @param array $results
     * @return array
     */
    public function parse_results( $results ) {
        // Prepare messages
        $success_items = [];
        $fail_items = [];
        $skipped_items = [];

        foreach ( $results as $key => $result ) {
            if ( $result[ 'result' ] === 'success' ) {
                $success_items[] = '<strong>' . esc_html( $result[ 'title' ] ) . '</strong>';
            } elseif ( $result[ 'result' ] === 'fail' ) {
                // translators: the permalinks page url
                $incl_inst = ( $key == 'rewrite_rules' ) ? ' (' . sprintf( __( 'This happens sometimes. You can also flush rewrite rules by simply resaving your <a href="%s">permalinks settings page</a>.', 'clear-cache-everywhere' ), get_admin_url( null, 'options-permalink.php' ) ) . ')' : '';
                $fail_items[] = '<strong>' . esc_html( $result[ 'title' ] ) . $incl_inst . '</strong>';
            } else {
                $skipped_items[] = '<strong>' . esc_html( $result[ 'title' ] ) . '</strong>';
            }
        }

        return [
            'success' => $success_items,
            'fail'    => $fail_items,
            'skipped' => $skipped_items
        ];
    } // End parse_results()


    /**
     * Log all notices
     *
     * @return void
     */
    public function log_notices( $results ) {
        // Prepare messages
        $parsed_results = $this->parse_results( $results );
        $success_items = $parsed_results[ 'success' ];
        $fail_items = $parsed_results[ 'fail' ];
        $skipped_items = $parsed_results[ 'skipped' ];

        if ( !empty( $success_items ) ) {
            error_log( CCEVERYWHERE_NAME . ': ' . __( 'Successfully cleared:', 'clear-cache-everywhere' ) . ' ' . implode( ', ', $success_items ) ); // phpcs:ignore 
        }

        if ( !empty( $fail_items ) ) {
            error_log( CCEVERYWHERE_NAME . ': ' . __( 'Failed to clear:', 'clear-cache-everywhere' ) . ' ' . implode( ', ', $fail_items ) ); // phpcs:ignore 
        }

        if ( $this->show_skipped_notice() && !empty( $skipped_items ) ) {
            error_log( CCEVERYWHERE_NAME . ': ' . __( 'Skipped:', 'clear-cache-everywhere' ) . ' ' . implode( ', ', $skipped_items ) ); // phpcs:ignore 
        }
    } // End log_notices()


    /**
     * Admin notices
     *
     * @return void
     */
    public function admin_notices() {
        if ( $results = get_transient( 'cce_clear_cache_results' ) ) {

            // Delete the transient so it only appears once
            delete_transient( 'cce_clear_cache_results' );

            // Messages
            $parsed_results = $this->parse_results( $results );
            $success_items = $parsed_results[ 'success' ];
            $fail_items = $parsed_results[ 'fail' ];
            $skipped_items = $parsed_results[ 'skipped' ];

            if ( !empty( $success_items ) ) {
                printf(
                    '<div class="notice notice-success cce-clear-cache-message"><p>%s: %s</p></div>',
                    esc_html__( 'Successfully cleared', 'clear-cache-everywhere' ),
                    wp_kses_post( implode( ', ', $success_items ) )
                );
            }

            if ( !empty( $fail_items ) ) {
                printf(
                    '<div class="notice notice-error cce-clear-cache-message"><p>%s: %s</p></div>',
                    esc_html__( 'Failed to clear', 'clear-cache-everywhere' ),
                    wp_kses_post( implode( ', ', $fail_items ) )
                );
            }

            if ( $this->show_skipped_notice() && !empty( $skipped_items ) ) {
                printf(
                    '<div class="notice notice-info cce-clear-cache-message"><p>%s: %s</p></div>',
                    esc_html__( 'Skipped', 'clear-cache-everywhere' ),
                    wp_kses_post( implode( ', ', $skipped_items ) )
                );
            }
        }
    } // End admin_notices()


    /**
     * Front-end notices
     *
     * @return void
     */
    public function front_notices() {
        if ( $results = get_transient( 'cce_clear_cache_results' ) ) {

            // Delete the transient so it only appears once
            delete_transient( 'cce_clear_cache_results' );

            // Prepare messages
            $parsed_results = $this->parse_results( $results );
            $success_items = $parsed_results[ 'success' ];
            $fail_items = $parsed_results[ 'fail' ];
            $skipped_items = $parsed_results[ 'skipped' ];

            echo '<div class="clear-cache-everywhere-notices">';

                if ( !empty( $success_items ) ) {
                    printf(
                        '<div class="cce-clear-cache-message success"><p>%s: %s</p></div>',
                        esc_html__( 'Successfully cleared', 'clear-cache-everywhere' ),
                        wp_kses_post( implode( ', ', $success_items ) )
                    );
                }
        
                if ( !empty( $fail_items ) ) {
                    printf(
                        '<div class="cce-clear-cache-message error"><p>%s: %s</p></div>',
                        esc_html__( 'Failed to clear', 'clear-cache-everywhere' ),
                        wp_kses_post( implode( ', ', $fail_items ) )
                    );
                }
        
                if ( $this->show_skipped_notice() && !empty( $skipped_items ) ) {
                    printf(
                        '<div class="cce-clear-cache-message info"><p>%s: %s</p></div>',
                        esc_html__( 'Skipped', 'clear-cache-everywhere' ),
                        wp_kses_post( implode( ', ', $skipped_items ) )
                    );
                }

            echo '</div>';
        }
    } // End front_notices()


    /**
	 * Enqueue JQuery
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
        if ( get_transient( 'cce_clear_cache_results' ) ) {

            // Enqueue jquery
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( CCEVERYWHERE_TEXTDOMAIN . '-js', CCEVERYWHERE_JS_PATH . 'front-notices.js', [ 'jquery' ], time(), true );
            
            // Enqueue css
            wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-css', CCEVERYWHERE_CSS_PATH . 'front-notices.css', [], time() );
        }
    } // End enqueue_scripts()

}