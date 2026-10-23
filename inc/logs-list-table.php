<?php
/**
 * Logs list table
 *
 * The table on the Logs tab, with status views, filters, per-page choice and bulk delete.
 */


/**
 * Define Namespaces
 */
namespace Apos37\ClearCache;


/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


/**
 * The class
 */
class LogsListTable extends \WP_List_Table {

    /**
     * Current filters
     *
     * @var array
     */
    private $filters = [];


    /**
     * Counts by status
     *
     * @var array
     */
    private $counts = [];


    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( [
            'singular' => 'log',
            'plural'   => 'logs',
            'ajax'     => false,
        ] );
    } // End __construct()


    /**
     * Read a filter from the query string
     *
     * @param string $key
     * @return string
     */
    private static function query_arg( $key ) {
        return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters.
    } // End query_arg()


    /**
     * Get the per-page value, remembering the user's choice
     *
     * @return int
     */
    public static function per_page() {
        $requested = absint( self::query_arg( 'per_page' ) );
        $user_id = get_current_user_id();

        if ( in_array( $requested, Logs::PER_PAGE, true ) ) {
            update_user_meta( $user_id, 'cceverywhere_logs_per_page', $requested );
            return $requested;
        }

        $saved = absint( get_user_meta( $user_id, 'cceverywhere_logs_per_page', true ) );

        return in_array( $saved, Logs::PER_PAGE, true ) ? $saved : Logs::PER_PAGE[ 0 ];
    } // End per_page()


    /**
     * Columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" />',
            'created_at'   => __( 'Date', 'clear-cache-everywhere' ),
            'status'       => __( 'Status', 'clear-cache-everywhere' ),
            'action_title' => __( 'Action', 'clear-cache-everywhere' ),
            'context'      => __( 'Triggered By', 'clear-cache-everywhere' ),
            'duration'     => __( 'Duration', 'clear-cache-everywhere' ),
            'details'      => __( 'Details', 'clear-cache-everywhere' ),
            'user_id'      => __( 'User', 'clear-cache-everywhere' ),
        ];
    } // End get_columns()


    /**
     * Sortable columns
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return [
            'created_at'   => [ 'created_at', true ],
            'status'       => [ 'status', false ],
            'action_title' => [ 'action_title', false ],
            'context'      => [ 'context', false ],
            'duration'     => [ 'duration', false ],
        ];
    } // End get_sortable_columns()


    /**
     * Bulk actions
     *
     * @return array
     */
    protected function get_bulk_actions() {
        return [ 'delete' => __( 'Delete', 'clear-cache-everywhere' ) ];
    } // End get_bulk_actions()


    /**
     * Load the items
     *
     * @return void
     */
    public function prepare_items() {
        $status = self::query_arg( 'status' );
        $context = sanitize_key( self::query_arg( 'context' ) );

        $this->filters = [
            'status'     => array_key_exists( $status, Logs::statuses() ) ? $status : '',
            'action_key' => sanitize_key( self::query_arg( 'log_action' ) ),
            'context'    => array_key_exists( $context, Logs::contexts() ) ? $context : '',
            'search'     => self::query_arg( 's' ),
        ];

        $per_page = self::per_page();
        $result = Logs::get_logs( $this->filters, sanitize_key( self::query_arg( 'orderby' ) ), self::query_arg( 'order' ), $per_page, $this->get_pagenum() );

        $this->items = $result[ 'items' ];
        $this->counts = Logs::count_by_status( $this->filters );
        $this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns(), 'created_at' ];

        $this->set_pagination_args( [
            'total_items' => $result[ 'total' ],
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $result[ 'total' ] / $per_page ),
        ] );
    } // End prepare_items()


    /**
     * Status views with counts
     *
     * @return array
     */
    protected function get_views() {
        $current = $this->filters[ 'status' ];
        $base = remove_query_arg( [ 'status', 'paged' ] );
        $views = array_merge( [ '' => __( 'All', 'clear-cache-everywhere' ) ], Logs::statuses() );
        $links = [];

        foreach ( $views as $status => $label ) {
            $count = $this->counts[ '' === $status ? 'all' : $status ] ?? 0;

            if ( '' !== $status && ! $count && $current !== $status ) {
                continue;
            }

            $url = '' === $status ? $base : add_query_arg( 'status', $status, $base );
            $links[ '' === $status ? 'all' : $status ] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
                esc_url( $url ),
                $current === $status ? ' class="current" aria-current="page"' : '',
                esc_html( $label ),
                esc_html( number_format_i18n( $count ) )
            );
        }

        return $links;
    } // End get_views()


    /**
     * Render a filter select
     *
     * @param string $name
     * @param string $label
     * @param array  $choices
     * @param string $current
     * @return void
     */
    private function filter_select( $name, $label, $choices, $current ) {
        ?>
        <label class="screen-reader-text" for="cceverywhere-filter-<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
        <select name="<?php echo esc_attr( $name ); ?>" id="cceverywhere-filter-<?php echo esc_attr( $name ); ?>">
            <?php foreach ( $choices as $value => $choice ) : ?>
                <option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $current, (string) $value ); ?>><?php echo esc_html( $choice ); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    } // End filter_select()


    /**
     * Per page and filters next to Bulk Actions
     *
     * @param string $which
     * @return void
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        $per_page = [];

        foreach ( Logs::PER_PAGE as $number ) {
            /* translators: %d: number of entries per page */
            $per_page[ $number ] = sprintf( __( '%d per page', 'clear-cache-everywhere' ), $number );
        }

        $actions = array_merge( [ '' => __( 'All actions', 'clear-cache-everywhere' ) ], Logs::logged_actions() );
        $contexts = array_merge( [ '' => __( 'All triggers', 'clear-cache-everywhere' ) ], Logs::contexts() );
        $statuses = array_merge( [ '' => __( 'All results', 'clear-cache-everywhere' ) ], Logs::statuses() );
        ?>
        <div class="alignleft actions cceverywhere-log-filters">
            <?php
            $this->filter_select( 'per_page', __( 'Entries per page', 'clear-cache-everywhere' ), $per_page, (string) self::per_page() );
            $this->filter_select( 'log_action', __( 'Filter by action', 'clear-cache-everywhere' ), $actions, $this->filters[ 'action_key' ] );
            $this->filter_select( 'context', __( 'Filter by trigger', 'clear-cache-everywhere' ), $contexts, $this->filters[ 'context' ] );
            $this->filter_select( 'status', __( 'Filter by result', 'clear-cache-everywhere' ), $statuses, $this->filters[ 'status' ] );
            submit_button( __( 'Filter', 'clear-cache-everywhere' ), '', 'filter_action', false );
            ?>
        </div>
        <?php
    } // End extra_tablenav()


    /**
     * Checkbox column
     *
     * @param object $item
     * @return string
     */
    protected function column_cb( $item ) {
        return '<input type="checkbox" name="log[]" value="' . esc_attr( (string) $item->id ) . '" />';
    } // End column_cb()


    /**
     * Default column output
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'created_at':
                $timestamp = strtotime( $item->created_at . ' UTC' );
                return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) );

            case 'status':
                $pills = [
                    'success' => [ 'is-ok', 'dashicons-yes-alt' ],
                    'fail'    => [ 'is-error', 'dashicons-dismiss' ],
                    'info'    => [ 'is-info', 'dashicons-info' ],
                    'skipped' => [ 'is-muted', 'dashicons-minus' ],
                ];
                $pill = $pills[ $item->status ] ?? $pills[ 'skipped' ];
                $label = Logs::statuses()[ $item->status ] ?? ucfirst( (string) $item->status );
                return '<span class="cceverywhere-status-pill ' . esc_attr( $pill[ 0 ] ) . '"><span class="dashicons ' . esc_attr( $pill[ 1 ] ) . '" aria-hidden="true"></span> ' . esc_html( $label ) . '</span>';

            case 'action_title':
                $title = '' !== (string) $item->action_title ? $item->action_title : $item->action_key;
                return '<a href="' . esc_url( add_query_arg( 'log_action', $item->action_key, remove_query_arg( [ 'paged' ] ) ) ) . '">' . esc_html( $title ) . '</a>';

            case 'context':
                return esc_html( Logs::contexts()[ $item->context ] ?? ucwords( str_replace( '_', ' ', (string) $item->context ) ) );

            case 'duration':
                return null === $item->duration ? '&mdash;' : esc_html( number_format_i18n( (float) $item->duration, 3 ) . 's' );

            case 'details':
                return '' === (string) $item->details ? '&mdash;' : '<div class="cceverywhere-log-details">' . esc_html( (string) $item->details ) . '</div>';

            case 'user_id':
                $user = $item->user_id ? get_userdata( (int) $item->user_id ) : false;
                $name = $user ? $user->display_name : ( $item->user_id ? __( '(deleted user)', 'clear-cache-everywhere' ) : __( 'System', 'clear-cache-everywhere' ) );
                return esc_html( $name );
        }

        return '';
    } // End column_default()


    /**
     * Row classes
     *
     * @param object $item
     * @return void
     */
    public function single_row( $item ) {
        echo '<tr class="' . esc_attr( 'fail' === $item->status ? 'cceverywhere-log-fail' : 'cceverywhere-log-' . $item->status ) . '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    } // End single_row()


    /**
     * Message when there are no logs
     *
     * @return void
     */
    public function no_items() {
        esc_html_e( 'No log entries found.', 'clear-cache-everywhere' );
    } // End no_items()

}
