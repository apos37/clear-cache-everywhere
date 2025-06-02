jQuery( $ => {
    // console.log( 'Clear Cache Everywhere Back Notice JS Loaded...' );

    document.addEventListener( 'DOMContentLoaded', function() {
        document.querySelector( '.cce-clear-cache-btn' ).addEventListener( 'click', function() {
            this.textContent = 'Clearing...';
        } );
    } );

} );