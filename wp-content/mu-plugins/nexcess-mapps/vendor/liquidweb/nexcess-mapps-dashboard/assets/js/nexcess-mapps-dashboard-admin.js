
/**
 * Check to make sure at least one box is checked.
 */
function confirmSomethingChecked() {

	// Set our variable that one was missed.
	var checkMissed = 'missed';

	// Now check we have something selected.
	jQuery( 'input.nexcess-mapps-dashboard-plugin-input-available' ).each( function() {

		// Make sure we have a checked box.
		if ( jQuery( this ).is( ':checked' ) ) {

			// Set our checked value.
			checkMissed = 'proceed';

			// And finish looping.
			return false;
		}

		// Nothing else to do in this loop.
	});

	// Return the resulting string.
	return checkMissed;
}

/**
 * Do both the inital check then the ongoing checks for the submit.
 */
function manageInstallerSubmitAccess() {

	// Do the check on first load.
	var firstLoadCheck  = confirmSomethingChecked();

	// Run the enable / disable based on the result.
	'missed' === firstLoadCheck ? toggleInstallerSubmit( 'disable' ) : toggleInstallerSubmit( 'enable' );

	// Now look for ongoing changes.
	jQuery( 'li.nexcess-mapps-dashboard-plugin-list-single' ).on( 'change', 'input.nexcess-mapps-dashboard-plugin-input-available', function( event ) {

		// If this is checked, then we know it's OK to enable.
		if ( this.checked ) {
			toggleInstallerSubmit( 'enable' );
		}

		// If it isn't checked, then do the bigger look.
		if ( ! this.checked ) {

			// Get our new check.
			var ongoingSetCheck = confirmSomethingChecked();

			// Run the enable / disable based on the result.
			'missed' === ongoingSetCheck ? toggleInstallerSubmit( 'disable' ) : toggleInstallerSubmit( 'enable' );
		}

		// No other check needed on the change look.
	});

	// Nothing else involved.
}

/**
 * Set our install state during user interaction.
 *
 * @param string installState  What state of the install we are in.
 */
function setInstallStatus( installState ) {

	// Handle our setup for when the button is first pressed.
	if ( 'kickoff' === installState ) {

		// Disable our buttons.
		toggleInstallerButtons( 'disable' );
	}

	// Handle our setup for when the installation begins.
	if ( 'installing' === installState ) {

		// Disable our buttons.
		toggleInstallerButtons( 'disable' );

		// Display the spinner.
		toggleSpinnerDisplay( 'show' );

		// Load up the installer screen.
		toggleInstallOverlay( 'display' );
	}

	// Handle our completed state.
	if ( 'completed' === installState ) {

		// Remove the screen.
		toggleInstallOverlay( 'remove' );

		// Hide the spinner.
		toggleSpinnerDisplay( 'hide' );

		// Re-enable our buttons.
		toggleInstallerButtons( 'enable' );
	}
}

/**
 * Set just the actual submit button to disabled when clicked.
 *
 * @param string buttonState  What state to set the button.
 */
function toggleInstallerSubmit( buttonState ) {

	// Set the attribute to disabled.
	if ( 'disable' === buttonState ) {
		jQuery( 'button#nexcess-button-run-install' ).attr( 'disabled', 'disabled' );
	}

	// Set the attribute to back to enbled.
	if ( 'enable' === buttonState ) {
		jQuery( 'button#nexcess-button-run-install' ).removeAttr( 'disabled' );
	}

	// And return.
	return;
}

/**
 * Set all the available buttons to disabled when clicked.
 *
 * @param string buttonState  What state to set the button.
 */
function toggleInstallerButtons( buttonState ) {

	// Now check we have something selected.
	jQuery( 'button.nexcess-self-install-single-button' ).each( function() {

		// Set the attribute to disabled.
		if ( 'disable' === buttonState ) {
			jQuery( this ).attr( 'disabled', 'disabled' );
		}

		// Set the attribute to back to enbled.
		if ( 'enable' === buttonState ) {
			jQuery( this ).removeAttr( 'disabled' );
		}

		// No other changes required.
	});

	// And return.
	return;
}

/**
 * Show or hide the spinner.
 *
 * @param string spinnerState  What state to set the spinner.
 */
function toggleSpinnerDisplay( spinnerState ) {

	// Handle our setup for when the installation begins.
	if ( 'show' === spinnerState ) {
		jQuery( 'span.nexcess-spinner' ).css( 'visibility', 'visible' );
	}

	// Handle our completed state.
	if ( 'hide' === spinnerState ) {
		jQuery( 'span.nexcess-spinner' ).css( 'visibility', 'hidden' );
	}
}

