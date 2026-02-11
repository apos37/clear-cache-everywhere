jQuery( $ => {
    // console.log( 'Clear Cache Everywhere Clearing JS Loaded...' );

    // Utility function to format cache clearing messages
    function formatCacheMessage( data ) {
        if ( ! data || ! data.status ) return '';

        const statusClass = data.status === 'success'
            ? 'success'
            : ( data.status === 'info' ? 'info' : 'fail' );

        const elapsed = data.end && data.start
            ? ' (' + ((data.end - data.start).toFixed(3)) + 's)'
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

    // Settings page functionality
    if ( cceverywhere_ajax.is_settings_page ) {
        const btn = $( 'button#cce-clear-cache-btn' );
        const mainResultDiv = $( '#cce-clear-cache-result' );

        // Clear All button click
        btn.on( 'click', function( e ) {
            e.preventDefault();
            btn.prop( 'disabled', true );
            $( '.cce-run-action-btn' ).prop( 'disabled', true ); // disable all individual buttons
            mainResultDiv.html( '' );

            $( '.cce-action-result' ).removeClass( 'success fail info' ).text( '' );

            const ajaxActions = cceverywhere_ajax.clearing_actions.filter( f => f.run_context === 'ajax' );
            if ( ! ajaxActions.length ) {
                mainResultDiv.text( cceverywhere_ajax.text.no_actions );
                btn.prop( 'disabled', false );
                $( '.cce-run-action-btn' ).prop( 'disabled', false );
                return;
            }

            let index = 0;

            function runNext() {
                if ( index >= ajaxActions.length ) {
                    mainResultDiv.text( cceverywhere_ajax.text.cache_cleared );
                    $( '.cce-action-result.run-context-page.enabled' )
                        .removeClass( 'success fail info' )
                        .html( `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );
                    window.location.href = window.location.href + ( window.location.href.includes( '?' ) ? '&' : '?' ) + 'cce_run_page=1';
                    return;
                }

                const action = ajaxActions[ index ];
                if ( ! action.enabled ) {
                    index++;
                    runNext();
                    return;
                }
                
                mainResultDiv.html( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }... <span class="cce-loading-spinner"></span><br>${ cceverywhere_ajax.text.please_wait }` );

                const actionResultDiv = $( '#cce-action-result-' + action.key );
                actionResultDiv.removeClass( 'success fail info' ).text( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }...` );

                $.post( cceverywhere_ajax.ajax_url, {
                    action: 'cceverywhere_clear_ajax_action',
                    nonce: cceverywhere_ajax.nonce,
                    key: action.key
                }, function( response ) {
                    if ( response.success ) {
                        const result = formatCacheMessage( response.data );
                        actionResultDiv.addClass( result.statusClass ).text( result.msg );
                    } else {
                        actionResultDiv.addClass( 'fail' ).text( 'FAIL - ' + ( response.data || cceverywhere_ajax.text.cache_failed ) );
                    }

                    index++;
                    runNext();
                });
            }

            runNext();
        });

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

                $.post( cceverywhere_ajax.ajax_url, {
                    action: 'cceverywhere_clear_ajax_action',
                    nonce: cceverywhere_ajax.nonce,
                    key: key
                }, function( response ) {
                    let text = '';
                    let statusClass = 'fail';

                    if ( response.success ) {
                        const result = formatCacheMessage( response.data );
                        statusClass = result.statusClass;
                        text = result.msg;
                    } else {
                        statusClass = 'fail';
                        text = 'FAIL - ' + ( response.data || cceverywhere_ajax.text.cache_failed );
                    }

                    resultDiv.removeClass( 'success fail info' ).addClass( statusClass ).text( text );
                    btn.prop( 'disabled', false );
                });

            // If it's a page action
            } else if ( action.run_context === 'page' ) {
                resultDiv.removeClass( 'success fail info' ).html( `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );

                // Reload page with single-page action query param
                const separator = window.location.href.includes( '?' ) ? '&' : '?';
                window.location.href = window.location.href + separator + 'cce_run_single_page=1&key=' + encodeURIComponent( key );
            }
        });

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
                window.location.href = window.location.href + ( window.location.href.includes( '?' ) ? '&' : '?' ) + 'cce_run_page=1';
                return;
            }

            let index = 0;
            let hasFail = false;

            function runNext() {
                if ( index >= ajaxActions.length ) {
                    window.location.href = window.location.href + ( window.location.href.includes( '?' ) ? '&' : '?' ) + 'cce_run_page=1';
                    return;
                }

                const action = ajaxActions[ index ];
                if ( ! action.enabled ) {
                    index++;
                    runNext();
                    return;
                }

                console.log( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }...` );

                $.post( cceverywhere_ajax.ajax_url, {
                    action: 'cceverywhere_clear_ajax_action',
                    nonce: cceverywhere_ajax.nonce,
                    key: action.key
                }, function( response ) {
                    let statusClass = 'fail';
                    let msg = '';

                    if ( response.success ) {
                        const result = formatCacheMessage( response.data );
                        statusClass = result.statusClass;
                        msg = result.msg;
                    } else {
                        statusClass = 'fail';
                        msg = 'FAIL - ' + ( response.data || cceverywhere_ajax.text.cache_failed );
                    }

                    console.log( msg );

                    if ( statusClass === 'fail' ) hasFail = true;

                    index++;
                    runNext();
                });
            }

            runNext();
        });
    }

    // Handle page-context actions triggered by query params
    const urlParams = new URLSearchParams( window.location.search );

    function removeQueryParam( paramRegex ) {
        const newUrl = window.location.href
            .replace( paramRegex, function( match, p1, p2 ) {
                return p1 === '?' && p2 ? '?' : '';
            })
            .replace( /(\?|\&)$/, '' );
        window.history.replaceState( null, '', newUrl );
    }

    if ( cceverywhere_ajax.nonce ) {

        // Run all page-context actions
        if ( urlParams.get( 'cce_run_page' ) === '1' ) {
            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_page_actions',
                nonce: cceverywhere_ajax.nonce
            }).always( function( response ) {
                console.log( 'Page-context actions completed. Updating results...' );
                console.log( response );

                // Iterate over all page-context actions and update result divs
                cceverywhere_ajax.clearing_actions.forEach( action => {
                    if ( action.run_context === 'page' ) {
                        const resultDiv = $( '#cce-action-result-' + action.key );
                        const res = response.data[ action.key ] || { status: 'fail', error_message: null };
                        const result = formatCacheMessage( res );
                        resultDiv.removeClass( 'success fail info' ).addClass( result.statusClass ).text( result.msg );
                    }
                });

                removeQueryParam( /([?&])cce_run_page=1(&|$)/ );
            });
        }

        // Run single page-context action
        if ( urlParams.get( 'cce_run_single_page' ) === '1' && urlParams.has( 'key' ) ) {
            const key = urlParams.get( 'key' );

            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_single_page_action',
                nonce: cceverywhere_ajax.nonce,
                key: key
            }).always( function( response ) {
                const key = urlParams.get( 'key' );
                const resultDiv = $( '#cce-action-result-' + key );
                const res = response.data || { status: 'fail', error_message: null };
                const result = formatCacheMessage( res );
                resultDiv.removeClass( 'success fail info' ).addClass( result.statusClass ).text( result.msg );
                removeQueryParam( /([?&])cce_run_single_page=1&key=[^&]+/ );
            });
        }
    }

});
