/**
 * Restore Panel Component.
 *
 * @package
 */

import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Format timestamp to readable date.
 *
 * @param {number} timestamp - Unix timestamp.
 * @return {string} Formatted date string.
 */
function formatDate(timestamp) {
	if (!timestamp) {
		return '-';
	}
	return new Date(timestamp * 1000).toLocaleString();
}

/**
 * Restore Panel component.
 *
 * @param {Object}   props           - Component props.
 * @param {Function} props.onRestore - Callback after restore completes.
 * @return {JSX.Element|null} The panel component or null if no backup.
 */
export function RestorePanel({ onRestore }) {
	const [backupInfo, setBackupInfo] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [isRestoring, setIsRestoring] = useState(false);
	const [notice, setNotice] = useState(null);
	const [showConfirm, setShowConfirm] = useState(false);

	/**
	 * Fetch backup information.
	 */
	const fetchBackupInfo = async () => {
		try {
			const response = await apiFetch({
				path: '/vmfa/v1/backup',
				method: 'GET',
			});
			setBackupInfo(response);
		} catch (err) {
			// Ignore fetch errors; panel will stay hidden.
		} finally {
			setIsLoading(false);
		}
	};

	/**
	 * Fetch backup info on mount.
	 */
	useEffect(() => {
		fetchBackupInfo();
	}, []);

	/**
	 * Handle restore action.
	 */
	const handleRestore = async () => {
		setIsRestoring(true);
		setNotice(null);

		try {
			const response = await apiFetch({
				path: '/vmfa/v1/restore',
				method: 'POST',
			});

			setNotice({
				type: 'success',
				message: sprintf(
					/* translators: 1: number of restored folders, 2: number of restored assignments. */
					__(
						'Restored %1$d folders and %2$d assignments.',
						'vmfa-ai-organizer'
					),
					response.folders_restored,
					response.assignments_restored
				),
			});

			setShowConfirm(false);

			if (onRestore) {
				onRestore();
			}

			// Refresh backup info.
			await fetchBackupInfo();
		} catch (err) {
			setNotice({
				type: 'error',
				message:
					err.message ||
					__('Failed to restore backup.', 'vmfa-ai-organizer'),
			});
		} finally {
			setIsRestoring(false);
		}
	};

	/**
	 * Handle delete backup action.
	 */
	const handleDeleteBackup = async () => {
		try {
			await apiFetch({
				path: '/vmfa/v1/backup',
				method: 'DELETE',
			});

			setBackupInfo({ exists: false });
			setNotice({
				type: 'info',
				message: __('Backup deleted.', 'vmfa-ai-organizer'),
			});
		} catch (err) {
			setNotice({
				type: 'error',
				message:
					err.message ||
					__('Failed to delete backup.', 'vmfa-ai-organizer'),
			});
		}
	};

	// Don't render if no backup exists.
	if (isLoading || !backupInfo?.exists) {
		return null;
	}

	return (
		<Card className="vmfa-restore-panel">
			<CardHeader>
				<h3>{__('Backup & Restore', 'vmfa-ai-organizer')}</h3>
			</CardHeader>
			<CardBody>
				{notice && (
					<Notice
						status={notice.type}
						isDismissible={true}
						onRemove={() => setNotice(null)}
					>
						{notice.message}
					</Notice>
				)}

				<div className="vmfa-backup-info">
					<p>
						<strong>
							{__('Backup Available', 'vmfa-ai-organizer')}
						</strong>
					</p>
					<div className="vmfa-backup-details">
						<div className="vmfa-backup-row">
							<span className="vmfa-backup-label">
								{__('Created:', 'vmfa-ai-organizer')}
							</span>
							<span className="vmfa-backup-value">
								{formatDate(backupInfo.timestamp)}
							</span>
						</div>
						<div className="vmfa-backup-row">
							<span className="vmfa-backup-label">
								{__('Folders:', 'vmfa-ai-organizer')}
							</span>
							<span className="vmfa-backup-value">
								{backupInfo.folder_count}
							</span>
						</div>
						<div className="vmfa-backup-row">
							<span className="vmfa-backup-label">
								{__('Assignments:', 'vmfa-ai-organizer')}
							</span>
							<span className="vmfa-backup-value">
								{backupInfo.assignment_count}
							</span>
						</div>
					</div>
				</div>

				{showConfirm ? (
					<div className="vmfa-restore-confirm">
						<p className="vmfa-restore-warning">
							{__(
								'This will replace all current folders and assignments with the backup. Are you sure?',
								'vmfa-ai-organizer'
							)}
						</p>
						<div className="vmfa-restore-actions">
							<Button
								variant="secondary"
								onClick={() => setShowConfirm(false)}
								disabled={isRestoring}
							>
								{__('Cancel', 'vmfa-ai-organizer')}
							</Button>
							<Button
								variant="primary"
								isDestructive
								onClick={handleRestore}
								isBusy={isRestoring}
								disabled={isRestoring}
							>
								{__('Yes, Restore Backup', 'vmfa-ai-organizer')}
							</Button>
						</div>
					</div>
				) : (
					<div className="vmfa-restore-actions">
						<Button
							variant="secondary"
							onClick={() => setShowConfirm(true)}
						>
							{__('Restore Backup', 'vmfa-ai-organizer')}
						</Button>
						<Button
							variant="link"
							isDestructive
							onClick={handleDeleteBackup}
						>
							{__('Delete Backup', 'vmfa-ai-organizer')}
						</Button>
					</div>
				)}
			</CardBody>
		</Card>
	);
}

export default RestorePanel;
