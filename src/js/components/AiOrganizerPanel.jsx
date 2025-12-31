/**
 * AI Organizer Panel Component.
 *
 * @package VmfaAiOrganizer
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	RadioControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { ScanProgress } from './ScanProgress';
import { PreviewModal } from './PreviewModal';
import { RestorePanel } from './RestorePanel';
import { useScanStatus } from '../hooks/useScanStatus';

/**
 * Main AI Organizer Panel component.
 *
 * @return {JSX.Element} The panel component.
 */
export function AiOrganizerPanel() {
	const [ mode, setMode ] = useState( 'organize_unassigned' );
	const [ dryRun, setDryRun ] = useState( true );
	const [ stats, setStats ] = useState( null );
	const [ showPreview, setShowPreview ] = useState( false );
	const [ previewResults, setPreviewResults ] = useState( [] );
	const [ notice, setNotice ] = useState( null );

	const {
		status,
		isLoading,
		error,
		startScan,
		cancelScan,
		resetScan,
		applyCachedResults,
		refresh,
	} = useScanStatus();

	/**
	 * Fetch statistics on mount.
	 */
	useEffect( () => {
		fetchStats();
	}, [] );

	/**
	 * Fetch cached results and show preview modal when dry run completes.
	 */
	useEffect( () => {
		if ( status.status === 'completed' && status.dry_run ) {
			fetchCachedResults();
		}
	}, [ status.status, status.dry_run ] );

	/**
	 * Fetch cached dry-run results for preview.
	 */
	const fetchCachedResults = async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa/v1/scan/cached-results',
				method: 'GET',
			} );
			setPreviewResults( response.results || [] );
			setShowPreview( true );
		} catch ( err ) {
			console.error( 'Failed to fetch cached results:', err );
			// Fallback to status.results if cached results fail.
			setPreviewResults( status.results || [] );
			setShowPreview( true );
		}
	};

	/**
	 * Fetch media statistics.
	 */
	const fetchStats = async () => {
		try {
			const response = await apiFetch( {
				path: '/vmfa/v1/stats',
				method: 'GET',
			} );
			setStats( response );
		} catch ( err ) {
			console.error( 'Failed to fetch stats:', err );
		}
	};

	/**
	 * Handle scan start.
	 */
	const handleStartScan = async () => {
		try {
			setNotice( null );
			await startScan( mode, dryRun );
			setNotice( {
				type: 'success',
				message: dryRun
					? __( 'Preview scan started. Results will be shown when complete. You can leave this page and return later.', 'vmfa-ai-organizer' )
					: __( 'Scan started. Media files are being organized. You can leave this page and return later.', 'vmfa-ai-organizer' ),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message: err.message || __( 'Failed to start scan.', 'vmfa-ai-organizer' ),
			} );
		}
	};

	/**
	 * Handle scan cancellation.
	 */
	const handleCancelScan = async () => {
		try {
			await cancelScan();
			setNotice( {
				type: 'info',
				message: __( 'Scan cancelled.', 'vmfa-ai-organizer' ),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message: err.message || __( 'Failed to cancel scan.', 'vmfa-ai-organizer' ),
			} );
		}
	};

	/**
	 * Handle reset.
	 */
	const handleReset = async () => {
		try {
			await resetScan();
			await fetchStats();
			setNotice( null );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message: err.message || __( 'Failed to reset.', 'vmfa-ai-organizer' ),
			} );
		}
	};

	/**
	 * Apply preview results using cached dry-run data.
	 */
	const handleApplyPreview = async () => {
		setShowPreview( false );
		try {
			setNotice( {
				type: 'info',
				message: __( 'Applying cached preview results...', 'vmfa-ai-organizer' ),
			} );
			const response = await applyCachedResults( mode );
			await fetchStats();
			setNotice( {
				type: 'success',
				message: response.message || __( 'Preview results applied successfully.', 'vmfa-ai-organizer' ),
			} );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				message: err.message || __( 'Failed to apply preview results.', 'vmfa-ai-organizer' ),
			} );
		}
	};

	const isRunning = status.status === 'running';
	const isCompleted = status.status === 'completed';
	const isCancelled = status.status === 'cancelled';

	const modeOptions = [
		{
			label: __( 'Organize Unassigned', 'vmfa-ai-organizer' ),
			value: 'organize_unassigned',
		},
		{
			label: __( 'Re-analyze All', 'vmfa-ai-organizer' ),
			value: 'reanalyze_all',
		},
		{
			label: __( 'Reorganize All (Reset & Rebuild)', 'vmfa-ai-organizer' ),
			value: 'reorganize_all',
		},
	];

	return (
		<div className="vmfa-ai-organizer-panel">
			{ notice && (
				<Notice
					status={ notice.type }
					isDismissible={ true }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ /* Statistics Card */ }
			{ stats && (
				<Card className="vmfa-stats-card">
					<CardHeader>
						<h3>{ __( 'Media Library Statistics', 'vmfa-ai-organizer' ) }</h3>
					</CardHeader>
					<CardBody>
						<div className="vmfa-stats-grid">
							<div className="vmfa-stat">
								<span className="vmfa-stat-value">{ stats.total_media }</span>
								<span className="vmfa-stat-label">{ __( 'Total Media', 'vmfa-ai-organizer' ) }</span>
							</div>
							<div className="vmfa-stat">
								<span className="vmfa-stat-value">{ stats.assigned }</span>
								<span className="vmfa-stat-label">{ __( 'In Folders', 'vmfa-ai-organizer' ) }</span>
							</div>
							<div className="vmfa-stat">
								<span className="vmfa-stat-value">{ stats.unassigned }</span>
								<span className="vmfa-stat-label">{ __( 'Unassigned', 'vmfa-ai-organizer' ) }</span>
							</div>
							<div className="vmfa-stat">
								<span className="vmfa-stat-value">{ stats.folders }</span>
								<span className="vmfa-stat-label">{ __( 'Folders', 'vmfa-ai-organizer' ) }</span>
							</div>
						</div>
					</CardBody>
				</Card>
			) }

			{ /* Scan Controls */ }
			{ ! isRunning && (
				<Card className="vmfa-scan-controls">
					<CardHeader>
						<h3>{ __( 'Scan Options', 'vmfa-ai-organizer' ) }</h3>
					</CardHeader>
					<CardBody>
						<RadioControl
							label={ __( 'Scan Mode', 'vmfa-ai-organizer' ) }
							help={ getModeHelp( mode ) }
							selected={ mode }
							options={ modeOptions }
							onChange={ setMode }
						/>

						{ mode === 'reorganize_all' && (
							<Notice status="warning" isDismissible={ false }>
								{ __( 'Warning: This will remove all existing folder assignments and reorganize from scratch. A backup will be created automatically.', 'vmfa-ai-organizer' ) }
							</Notice>
						) }

						<CheckboxControl
							label={ __( 'Preview mode (dry run)', 'vmfa-ai-organizer' ) }
							help={ __( 'Show proposed changes without applying them.', 'vmfa-ai-organizer' ) }
							checked={ dryRun }
							onChange={ setDryRun }
						/>

						<div className="vmfa-scan-actions">
							<Button
								variant="primary"
								onClick={ handleStartScan }
								disabled={ isLoading || ( mode === 'organize_unassigned' && stats?.unassigned === 0 ) }
							>
								{ dryRun
									? __( 'Preview Changes', 'vmfa-ai-organizer' )
									: __( 'Start Organizing', 'vmfa-ai-organizer' )
								}
							</Button>

							{ ( isCompleted || isCancelled ) && (
								<Button
									variant="secondary"
									onClick={ handleReset }
									disabled={ isLoading }
								>
									{ __( 'Reset', 'vmfa-ai-organizer' ) }
								</Button>
							) }
						</div>
					</CardBody>
				</Card>
			) }

			{ /* Progress Display */ }
			{ ( isRunning || isCompleted || isCancelled ) && (
				<ScanProgress
					status={ status }
					onCancel={ handleCancelScan }
					onReset={ handleReset }
					isLoading={ isLoading }
				/>
			) }

			{ /* Restore Panel */ }
			<RestorePanel onRestore={ () => { fetchStats(); refresh(); } } />

			{ /* Preview Modal */ }
			{ showPreview && (
				<PreviewModal
					results={ previewResults }
					onClose={ () => setShowPreview( false ) }
					onApply={ handleApplyPreview }
				/>
			) }
		</div>
	);
}

/**
 * Get help text for scan mode.
 *
 * @param {string} mode - Scan mode.
 * @return {string} Help text.
 */
function getModeHelp( mode ) {
	switch ( mode ) {
		case 'organize_unassigned':
			return __( 'Only process media files that are not already in a folder.', 'vmfa-ai-organizer' );
		case 'reanalyze_all':
			return __( 'Re-analyze all media and suggest new folder assignments.', 'vmfa-ai-organizer' );
		case 'reorganize_all':
			return __( 'Remove all folders and assignments, then create a new AI-optimized structure.', 'vmfa-ai-organizer' );
		default:
			return '';
	}
}

export default AiOrganizerPanel;
