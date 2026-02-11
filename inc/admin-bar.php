<?php 
/**
 * Plugin settings
 */


/**
 * Define Namespaces
 */
namespace Apos37\ClearCache;


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
add_action( 'init', function() {
	(new AdminBar())->init();
} );


/**
 * The class
 */
class AdminBar {

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
        
        // Add admin bar menu button
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_button' ], 100 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

    } // End init()


    /**
     * Add a button to the admin bar
     * 
     * @return void
     */
    public function add_admin_bar_button( $wp_admin_bar ) {
        // Check if the user has the necessary permissions
        if ( current_user_can( 'manage_options' ) ) {

            // The clear cache url
            $clear_cache_url = add_query_arg( [
                (new Clear())->action_param => 1,
                '_wpnonce' => wp_create_nonce( $this->nonce )
            ] );

            // Add a custom button
            $wp_admin_bar->add_node( [
                'id'    => 'cceverywhere_adminbar_btn',
                'title' => '<span class="ab-icon dashicons dashicons-editor-removeformatting" title="' . CCEVERYWHERE_NAME . '"></span><span class="ab-label">' . __( 'Clear Cache', 'clear-cache-everywhere' ) . '</span>',
                'href'  => $clear_cache_url,
                'meta'  => [
                    'class' => 'clear-cache-button'
                ]
            ] );
        }
    } // End add_admin_bar_button()


    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts() {
		wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-admin-bar', CCEVERYWHERE_CSS_PATH . 'admin-bar.css', [], CCEVERYWHERE_SCRIPT_VERSION );
    } // End enqueue_scripts()
}
