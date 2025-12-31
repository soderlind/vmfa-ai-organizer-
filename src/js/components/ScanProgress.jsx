/**
 * Scan Progress Component.
 *
 * @package VmfaAiOrganizer
 */

import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Format elapsed time.
 *
 * @param {number} startTime - Start timestamp.
 * @return {string} Formatted time string.
 */
function formatElapsedTime( startTime ) {
	if ( ! startTime ) {
		return '--:--';
	}

	const elapsed = Math.floor( Date.now() / 1000 - startTime );
	const minutes = Math.floor( elapsed / 60 );
	const seconds = elapsed % 60;

	return `${ String( minutes ).padStart( 2, '0' ) }:${ String( seconds ).padStart( 2, '0' ) }`;
}

/**
 * Get status label.
 *
 * @param {string}  status     - Status string.
 * @param {number}  processed  - Number of items processed.
 * @param {boolean} dryRun     - Whether this is a dry run.
 * @return {string} Human-readable status.
 */
function getStatusLabel( status, processed = 0, dryRun = false ) {
	if ( status === 'running' && processed === 0 ) {
		return dryRun
			? __( 'Preparing preview...', 'vmfa-ai-organizer' )
			: __( 'Initializing...', 'vmfa-ai-organizer' );
	}

	const labels = {
		idle: __( 'Ready', 'vmfa-ai-organizer' ),
		running: __( 'Processing...', 'vmfa-ai-organizer' ),
		completed: __( 'Completed', 'vmfa-ai-organizer' ),
		cancelled: __( 'Cancelled', 'vmfa-ai-organizer' ),
		failed: __( 'Failed', 'vmfa-ai-organizer' ),
	};
	return labels[ status ] || status;
}

/**
 * Get mode label.
 *
 * @param {string} mode - Mode string.
 * @return {string} Human-readable mode.
 */
function getModeLabel( mode ) {
	const labels = {
		organize_unassigned: __( 'Organize Unassigned', 'vmfa-ai-organizer' ),
		reanalyze_all: __( 'Re-analyze All', 'vmfa-ai-organizer' ),
		reorganize_all: __( 'Reorganize All', 'vmfa-ai-organizer' ),
	};
	return labels[ mode ] || mode;
}

/**
 * Scan Progress component.
 *
 * @param {Object}   props           - Component props.
 * @param {Object}   props.status    - Current scan status.
 * @param {Function} props.onCancel  - Cancel handler.
 * @param {Function} props.onReset   - Reset handler.
 * @param {boolean}  props.isLoading - Whether an action is loading.
 * @return {JSX.Element} The progress component.
 */
export function ScanProgress( { status, onCancel, onReset, isLoading } ) {
	const isRunning = status.status === 'running';
	const isCompleted = status.status === 'completed';

	return (
		<Card className="vmfa-scan-progress">
			<CardHeader>
				<h3>
					{ isRunning && <Spinner /> }
					{ getStatusLabel( status.status, status.processed, status.dry_run ) }
					{ status.dry_run && (
						<span className="vmfa-badge vmfa-badge-info">
							{ __( 'Preview', 'vmfa-ai-organizer' ) }
						</span>
					) }
				</h3>
			</CardHeader>
			<CardBody>
				<div className="vmfa-progress-info">
					<div className="vmfa-progress-row">
						<span className="vmfa-progress-label">
							{ __( 'Mode:', 'vmfa-ai-organizer' ) }
						</span>
						<span className="vmfa-progress-value">
							{ getModeLabel( status.mode ) }
						</span>
					</div>

					<div className="vmfa-progress-row">
						<span className="vmfa-progress-label">
							{ __( 'Progress:', 'vmfa-ai-organizer' ) }
						</span>
						<span className="vmfa-progress-value">
							{ status.processed === 0 && isRunning
								? __( 'Starting...', 'vmfa-ai-organizer' )
								: `${ status.processed } / ${ status.total } (${ status.percentage }%)`
							}
						</span>
					</div>

					<div className={ `vmfa-progress-bar-container${ isRunning && status.processed === 0 ? ' vmfa-progress-indeterminate' : '' }` }>
						<div
							className="vmfa-progress-bar"
							style={ { width: isRunning && status.processed === 0 ? '100%' : `${ status.percentage }%` } }
						/>
					</div>

					{ isRunning && status.processed === 0 && (
						<div className="vmfa-progress-hint">
							{ __( 'Connecting to AI provider and analyzing first batch... Initialization may take a couple of minutes.', 'vmfa-ai-organizer' ) }
						</div>
					) }

					{ isRunning && (
						<div className="vmfa-progress-row">
							<span className="vmfa-progress-label">
								{ __( 'Elapsed:', 'vmfa-ai-organizer' ) }
							</span>
							<span className="vmfa-progress-value">
								{ formatElapsedTime( status.started_at ) }
							</span>
						</div>
					) }

					{ isCompleted && ! status.dry_run && (
						<>
							<div className="vmfa-progress-row">
								<span className="vmfa-progress-label">
									{ __( 'Applied:', 'vmfa-ai-organizer' ) }
								</span>
								<span className="vmfa-progress-value vmfa-success">
									{ status.applied }
								</span>
							</div>
							{ status.failed > 0 && (
								<div className="vmfa-progress-row">
									<span className="vmfa-progress-label">
										{ __( 'Failed:', 'vmfa-ai-organizer' ) }
									</span>
									<span className="vmfa-progress-value vmfa-error">
										{ status.failed }
									</span>
								</div>
							) }
						</>
					) }

					{ status.error && (
						<div className="vmfa-progress-error">
							{ status.error }
						</div>
					) }
				</div>

				<div className="vmfa-progress-actions">
					{ isRunning && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ onCancel }
							disabled={ isLoading }
						>
							{ __( 'Cancel Scan', 'vmfa-ai-organizer' ) }
						</Button>
					) }

					{ ! isRunning && (
						<Button
							variant="secondary"
							onClick={ onReset }
							disabled={ isLoading }
						>
							{ __( 'Start New Scan', 'vmfa-ai-organizer' ) }
						</Button>
					) }
				</div>

				{ /* Recent Results */ }
				{ status.results && status.results.length > 0 && (
					<ResultsList results={ status.results } />
				) }
			</CardBody>
		</Card>
	);
}

