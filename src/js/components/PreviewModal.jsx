/**
 * Preview Modal Component.
 *
 * @package
 */

import { Modal, Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Preview Modal component.
 *
 * @param {Object}   props         - Component props.
 * @param {Array}    props.results - Analysis results to preview.
 * @param {Function} props.onClose - Close handler.
 * @param {Function} props.onApply - Apply handler.
 * @return {JSX.Element} The modal component.
 */
export function PreviewModal({ results, onClose, onApply }) {
	// Group results by action.
	const grouped = results.reduce(
		(acc, result) => {
			acc[result.action] = acc[result.action] || [];
			acc[result.action].push(result);
			return acc;
		},
		{ assign: [], create: [], skip: [] }
	);

	// Calculate statistics.
	const assignCount = grouped.assign.length;
	const createCount = grouped.create.length;
	const skipCount = grouped.skip.length;

	// Get unique new folders to create and sort alphabetically.
	const newFolders = [
		...new Set(
			grouped.create.map((r) => r.new_folder_path).filter(Boolean)
		),
	].sort((a, b) => a.localeCompare(b));

	// Sort assignments alphabetically by folder name.
	const sortedAssignments = [...grouped.assign].sort((a, b) => {
		const folderA = a.folder_name || a.new_folder_path || '';
		const folderB = b.folder_name || b.new_folder_path || '';
		return folderA.localeCompare(folderB);
	});

	return (
		<Modal
			title={__('Preview Changes', 'vmfa-ai-organizer')}
			onRequestClose={onClose}
			className="vmfa-preview-modal"
			size="large"
		>
			<div className="vmfa-preview-content">
				{/* Summary */}
				<div className="vmfa-preview-summary">
					<h3>{__('Summary', 'vmfa-ai-organizer')}</h3>
					<div className="vmfa-preview-stats">
						<div className="vmfa-preview-stat">
							<span className="vmfa-stat-value">
								{assignCount}
							</span>
							<span className="vmfa-stat-label">
								{__(
									'Will be assigned to existing folders',
									'vmfa-ai-organizer'
								)}
							</span>
						</div>
						<div className="vmfa-preview-stat">
							<span className="vmfa-stat-value">
								{createCount}
							</span>
							<span className="vmfa-stat-label">
								{__(
									'Will create new folders',
									'vmfa-ai-organizer'
								)}
							</span>
						</div>
						<div className="vmfa-preview-stat">
							<span className="vmfa-stat-value">{skipCount}</span>
							<span className="vmfa-stat-label">
								{__(
									'No suitable folder found',
									'vmfa-ai-organizer'
								)}
							</span>
						</div>
					</div>
				</div>

				{/* New folders to create */}
				{newFolders.length > 0 && (
					<div className="vmfa-preview-section">
						<h4>
							{__('New Folders to Create', 'vmfa-ai-organizer')}
						</h4>
						<ul className="vmfa-folder-list">
							{newFolders.map((folder, index) => (
								<li key={index} className="vmfa-folder-item">
									<span className="vmfa-folder-icon">📁</span>
									<span className="vmfa-folder-path">
										{folder}
									</span>
								</li>
							))}
						</ul>
					</div>
				)}

				{/* Assignments preview */}
				{assignCount > 0 && (
					<div className="vmfa-preview-section">
						<h4>{__('Folder Assignments', 'vmfa-ai-organizer')}</h4>
						<div className="vmfa-preview-table-wrapper">
							<table className="vmfa-preview-table">
								<thead>
									<tr>
										<th>
											{__(
												'Media ID',
												'vmfa-ai-organizer'
											)}
										</th>
										<th>
											{__('Folder', 'vmfa-ai-organizer')}
										</th>
										<th>
											{__(
												'Confidence',
												'vmfa-ai-organizer'
											)}
										</th>
										<th>
											{__('Reason', 'vmfa-ai-organizer')}
										</th>
									</tr>
								</thead>
								<tbody>
									{sortedAssignments
										.slice(0, 20)
										.map((result, index) => (
											<tr key={index}>
												<td>#{result.attachment_id}</td>
												<td>
													{result.folder_name ||
														result.new_folder_path ||
														'-'}
												</td>
												<td>
													<span
														className={getConfidenceClass(
															result.confidence
														)}
													>
														{Math.round(
															result.confidence *
																100
														)}
														%
													</span>
												</td>
												<td>{result.reason}</td>
											</tr>
										))}
								</tbody>
							</table>
							{sortedAssignments.length > 20 && (
								<p className="vmfa-preview-more">
									{sprintf(
										/* translators: %d: Number of additional assignments not shown in the preview table. */
										__('And %d more…', 'vmfa-ai-organizer'),
										sortedAssignments.length - 20
									)}
								</p>
							)}
						</div>
					</div>
				)}

				{/* Skipped items */}
				{skipCount > 0 && (
					<div className="vmfa-preview-section vmfa-preview-skipped">
						<h4>{__('Skipped Items', 'vmfa-ai-organizer')}</h4>
						<p className="description">
							{__(
								'These items could not be matched to any folder.',
								'vmfa-ai-organizer'
							)}
						</p>
						<ul className="vmfa-skipped-list">
							{grouped.skip.slice(0, 10).map((result, index) => (
								<li key={index}>
									#{result.attachment_id}: {result.reason}
								</li>
							))}
						</ul>
						{skipCount > 10 && (
							<p className="vmfa-preview-more">
								{sprintf(
									/* translators: %d: Number of additional skipped items not shown in the preview list. */
									__('And %d more…', 'vmfa-ai-organizer'),
									skipCount - 10
								)}
							</p>
						)}
					</div>
				)}
			</div>

			<div className="vmfa-preview-actions">
				<Button
					variant="primary"
					onClick={onApply}
					disabled={assignCount === 0 && createCount === 0}
				>
					{__('Apply Changes', 'vmfa-ai-organizer')}
				</Button>
				<Button variant="secondary" onClick={onClose}>
					{__('Cancel', 'vmfa-ai-organizer')}
				</Button>
			</div>
		</Modal>
	);
}

/**
 * Get CSS class for confidence level.
 *
 * @param {number} confidence - Confidence value (0-1).
 * @return {string} CSS class name.
 */
function getConfidenceClass(confidence) {
	if (confidence >= 0.8) {
		return 'vmfa-confidence-high';
	}
	if (confidence >= 0.5) {
		return 'vmfa-confidence-medium';
	}
	return 'vmfa-confidence-low';
}

export default PreviewModal;
