jQuery( $ => {
    // console.log( 'Clear Cache Everywhere Front Notice JS Loaded...' );

    setTimeout( function() {
        $( '.clear-cache-everywhere-notices' ).fadeOut( 500, function() {
            $( this ).remove();
        } );
    }, 2000 );

} );