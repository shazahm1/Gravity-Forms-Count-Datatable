( function( $ ) {

	// $( document ).ready( function() {
	// 	$( '#date_range' ).trigger( 'change' );
	// } );

	$( '#date_range' ).on( 'change', function() {

		var selected = $( this ).val();

		if ( 'custom' === selected ) {

			$( '#custom_date_range' ).fadeIn( 500 );

		} else {

			$( '#custom_date_range' ).fadeOut( 500 );
		}
	} );

} )( jQuery );
