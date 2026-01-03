/**
 * Tests for useScanStatus hook.
 *
 * @package
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useScanStatus } from '../hooks/useScanStatus';

vi.mock('@wordpress/api-fetch');

describe('useScanStatus', () => {
	beforeEach(() => {
		vi.clearAllMocks();
	});

	it('should return initial idle status', async () => {
		apiFetch.mockResolvedValue({
			status: 'idle',
			mode: '',
			dry_run: false,
			total: 0,
			processed: 0,
			percentage: 0,
		});

		const { result } = renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		expect(result.current.status.status).toBe('idle');
	});

	it('should fetch status on mount', async () => {
		apiFetch.mockResolvedValue({
			status: 'idle',
			total: 0,
			processed: 0,
		});

		renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(apiFetch).toHaveBeenCalledWith({
				path: '/vmfa/v1/scan/status',
				method: 'GET',
			});
		});
	});

	it('should start scan with correct parameters', async () => {
		apiFetch
			.mockResolvedValueOnce({ status: 'idle' })
			.mockResolvedValueOnce({ success: true })
			.mockResolvedValueOnce({ status: 'running' });

		const { result } = renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		await act(async () => {
			await result.current.startScan('organize_unassigned', true);
		});

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/vmfa/v1/scan',
			method: 'POST',
			data: { mode: 'organize_unassigned', dry_run: true },
		});
	});

	it('should cancel scan', async () => {
		apiFetch
			.mockResolvedValueOnce({ status: 'running' })
			.mockResolvedValueOnce({ success: true })
			.mockResolvedValueOnce({ status: 'cancelled' });

		const { result } = renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		await act(async () => {
			await result.current.cancelScan();
		});

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/vmfa/v1/scan/cancel',
			method: 'POST',
		});
	});

	it('should handle API errors', async () => {
		apiFetch.mockRejectedValue(new Error('Network error'));

		const { result } = renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(result.current.error).toBe('Network error');
		});
	});

	it('should calculate percentage correctly', async () => {
		apiFetch.mockResolvedValue({
			status: 'running',
			total: 100,
			processed: 50,
			percentage: 50,
		});

		const { result } = renderHook(() => useScanStatus());

		await waitFor(() => {
			expect(result.current.status.percentage).toBe(50);
		});
	});
});
