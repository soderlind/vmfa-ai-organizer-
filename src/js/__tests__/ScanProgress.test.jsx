/**
 * Tests for ScanProgress component.
 *
 * @package
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ScanProgress } from '../components/ScanProgress';

describe('ScanProgress', () => {
	const defaultStatus = {
		status: 'running',
		mode: 'organize_unassigned',
		dry_run: false,
		total: 100,
		processed: 50,
		percentage: 50,
		applied: 0,
		failed: 0,
		results: [],
		started_at: Math.floor(Date.now() / 1000) - 60,
	};

	it('should render progress information', () => {
		render(
			<ScanProgress
				status={defaultStatus}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText(/50 \/ 100/)).toBeInTheDocument();
	});

	it('should show cancel button when running', () => {
		render(
			<ScanProgress
				status={defaultStatus}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText('Cancel Scan')).toBeInTheDocument();
	});

	it('should show reset button when completed', () => {
		const completedStatus = {
			...defaultStatus,
			status: 'completed',
			processed: 100,
			percentage: 100,
		};

		render(
			<ScanProgress
				status={completedStatus}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText('Start New Scan')).toBeInTheDocument();
	});

	it('should display preview badge for dry run', () => {
		const dryRunStatus = {
			...defaultStatus,
			dry_run: true,
		};

		render(
			<ScanProgress
				status={dryRunStatus}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText('Preview')).toBeInTheDocument();
	});

	it('should show applied and failed counts when completed', () => {
		const completedStatus = {
			...defaultStatus,
			status: 'completed',
			processed: 100,
			percentage: 100,
			applied: 95,
			failed: 5,
		};

		render(
			<ScanProgress
				status={completedStatus}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText('95')).toBeInTheDocument();
		expect(screen.getByText('5')).toBeInTheDocument();
	});

	it('should render recent results', () => {
		const statusWithResults = {
			...defaultStatus,
			results: [
				{
					attachment_id: 123,
					action: 'assign',
					reason: 'Matched to Photos folder',
					confidence: 0.9,
				},
			],
		};

		render(
			<ScanProgress
				status={statusWithResults}
				onCancel={vi.fn()}
				onReset={vi.fn()}
				isLoading={false}
			/>
		);

		expect(screen.getByText('#123')).toBeInTheDocument();
		expect(screen.getByText('90%')).toBeInTheDocument();
	});
});
