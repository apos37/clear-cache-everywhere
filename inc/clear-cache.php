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
if ( ! defined( 'ABSPATH' ) ) exit;


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
     * Option key for storing the last cache-clearing results
     *
     * @var string
     */
    public $option_key = 'clear_cache_everywhere_last_results';


    /**
     * Load on init
     */
    public function init() {

        // On wp_loaded
        add_action( 'wp_loaded', [ $this, 'clear_all' ] );

        // Register AJAX hooks
        add_action( 'wp_ajax_cceverywhere_clear_ajax_action', [ $this, 'ajax_clear_action' ] );
        add_action( 'wp_ajax_cceverywhere_run_page_actions', [ $this, 'ajax_run_page_actions' ] );
        add_action( 'wp_ajax_cceverywhere_run_single_page_action', [ $this, 'ajax_run_single_page_action' ] );

        // Enqueue script
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


    /**
     * Clear all caches if the correct query parameters are present.
     *
     * @param bool $force Optional. If true, forces cache clearing without checking query parameters. Default false.
     * @return array|null Returns an array of results if $force is true, otherwise null.
     */
    public function clear_all( $force = false ) {
        if ( ! $force &&
            ( ! isset( $_GET[ $this->action_param ] ) || absint( wp_unslash( $_GET[ $this->action_param ] ) ) !== 1 ||
            ! isset( $_GET[ '_wpnonce' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ '_wpnonce' ] ) ), $this->nonce ) )
        ) {
            return;
        }

        /**
         * Fires before cache clearing.
         */
        do_action( 'cceverywhere_before_clear_cache' );

        /**
         * Run each action
         */
        $clearing_actions = $this->get_clearing_actions();

        foreach ( $clearing_actions as $action ) {
            if ( ! $action[ 'enabled' ] ) {
                $this->update_clear_action( $action[ 'key' ], [
                    'start'         => microtime( true ),
                    'end'           => microtime( true ),
                    'status'        => 'skipped',
                    'error_message' => __( 'Action disabled in settings.', 'clear-cache-everywhere' )
                ] );
                continue;
            }

            $key = $action[ 'key' ];
            $dev_callback = $action[ 'callback' ];

            // Determine the callback
            if ( ! empty( $dev_callback ) ) {
                if ( is_callable( $dev_callback ) ) {
                    $callback = $dev_callback;
                } else {
                    $this->update_clear_action( $key, [
                        'start'         => microtime( true ),
                        'end'           => microtime( true ),
                        'status'        => 'skipped',
                        'error_message' => __( 'Provided callback is not callable.', 'clear-cache-everywhere' )
                    ] );
                    continue;
                }
            } else {
                $method = 'clear_' . $key;
                if ( ! method_exists( $this, $method ) ) {
                    $this->update_clear_action( $key, [
                        'start'         => microtime( true ),
                        'end'           => microtime( true ),
                        'status'        => 'skipped',
                        'error_message' => __( 'Method does not exist.', 'clear-cache-everywhere' )
                    ] );
                    continue;
                }
                $callback = [ $this, $method ];
            }

            // Mark as running
            $this->update_clear_action( $key, [
                'start'         => microtime( true ),
                'status'        => 'running',
                'error_message' => null
            ] );

            // Execute the callback with try/catch
            try {
                $result = call_user_func( $callback );

                $status = $result[ 'status' ] ?? 'fail';
                $error  = $result[ 'error_message' ] ?? null;
            } catch ( \Throwable $e ) {
                $status = 'fail';
                $error  = $e->getMessage();
            }

            // Update action with final result
            $this->update_clear_action( $key, [
                'end'           => microtime( true ),
                'status'        => $status,
                'error_message' => $error
            ] );
        }

        /**
         * Fires after cache clearing.
         */
        do_action( 'cceverywhere_after_clear_cache' );

        /**
         * If this was a page request, redirect to remove nonce/action
         */
        if ( ! $force ) {
            wp_safe_redirect( remove_query_arg( [ $this->action_param, '_wpnonce' ] ) );
            exit;
        }
    } // End clear_all()


    /**
     * Return only actionable fields with run_context and enabled status.
     *
     * @return array List of actions with keys: key, title, run_context, enabled.
     */
    public function get_clearing_actions() {
        $fields = ( new Settings() )->get_settings_fields();
        $actions = [];

        foreach ( $fields as $field ) {
            if ( empty( $field[ 'run_context' ] ) ) {
                continue;
            }

            $enabled = (bool) get_option( CCEVERYWHERE__TEXTDOMAIN . '_' . $field[ 'key' ], $field[ 'default' ] ?? false );

            $actions[] = [
                'key'        => $field[ 'key' ],
                'title'      => $field[ 'title' ],
                'run_context'=> $field[ 'run_context' ],
                'callback'   => $field[ 'callback' ] ?? null,
                'enabled'    => $enabled
            ];
        }

        return $actions;
    } // End get_clearing_actions()


    /**
     * Update the status of a clear action in the options table.
     *
     * @param string $key The key of the clear action (e.g., 'rewrite_rules').
     * @param array $data An associative array containing the data to update (e.g., ['status' => 'success', 'error_message' => null]).
     */
    private function update_clear_action( $key, $data ) {
        // Retrieve current results
        $results = get_option( $this->option_key, [] );

        // Ensure the action exists
        if ( ! isset( $results[ $key ] ) ) {
            $results[ $key ] = [
                'start'         => null,
                'end'           => null,
                'status'        => 'skipped',
                'error_message' => null,
            ];
        }

        // Update only the keys provided in $data
        foreach ( $data as $k => $v ) {
            $results[ $key ][ $k ] = $v;
        }

        // Save back to the option
        update_option( $this->option_key, $results );
    } // End update_clear_action()


    /**
     * Clear rewrite rules
     *
     * @return array
     */
    public function clear_rewrite_rules() {
        if ( ! delete_option( 'rewrite_rules' ) ) {
            return [ 'status' => 'fail', 'error_message' => 'Failed to delete rewrite_rules option.' ];
        }

        flush_rewrite_rules();
        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_rewrite_rules()


    /**
     * Clear WordPress Object Cache
     *
     * @return array
     */
    public function clear_wp_cache_flush() {
        if ( wp_cache_flush() ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'wp_cache_flush() returned false.' ];
    } // End clear_wp_cache_flush()


    /**
     * Clear transients
     *
     * @return array
     */
    public function clear_transients() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '\_transient\_%'
            OR option_name LIKE '\_site\_transient\_%'"
        );

        if ( false === $deleted ) {
            return [ 'status' => 'fail', 'error_message' => 'Failed to delete transients from database.' ];
        }

        wp_cache_flush();

        // $transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" ); // phpcs:ignore 

        // if ( !empty( $transients ) ) {
        //     foreach ( $transients as $transient ) {
        //         $name = str_replace( '_transient_', '', $transient );
        //         delete_transient( $name );
        //     }
        //     $results[ 'transients' ][ 'result' ] = 'success';
        // } else {
        //     $results[ 'transients' ][ 'result' ] = 'fail';
        // }
        
        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_transients()

    
    /**
     * Clear PHP sessions
     *
     * @return array
     */
    public function clear_opcache_reset() {
        if ( ! function_exists( 'opcache_reset' ) ) {
            return [ 'status' => 'fail', 'error_message' => 'OPcache not available.' ];
        }

        if ( opcache_reset() ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'opcache_reset() failed.' ];
    } // End clear_opcache_reset()


    /**
     * Clear Varnish cache
     *
     * @return array
     */
    public function clear_varnish() {
        $home_url = home_url();
        $host = wp_parse_url( $home_url, PHP_URL_HOST );

        $response = wp_remote_head( $home_url );
        if ( is_wp_error( $response ) ) {
            return [ 'status' => 'info', 'error_message' => 'Unable to detect Varnish headers.' ];
        }

        $headers = wp_remote_retrieve_headers( $response );

        $has_varnish =
            isset( $headers[ 'x-varnish' ] ) ||
            (
                isset( $headers[ 'via' ] ) &&
                strpos( strtolower( $headers[ 'via' ] ), 'varnish' ) !== false
            );

        if ( ! $has_varnish ) {
            return [ 'status' => 'info', 'error_message' => 'Varnish not detected.' ];
        }

        $purge_response = wp_remote_request(
            $home_url,
            [
                'method'  => 'PURGE',
                'headers' => [
                    'Host' => $host,
                ],
            ]
        );

        if ( is_wp_error( $purge_response ) ) {
            return [ 'status' => 'fail', 'error_message' => 'Varnish PURGE request failed.' ];
        }

        $status_code = wp_remote_retrieve_response_code( $purge_response );

        if ( ! in_array( $status_code, [ 200, 204 ], true ) ) {
            return [ 'status' => 'fail', 'error_message' => 'Unexpected Varnish response code: ' . $status_code ];
        }

        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_varnish()


    /**
     * Clear Redis/Memcached
     *
     * @return array
     */
    public function clear_redis_memcached() {
        global $wp_object_cache;

        // Redis
        if ( class_exists( 'Redis' ) && method_exists( $wp_object_cache, 'redis_instance' ) ) {
            $redis = $wp_object_cache->redis_instance();
            if ( $redis instanceof \Redis ) {
                if ( $redis->flushAll() ) {
                    return [ 'status' => 'success', 'error_message' => null ];
                }

                return [ 'status' => 'fail', 'error_message' => 'Redis flushAll() failed.' ];
            }
        }

        // Memcached
        if ( class_exists( 'Memcached' ) && isset( $wp_object_cache->m ) ) {
            if ( $wp_object_cache->m instanceof \Memcached ) {
                if ( $wp_object_cache->m->flush() ) {
                    return [ 'status' => 'success', 'error_message' => null ];
                }

                return [ 'status' => 'fail', 'error_message' => 'Memcached flush() failed.' ];
            }
        }

        // Memcache (legacy)
        if ( class_exists( 'Memcache' ) && isset( $wp_object_cache->m ) ) {
            if ( $wp_object_cache->m instanceof \Memcache ) {
                if ( $wp_object_cache->m->flush() ) {
                    return [ 'status' => 'success', 'error_message' => null ];
                }

                return [ 'status' => 'fail', 'error_message' => 'Memcache flush() failed.' ];
            }
        }

        // Fallback
        if ( wp_cache_flush() ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'No persistent cache backend flushed.' ];
    } // End clear_redis_memcached()


    /**
     * Clear Fragment Cache
     *
     * @return array
     */
    public function clear_fragment_cache() {
        global $wpdb;

        // Only target actual fragment cache options, ignoring this plugin's own key
        $prefixes = [
            '_fragment_',       // standard fragment cache keys
            '_fragment_cache_', // some plugins use this
        ];

        $like_clauses = [];
        foreach ( $prefixes as $prefix ) {
            $like_clauses[] = $wpdb->prepare( "option_name LIKE %s", '%' . $wpdb->esc_like( $prefix ) . '%' );
        }

        // Ignore this plugin's own fragment cache option
        $not_like = $wpdb->prepare( "option_name NOT LIKE %s", '%clear_cache_everywhere_fragment_cache%' );

        $query = "DELETE FROM {$wpdb->options} WHERE (" . implode( ' OR ', $like_clauses ) . ") AND {$not_like}";

        $deleted = $wpdb->query( $query );

        if ( false !== $deleted ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'Failed to clear fragment cache.' ];
    } // End clear_fragment_cache()


    /**
     * Clear REST API Cache
     *
     * @return array
     */
    public function clear_rest_api_cache() {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '%rest\_cache%'
            OR option_name LIKE '%api\_cache%'"
        );

        do_action( 'cceverywhere_clear_rest_cache' );

        if ( false !== $deleted || did_action( 'cceverywhere_clear_rest_cache' ) ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'REST API cache clear failed.' ];
    } // End clear_rest_api_cache()


    /**
     * Clear hosting-level cache via purge URL
     *
     * @return array
     */
    public function clear_hosting_cache() {
        $purge_url = get_option( CCEVERYWHERE__TEXTDOMAIN . '_hosting_purge_url' );

        if ( empty( $purge_url ) ) {
            return [ 'status' => 'skipped', 'error_message' => 'No hosting purge URL configured.' ];
        }

        $response = wp_remote_get( sanitize_url( $purge_url ) );

        if ( is_wp_error( $response ) ) {
            return [ 'status' => 'fail', 'error_message' => $response->get_error_message() ];
        }

        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_hosting_cache()


    /**
     * Clear PHP sessions
     *
     * @return array
     */
    public function clear_sessions() {
        if ( session_status() === PHP_SESSION_NONE ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        session_unset();
        session_destroy();
        $_SESSION = [];

        if ( empty( $_SESSION ) ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        return [ 'status' => 'fail', 'error_message' => 'Session data still present after destroy.' ];
    } // End clear_sessions()


    /**
     * Clear cookies
     *
     * @return array
     */
    public function clear_cookies() {
        if ( empty( $_COOKIE ) ) {
            return [ 'status' => 'success', 'error_message' => null ];
        }

        $sanitized_cookies = filter_var_array( $_COOKIE, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

        foreach ( $sanitized_cookies as $cookie_name => $cookie_value ) {
            $cookie_name = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $cookie_name );

            if ( strpos( $cookie_name, 'wordpress_logged_in_' ) !== false ) {
                continue;
            }

            setcookie( $cookie_name, '', time() - 3600, '/' );
            unset( $_COOKIE[ $cookie_name ] );
        }

        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_cookies()


    /**
     * Clear browser cache
     *
     * @return array
     */
    public function clear_browser_cache() {
        if ( headers_sent() ) {
            return [ 'status' => 'fail', 'error_message' => 'Headers already sent.' ];
        }

        header( 'Cache-Control: no-cache, no-store, must-revalidate', true );
        header( 'Pragma: no-cache', true );
        header( 'Expires: 0', true );

        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_browser_cache()


    /**
     * Clear Cornerstone cache
     *
     * @return array
     */
    public function clear_cornerstone() {
        do_action( 'cs_purge_tmp' );
        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_cornerstone()


    /**
     * Clear Elementor cache
     *
     * @return array
     */
    public function clear_elementor() {
        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Elementor class not found.' ];
    } // End clear_elementor()


    /**
     * Clear WP Super Cache
     *
     * @return array
     */
    public function clear_wp_super_cache() {
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function wp_cache_clear_cache() not available.' ];
    } // End clear_wp_super_cache()


    /**
     * Clear W3 Total Cache
     *
     * @return array
     */
    public function clear_w3_total_cache() {
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function w3tc_flush_all() not available.' ];
    } // End clear_w3_total_cache()


    /**
     * Clear WP Rocket cache
     *
     * @return array
     */
    public function clear_wp_rocket() {
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function rocket_clean_domain() not available.' ];
    } // End clear_wp_rocket()


    /**
     * Clear LiteSpeed Cache
     *
     * @return array
     */
    public function clear_litespeed_cache() {
        do_action( 'litespeed_purge_all' );
        return [ 'status' => 'success', 'error_message' => null ];
    } // End clear_litespeed_cache()


    /**
     * Clear SG Optimizer cache
     *
     * @return array
     */
    public function clear_sg_optimizer() {
        if ( class_exists( 'SG_CachePress_Supercacher' ) ) {
            \SG_CachePress_Supercacher::purge_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'SG_CachePress_Supercacher class not found.' ];
    } // End clear_sg_optimizer()


    /**
     * Clear Cloudflare cache
     *
     * @return array
     */
    public function clear_cloudflare() {
        if ( class_exists( 'CF\WordPress\Hooks' ) ) {
            \CF\WordPress\Hooks::purgeCacheEverything();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Cloudflare Hooks class not found.' ];
    } // End clear_cloudflare()


    /**
     * Clear Autoptimize cache
     *
     * @return array
     */
    public function clear_autoptimize() {
        if ( class_exists( 'autoptimizeCache' ) ) {
            $autoptimize_cache = new autoptimizeCache();
            $autoptimize_cache->clearall();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'autoptimizeCache class not found.' ];
    } // End clear_autoptimize()


    /**
     * Clear Swift Performance cache
     *
     * @return array
     */
    public function clear_swift_performance() {
        if ( function_exists( 'swift_performance_cache_clear' ) ) {
            swift_performance_cache_clear();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function swift_performance_cache_clear() not available.' ];
    } // End clear_swift_performance()


    /**
     * Clear Comet Cache
     *
     * @return array
     */
    public function clear_comet_cache() {
        if ( function_exists( 'comet_cache_clear_cache' ) ) {
            comet_cache_clear_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function comet_cache_clear_cache() not available.' ];
    } // End clear_comet_cache()


    /**
     * Clear WP Fastest Cache
     *
     * @return array
     */
    public function clear_wp_fastest_cache() {
        if ( function_exists( 'wpfc_clear_cache' ) ) {
            wpfc_clear_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function wpfc_clear_cache() not available.' ];
    } // End clear_wp_fastest_cache()


    /**
     * Clear Hummingbird Cache
     *
     * @return array
     */
    public function clear_hummingbird_cache() {
        if ( function_exists( 'Hummingbird\Cache::clear_all_cache' ) ) {
            \Hummingbird\Cache::clear_all_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function Hummingbird\Cache::clear_all_cache() not available.' ];
    } // End clear_hummingbird_cache()


    /**
     * Clear Nginx Helper cache
     *
     * @return array
     */
    public function clear_nginx_helper() {
        if ( function_exists( 'nginx_helper_flush_cache' ) ) {
            nginx_helper_flush_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function nginx_helper_flush_cache() not available.' ];
    } // End clear_nginx_helper()


    /**
     * Clear WP Optimize cache
     *
     * @return array
     */
    public function clear_wp_optimize() {
        if ( function_exists( 'wp_optimize_clear_cache' ) ) {
            wp_optimize_clear_cache();
            return [ 'status' => 'success', 'error_message' => null ];
        }
        return [ 'status' => 'fail', 'error_message' => 'Function wp_optimize_clear_cache() not available.' ];
    } // End clear_wp_optimize()


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
     * Run a single AJAX action
     */
    public function ajax_clear_action() {
        check_ajax_referer( $this->nonce, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'clear-cache-everywhere' ) );
        }

        $key = sanitize_text_field( wp_unslash( $_POST[ 'key' ] ?? '' ) );
        if ( ! $key ) {
            wp_send_json_error( __( 'Missing action key.', 'clear-cache-everywhere' ) );
        }

        $clearing_actions = $this->get_clearing_actions();
        $action = null;
        foreach ( $clearing_actions as $a ) {
            if ( $a[ 'key' ] === $key && $a[ 'run_context' ] === 'ajax' ) {
                $action = $a;
                break;
            }
        }

        if ( ! $action ) {
            wp_send_json_error( __( 'Invalid or non-AJAX action.', 'clear-cache-everywhere' ) );
        }

        // Determine callback
        $dev_callback = $action[ 'callback' ];
        if ( ! empty( $dev_callback ) && is_callable( $dev_callback ) ) {
            $callback = $dev_callback;
        } else {
            $method = 'clear_' . $key;
            if ( ! method_exists( $this, $method ) ) {
                $this->update_clear_action( $key, [
                    'start'         => time(),
                    'end'           => time(),
                    'status'        => 'skipped',
                    'error_message' => __( 'Method does not exist.', 'clear-cache-everywhere' )
                ] );
                wp_send_json_error( __( 'Method does not exist.', 'clear-cache-everywhere' ) );
            }
            $callback = [ $this, $method ];
        }

        // Mark running
        $start_time = microtime( true );
        $this->update_clear_action( $key, [
            'start'         => $start_time,
            'status'        => 'running',
            'error_message' => null
        ] );

        // Execute
        try {
            $result = call_user_func( $callback );
            $status = $result[ 'status' ] ?? 'fail';
            $error  = $result[ 'error_message' ] ?? null;
        } catch ( \Throwable $e ) {
            $status = 'fail';
            $error  = $e->getMessage();
        }

        // Update final
        $end_time = microtime( true );
        $this->update_clear_action( $key, [
            'end'           => $end_time,
            'status'        => $status,
            'error_message' => $error
        ] );

        wp_send_json_success( [
            'key'           => $key,
            'status'        => $status,
            'error_message' => $error,
            'start'         => $start_time,
            'end'           => $end_time,
            'datetime'      => wp_date( 'F j, Y \a\t g:i a', (int) floor( $end_time ) )
        ] );
    } // End ajax_clear_action()


    /**
     * Run all page-context actions
     */
    public function ajax_run_page_actions() {
        check_ajax_referer( $this->nonce, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'clear-cache-everywhere' ) );
        }

        $clearing_actions = $this->get_clearing_actions();
        $results = [];

        foreach ( $clearing_actions as $action ) {
            if ( $action[ 'enabled' ] !== true ) {
                continue;
            }

            $key = $action[ 'key' ] ?? null;
            $run_context = $action[ 'run_context' ] ?? null;

            if ( ! $key || ! $run_context || $run_context !== 'page' ) {
                continue;
            }

            if ( isset( $run_context[ 'callback' ] ) && is_callable( $run_context[ 'callback' ] ) ) {
                $callback = $run_context[ 'callback' ];
            } else {
                $method = 'clear_' . $key;
                if ( ! method_exists( $this, $method ) ) {
                    $time_now = microtime( true );
                    $this->update_clear_action( $key, [
                        'start'         => $time_now,
                        'end'           => $time_now,
                        'status'        => 'skipped',
                        'error_message' => __( 'Method does not exist.', 'clear-cache-everywhere' )
                    ] );
                    $results[ $key ] = [
                        'status'        => 'skipped',
                        'error_message' => __( 'Method does not exist.', 'clear-cache-everywhere' ),
                        'start'         => $time_now,
                        'end'           => $time_now,
                        'datetime'      => wp_date( 'F j, Y \a\t g:i a', (int) floor( $time_now ) )
                    ];
                    continue;
                }
                $callback = [ $this, $method ];
            }

            $start_time = microtime( true );
            $this->update_clear_action( $key, [
                'start'         => $start_time,
                'status'        => 'running',
                'error_message' => null
            ] );

            try {
                $result = call_user_func( $callback );
                $status = $result[ 'status' ] ?? 'fail';
                $error  = $result[ 'error_message' ] ?? null;
            } catch ( \Throwable $e ) {
                $status = 'fail';
                $error  = $e->getMessage();
            }

            $end_time = microtime( true );
            $this->update_clear_action( $key, [
                'end'           => $end_time,
                'status'        => $status,
                'error_message' => $error
            ] );

            $results[ $key ] = [
                'status'        => $status,
                'error_message' => $error,
                'start'         => $start_time,
                'end'           => $end_time,
                'datetime'      => wp_date( 'F j, Y \a\t g:i a', (int) floor( $end_time ) )
            ];
        }

        wp_send_json_success( $results );
    } // End ajax_run_page_actions()


    /**
     * Run a single page or AJAX action
     */
    public function ajax_run_single_page_action() {
        check_ajax_referer( $this->nonce, 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'clear-cache-everywhere' ) );
        }

        $key = sanitize_text_field( wp_unslash( $_POST[ 'key' ] ?? '' ) );
        if ( ! $key ) {
            wp_send_json_error( __( 'Missing action key.', 'clear-cache-everywhere' ) );
        }

        $clearing_actions = $this->get_clearing_actions();
        $action = null;
        foreach ( $clearing_actions as $action_item ) {
            if ( $action_item[ 'key' ] === $key ) {
                $action = $action_item;
                break;
            }
        }

        if ( ! $action ) {
            wp_send_json_error( __( 'Invalid action key.', 'clear-cache-everywhere' ) );
        }

        // Determine callback
        $callback = null;
        if ( isset( $action[ 'callback' ] ) && is_callable( $action[ 'callback' ] ) ) {
            $callback = $action[ 'callback' ];
        } else {
            $method = 'clear_' . $key;
            if ( method_exists( $this, $method ) ) {
                $callback = [ $this, $method ];
            } else {
                $time_now = microtime( true );
                $this->update_clear_action( $key, [
                    'start'         => $time_now,
                    'end'           => $time_now,
                    'status'        => 'skipped',
                    'error_message' => __( 'Method does not exist.', 'clear-cache-everywhere' )
                ] );
                wp_send_json_error( __( 'Method does not exist.', 'clear-cache-everywhere' ) );
            }
        }

        // Mark running
        $start_time = microtime( true );
        $this->update_clear_action( $key, [
            'start'         => $start_time,
            'status'        => 'running',
            'error_message' => null
        ] );

        // Execute
        try {
            $result = call_user_func( $callback );
            $status = $result[ 'status' ] ?? 'fail';
            $error  = $result[ 'error_message' ] ?? null;
        } catch ( \Throwable $e ) {
            $status = 'fail';
            $error  = $e->getMessage();
        }

        // Update final state
        $end_time = microtime( true );
        $this->update_clear_action( $key, [
            'end'           => $end_time,
            'status'        => $status,
            'error_message' => $error
        ] );

        wp_send_json_success( [
            'key'           => $key,
            'status'        => $status,
            'error_message' => $error,
            'start'         => $start_time,
            'end'           => $end_time,
            'datetime'      => wp_date( 'F j, Y \a\t g:i a', (int) floor( $end_time ) )
        ] );
    } // End ajax_run_single_page_action()


    /**
	 * Enqueue JQuery
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook = '' ) {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( CCEVERYWHERE_TEXTDOMAIN . '-clear', CCEVERYWHERE_JS_PATH . 'clear-cache.js', [ 'jquery' ], CCEVERYWHERE_SCRIPT_VERSION, true );

        $clearing_actions = $this->get_clearing_actions();
        $page_context_titles = [];
        foreach ( $clearing_actions as $action ) {
            if ( $action[ 'enabled' ] && $action[ 'run_context' ] === 'page' ) {
                $page_context_titles[] = $action[ 'title' ];
            }
        }
        if ( empty( $page_context_titles ) ) {
            $cache_cleared_text = __( 'All done. Reloading page now...', 'clear-cache-everywhere' );
        } else {
            $cache_cleared_text = sprintf( __( 'Reloading page now to clear %s...', 'clear-cache-everywhere' ), implode( ', ', $page_context_titles ) );
        }

        wp_localize_script( CCEVERYWHERE_TEXTDOMAIN . '-clear', 'cceverywhere_ajax', [
            'ajax_url'         => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( $this->nonce ),
            'is_settings_page' => ( is_admin() && $hook === CCEVERYWHERE_SETTINGS_SCREEN_ID ),
            'clearing_actions' => $this->get_clearing_actions(),
            'text'             => [
                'no_actions'     => __( 'No cache clearing actions configured. Please update your settings below.', 'clear-cache-everywhere' ),
                'clearing_cache' => __( 'Clearing', 'clear-cache-everywhere' ),
                'cache_cleared'  => $cache_cleared_text,
                'cache_failed'   => __( 'Cache clearing failed. Please try again.', 'clear-cache-everywhere' ),
                'page_reload'    => __( 'Reloading page to clear this action...', 'clear-cache-everywhere' ),
                'please_wait'    => __( 'Please do not navigate away or close the browser while cache clearing is in progress.', 'clear-cache-everywhere' ),
                'last_cleared'   => __( 'Last cleared on ', 'clear-cache-everywhere' ),
                'last_attempted' => __( 'Last attempted on ', 'clear-cache-everywhere' ),
            ],
        ] );
    } // End enqueue_scripts()

}