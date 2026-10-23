jQuery( $ => {
    const statusText = $( '#cceverywhere-logs-status' );

    // Clear All and Clear All Success
    $( '.cceverywhere-clear-logs' ).on( 'click', function() {
        const button = $( this );
        const status = String( button.data( 'status' ) || '' );

        if ( ! window.confirm( status === 'success' ? cceverywhere_logs.text.confirm_success : cceverywhere_logs.text.confirm_all ) ) {
            return;
        }

        $( '.cceverywhere-clear-logs' ).prop( 'disabled', true );
        statusText.removeClass( 'is-error' ).html( '<span class="dashicons dashicons-update cceverywhere-spin"></span>' );

        $.post( cceverywhere_logs.ajax_url, {
            action: 'cceverywhere_clear_logs',
            nonce: cceverywhere_logs.nonce,
            status: status
        } ).done( response => {
            if ( cceverywhere_logs.console_log && window.console ) {
                console.log( '[Clear Cache Everywhere] Clear logs', response );
            }

            if ( ! response.success ) {
                $( '.cceverywhere-clear-logs' ).prop( 'disabled', false );
                statusText.addClass( 'is-error' ).text( response.data || cceverywhere_logs.text.error );
                return;
            }

            statusText.text( cceverywhere_logs.text.cleared.replace( '%d', response.data.deleted ) );
            setTimeout( () => window.location.reload(), 700 );
        } ).fail( jqXHR => {
            $( '.cceverywhere-clear-logs' ).prop( 'disabled', false );
            statusText.addClass( 'is-error' ).text( `${ cceverywhere_logs.text.error } (HTTP ${ jqXHR.status })` );
        } );
    } );

    // Changing the per-page choice applies the filters
    $( '#cceverywhere-filter-per_page' ).on( 'change', function() {
        $( '#cceverywhere-logs-form' ).find( 'input[name="filter_action"]' ).trigger( 'click' );
    } );
} );