/**
 * Expandable Results List component.
 *
 * @param {Object} props - Component props.
 * @param {Array}  props.results - Array of results.
 * @return {JSX.Element} The results list.
 */
function ResultsList( { results } ) {
	const [ expandedItems, setExpandedItems ] = useState( {} );

	const toggleItem = ( index ) => {
		setExpandedItems( ( prev ) => ( {
			...prev,
			[ index ]: ! prev[ index ],
		} ) );
	};

	// Reverse to show most recent first.
	const sortedResults = [ ...results ].reverse();

	return (
		<div className="vmfa-recent-results">
			<h4>{ __( 'Recent Results', 'vmfa-ai-organizer' ) } ({ results.length })</h4>
			<div className="vmfa-results-list">
				{ sortedResults.map( ( result, index ) => (
					<div
						key={ index }
						className={ `vmfa-result-item vmfa-result-${ result.action }${ expandedItems[ index ] ? ' is-expanded' : '' }` }
					>
						<button
							type="button"
							className="vmfa-result-header"
							onClick={ () => toggleItem( index ) }
							aria-expanded={ expandedItems[ index ] }
						>
							<span className="vmfa-result-action">
								{ getActionIcon( result.action ) }
							</span>
							<span className="vmfa-result-filename" title={ result.filename || `#${ result.attachment_id }` }>
								{ result.filename || `#${ result.attachment_id }` }
							</span>
							{ result.folder_name && (
								<span className="vmfa-result-folder">
									→ { result.folder_name }
								</span>
							) }
							<span className="vmfa-result-confidence">
								{ Math.round( ( result.confidence || 0 ) * 100 ) }%
							</span>
							<span className="vmfa-result-toggle">
								{ expandedItems[ index ] ? '▲' : '▼' }
							</span>
						</button>
						{ expandedItems[ index ] && (
							<div className="vmfa-result-details">
								<dl>
									<dt>{ __( 'Attachment ID:', 'vmfa-ai-organizer' ) }</dt>
									<dd>#{ result.attachment_id }</dd>

									<dt>{ __( 'Action:', 'vmfa-ai-organizer' ) }</dt>
									<dd>{ getActionLabel( result.action ) }</dd>

									{ result.folder_name && (
										<>
											<dt>{ __( 'Folder:', 'vmfa-ai-organizer' ) }</dt>
											<dd>{ result.folder_name }</dd>
										</>
									) }

									{ result.new_folder_path && (
										<>
											<dt>{ __( 'New Folder:', 'vmfa-ai-organizer' ) }</dt>
											<dd>{ result.new_folder_path }</dd>
										</>
									) }

									<dt>{ __( 'Confidence:', 'vmfa-ai-organizer' ) }</dt>
									<dd>{ Math.round( ( result.confidence || 0 ) * 100 ) }%</dd>

									<dt>{ __( 'Reason:', 'vmfa-ai-organizer' ) }</dt>
									<dd className="vmfa-result-reason-text">{ result.reason || __( 'No reason provided', 'vmfa-ai-organizer' ) }</dd>
								</dl>
							</div>
						) }
					</div>
				) ) }
			</div>
		</div>
	);
}

/**
 * Get icon for action type.
 *
 * @param {string} action - Action type.
 * @return {string} Icon character.
 */
function getActionIcon( action ) {
	switch ( action ) {
		case 'assign':
			return '📁';
		case 'create':
			return '➕';
		case 'skip':
			return '⏭️';
		default:
			return '❓';
	}
}

/**
 * Get human-readable label for action type.
 *
 * @param {string} action - Action type.
 * @return {string} Action label.
 */
function getActionLabel( action ) {
	switch ( action ) {
		case 'assign':
			return __( 'Assign to existing folder', 'vmfa-ai-organizer' );
		case 'create':
			return __( 'Create new folder', 'vmfa-ai-organizer' );
		case 'skip':
			return __( 'Skipped', 'vmfa-ai-organizer' );
		default:
			return action;
	}
}

export default ScanProgress;
