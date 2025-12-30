/**
 * Vitest setup file.
 *
 * @package VmfaAiOrganizer
 */

import { vi } from 'vitest';
import '@testing-library/jest-dom/vitest';

// Mock WordPress packages
vi.mock( '@wordpress/api-fetch', () => ( {
	default: vi.fn(),
} ) );

vi.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	_n: ( single, plural, number ) => ( number === 1 ? single : plural ),
	_x: ( text ) => text,
	sprintf: ( format, ...args ) => {
		let i = 0;
		return format.replace( /%[sd]/g, () => args[ i++ ] );
	},
} ) );

// Mock window.matchMedia
Object.defineProperty( window, 'matchMedia', {
	writable: true,
	value: vi.fn().mockImplementation( ( query ) => ( {
		matches: false,
		media: query,
		onchange: null,
		addListener: vi.fn(),
		removeListener: vi.fn(),
		addEventListener: vi.fn(),
		removeEventListener: vi.fn(),
		dispatchEvent: vi.fn(),
	} ) ),
} );

// Mock ResizeObserver
global.ResizeObserver = vi.fn().mockImplementation( () => ( {
	observe: vi.fn(),
	unobserve: vi.fn(),
	disconnect: vi.fn(),
} ) );
