jQuery( $ => {
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

    function debugLog( label, data ) {
        if ( ! cceverywhere_ajax.console_log || ! window.console ) {
            return;
        }

        if ( typeof data === 'undefined' ) {
            console.log( '[Clear Cache Everywhere]', label );
            return;
        }

        console.log( '[Clear Cache Everywhere]', label, data );
    }

    function postAction( key, source ) {
        return $.post( cceverywhere_ajax.ajax_url, {
            action: 'cceverywhere_clear_ajax_action',
            nonce: cceverywhere_ajax.nonce,
            key: key,
            source: source
        } ).then( response => {
            debugLog( key, response );
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
                source: source,
                http_status: jqXHR.status,
                detail: detail
            } ).then( () => result, () => result );
        } );
    }

    function runActions( actions, source, hooks ) {
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

            postAction( action.key, source ).then( result => {
                if ( result.statusClass === 'fail' ) {
                    failures.push( action.title );
                }
                hooks.finish( action, result );
                next();
            } );
        }

        next();
    }

    const resultClasses = 'success fail info skipped running';

    function setRunning( resultDiv, html ) {
        resultDiv.removeClass( resultClasses ).addClass( 'running' ).html( html );
    }

    function setResult( resultDiv, statusClass, text ) {
        resultDiv.removeClass( resultClasses ).addClass( statusClass ).text( text );
    }

    function pageActionsUrl() {
        return window.location.href + ( window.location.href.includes( '?' ) ? '&' : '?' ) + 'cce_run_page=1';
    }

    if ( cceverywhere_ajax.is_settings_page ) {
        const btn = $( 'button#cce-clear-cache-btn' );
        const mainResultDiv = $( '#cce-clear-cache-result' );

        btn.on( 'click', function( e ) {
            e.preventDefault();
            btn.prop( 'disabled', true );
            $( '.cce-run-action-btn' ).prop( 'disabled', true );
            mainResultDiv.html( '' );

            const ajaxActions = cceverywhere_ajax.clearing_actions.filter( f => f.run_context === 'ajax' );
            if ( ! ajaxActions.length ) {
                mainResultDiv.text( cceverywhere_ajax.text.no_actions );
                btn.prop( 'disabled', false );
                $( '.cce-run-action-btn' ).prop( 'disabled', false );
                return;
            }

            runActions( ajaxActions, 'settings', {
                start: action => {
                    mainResultDiv.html( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }... <span class="cce-loading-spinner"></span><br>${ cceverywhere_ajax.text.please_wait }` );
                    setRunning( $( '#cce-action-result-' + action.key ), `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }... <span class="cce-loading-spinner"></span>` );
                },
                finish: ( action, result ) => {
                    setResult( $( '#cce-action-result-' + action.key ), result.statusClass, result.msg );
                },
                done: failures => {
                    const reload = () => {
                        setRunning( $( '.cce-action-result.run-context-page.enabled' ), `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );
                        window.location.href = pageActionsUrl();
                    };

                    if ( failures.length ) {
                        mainResultDiv.text( cceverywhere_ajax.text.completed_with.replace( '%1$d', failures.length ).replace( '%2$s', failures.join( ', ' ) ) );
                        setTimeout( reload, 3000 );
                        return;
                    }

                    mainResultDiv.text( cceverywhere_ajax.text.cache_cleared );
                    reload();
                }
            } );
        } );

        $( '.cce-run-action-btn' ).on( 'click', function( e ) {
            e.preventDefault();
            const btn = $( this );
            const key = btn.data( 'key' );
            const action = cceverywhere_ajax.clearing_actions.find( f => f.key === key );
            if ( ! action ) return;

            const resultDiv = $( '#cce-action-result-' + key );
            btn.prop( 'disabled', true );

            if ( action.run_context === 'ajax' ) {
                setRunning( resultDiv, `${ cceverywhere_ajax.text.clearing_cache } ${ action.title } <span class="cce-loading-spinner"></span>` );

                postAction( key, 'individual' ).then( result => {
                    setResult( resultDiv, result.statusClass, result.msg );
                    btn.prop( 'disabled', false );
                } );

            } else if ( action.run_context === 'page' ) {
                setRunning( resultDiv, `${ cceverywhere_ajax.text.page_reload } <span class="cce-loading-spinner"></span>` );

                const separator = window.location.href.includes( '?' ) ? '&' : '?';
                window.location.href = window.location.href + separator + 'cce_run_single_page=1&key=' + encodeURIComponent( key );
            }
        } );
    }

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

            runActions( ajaxActions, 'admin_bar', {
                start: action => debugLog( `${ cceverywhere_ajax.text.clearing_cache } ${ action.title }...` ),
                finish: ( action, result ) => debugLog( action.title, result.msg ),
                done: failures => {
                    if ( failures.length ) {
                        console.error( 'Clear Cache Everywhere failures:', failures );
                    }
                    window.location.href = pageActionsUrl();
                }
            } );
        } );
    }

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

        if ( urlParams.get( 'cce_run_page' ) === '1' ) {
            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_page_actions',
                nonce: cceverywhere_ajax.nonce,
                source: 'page'
            } ).always( function( response ) {
                debugLog( 'Page-context actions completed', response );

                const pageData = ( response && response.data ) || {};

                cceverywhere_ajax.clearing_actions.forEach( action => {
                    if ( action.run_context === 'page' && action.enabled ) {
                        const resultDiv = $( '#cce-action-result-' + action.key );
                        const res = pageData[ action.key ] || { status: 'fail', error_message: cceverywhere_ajax.text.no_result };
                        const result = formatCacheMessage( res );
                        setResult( resultDiv, result.statusClass, result.msg );
                    }
                } );

                removeQueryParam( /([?&])cce_run_page=1(&|$)/ );
            } );
        }

        if ( urlParams.get( 'cce_run_single_page' ) === '1' && urlParams.has( 'key' ) ) {
            const key = urlParams.get( 'key' );

            $.post( cceverywhere_ajax.ajax_url, {
                action: 'cceverywhere_run_single_page_action',
                nonce: cceverywhere_ajax.nonce,
                key: key,
                source: 'individual'
            } ).always( function( response ) {
                debugLog( key, response );
                const resultDiv = $( '#cce-action-result-' + key );
                const res = ( response && response.data && response.data.status ) ? response.data : { status: 'fail', error_message: cceverywhere_ajax.text.no_result };
                const result = formatCacheMessage( res );
                setResult( resultDiv, result.statusClass, result.msg );
                removeQueryParam( /([?&])cce_run_single_page=1&key=[^&]+/ );
            } );
        }
    }
} );