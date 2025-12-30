/**
 * Virtual Media Folders AI Organizer - Admin Scripts
 *
 * @package VmfaAiOrganizer
 */

import { createRoot } from '@wordpress/element';
import { AiOrganizerPanel } from './components/AiOrganizerPanel';

import './styles/admin.scss';

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
	document.addEventListener( 'DOMContentLoaded', initAiOrganizer );
} else {
	initAiOrganizer();
}
