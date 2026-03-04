/**
 * Metabox Handler for Donation CPT Admin
 *
 * @package CharityPlugin
 */

( function( $ ) {
	'use strict';

	const DonationMetabox = {
		init() {
			this.table = $( '#donation-rows-table' );
			if ( ! this.table.length ) {
				return;
			}

			this.bindEvents();
			this.ensureMinRows();
		},

		bindEvents() {
			const self = this;

			$( document ).on( 'click', '#donation-row-add', ( e ) => {
				e.preventDefault();
				self.addRow();
			} );

			$( document ).on( 'click', '.donation-row-remove', ( e ) => {
				e.preventDefault();
				self.removeRow( e.currentTarget );
			} );

			// Format number inputs
			this.table.on( 'input', '.charity-amount-input', ( e ) => {
				self.formatNumberInput( e.currentTarget );
			} );
		},

		addRow() {
			const template = $( '#donation-row-template' ).html();
			if ( ! template ) {
				return;
			}
			this.table.find( 'tbody' ).append( template );
		},

		removeRow( button ) {
			const $tbody = this.table.find( 'tbody' );
			$( button ).closest( 'tr' ).remove();

			// Ensure at least one row
			if ( 0 === $tbody.children( 'tr' ).length ) {
				this.addRow();
			}
		},

		formatNumberInput( input ) {
			// Remove non-numeric characters except decimal
			const value = input.value.split( '.' )[ 0 ].replace( /[^\d]/g, '' );
			input.value = value;
		},

		ensureMinRows() {
			if ( 0 === this.table.find( 'tbody tr' ).length ) {
				this.addRow();
			}
		},
	};

	// Initialize on document ready
	$( () => {
		DonationMetabox.init();
	} );

} )( jQuery );
