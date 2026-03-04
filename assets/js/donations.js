/**
 * Charity Donation Frontend Module
 *
 * @package CharityPlugin
 */

( function( $ ) {
	'use strict';

	/**
	 * DonationForm Module - Handles donation form interactions
	 */
	const DonationForm = {
		// Configuration
		config: {
			wrapperSelector: '.donation-wrapper',
			cardSelector: '.donation-card',
			customInputSelector: '.donation-custom-input',
			submitButtonSelector: '.donation-submit',
			messageSelector: '.donation-msg',
			extraCheckboxSelector: '.donation-extra-checkbox',
		},

		/**
		 * Initialize the donation form
		 */
		init() {
			if ( ! window.CharityDonations ) {
				console.warn( 'CharityDonations localization data not found.' );
				return;
			}

			this.bindEvents();
			this.injectCheckoutExtras();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			$( document ).on( 'click', this.config.cardSelector, ( e ) => {
				this.selectCard( e.currentTarget );
			} );

			$( document ).on( 'input', this.config.customInputSelector, ( e ) => {
				this.handleCustomInput( e.currentTarget );
			} );

			$( document ).on( 'keydown', this.config.customInputSelector, ( e ) => {
				this.handleCustomKeydown( e );
			} );

			$( document ).on( 'click', this.config.submitButtonSelector, ( e ) => {
				this.handleSubmit( e );
			} );

			// Cart fragment updates
			$( document.body ).on( 'removed_from_cart', () => {
				this.clearFragments();
			} );

			// Force hard navigation on remove
			$( document ).on( 'click touchend', 'a.remove_from_cart_button', ( e ) => {
				e.preventDefault();
				e.stopImmediatePropagation();
				window.location.assign( e.currentTarget.href );
			} );
		},

		/**
		 * Handle card selection
		 */
		selectCard( card ) {
			const $card = $( card );
			const $wrapper = $card.closest( this.config.wrapperSelector );

			$wrapper.find( this.config.cardSelector )
				.removeClass( 'active' )
				.attr( 'aria-selected', 'false' );

			$card.addClass( 'active' ).attr( 'aria-selected', 'true' );

			// Clear custom input when card is selected
			$wrapper.find( this.config.customInputSelector ).val( '' );
		},

		/**
		 * Handle custom amount input
		 */
		handleCustomInput( input ) {
			const $input = $( input );
			const $wrapper = $input.closest( this.config.wrapperSelector );
			const amount = this.parseAmount( $input.val() );

			if ( amount > 0 ) {
				// Deselect cards when custom amount is entered
				$wrapper.find( this.config.cardSelector )
					.removeClass( 'active' )
					.attr( 'aria-selected', 'false' );
			}
		},

		/**
		 * Handle Enter key in custom input
		 */
		handleCustomKeydown( e ) {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				$( e.currentTarget ).closest( this.config.wrapperSelector )
					.find( this.config.submitButtonSelector )
					.trigger( 'click' );
			}
		},

		/**
		 * Handle form submission
		 */
		handleSubmit( e ) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			const $wrapper = $button.closest( this.config.wrapperSelector );

			// Validate inputs
			const postId = this.getPostId( $wrapper );
			const amount = this.getSelectedAmount( $wrapper );
			const description = this.getSelectedDescription( $wrapper );

			if ( ! this.validate( postId, amount ) ) {
				return;
			}

			// Disable button and submit
			$button.prop( 'disabled', true );
			$button.attr( 'aria-busy', 'true' );

			// Get selected extras
			const extras = this.getSelectedExtras( $wrapper );

			this.submitDonation( {
				post_id: postId,
				amount: amount,
				description: description,
				extras: extras,
			}, $button, $wrapper );
		},

		/**
		 * Submit donation via AJAX
		 */
		submitDonation( data, $button, $wrapper ) {
			const self = this;

			$.ajax( {
				url: window.CharityDonations.ajax_url,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'donation_add_to_cart',
					nonce: window.CharityDonations.nonce,
					post_id: data.post_id,
					amount: data.amount,
					description: data.description,
					extras: JSON.stringify( data.extras ),
				},
				success( response ) {
					self.handleSuccess( response, $wrapper, $button );
				},
				error( xhr, status, error ) {
					self.handleError( xhr, status, error, $button, $wrapper );
				},
				complete() {
					$button.prop( 'disabled', false );
					$button.attr( 'aria-busy', 'false' );
				},
			} );
		},

		/**
		 * Handle successful donation submission
		 */
		handleSuccess( response, $wrapper, $button ) {
			if ( ! response || ! response.success ) {
				const message = response && response.data && response.data.message
					? response.data.message
					: 'Could not add to cart. Please try again.';
				this.showMessage( $wrapper, message, 'error' );
				return;
			}

			// Clear cart fragments
			try {
				sessionStorage.removeItem( 'wc_fragments' );
				localStorage.removeItem( 'wc_fragments' );
				localStorage.removeItem( 'wc_cart_hash' );
			} catch ( e ) {
				// Ignore localStorage errors
			}

			// Trigger updates
			$( document.body ).trigger( 'wc_fragment_refresh' );
			$( document.body ).trigger( 'added_to_cart' );

			// Show success message
			this.showMessage( $wrapper, 'Added to basket.', 'success' );

			// Redirect disabled: do not navigate to checkout automatically
			// Previously redirected to response.data.redirect here; now intentionally no-op.
		},

		/**
		 * Handle error response
		 */
		handleError( xhr, status, error, $button, $wrapper ) {
			console.error( 'Donation error:', status, error );

			let message = 'An error occurred. Please try again.';

			// Try to parse error response
			try {
				if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					message = xhr.responseJSON.data.message;
				}
			} catch ( e ) {
				// Fall back to generic message
			}

			this.showMessage( $wrapper, message, 'error' );
		},

		/**
		 * Show feedback message
		 */
		showMessage( $wrapper, message, type ) {
			const $msg = $wrapper.find( this.config.messageSelector );
			$msg.removeClass( 'message-error message-success' )
				.addClass( `message-${ type }` )
				.text( message )
				.show();

			// Auto-hide after 5 seconds
			if ( 'success' === type ) {
				setTimeout( () => {
					$msg.fadeOut();
				}, 3000 );
			}
		},

		/**
		 * Get post ID from wrapper
		 */
		getPostId( $wrapper ) {
			return parseInt( $wrapper.data( 'post-id' ), 10 ) || 0;
		},

		/**
		 * Get selected donation amount
		 */
		getSelectedAmount( $wrapper ) {
			const customAmount = this.getCustomAmount( $wrapper );
			if ( customAmount > 0 ) {
				return customAmount;
			}

			const $active = $wrapper.find( `${ this.config.cardSelector }.active` ).first();
			if ( $active.length ) {
				return this.parseAmount( $active.data( 'amount' ) );
			}

			return 0;
		},

		/**
		 * Get custom amount input value
		 */
		getCustomAmount( $wrapper ) {
			const $input = $wrapper.find( this.config.customInputSelector );
			if ( ! $input.length ) {
				return 0;
			}

			return this.parseAmount( $input.val() );
		},

		/**
		 * Get selected description
		 */
		getSelectedDescription( $wrapper ) {
			if ( this.getCustomAmount( $wrapper ) > 0 ) {
				const $input = $wrapper.find( this.config.customInputSelector );
				return $input.attr( 'placeholder' ) || 'Custom amount';
			}

			const $active = $wrapper.find( `${ this.config.cardSelector }.active` ).first();
			if ( $active.length ) {
				const desc = $active.data( 'desc' );
				if ( desc && String( desc ).trim() ) {
					return String( desc );
				}
			}

			return 'Donation';
		},

		/**
		 * Get selected extras as array
		 */
		getSelectedExtras( $wrapper ) {
			const extras = [];
			$wrapper.find( `${ this.config.extraCheckboxSelector }:checked` ).each( ( i, el ) => {
				const $checkbox = $( el );
				extras.push( {
					label: $checkbox.data( 'label' ),
					amount: parseFloat( $checkbox.data( 'amount' ) ),
				} );
			} );
			return extras;
		},

		/**
		 * Parse amount string to float
		 */
		parseAmount( value ) {
			if ( null == value ) {
				return 0;
			}

			const str = String( value ).replace( /[^\d.,\-]/g, '' ).replace( ',', '.' );
			const num = parseFloat( str );

			return isNaN( num ) ? 0 : num;
		},

		/**
		 * Validate donation data
		 */
		validate( postId, amount ) {
			if ( ! postId ) {
				alert( 'Could not determine the donation campaign. Please refresh the page and try again.' );
				return false;
			}

			if ( amount <= 0 ) {
				alert( 'Please choose an amount or enter a valid custom amount.' );
				return false;
			}

			return true;
		},

		/**
		 * Clear WooCommerce cart fragments
		 */
		clearFragments() {
			try {
				sessionStorage.removeItem( 'wc_fragments' );
				localStorage.removeItem( 'wc_fragments' );
				localStorage.removeItem( 'wc_cart_hash' );
			} catch ( e ) {
				// Ignore errors
			}

			$( document.body ).trigger( 'wc_fragment_refresh' );
		},

		/**
		 * Inject extras UI into WooCommerce checkout DOM (robust fallback)
		 */
		injectCheckoutExtras() {
			// respect show_extras flag
			if ( ! window.CharityDonations || ! parseInt( window.CharityDonations.show_extras, 10 ) ) {
				return;
			}
			if ( ! Array.isArray( window.CharityDonations.extras ) || window.CharityDonations.extras.length === 0 ) {
			}

			const extras = window.CharityDonations.extras;

			// Ensure a single container exists (append to body initially)
			let $container = $( '#charity-checkout-extras-injected' );
			if ( ! $container.length ) {
				$container = $( '<div id="charity-checkout-extras-injected" class="charity-checkout-extras-injected" style="margin:20px 0;padding:15px;background:#f9f9f9;border-radius:4px;" />' );
				$container.append( '<h3>Support Us Further</h3>' );
				$container.append( '<p class="charity-checkout-extras-intro">Add optional extras to your donation:</p>' );
				const $list = $( '<div class="charity-checkout-extras-list" />' );

				extras.forEach( function( extra ) {
					const price = ( typeof wc_price === 'function' ) ? wc_price( extra.amount ) : ( '+' + extra.amount );
					const $item = $( '<label class="charity-checkout-extra-item" style="display:flex;align-items:center;padding:8px 0;cursor:pointer;" />' );
					const $checkbox = $( '<input type="checkbox" class="charity-checkout-extra-checkbox" style="margin-right:10px;" />' )
						.data( 'label', extra.label )
						.data( 'amount', extra.amount );
					$item.append( $checkbox );
					$item.append( $( '<span style="flex:1;color:#333;" />' ).text( extra.label ) );
					$item.append( $( '<span style="color:#27ae60;font-weight:700;margin-left:10px;white-space:nowrap;" />' ).html( price ) );
					$list.append( $item );
				} );

				$container.append( $list );
				// append to body as staging area
				$( 'body' ).append( $container );
			}

			// Placement logic tries multiple selectors and moves the container
			const placeExtras = function() {
				const $elementorSubtotal = $( '.elementor-menu-cart__subtotal' ).first();
				if ( $elementorSubtotal && $elementorSubtotal.length ) {
					$elementorSubtotal.before( $container );
					return true;
				}

				const $blocksTotals = $( '.wc-block-components-totals-wrapper' ).first();
				if ( $blocksTotals && $blocksTotals.length ) {
					$blocksTotals.after( $container );
					return true;
				}

				let $orderTotal = $( '#order_review .order-total' ).last();
				if ( $orderTotal && $orderTotal.length ) {
					const $tr = $orderTotal.closest( 'tr' );
					if ( $tr && $tr.length ) {
						$tr.after( $container );
					} else {
						$orderTotal.after( $container );
					}
					return true;
				}

				const $review = $( '#order_review, .woocommerce-checkout-review-order, .woocommerce-checkout' ).first();
				if ( $review && $review.length ) {
					$review.after( $container );
					return true;
				}

				return false;
			};

			// Try immediate placement
			placeExtras();

			// Re-run placement when checkout updates (WC or other plugins)
			$( document.body ).on( 'update_checkout updated_checkout', function() {
				placeExtras();
			} );

			// Use MutationObserver as last resort to detect late DOM inserts
			const observer = new MutationObserver( function( mutations ) {
				for ( let i = 0; i < mutations.length; i++ ) {
					const m = mutations[i];
					if ( m.addedNodes && m.addedNodes.length ) {
						if ( placeExtras() ) {
							observer.disconnect();
							break;
						}
					}
				}
			} );

			observer.observe( document.body, { childList: true, subtree: true } );

			// Handle checkbox changes (delegated in case of re-insert)
			$( document ).on( 'change', '#charity-checkout-extras-injected .charity-checkout-extra-checkbox', function() {
				const $cb = $( this );
				const label = $cb.data( 'label' );
				const amount = parseFloat( $cb.data( 'amount' ) );
				const add = $cb.is( ':checked' ) ? 1 : 0;

				$.ajax({
					url: window.CharityDonations.ajax_url,
					method: 'POST',
					dataType: 'json',
					data: {
						action: 'charity_update_checkout_extras',
						nonce: window.CharityDonations.nonce,
						label: label,
						amount: amount,
						add: add,
					},
					success: function( res ) {
						if ( res && res.success ) {
							$( document.body ).trigger( 'update_checkout' );
						} else {
							alert( ( res && res.data && res.data.message ) ? res.data.message : 'Failed to update extras' );
							$cb.prop( 'checked', ! add );
						}
					},
					error: function() {
						alert( 'Failed to update extras. Please try again.' );
						$cb.prop( 'checked', ! add );
					}
				});
			});
		},

	};

	// Initialize on document ready
	$( () => {
		DonationForm.init();
	} );

	// Export for testing
	if ( typeof window !== 'undefined' ) {
		window.CharityDonationForm = DonationForm;
	}

} )( jQuery );
