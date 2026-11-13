/*
 * Orders list: the "PDF Factura" bulk action opens the PDF of the checked orders in a new tab and
 * leaves the list as it was, like PDF Invoices' bulk action did. Without this script the form
 * submits and the server sends the browser to the PDF (includes/invoice-pdf.php).
 * jl22Invoice = { action, url (with the nonce), max } from wp_localize_script.
 */
( function () {
	'use strict';
	if ( typeof jl22Invoice === 'undefined' ) {
		return;
	}
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '#doaction, #doaction2' );
		if ( ! button ) {
			return;
		}
		var select = document.getElementById( button.id === 'doaction' ? 'bulk-action-selector-top' : 'bulk-action-selector-bottom' );
		if ( ! select || select.value !== jl22Invoice.action ) {
			return;
		}
		var ids = Array.prototype.map.call(
			document.querySelectorAll( '#the-list input[name="post[]"]:checked, #the-list input[name="id[]"]:checked' ),
			function ( input ) {
				return input.value;
			}
		).slice( 0, Number( jl22Invoice.max ) || 100 );
		if ( ! ids.length ) {
			return; // WordPress itself does nothing without orders checked
		}
		event.preventDefault();
		var url = new URL( jl22Invoice.url, window.location.href );
		url.searchParams.set( 'order_ids', ids.join( ',' ) );
		window.open( url.toString(), '_blank', 'noopener' );
	} );
} )();
