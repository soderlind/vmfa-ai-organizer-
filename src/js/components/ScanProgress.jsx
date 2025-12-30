/**
 * Scan Progress Component.
 *
 * @package VmfaAiOrganizer
 */

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
 * @param {string} status - Status string.
 * @return {string} Human-readable status.
 */
function getStatusLabel( status ) {
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
					{ getStatusLabel( status.status ) }
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
							{ status.processed } / { status.total } ({ status.percentage }%)
						</span>
					</div>

					<div className="vmfa-progress-bar-container">
						<div
							className="vmfa-progress-bar"
							style={ { width: `${ status.percentage }%` } }
						/>
					</div>

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
					<div className="vmfa-recent-results">
						<h4>{ __( 'Recent Results', 'vmfa-ai-organizer' ) }</h4>
						<ul className="vmfa-results-list">
							{ status.results.slice( -5 ).reverse().map( ( result, index ) => (
								<li key={ index } className={ `vmfa-result-item vmfa-result-${ result.action }` }>
									<span className="vmfa-result-action">
										{ getActionIcon( result.action ) }
									</span>
									<span className="vmfa-result-id">
										#{ result.attachment_id }
									</span>
									<span className="vmfa-result-reason">
										{ result.reason }
									</span>
									<span className="vmfa-result-confidence">
										{ Math.round( result.confidence * 100 ) }%
									</span>
								</li>
							) ) }
						</ul>
					</div>
				) }
			</CardBody>
		</Card>
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

export default ScanProgress;
