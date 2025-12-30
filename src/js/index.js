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

	// Update Azure fields visibility based on openai_type when OpenAI is selected.
	if ( provider === 'openai' ) {
		updateAzureFields();
	}
}

/**
 * Update visibility of Azure-specific fields based on openai_type selector.
 */
function updateAzureFields() {
	const openaiTypeSelect = document.getElementById( 'vmfa_openai_type' );
	if ( ! openaiTypeSelect ) {
		return;
	}

	const isAzure = openaiTypeSelect.value === 'azure';

	document.querySelectorAll( '.vmfa-azure-field' ).forEach( ( field ) => {
		const row = field.closest( 'tr' );
		if ( row ) {
			// Mark as Azure row for CSS targeting
			row.classList.add( 'vmfa-azure-row' );
			if ( isAzure ) {
				row.classList.add( 'vmfa-azure-active' );
			} else {
				row.classList.remove( 'vmfa-azure-active' );
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

	// Listen for OpenAI type changes.
	const openaiTypeSelect = document.getElementById( 'vmfa_openai_type' );
	if ( openaiTypeSelect ) {
		openaiTypeSelect.addEventListener( 'change', () => {
			updateAzureFields();
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
