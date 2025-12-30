/**
 * Virtual Media Folders AI Organizer - Admin Scripts
 *
 * @package VmfaAiOrganizer
 */

import { createRoot } from '@wordpress/element';
import { AiOrganizerPanel } from './components/AiOrganizerPanel';

import './styles/admin.scss';

/**
 * Update visibility of provider-specific settings fields.
 *
 * @param {string} provider - The selected provider key.
 */
function updateProviderFields( provider ) {
	document.querySelectorAll( '.vmfa-provider-field' ).forEach( ( field ) => {
		const row = field.closest( 'tr' );
		if ( row ) {
			row.classList.add( 'vmfa-provider-row' );
			const fieldProvider = field.dataset.provider;
			if ( fieldProvider === provider ) {
				row.classList.add( 'vmfa-provider-active' );
			} else {
				row.classList.remove( 'vmfa-provider-active' );
			}
		}
	} );
}

/**
 * Initialize the provider field toggle.
 */
function initProviderToggle() {
	const providerSelect = document.getElementById( 'vmfa_ai_provider' );
	if ( providerSelect ) {
		// Set initial state.
		updateProviderFields( providerSelect.value );

		// Listen for changes.
		providerSelect.addEventListener( 'change', ( e ) => {
			updateProviderFields( e.target.value );
		} );
	}
}

/**
 * Initialize the AI Organizer panel.
 */
function initAiOrganizer() {
	const container = document.getElementById( 'vmfa-ai-organizer-scanner' );

	if ( container ) {
		const root = createRoot( container );
		root.render( <AiOrganizerPanel /> );
	}
}

// Initialize when DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		initProviderToggle();
		initAiOrganizer();
	} );
} else {
	initProviderToggle();
	initAiOrganizer();
}
