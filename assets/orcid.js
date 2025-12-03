/**
 * ORCID Validation Script
 *
 * Handles client-side ORCID validation using WordPress AJAX.
 *
 * @package ORCID
 * @since 1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * ORCID Validator object.
	 */
	const OrcidValidator = {
		/**
		 * Initialize the validator.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			const $orcidField = $( '#orcid' );

			if ( ! $orcidField.length ) {
				return;
			}

			// Validate on blur.
			$orcidField.on( 'blur', this.handleBlur.bind( this ) );

			// Validate on form submission.
			$( '.comment-form' ).on( 'submit', this.handleSubmit.bind( this ) );

			// Also handle user profile form.
			$( '#your-profile, #createuser' ).on( 'submit', this.handleSubmit.bind( this ) );
		},

		/**
		 * Handle blur event on ORCID field.
		 *
		 * @param {Event} e The blur event.
		 */
		handleBlur: function( e ) {
			const value = $( e.target ).val().trim();

			if ( value === '' ) {
				this.hideAllIcons();
				this.setInstructions( orcidData.i18n.example );
				return;
			}

			this.validateOrcid( value );
		},

		/**
		 * Handle form submission.
		 *
		 * @param {Event} e The submit event.
		 * @return {boolean} Whether to allow form submission.
		 */
		handleSubmit: function( e ) {
			const $orcidField = $( '#orcid' );
			const value = $orcidField.val().trim();

			// Allow empty ORCID.
			if ( value === '' ) {
				return true;
			}

			// Check if format is valid before submitting.
			if ( ! this.isValidFormat( value ) ) {
				e.preventDefault();
				this.showIcon( 'failure' );
				this.setInstructions( orcidData.i18n.invalidFormat );
				return false;
			}

			return true;
		},

		/**
		 * Validate ORCID via AJAX.
		 *
		 * @param {string} orcid The ORCID to validate.
		 */
		validateOrcid: function( orcid ) {
			// First check local format validation.
			if ( ! this.isValidFormat( orcid ) ) {
				this.showIcon( 'failure' );
				this.setInstructions( orcidData.i18n.invalidFormat );
				return;
			}

			// Show loading indicator.
			this.showIcon( 'waiting' );

			// Make AJAX request.
			$.ajax( {
				url: orcidData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'validate_orcid',
					nonce: orcidData.nonce,
					orcid: this.extractOrcidId( orcid )
				},
				success: this.handleValidationSuccess.bind( this ),
				error: this.handleValidationError.bind( this )
			} );
		},

		/**
		 * Handle successful validation response.
		 *
		 * @param {Object} response The AJAX response.
		 */
		handleValidationSuccess: function( response ) {
			if ( response.success && response.data ) {
				this.showIcon( 'success' );
				const message = orcidData.i18n.youAre.replace( '%s', response.data.name || response.data.orcid );
				this.setInstructions( message );
			} else {
				this.showIcon( 'failure' );
				this.setInstructions( response.data?.message || orcidData.i18n.notFound );
			}
		},

		/**
		 * Handle validation error.
		 */
		handleValidationError: function() {
			this.showIcon( 'failure' );
			this.setInstructions( orcidData.i18n.notFound );
		},

		/**
		 * Check if ORCID format is valid.
		 *
		 * Accepts formats:
		 * - 0000-0000-0000-000X
		 * - https://orcid.org/0000-0000-0000-000X
		 * - orcid.org/0000-0000-0000-000X
		 *
		 * @param {string} input The input to validate.
		 * @return {boolean} True if format is valid.
		 */
		isValidFormat: function( input ) {
			const orcid = this.extractOrcidId( input );
			// ORCID format: 0000-0000-0000-000X (where X can be 0-9 or X).
			const orcidRegex = /^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{3}[0-9X]$/;
			return orcidRegex.test( orcid );
		},

		/**
		 * Extract ORCID ID from URL or string.
		 *
		 * @param {string} input The input string.
		 * @return {string} The extracted ORCID ID.
		 */
		extractOrcidId: function( input ) {
			const pos = input.indexOf( 'orcid.org/' );
			if ( pos !== -1 ) {
				return input.substring( pos + 10 );
			}
			return input;
		},

		/**
		 * Show a specific icon and hide others.
		 *
		 * @param {string} iconType The icon type: 'success', 'failure', or 'waiting'.
		 */
		showIcon: function( iconType ) {
			this.hideAllIcons();
			$( '#orcid-' + iconType ).show();
		},

		/**
		 * Hide all validation icons.
		 */
		hideAllIcons: function() {
			$( '.orcid-icon' ).hide();
		},

		/**
		 * Set the instructions text.
		 *
		 * @param {string} text The text to display.
		 */
		setInstructions: function( text ) {
			$( '#orcid-instructions' ).text( text );
		}
	};

	/**
	 * Initialize when document is ready.
	 */
	$( document ).ready( function() {
		OrcidValidator.init();
	} );

} )( jQuery );
