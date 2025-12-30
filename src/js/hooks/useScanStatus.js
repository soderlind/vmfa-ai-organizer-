/**
 * Custom hook for managing scan status polling.
 *
 * @package VmfaAiOrganizer
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * @typedef {Object} ScanStatus
 * @property {string}  status       - Current status: 'idle', 'running', 'completed', 'cancelled', 'failed'
 * @property {string}  mode         - Scan mode: 'organize_unassigned', 'reanalyze_all', 'reorganize_all'
 * @property {boolean} dry_run      - Whether this is a dry run
 * @property {number}  total        - Total items to process
 * @property {number}  processed    - Items processed so far
 * @property {number}  percentage   - Progress percentage
 * @property {number}  applied      - Items successfully applied
 * @property {number}  failed       - Items that failed
 * @property {Array}   results      - Recent analysis results
 * @property {number}  started_at   - Timestamp when scan started
 * @property {number}  completed_at - Timestamp when scan completed
 * @property {string}  error        - Error message if any
 */

/**
 * Hook for polling scan status.
 *
 * @param {number} pollInterval - Polling interval in milliseconds.
 * @return {Object} Scan status and control functions.
 */
export function useScanStatus( pollInterval = 2000 ) {
	const [ status, setStatus ] = useState( /** @type {ScanStatus} */ ( {
		status: 'idle',
		mode: '',
		dry_run: false,
		total: 0,
		processed: 0,
		percentage: 0,
		applied: 0,
		failed: 0,
		results: [],
		started_at: null,
		completed_at: null,
		error: null,
	} ) );

	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const pollIntervalRef = useRef( null );

	/**
	 * Fetch current scan status.
	 */
	const fetchStatus = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/status',
				method: 'GET',
			} );
			setStatus( response );
			setError( null );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch scan status' );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	/**
	 * Start polling when scan is running.
	 */
	useEffect( () => {
		// Initial fetch.
		fetchStatus();

		// Set up polling if scan is running.
		if ( status.status === 'running' ) {
			pollIntervalRef.current = setInterval( fetchStatus, pollInterval );
		}

		return () => {
			if ( pollIntervalRef.current ) {
				clearInterval( pollIntervalRef.current );
			}
		};
	}, [ status.status, fetchStatus, pollInterval ] );

	/**
	 * Start a new scan.
	 *
	 * @param {string}  mode    - Scan mode.
	 * @param {boolean} dryRun  - Whether to run in dry-run mode.
	 * @return {Promise<Object>} Scan start result.
	 */
	const startScan = useCallback( async ( mode, dryRun = false ) => {
		try {
			setIsLoading( true );
			const response = await apiFetch( {
				path: '/vmfa/v1/scan',
				method: 'POST',
				data: { mode, dry_run: dryRun },
			} );
			await fetchStatus();
			return response;
		} catch ( err ) {
			setError( err.message || 'Failed to start scan' );
			throw err;
		} finally {
			setIsLoading( false );
		}
	}, [ fetchStatus ] );

	/**
	 * Cancel the current scan.
	 *
	 * @return {Promise<Object>} Cancel result.
	 */
	const cancelScan = useCallback( async () => {
		try {
			setIsLoading( true );
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/cancel',
				method: 'POST',
			} );
			await fetchStatus();
			return response;
		} catch ( err ) {
			setError( err.message || 'Failed to cancel scan' );
			throw err;
		} finally {
			setIsLoading( false );
		}
	}, [ fetchStatus ] );

	/**
	 * Reset scan progress.
	 *
	 * @return {Promise<Object>} Reset result.
	 */
	const resetScan = useCallback( async () => {
		try {
			setIsLoading( true );
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/reset',
				method: 'POST',
			} );
			await fetchStatus();
			return response;
		} catch ( err ) {
			setError( err.message || 'Failed to reset scan' );
			throw err;
		} finally {
			setIsLoading( false );
		}
	}, [ fetchStatus ] );

	/**
	 * Apply cached dry-run results.
	 *
	 * @param {string} mode - Original scan mode.
	 * @return {Promise<Object>} Apply result.
	 */
	const applyCachedResults = useCallback( async ( mode ) => {
		try {
			setIsLoading( true );
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/apply-cached',
				method: 'POST',
				data: { mode },
			} );
			await fetchStatus();
			return response;
		} catch ( err ) {
			setError( err.message || 'Failed to apply cached results' );
			throw err;
		} finally {
			setIsLoading( false );
		}
	}, [ fetchStatus ] );

	/**
	 * Get count of cached dry-run results.
	 *
	 * @return {Promise<number>} Cached results count.
	 */
	const getCachedCount = useCallback( async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/cached-count',
				method: 'GET',
			} );
			return response.count;
		} catch ( err ) {
			setError( err.message || 'Failed to get cached count' );
			return 0;
		}
	}, [] );

	return {
		status,
		isLoading,
		error,
		startScan,
		cancelScan,
		resetScan,
		applyCachedResults,
		getCachedCount,
		refresh: fetchStatus,
	};
}

export default useScanStatus;
