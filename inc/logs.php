<?php
/**
 * Logs
 *
 * Stores every clearing result in a custom table, shows them on the Logs tab, and purges old entries daily.
 */


/**
 * Define Namespaces
 */
namespace Apos37\ClearCache;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * Instantiate the class
 */
new Logs();


/**
 * The class
 */
class Logs {

    /**
     * Schema version
     *
     * @var string
     */
    const DB_VERSION = '1.1';


    /**
     * Option that stores the installed schema version
     *
     * @var string
     */
    const DB_VERSION_OPTION = 'cceverywhere_logs_db_version';


    /**
     * Nonce action
     *
     * @var string
     */
    const NONCE = 'cceverywhere_logs';


    /**
     * Daily purge event
     *
     * @var string
     */
    const CRON_EVENT = 'cceverywhere_purge_logs';


    /**
     * Allowed per-page values
     *
     * @var array
     */
    const PER_PAGE = [ 20, 50, 100, 250 ];


    /**
     * Default number of days to keep logs
     *
     * @var int
     */
    const DEFAULT_RETENTION = 30;


    /**
     * Whether the table has been checked this request
     *
     * @var bool
     */
    private static $installed = false;


    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'admin_init' ] );
        add_action( self::CRON_EVENT, [ $this, 'run_purge' ] );
        add_action( 'cceverywhere_subheader_left', [ $this, 'render_subheader_left' ] );
        add_action( 'cceverywhere_subheader_right', [ $this, 'render_subheader_right' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_cceverywhere_clear_logs', [ $this, 'ajax_clear_logs' ] );
    } // End __construct()


    /**
     * Install the table, schedule the purge, and handle bulk deletes
     *
     * @return void
     */
    public function admin_init() {
        self::maybe_install();

        if ( ! wp_next_scheduled( self::CRON_EVENT ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_EVENT );
        }

        $this->process_bulk_action();
    } // End admin_init()


    /**
     * Get the table name
     *
     * @return string
     */
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'cceverywhere_logs';
    } // End table()


    /**
     * Create or update the table when the schema is out of date
     *
     * @return bool
     */
    public static function maybe_install() {
        if ( self::$installed ) {
            return true;
        }

        if ( version_compare( (string) get_option( self::DB_VERSION_OPTION, '' ), self::DB_VERSION, '>=' ) ) {
            self::$installed = true;
            return true;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta( "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            action_key varchar(100) NOT NULL,
            action_title varchar(191) DEFAULT NULL,
            context varchar(50) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT '',
            duration decimal(10,3) DEFAULT NULL,
            details longtext DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY action_key (action_key),
            KEY context (context),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};" );

        $has_ip = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'ip_address' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
        if ( $has_ip ) {
            $wpdb->query( "ALTER TABLE {$table} DROP COLUMN ip_address" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        self::$installed = true;

        return true;
    } // End maybe_install()


    /**
     * Get an option name for this plugin
     *
     * @param string $key
     * @return string
     */
    public static function option_name( $key ) {
        return CCEVERYWHERE__TEXTDOMAIN . '_' . $key;
    } // End option_name()


    /**
     * Number of days to keep logs (0 keeps them forever)
     *
     * @return int
     */
    public static function get_retention() {
        $days = get_option( self::option_name( 'log_retention' ), self::DEFAULT_RETENTION );
        return '' === $days ? self::DEFAULT_RETENTION : absint( $days );
    } // End get_retention()


    /**
     * Whether successful, info, and skipped results are logged
     *
     * @return bool
     */
    public static function log_success() {
        return filter_var( get_option( self::option_name( 'log_success' ), false ), FILTER_VALIDATE_BOOLEAN );
    } // End log_success()


    /**
     * Whether AJAX responses are printed to the browser console
     *
     * @return bool
     */
    public static function console_log() {
        return filter_var( get_option( self::option_name( 'console_log' ), true ), FILTER_VALIDATE_BOOLEAN );
    } // End console_log()


    /**
     * Context labels
     *
     * @return array
     */
    public static function contexts() {
        return [
            'settings'   => __( 'Clear All (Settings)', 'clear-cache-everywhere' ),
            'admin_bar'  => __( 'Admin Bar', 'clear-cache-everywhere' ),
            'individual' => __( 'Individual Clear', 'clear-cache-everywhere' ),
            'page'       => __( 'Page Reload', 'clear-cache-everywhere' ),
            'ajax'       => __( 'AJAX', 'clear-cache-everywhere' ),
            'url'        => __( 'URL', 'clear-cache-everywhere' ),
            'cron'       => __( 'Cron', 'clear-cache-everywhere' ),
            'developer'  => __( 'Helper Function', 'clear-cache-everywhere' ),
        ];
    } // End contexts()


    /**
     * Status labels
     *
     * @return array
     */
    public static function statuses() {
        return [
            'success' => __( 'Success', 'clear-cache-everywhere' ),
            'fail'    => __( 'Failed', 'clear-cache-everywhere' ),
            'info'    => __( 'Info', 'clear-cache-everywhere' ),
            'skipped' => __( 'Skipped', 'clear-cache-everywhere' ),
        ];
    } // End statuses()


    /**
     * Work out what triggered the current request
     *
     * @return string
     */
    public static function detect_context() {
        $source = isset( $_POST[ 'source' ] ) ? sanitize_key( wp_unslash( $_POST[ 'source' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- label only; the calling AJAX handler verifies the nonce.

        if ( in_array( $source, [ 'settings', 'admin_bar', 'individual', 'page' ], true ) ) {
            return $source;
        }

        if ( wp_doing_ajax() ) {
            return 'ajax';
        }

        if ( wp_doing_cron() ) {
            return 'cron';
        }

        return isset( $_GET[ 'clear-cache-now' ] ) ? 'url' : 'developer'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- label only.
    } // End detect_context()


    /**
     * Server details added to failures so they can be diagnosed
     *
     * @return string
     */
    public static function environment() {
        $is_browser_report = wp_doing_ajax() && isset( $_POST[ 'action' ] ) && 'cceverywhere_log_client_error' === $_POST[ 'action' ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only check.

        $parts = [
            'PHP ' . PHP_VERSION,
            'WP ' . get_bloginfo( 'version' ),
            'memory_limit ' . ini_get( 'memory_limit' ),
            'max_execution_time ' . ini_get( 'max_execution_time' ) . 's',
        ];

        if ( ! $is_browser_report ) {
            $parts[] = 'peak memory ' . size_format( memory_get_peak_usage( true ), 1 );
        }

        return implode( ' | ', $parts );
    } // End environment()


    /**
     * Add a log entry for a finished clearing action
     *
     * @param string $key
     * @param string $title
     * @param array  $result start, end, status, error_message
     * @return int Log ID, or 0 when not logged.
     */
    public static function add( $key, $title, $result ) {
        $status = sanitize_key( $result[ 'status' ] ?? 'fail' );

        if ( 'fail' !== $status && ! self::log_success() ) {
            return 0;
        }

        if ( ! self::maybe_install() ) {
            return 0;
        }

        global $wpdb;

        $duration = ( ! empty( $result[ 'start' ] ) && ! empty( $result[ 'end' ] ) ) ? round( (float) $result[ 'end' ] - (float) $result[ 'start' ], 3 ) : null;
        $details = trim( (string) ( $result[ 'error_message' ] ?? '' ) );

        if ( 'fail' === $status ) {
            $details = trim( $details . "\n" . self::environment() );
        }

        $user_id = get_current_user_id();

        $inserted = $wpdb->insert( self::table(), [
            'action_key'   => substr( sanitize_key( $key ), 0, 100 ),
            'action_title' => substr( wp_strip_all_tags( (string) $title ), 0, 191 ),
            'context'      => self::detect_context(),
            'status'       => $status,
            'duration'     => $duration,
            'details'      => sanitize_textarea_field( $details ),
            'user_id'      => $user_id ? $user_id : null,
            'created_at'   => current_time( 'mysql', true ),
        ], [ '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table.

        return $inserted ? (int) $wpdb->insert_id : 0;
    } // End add()


    /**
     * Prepare a query only when it has placeholders
     *
     * @param string $sql
     * @param array  $args
     * @return string
     */
    private static function prepare( $sql, $args ) {
        global $wpdb;
        return empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders are passed in $args.
    } // End prepare()


    /**
     * Build the WHERE clause for the filters
     *
     * @param array $filters status, action_key, context, search
     * @return array [ string $where, array $args ]
     */
    private static function where( $filters ) {
        global $wpdb;

        $where = [ '1=1' ];
        $args = [];

        foreach ( [ 'status', 'action_key', 'context' ] as $column ) {
            if ( ! empty( $filters[ $column ] ) ) {
                $where[] = "{$column} = %s";
                $args[] = $filters[ $column ];
            }
        }

        if ( ! empty( $filters[ 'search' ] ) ) {
            $like = '%' . $wpdb->esc_like( $filters[ 'search' ] ) . '%';
            $where[] = '( details LIKE %s OR action_title LIKE %s OR action_key LIKE %s )';
            array_push( $args, $like, $like, $like );
        }

        return [ implode( ' AND ', $where ), $args ];
    } // End where()


    /**
     * Get log entries
     *
     * @param array  $filters
     * @param string $orderby
     * @param string $order
     * @param int    $per_page
     * @param int    $page
     * @return array [ 'items' => array, 'total' => int ]
     */
    public static function get_logs( $filters, $orderby, $order, $per_page, $page ) {
        global $wpdb;

        self::maybe_install();

        $table = self::table();
        list( $where, $args ) = self::where( $filters );
        $orderby = in_array( $orderby, [ 'created_at', 'status', 'action_title', 'context', 'duration' ], true ) ? $orderby : 'created_at';
        $order = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';
        $offset = max( 0, ( $page - 1 ) * $per_page );

        $total = (int) $wpdb->get_var( self::prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom table, prepared above.
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d", array_merge( $args, [ $per_page, $offset ] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table; orderby and order are whitelisted.

        return [ 'items' => (array) $items, 'total' => $total ];
    } // End get_logs()


    /**
     * Count logs by status, honoring the other filters
     *
     * @param array $filters
     * @return array
     */
    public static function count_by_status( $filters ) {
        global $wpdb;

        $table = self::table();
        $filters[ 'status' ] = '';
        list( $where, $args ) = self::where( $filters );

        $rows = $wpdb->get_results( self::prepare( "SELECT status, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY status", $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- custom table, prepared above.
        $counts = array_fill_keys( array_merge( [ 'all' ], array_keys( self::statuses() ) ), 0 );

        foreach ( (array) $rows as $row ) {
            $counts[ 'all' ] += (int) $row->total;

            if ( isset( $counts[ $row->status ] ) ) {
                $counts[ $row->status ] += (int) $row->total;
            }
        }

        return $counts;
    } // End count_by_status()


    /**
     * Get the distinct actions that have been logged
     *
     * @return array action_key => action_title
     */
    public static function logged_actions() {
        global $wpdb;

        $table = self::table();
        $rows = $wpdb->get_results( "SELECT action_key, MAX(action_title) AS action_title FROM {$table} GROUP BY action_key ORDER BY action_title ASC" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, no input.
        $actions = [];

        foreach ( (array) $rows as $row ) {
            $actions[ $row->action_key ] = $row->action_title ? $row->action_title : $row->action_key;
        }

        return $actions;
    } // End logged_actions()


    /**
     * Delete log entries by ID
     *
     * @param array $ids
     * @return int
     */
    public static function delete_logs( $ids ) {
        global $wpdb;

        $ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $table = self::table();
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, placeholders built above.
    } // End delete_logs()


    /**
     * Clear all logs, or only the ones that are not failures
     *
     * @param bool $keep_failures
     * @return int
     */
    public static function clear_logs( $keep_failures = false ) {
        global $wpdb;

        $table = self::table();

        if ( $keep_failures ) {
            return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status != %s", 'fail' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
        }

        return (int) $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, no input.
    } // End clear_logs()


    /**
     * Delete logs older than the retention setting
     *
     * @return void
     */
    public function run_purge() {
        global $wpdb;

        $days = self::get_retention();

        if ( $days <= 0 || ! self::maybe_install() ) {
            return;
        }

        $table = self::table();
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table.
    } // End run_purge()


    /**
     * Remove the scheduled purge (called on deactivation)
     *
     * @return void
     */
    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_EVENT );
    } // End unschedule()


    /**
     * Check if the Logs tab is showing
     *
     * @return bool
     */
    public static function is_logs_tab() {
        $page = isset( $_GET[ 'page' ] ) ? sanitize_key( wp_unslash( $_GET[ 'page' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
        return CCEVERYWHERE__TEXTDOMAIN === $page && 'logs' === Settings::get_current_tab();
    } // End is_logs_tab()


    /**
     * Get the Logs tab URL
     *
     * @param array $args
     * @return string
     */
    public static function url( $args = [] ) {
        return add_query_arg( array_merge( [ 'page' => CCEVERYWHERE__TEXTDOMAIN, 'tab' => 'logs' ], $args ), admin_url( 'tools.php' ) );
    } // End url()


    /**
     * Delete the selected entries, then reload with a clean URL
     *
     * @return void
     */
    public function process_bulk_action() {
        if ( ! self::is_logs_tab() || ! isset( $_GET[ '_wp_http_referer' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; the nonce is checked below.
            return;
        }

        $action = isset( $_GET[ 'action' ] ) ? sanitize_key( wp_unslash( $_GET[ 'action' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.

        if ( 'delete' !== $action && isset( $_GET[ 'action2' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
            $action = sanitize_key( wp_unslash( $_GET[ 'action2' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
        }

        if ( 'delete' === $action && ! isset( $_GET[ 'filter_action' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
            check_admin_referer( 'bulk-logs' );

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to do this.', 'clear-cache-everywhere' ), 403 );
            }

            self::delete_logs( isset( $_GET[ 'log' ] ) ? (array) wp_unslash( $_GET[ 'log' ] ) : [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast with absint in delete_logs().
        }

        $keep = [];

        foreach ( [ 'per_page', 'log_action', 'context', 'status', 's', 'orderby', 'order', 'paged' ] as $key ) {
            $value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.

            if ( '' !== $value ) {
                $keep[ $key ] = $value;
            }
        }

        wp_safe_redirect( self::url( $keep ) );
        exit;
    } // End process_bulk_action()


    /**
     * Render the clear buttons
     *
     * @param string $tab
     * @return void
     */
    public function render_subheader_left( $tab ) {
        if ( 'logs' !== $tab ) {
            return;
        }
        ?>
        <button type="button" class="cceverywhere-button cceverywhere-clear-logs" data-status=""><?php esc_html_e( 'Clear All', 'clear-cache-everywhere' ); ?></button>
        <button type="button" class="cceverywhere-button cceverywhere-clear-logs" data-status="success"><?php esc_html_e( 'Clear All Success', 'clear-cache-everywhere' ); ?></button>
        <span id="cceverywhere-logs-status"></span>
        <?php
    } // End render_subheader_left()


    /**
     * Render the search box
     *
     * @param string $tab
     * @return void
     */
    public function render_subheader_right( $tab ) {
        if ( 'logs' !== $tab ) {
            return;
        }

        $search = isset( $_GET[ 's' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 's' ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
        ?>
        <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" class="cceverywhere-log-search" role="search">
            <input type="hidden" name="page" value="<?php echo esc_attr( CCEVERYWHERE__TEXTDOMAIN ); ?>">
            <input type="hidden" name="tab" value="logs">
            <div class="cceverywhere-search">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search logs...', 'clear-cache-everywhere' ); ?>" aria-label="<?php esc_attr_e( 'Search logs', 'clear-cache-everywhere' ); ?>">
            </div>
            <button type="submit" class="cceverywhere-button"><?php esc_html_e( 'Search', 'clear-cache-everywhere' ); ?></button>
            <?php if ( '' !== $search ) : ?>
                <a href="<?php echo esc_url( self::url() ); ?>" class="cceverywhere-button cceverywhere-button-secondary"><?php esc_html_e( 'Clear', 'clear-cache-everywhere' ); ?></a>
            <?php endif; ?>
        </form>
        <?php
    } // End render_subheader_right()


    /**
     * Render the Logs tab
     *
     * @return void
     */
    public static function render_page() {
        require_once CCEVERYWHERE_INCLUDES_ABSPATH . 'logs-list-table.php';

        $table = new LogsListTable();
        $table->prepare_items();
        ?>
        <div class="wrap cceverywhere-wrap cceverywhere-logs">
            <hr class="wp-header-end">
            <form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>" class="cceverywhere-list-container" id="cceverywhere-logs-form">
                <input type="hidden" name="page" value="<?php echo esc_attr( CCEVERYWHERE__TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="logs">
                <?php
                $table->views();
                $table->display();
                ?>
            </form>
        </div>
        <?php
    } // End render_page()


    /**
     * Enqueue the Logs tab assets
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( CCEVERYWHERE_SETTINGS_SCREEN_ID !== $hook || ! self::is_logs_tab() ) {
            return;
        }

        wp_enqueue_style( CCEVERYWHERE_TEXTDOMAIN . '-logs', CCEVERYWHERE_CSS_PATH . 'logs.css', [ CCEVERYWHERE_TEXTDOMAIN . '-theme' ], CCEVERYWHERE_SCRIPT_VERSION );
        wp_enqueue_script( CCEVERYWHERE_TEXTDOMAIN . '-logs', CCEVERYWHERE_JS_PATH . 'logs.js', [ 'jquery' ], CCEVERYWHERE_SCRIPT_VERSION, true );

        wp_localize_script( CCEVERYWHERE_TEXTDOMAIN . '-logs', 'cceverywhere_logs', [
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( self::NONCE ),
            'console_log' => self::console_log(),
            'text'        => [
                'confirm_all'     => __( 'Delete every log entry? This cannot be undone.', 'clear-cache-everywhere' ),
                'confirm_success' => __( 'Delete every successful, info, and skipped entry? Failures are kept.', 'clear-cache-everywhere' ),
                /* translators: %d: number of entries deleted */
                'cleared'         => __( 'Deleted %d entries. Reloading...', 'clear-cache-everywhere' ),
                'error'           => __( 'Could not clear the logs.', 'clear-cache-everywhere' ),
            ],
        ] );
    } // End enqueue_scripts()


    /**
     * AJAX: clear logs
     *
     * @return void
     */
    public function ajax_clear_logs() {
        check_ajax_referer( self::NONCE, 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'clear-cache-everywhere' ) );
        }

        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : '';

        wp_send_json_success( [ 'deleted' => self::clear_logs( 'success' === $status ) ] );
    } // End ajax_clear_logs()

}