/**
 * Show or hide the install overlay.
 *
 * @param string overlayState  Whether to show or hide the overlay.
 */
function toggleInstallOverlay( overlayState ) {

	// Handle our setup for when the installation begins.
	if ( 'display' === overlayState ) {
		jQuery( 'body' ).append( PlatformInstallAdmin.overlayWindow );
	}

	// Handle our completed state.
	if ( 'remove' === overlayState ) {
		jQuery( 'body' ).find( 'div#nexcess-installer-status-screen' ).remove();
	}
}

/**
 * Clear any possible Freemius notifications.
 */
function clearFreemiusNotices() {

	// Check for any possible notice in a loop.
	jQuery( '.fs-notice' ).each( function() {

		// Straight-up remove them.
		jQuery( this ).remove();

		// No other changes required.
	});

	// And return.
	return;
}

/**
 * Now let's get started.
 */
jQuery( document ).ready( function($) {

	/**
	 * Set some object vars for later.
	 */
	var $installerBody  = $( 'body.nexcess-selfinstall-body' );
	var $installerWrap  = $( 'div.nexcess-mapps-dashboard-page-wrap' );
	var $installerIntro = $( 'div.nexcess-mapps-dashboard-page-wrap div.nexcess-mapps-dashboard-intro-wrap' );
	var $installerFails = $( 'div.nexcess-mapps-dashboard-page-wrap div.nexcess-mapps-dashboard-failed-installs-wrap' );
	var $installerForm  = $( 'div.nexcess-mapps-dashboard-page-wrap form#nexcess-mapps-dashboard-form' );

	// Make sure we can always make the status screen disappear.
	$( document ).on( 'keyup', function( event ) {

		// Check for the escape key and close it.
		if ( event.keyCode == 27 && $installerBody.is( ':visible' ) ) {
			setInstallStatus( 'completed' );
		}
	});

	// Don't even think about running this anywhere else.
	if ( $installerBody.length > 0 ) {

		// Clear the Freemius stuff.
		clearFreemiusNotices();

		// Set the submit access function.
		manageInstallerSubmitAccess();

		/**
		 * Make sure users are aware they are clicking the reset.
		 */
		$( '.nexcess-mapps-dashboard-button-reset-choices-action' ).on( 'click', 'button', function () {

			// Bail if we didn't confirm.
			if ( ! confirm( PlatformInstallAdmin.resetText ) ) {
				return false;
			}

			// Maybe something else?
		});

		/**
		 * Handle the "select all" button action.
		 */
		$( '.nexcess-mapps-dashboard-button-select-all-action' ).on( 'click', 'button', function ( event ) {

			// Set what our div class should be.
			var arePlugins  = $( 'div.nexcess-mapps-dashboard-plugin-group' ).find( 'input.nexcess-mapps-dashboard-plugin-input' );

			// If we have no corresponding plugins, bail.
			if ( arePlugins.length === 0 ) {
				return false;
			}

			// Loop through the items and check them all.
			$( arePlugins ).each( function() {

				// Only check those boxes that aren't disabled.
				if ( false === $( this ).is( ':disabled' ) ) {
					$( this ).prop( 'checked', true );
				}

			});

			// Set the submit access function.
			manageInstallerSubmitAccess();

			// That is it for now.
		});

		/**
		 * Handle the checkbox toggle of each group.
		 */
		$( '.nexcess-mapps-dashboard-group-action' ).on( 'click', 'button', function ( event ) {

			// Get our button group value.
			var buttonGroup = $( this ).attr( 'value' );

			// Set what our div class should be.
			var arePlugins  = $( 'div[data-group="' + buttonGroup + '"]' ).find( 'input.nexcess-mapps-dashboard-plugin-input' );

			// If we have no corresponding plugins, bail.
			if ( arePlugins.length === 0 ) {
				return false;
			}

			// Loop through the items and check them all.
			$( arePlugins ).each( function() {

				// Only check those boxes that aren't disabled.
				if ( false === $( this ).is( ':disabled' ) ) {
					$( this ).prop( 'checked', true );
				}

			});

			// Set the submit access function.
			manageInstallerSubmitAccess();

			// That is it for now.
		});

		/**
		 * Handle our submit button stuff.
		 */
		/*
		$( '.nexcess-mapps-dashboard-button-run-install-action' ).on( 'click', 'button', function ( event ) {

			// Stop the actual submit.
			event.preventDefault();

			// Run the button disable.
			setInstallStatus( 'kickoff' );

			// Clear any existing notices.
			$installerWrap.find( '.nexcess-installer-notice' ).remove();

			// Check to make sure we have something.
			var checkedEverything   = confirmSomethingChecked();

			// If we missed, handle it.
			if ( 'missed' === checkedEverything ) {

				// Run the button toggle.
				setInstallStatus( 'completed' );

				// And return false. @todo figure an actual error return.
				return false;
			}

			// Bail if we didn't confirm.
			if ( ! confirm( PlatformInstallAdmin.installText ) ) {

				// Run the button toggle.
				setInstallStatus( 'completed' );

				// And return false.
				return false;
			}

			// Now set the installer screen.
			setInstallStatus( 'installing' );

			// Allow the submission to go through as requested.
			$installerForm.submit();

			// That is it for now.
		});
		*/

		/**
		 * Check for the running an install request.
		 */
		/*
		$installerForm.submit( function( event ) {

			// Stop the actual click.
			event.preventDefault();

			// Fetch the submitted nonce.
			var installNonce    = document.getElementById( 'nexcess-selfinstall-nonce' ).value;

			// Bail real quick without a nonce.
			if ( '' === installNonce || undefined === installNonce ) {

				// Run the button toggle.
				setInstallStatus( 'completed' );

				// And return false.
				return false;
			}

			// Get our choices made.
			var installSlugs    = $( 'div.nexcess-mapps-dashboard-plugin-group input.nexcess-mapps-dashboard-plugin-input-available:checked' ).map( function() {
				return this.value;
			}).get().join();

			// Build the data structure for the call.
			var data = {
				action: 'nexcess_selfinstall_install_requested_plugins',
				slugs: installSlugs,
				nonce: installNonce
			};

			// Send out the ajax call itself.
			jQuery.post( ajaxurl, data, function( response ) {

				// Run the button toggle.
				setInstallStatus( 'completed' );

				// If we have no data, just bail.
				if ( undefined === response.data ) {
					return false;
				}

				// Handle the notice on it's own.
				if ( undefined !== response.data.notice && '' !== response.data.notice ) {

					// Add the message.
					$installerIntro.find( 'h1:first' ).after( response.data.notice );
				}

				// Handle the potential Iconic notice.
				if ( undefined !== response.data.iconic && false !== response.data.iconic && '' !== response.data.iconic ) {

					// Add the message.
					$installerIntro.find( 'p.nexcess-mapps-dashboard-intro-subtitle:first' ).before( response.data.iconic );
				}

				// Handle the potential licensing notice.
				if ( undefined !== response.data.licensing && false !== response.data.licensing && '' !== response.data.licensing ) {

					// Add the message.
					$installerIntro.find( 'p.nexcess-mapps-dashboard-intro-subtitle:first' ).before( response.data.licensing );
				}

				// Handle the possible failures.
				if ( undefined !== response.data.fails && '' !== response.data.fails ) {

					// Add the message.
					$installerFails.empty().append( response.data.fails ).removeClass( 'nexcess-mapps-dashboard-failed-installs-empty' );
				}

				// No error, save our items.
				if ( response.success === true || response.success === 'true' ) {

					// Handle loading the markup.
					if ( undefined !== response.data && undefined !== response.data.markup && '' !== response.data.markup ) {
						$( 'div.nexcess-mapps-dashboard-plugin-list' ).empty().append( response.data.markup );
					}

					// No additional actions on a successful return.
				}

			}, 'json' );
		});
		*/
		/**
		 * Handle dismissing the notice.
		 */
		$installerFails.on( 'click', '.nexcess-mapps-dashboard-failed-dismiss', function() {

			// Get the nonce on the button.
			var failedNonce = $( this ).data( 'nonce' );

			// Fade out and remove the notice itself.
			$installerFails.fadeOut( 500, function() {
				$installerFails.remove();
			});

			// Build the data structure for the call.
			var data = {
				action: 'nexcess_selfinstall_clear_failed_notice',
				nonce: failedNonce
			};

			// Send out the ajax call itself.
			jQuery.post( ajaxurl, data, null, 'json' );

			// That is it for now.
		});

		/**
		 * Handle the notice dismissal.
		 */
		$installerBody.on( 'click', '.notice-dismiss', function() {
			$installerBody.find( '.nexcess-installer-notice.is-dismissible' ).remove();
		});

		// Nothing else here.
	}

//********************************************************
// You're still here? It's over. Go home.
//********************************************************
});
