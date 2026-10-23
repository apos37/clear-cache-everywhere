jQuery( $ => {
    // console.log( 'Clear Cache Everywhere Clearing JS Loaded...' );

    // Utility function to format cache clearing messages
    function formatCacheMessage( data ) {
        if ( ! data || ! data.status ) return '';

        const statusClass = data.status === 'success'
            ? 'success'
            : ( data.status === 'info' ? 'info' : 'fail' );

        const elapsed = data.end && data.start
            ? ' (' + ( ( data.end - data.start ).toFixed( 3 ) ) + 's)'
            : '';

        const label = statusClass === 'success'
            ? cceverywhere_ajax.text.last_cleared
            : cceverywhere_ajax.text.last_attempted;

        let msg = `${ label } ${ data.datetime || '' }${ elapsed } - ${ statusClass.toUpperCase() }`;

        if ( data.error_message ) {
            msg += ' - ' + data.error_message;
        }

        return { msg, statusClass };
    }

    // Run one AJAX action. Always resolves with { statusClass, msg }, even on HTTP errors
    function postAction( key ) {
        return $.post( cceverywhere_ajax.ajax_url, {
            action: 'cceverywhere_clear_ajax_action',
            nonce: cceverywhere_ajax.nonce,
            key: key
        } ).then( response => {
            if ( response.success ) {
                return formatCacheMessage( response.data );
            }
            return { statusClass: 'fail', msg: 'FAIL - ' + ( response.data || cceverywhere_ajax.text.cache_failed ) };
        }, jqXHR => {
            const detail = ( jqXHR.responseText || jqXHR.statusText || 'No response body' ).replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim().substring( 0, 400 );
            const result = { statusClass: 'fail', msg: `FAIL - HTTP ${ jqXHR.status } - ${ detail }` };
            console.error( `Clear Cache Everywhere: ${ key } failed`, jqXHR.status, jqXHR.responseText );
            return $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_log_client_error',
                nonce: cceverywhere_ajax.nonce,
                key: key,
                http_status: jqXHR.status,
                detail: detail
            } ).then( () => result, () => result );
        } );
    }

    // Run a list of actions in order, continuing past failures
    function runActions( actions, hooks ) {
        const failures = [];
        let index = 0;

        function next() {
            if ( index >= actions.length ) {
                hooks.done( failures );
                return;
            }

            const action = actions[ index ];
            index++;

            if ( ! action.enabled ) {
                next();
                return;
            }

            hooks.start( action );

            postAction( action.key ).then( result => {
                if ( result.statusClass === 'fail' ) {
                    failures.push( action.title );
                }
                hooks.finish( action, result );
                next();
            } );
        }

        next();
    }

    function pageActionsUrl() {
        return window.location.href + ( window.location.href.includes( '?' ) ? '&' : '?' ) + 'cce_run_page=1';
    }

    // Settings page functionality
    if ( cceverywhere_ajax.is_settings_page ) {
        const btn = $( 'button#cce-clear-cache-btn' );
        const mainResultDiv = $( '#cce-clear-cache-result' );

        // Clear All button click
        btn.on( 'click', function( e ) {
            e.preventDefault();
            btn.prop( 'disabled', true );
            $( '.cce-run-action-btn' ).prop( 'disabled', true );
            mainResultDiv.html( '' );

            $( '.cce-action-result' ).removeClass( 'success fail info' ).text( '' );

            const ajaxActions = cceverywhere_ajax.clearing_actions.filter( f => f.run_context === 'ajax' );
            if ( ! ajaxActions.length ) {
                mainResultDiv.text( cceverywhere_ajax.text.no_actions );
                btn.prop( 'disabled', false );
                $( '.cce-run-action-btn' ).prop( 'disabled', false );
                return;
            }

            runActions( ajaxActions, {
                start: action => {
                    mainResultDiv.html( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }... <span class="cce-loading-spinner"></span><br>${ cceverywhere_ajax.text.please_wait }` );
                    $( '#cce-action-result-' + action.key ).removeClass( 'success fail info' ).text( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }...` );
                },
                finish: ( action, result ) => {
                    $( '#cce-action-result-' + action.key ).addClass( result.statusClass ).text( result.msg );
                },
                done: failures => {
                    const reload = () => {
                        $( '.cce-action-result.run-context-page.enabled' )
                            .removeClass( 'success fail info' )
                            .html( `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );
                        window.location.href = pageActionsUrl();
                    };

                    if ( failures.length ) {
                        mainResultDiv.text( `Completed with ${ failures.length } failure(s): ${ failures.join( ', ' ) }. Reloading...` );
                        setTimeout( reload, 3000 );
                        return;
                    }

                    mainResultDiv.text( cceverywhere_ajax.text.cache_cleared );
                    reload();
                }
            } );
        } );

        // Individual "Run Independently" buttons
        $( '.cce-run-action-btn' ).on( 'click', function( e ) {
            e.preventDefault();
            const btn = $( this );
            const key = btn.data( 'key' );
            const action = cceverywhere_ajax.clearing_actions.find( f => f.key === key );
            if ( ! action ) return;

            const resultDiv = $( '#cce-action-result-' + key );
            btn.prop( 'disabled', true );

            // If it's an AJAX action
            if ( action.run_context === 'ajax' ) {
                resultDiv.removeClass( 'success fail info' ).html( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title } <span class="cce-loading-spinner"></span>` );

                postAction( key ).then( result => {
                    resultDiv.removeClass( 'success fail info' ).addClass( result.statusClass ).text( result.msg );
                    btn.prop( 'disabled', false );
                } );

            // If it's a page action
            } else if ( action.run_context === 'page' ) {
                resultDiv.removeClass( 'success fail info' ).html( `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );

                // Reload page with single-page action query param
                const separator = window.location.href.includes( '?' ) ? '&' : '?';
                window.location.href = window.location.href + separator + 'cce_run_single_page=1&key=' + encodeURIComponent( key );
            }
        } );

    }

    // Admin bar button
    const adminBarBtn = $( '#wp-admin-bar-cceverywhere_adminbar_btn' );
    if ( adminBarBtn.length ) {
        adminBarBtn.on( 'click', function( e ) {
            e.preventDefault();
            const link = $( this ).find( 'a' );
            link.css( 'pointer-events', 'none' ).addClass( 'cce-running' );

            const ajaxActions = cceverywhere_ajax.clearing_actions.filter( f => f.run_context === 'ajax' );
            if ( ! ajaxActions.length ) {
                link.removeClass( 'cce-running' ).addClass( 'bg-success text-white' );
                window.location.href = pageActionsUrl();
                return;
            }

            runActions( ajaxActions, {
                start: action => console.log( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }...` ),
                finish: ( action, result ) => console.log( result.msg ),
                done: failures => {
                    if ( failures.length ) {
                        console.error( 'Clear Cache Everywhere failures:', failures );
                    }
                    window.location.href = pageActionsUrl();
                }
            } );
        } );
    }

    // Handle page-context actions triggered by query params
    const urlParams = new URLSearchParams( window.location.search );

    function removeQueryParam( paramRegex ) {
        const newUrl = window.location.href
            .replace( paramRegex, function( match, p1, p2 ) {
                return p1 === '?' && p2 ? '?' : '';
            } )
            .replace( /(\?|&)$/, '' );
        window.history.replaceState( null, '', newUrl );
    }

    if ( cceverywhere_ajax.nonce ) {

        // Run all page-context actions
        if ( urlParams.get( 'cce_run_page' ) === '1' ) {
            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_page_actions',
                nonce: cceverywhere_ajax.nonce
            } ).always( function( response ) {
                console.log( 'Page-context actions completed. Updating results...' );
                console.log( response );

                // Iterate over all page-context actions and update result divs
                const pageData = ( response && response.data ) || {};

                cceverywhere_ajax.clearing_actions.forEach( action => {
                    if ( action.run_context === 'page' ) {
                        const resultDiv = $( '#cce-action-result-' + action.key );
                        const res = pageData[ action.key ] || { status: 'fail', error_message: 'No result returned. Check the browser console and debug log.' };
                        const result = formatCacheMessage( res );
                        resultDiv.removeClass( 'success fail info' ).addClass( result.statusClass ).text( result.msg );
                    }
                } );

                removeQueryParam( /([?&])cce_run_page=1(&|$)/ );
            } );
        }

        // Run single page-context action
        if ( urlParams.get( 'cce_run_single_page' ) === '1' && urlParams.has( 'key' ) ) {
            const key = urlParams.get( 'key' );

            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_single_page_action',
                nonce: cceverywhere_ajax.nonce,
                key: key
            } ).always( function( response ) {
                const key = urlParams.get( 'key' );
                const resultDiv = $( '#cce-action-result-' + key );
                const res = response.data || { status: 'fail', error_message: null };
                const result = formatCacheMessage( res );
                resultDiv.removeClass( 'success fail info' ).addClass( result.statusClass ).text( result.msg );
                removeQueryParam( /([?&])cce_run_single_page=1&key=[^&]+/ );
            } );
        }
    }

} );