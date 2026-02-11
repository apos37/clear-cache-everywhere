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

        // JQuery and CSS
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
        ?>
		<div class="wrap">
			<h1><?php echo esc_attr( get_admin_page_title() ) ?></h1>

            <!-- Clear Cache Button -->
            <br><br>
            <?php
            $clear_cache_url = add_query_arg( [
                (new Clear())->action_param => 1,
                '_wpnonce' => wp_create_nonce( $this->nonce )
            ] );
            ?>
            <button id="cce-clear-cache-btn" class="button button-secondary cce-clear-cache-btn" href="<?php echo esc_url( $clear_cache_url ); ?>">
                <?php esc_html_e( 'Clear Cache Now', 'clear-cache-everywhere' ); ?>
            </button>

            <!-- Result Container -->
            <?php
            $last_results = get_option( 'clear_cache_everywhere_last_results', [] );
            $total_elapsed = 0;

            $clearing_actions = ( new Clear() )->get_clearing_actions();

            if ( ! empty( $last_results ) && ! empty( $clearing_actions ) ) {
                foreach ( $clearing_actions as $action ) {
                    if ( ! empty( $action[ 'enabled' ] ) ) {
                        $key = $action[ 'key' ];
                        if ( isset( $last_results[ $key ] ) ) {
                            $res = $last_results[ $key ];
                            if ( ! empty( $res[ 'start' ] ) && ! empty( $res[ 'end' ] ) ) {
                                $total_elapsed += $res[ 'end' ] - $res[ 'start' ];
                            }
                        }
                    }
                }
            }
            ?>
            <div id="cce-clear-cache-result">
                <?php if ( $total_elapsed > 0 ) : ?>
                    <?php
                    echo sprintf(
                        /* translators: %s is total elapsed seconds for clearing actions only */
                        __( 'Total time for clearing all enabled options the last time they were cleared: <strong>%s seconds</strong>.<br><em>Note: This does not include page reload or any recaching by the site or plugins afterwards.</em>', 'clear-cache-everywhere' ),
                        number_format( $total_elapsed, 3 )
                    );
                    ?>
                <?php else : ?>
                    <?php
                    echo __( 'No enabled clearing actions have been run. You may clear all using the button above, or individually using the buttons below.', 'clear-cache-everywhere' );
                    ?>
                <?php endif; ?>
            </div>

            <!-- Settings Form -->
            <br><br>
            <h2><?php esc_html_e( 'Choose below which items you want to clear.', 'clear-cache-everywhere' ); ?></h2>
			<form method="post" action="options.php">
				<?php
					settings_fields( CCEVERYWHERE_TEXTDOMAIN );
					do_settings_sections( CCEVERYWHERE_TEXTDOMAIN );
					?><br><br><?php
                    submit_button();
				?>
			</form>
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
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Resets PHP OPcache to clear cached scripts. May take a few seconds on large sites.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'varnish',
                'title'       => __( 'Varnish Cache', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
                'run_context' => 'ajax',
                'comments'    => __( 'Purges the Varnish cache if detected. Network requests may take a few seconds to propagate.', 'clear-cache-everywhere' ),
            ],
            [
                'key'         => 'redis_memcached',
                'title'       => __( 'Redis/Memcached', 'clear-cache-everywhere' ),
                'type'        => 'checkbox',
                'sanitize'    => 'sanitize_checkbox',
                'section'     => 'defaults',
                'default'     => TRUE,
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
                'default'     => TRUE,
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
        ];

        // Integrations
        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
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

        if ( is_plugin_active( 'elementor/elementor.php' ) ) {
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

        if ( is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
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

        if ( is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
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

        if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
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

        if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
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

        if ( is_plugin_active( 'sg-cachepress/sg-cachepress.php' ) ) {
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

        if ( is_plugin_active( 'cloudflare/cloudflare.php' ) ) {
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

        if ( is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
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

        if ( is_plugin_active( 'swift-performance-lite/performance.php' ) || is_plugin_active( 'swift-performance/performance.php' ) ) {
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

        if ( is_plugin_active( 'comet-cache/comet-cache.php' ) ) {
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

        if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
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

        if ( is_plugin_active( 'hummingbird-performance/hummingbird.php' ) ) {
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

        if ( is_plugin_active( 'nginx-helper/nginx-helper.php' ) ) {
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

        if ( is_plugin_active( 'wp-optimize/wp-optimize.php' ) ) {
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
                ' <button class="button button-small cce-run-action-btn" data-key="%s">%s</button>',
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
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== CCEVERYWHERE_SETTINGS_SCREEN_ID ) {
            return;
        }

		// CSS
		wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-settings', CCEVERYWHERE_CSS_PATH . 'settings.css', [], CCEVERYWHERE_SCRIPT_VERSION );
    } // End enqueue_scripts()

}
