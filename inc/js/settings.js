jQuery( $ => {
    const form = $( '#cceverywhere-settings-form' );
    const saveButton = $( '#cceverywhere-save-settings' );
    const saveReminder = $( '#cceverywhere-save-reminder' );
    const originalSaveText = saveButton.text();
    let isDirty = false;
    let isSaving = false;

    function markDirty() {
        if ( ! isDirty ) {
            isDirty = true;
            $( '#cceverywhere-save-status' ).remove();
            saveReminder.fadeIn( 150 );
        }
    }

    function clearDirty() {
        isDirty = false;
        saveReminder.hide();
    }

    function showSaving() {
        isSaving = true;
        $( '#cceverywhere-save-status' ).remove();
        saveButton.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update cceverywhere-spin"></span> ' + cceverywhere_settings.text.saving );
    }

    function showResult( message, success = true ) {
        isSaving = false;
        saveButton.prop( 'disabled', false ).text( originalSaveText );
        const status = $( '<span id="cceverywhere-save-status"></span>' ).text( message ).css( { color: success ? 'green' : 'red' } );
        saveButton.after( status );
    }

    function syncClearingActions( data ) {
        cceverywhere_ajax.clearing_actions = data.clearing_actions;
        cceverywhere_ajax.text.cache_cleared = data.cache_cleared_text;
        data.clearing_actions.forEach( action => {
            $( '#cce-action-result-' + action.key ).toggleClass( 'enabled', !! action.enabled ).toggleClass( 'disabled', ! action.enabled );
        } );
    }

    function saveSettings() {
        if ( isSaving ) {
            return;
        }

        if ( document.activeElement && typeof document.activeElement.blur === 'function' ) {
            document.activeElement.blur();
        }

        showSaving();

        $.ajax( {
            url: cceverywhere_settings.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: form.serialize() + '&' + $.param( { action: 'cceverywhere_save_settings', nonce: cceverywhere_settings.nonce } ),
            success: function( response ) {
                if ( response.success ) {
                    syncClearingActions( response.data );
                    clearDirty();
                    showResult( response.data.msg || cceverywhere_settings.text.saved );
                } else {
                    showResult( response.data && response.data.msg ? response.data.msg : cceverywhere_settings.text.error_saving, false );
                }
            },
            error: function() {
                showResult( cceverywhere_settings.text.error_saving, false );
            }
        } );
    }

    $( document ).on( 'change input', '#cceverywhere-settings-form [name]', markDirty );

    $( window ).on( 'beforeunload', function( e ) {
        if ( isDirty ) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    } );

    saveButton.on( 'click', saveSettings );

    form.on( 'submit', function( e ) {
        e.preventDefault();
        saveSettings();
    } );

    $( document ).on( 'keydown', function( e ) {
        if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) {
            e.preventDefault();
            saveSettings();
        }
    } );
} );