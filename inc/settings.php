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
            <a class="button button-secondary cce-clear-cache-btn" href="<?php echo esc_url( $clear_cache_url ); ?>">
                <?php esc_html_e( 'Clear Cache Now', 'clear-cache-everywhere' ); ?>
            </a>

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
                'key'       => 'rewrite_rules',
                'title'     => __( 'Rewrite Rules', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'wp_cache_flush',
                'title'     => __( 'WordPress Object Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'transients',
                'title'     => __( 'Transients', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'sessions',
                'title'     => __( 'Sessions', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'cookies',
                'title'     => __( 'Cookies', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'browser_cache',
                'title'     => __( 'Browser Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'defaults',
                'default'   => TRUE,
            ],
            [
                'key'       => 'hosting_cache',
                'title'     => __( 'Hosting Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'hosting',
                'default'   => FALSE,
                'comments'  => __( 'If your hosting provides you with a cache purge URL, you can enter it below and enable clearing it here.', 'clear-cache-everywhere' )
            ],
            [
                'key'       => 'hosting_purge_url',
                'title'     => __( 'Hosting Purge URL', 'clear-cache-everywhere' ),
                'type'      => 'text',
                'sanitize'  => 'sanitize_text_field',
                'section'   => 'hosting',
            ],
        ];

        // Integrations
        if ( is_plugin_active( 'cornerstone/cornerstone.php' ) ) {
            $fields[] = [
                'key'       => 'cornerstone',
                'title'     => __( 'Cornerstone Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'elementor/elementor.php' ) ) {
            $fields[] = [
                'key'       => 'elementor',
                'title'     => __( 'Elementor Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'wp-super-cache/wp-cache.php' ) ) {
            $fields[] = [
                'key'       => 'wp_super_cache',
                'title'     => __( 'WP Super Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'w3-total-cache/w3-total-cache.php' ) ) {
            $fields[] = [
                'key'       => 'w3_total_cache',
                'title'     => __( 'W3 Total Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) ) {
            $fields[] = [
                'key'       => 'wp_rocket',
                'title'     => __( 'WP Rocket', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
            $fields[] = [
                'key'       => 'litespeed_cache',
                'title'     => __( 'LiteSpeed Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'sg-cachepress/sg-cachepress.php' ) ) {
            $fields[] = [
                'key'       => 'sg_optimizer',
                'title'     => __( 'SiteGround Optimizer', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'cloudflare/cloudflare.php' ) ) {
            $fields[] = [
                'key'       => 'cloudflare',
                'title'     => __( 'Cloudflare Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'autoptimize/autoptimize.php' ) ) {
            $fields[] = [
                'key'       => 'autoptimize',
                'title'     => __( 'Autoptimize Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }
        
        if ( is_plugin_active( 'swift-performance-lite/performance.php' ) || is_plugin_active( 'swift-performance/performance.php' ) ) {
            $fields[] = [
                'key'       => 'swift_performance',
                'title'     => __( 'Swift Performance Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'comet-cache/comet-cache.php' ) ) {
            $fields[] = [
                'key'       => 'comet_cache',
                'title'     => __( 'Comet Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
            $fields[] = [
                'key'       => 'wp_fastest_cache',
                'title'     => __( 'WP Fastest Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'hummingbird-performance/hummingbird.php' ) ) {
            $fields[] = [
                'key'       => 'hummingbird_cache',
                'title'     => __( 'Hummingbird Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'nginx-helper/nginx-helper.php' ) ) {
            $fields[] = [
                'key'       => 'nginx_helper',
                'title'     => __( 'Nginx Helper', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        if ( is_plugin_active( 'wp-optimize/wp-optimize.php' ) ) {
            $fields[] = [
                'key'       => 'wp_optimize',
                'title'     => __( 'WP-Optimize Cache', 'clear-cache-everywhere' ),
                'type'      => 'checkbox',
                'sanitize'  => 'sanitize_checkbox',
                'section'   => 'integrations',
                'default'   => TRUE,
            ];
        }

        // Apply filter to allow developers to add custom fields
        $fields = filter_var_array( apply_filters( 'cceverywhere_custom_settings', $fields ), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

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
                return isset( $field['section'] ) && $field['section'] === $section_key;
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
            ];

            // Add comments
            if ( isset( $field[ 'comments' ] ) ) {
                $args[ 'comments' ] = $field[ 'comments' ];
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

            // Add the field
            register_setting( $slug, $option_name, sanitize_key( $field[ 'sanitize' ] ) );
            add_settings_field( $option_name, $field[ 'title' ], [ $this, $callback ], $slug, $field[ 'section' ], $args );
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
        $comments = isset( $args[ 'comments' ] ) ? ' <p class="description">' . $args[ 'comments' ] . '</p>' : '';

        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s/>%s',
            esc_attr( $args[ 'name' ] ),
            esc_attr( $args[ 'name' ] ),
            checked( 1, $value, false ),
            wp_kses_post( $comments )
        );
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
		// JavaScript
        wp_register_script( CCEVERYWHERE_TEXTDOMAIN, CCEVERYWHERE_JS_PATH.'back-notice.js', [ 'jquery' ], CCEVERYWHERE_VERSION, true );
		wp_enqueue_script( CCEVERYWHERE_TEXTDOMAIN );

        // Check if we are on the correct admin page
        if ( $hook !== 'appearance_page_'.CCEVERYWHERE_TEXTDOMAIN ) {
            return;
        }

		// CSS
		wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-styles', CCEVERYWHERE_CSS_PATH . 'settings.css', [], CCEVERYWHERE_VERSION );
    } // End enqueue_scripts()

}
