import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig( {
	plugins: [ react() ],
	test: {
		globals: true,
		environment: 'jsdom',
		setupFiles: [ './src/js/__tests__/setup.js' ],
		include: [ 'src/js/**/*.{test,spec}.{js,jsx}' ],
		coverage: {
			provider: 'v8',
			reporter: [ 'text', 'html' ],
			include: [ 'src/js/**/*.{js,jsx}' ],
			exclude: [ 'src/js/__tests__/**', 'src/js/index.js' ],
		},
	},
	resolve: {
		alias: {
			'@wordpress/element': 'react',
		},
	},
} );
