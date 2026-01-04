<?php
/**
 * Media Scanner Service.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\Services;

use VmfaAiOrganizer\Plugin;

/**
 * Service for scanning and organizing media in batches.
 */
class MediaScannerService {

	/**
	 * Valid scan modes.
	 *
	 * @var array<string>
	 */
	private const VALID_MODES = array( 'organize_unassigned', 'reanalyze_all', 'reorganize_all' );

	/**
	 * Get the valid scan modes.
	 *
	 * @return array<string>
	 */
	public function get_valid_modes(): array {
		return self::VALID_MODES;
	}

	/**
	 * Progress option name.
	 */
	private const PROGRESS_OPTION = 'vmfa_scan_progress';

	/**
	 * Pending results option name.
	 */
	private const PENDING_RESULTS_OPTION = 'vmfa_scan_pending_results';

	/**
	 * Cached dry-run results option name.
	 */
	private const DRYRUN_CACHE_OPTION = 'vmfa_scan_dryrun_cache';

	/**
	 * AI Analysis Service.
	 *
	 * @var AIAnalysisService
	 */
	private AIAnalysisService $analysis_service;

	/**
	 * Backup Service.
	 *
	 * @var BackupService
	 */
	private BackupService $backup_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->analysis_service = new AIAnalysisService();
		$this->backup_service   = new BackupService();
	}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'vmfa_process_media_batch', array( $this, 'process_batch' ), 10, 3 );
		add_action( 'vmfa_apply_assignments', array( $this, 'apply_assignments' ) );
		add_action( 'vmfa_finalize_scan', array( $this, 'finalize_scan' ) );
		add_action( 'vmfa_cleanup_folders', array( $this, 'cleanup_folders' ) );
	}

	/**
	 * Start a new scan.
	 *
	 * @param string $mode    Scan mode: 'organize_unassigned', 'reanalyze_all', 'reorganize_all'.
	 * @param bool   $dry_run Whether to run in dry-run mode.
	 * @return array{success: bool, message: string, total?: int}
	 */
	public function start_scan( string $mode, bool $dry_run = false ): array {
		// Check if AI provider is configured.
		if ( ! $this->analysis_service->is_provider_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'No AI provider configured. Please configure an AI provider in the AI Provider settings tab before scanning.', 'vmfa-ai-organizer' ),
			);
		}

		$already_running_error = $this->get_already_running_error();
		if ( null !== $already_running_error ) {
			return $already_running_error;
		}

		// Validate mode.
		if ( ! $this->is_valid_mode( $mode ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid scan mode.', 'vmfa-ai-organizer' ),
			);
		}

		// Get attachment IDs based on mode.
		$attachment_ids = $this->get_attachment_ids_for_mode( $mode );

		if ( empty( $attachment_ids ) ) {
			return array(
				'success' => false,
				'message' => __( 'No media files found to process.', 'vmfa-ai-organizer' ),
			);
		}

		$this->run_reorganize_all_preflight_for_scan_start( $mode, $dry_run );
		$this->initialize_progress( $mode, $dry_run, count( $attachment_ids ) );

		// Store attachment IDs for processing.
		update_option( 'vmfa_scan_attachment_ids', $attachment_ids, false );

		// Clear session suggested folders for fresh consistency.
		$this->analysis_service->clear_session_suggested_folders();

		// Clear pending results.
		delete_option( self::PENDING_RESULTS_OPTION );

		// Clear dry-run cache when starting a new dry-run.
		if ( $dry_run ) {
			delete_option( self::DRYRUN_CACHE_OPTION );
		}

		$batch_size = $this->get_batch_size();
		$this->schedule_scan_start_actions( $mode, $dry_run, $batch_size );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: number of media files */
				__( 'Started scanning %d media files.', 'vmfa-ai-organizer' ),
				count( $attachment_ids )
			),
			'total'   => count( $attachment_ids ),
		);
	}

	/**
	 * Cleanup folders for reorganize mode.
	 *
	 * @return void
	 */
	public function cleanup_folders(): void {
		$progress = $this->get_progress();

		if ( 'reorganize_all' !== $progress[ 'mode' ] ) {
			return;
		}

		// Remove all existing folders.
		$this->backup_service->remove_all_folders();

		// Force refresh the folder paths cache to ensure it's empty.
		$this->analysis_service->get_folder_paths( true );

		// Clear session-suggested folders for fresh start.
		delete_option( 'vmfa_session_suggested_folders' );

		$this->schedule_first_batch( (bool) ( $progress[ 'dry_run' ] ?? false ), $this->get_batch_size() );
	}

	/**
	 * Process a batch of media files.
	 *
	 * @param int  $batch_number Current batch number.
	 * @param int  $batch_size   Number of items per batch.
	 * @param bool $dry_run      Whether this is a dry run.
	 * @return void
	 */
	public function process_batch( int $batch_number, int $batch_size, bool $dry_run ): void {
		$attachment_ids = get_option( 'vmfa_scan_attachment_ids', array() );
		$progress       = $this->get_progress();

		if ( empty( $attachment_ids ) || 'running' !== $progress[ 'status' ] ) {
			return;
		}

		// Safety check: if already processed all items, finalize.
		$total = count( $attachment_ids );
		if ( $progress[ 'processed' ] >= $total ) {
			$this->schedule_completion( $dry_run );
			return;
		}

		// Get batch of IDs based on what's already processed, not batch number.
		// This prevents issues with duplicate action scheduler runs.
		$batch_ids = array_slice( $attachment_ids, $progress[ 'processed' ], $batch_size );

		if ( empty( $batch_ids ) ) {
			// No more batches, finalize.
			$this->schedule_completion( $dry_run );
			return;
		}

		// Force refresh folder paths at start of each batch to ensure fresh data.
		// This is critical for "Reorganize All" where folders are deleted before first batch.
		$this->analysis_service->get_folder_paths( true );

		// Process each attachment in batch.
		$batch_results   = array();
		$pending_results = get_option( self::PENDING_RESULTS_OPTION, array() );
		$dryrun_cache    = $dry_run ? get_option( self::DRYRUN_CACHE_OPTION, array() ) : array();

		foreach ( $batch_ids as $attachment_id ) {
			// Check for cancellation between each item.
			$current_progress = $this->get_progress();
			if ( 'running' !== $current_progress[ 'status' ] ) {
				// Scan was cancelled, stop processing.
				return;
			}

			// Update current item being processed for CLI progress display.
			$attachment      = get_post( $attachment_id );
			$attachment_name = $attachment ? basename( get_attached_file( $attachment_id ) ?: $attachment->post_title ) : '';
			$this->update_progress(
				array(
					'current_item'  => (int) $attachment_id,
					'current_title' => $attachment_name,
				)
			);

			$result          = $this->analysis_service->analyze_media( (int) $attachment_id );
			$batch_results[] = $result;

			// Store pending result for later application.
			if ( ! $dry_run && in_array( $result[ 'action' ], array( 'assign', 'create' ), true ) ) {
				$pending_results[] = $result;
			}

			// Cache ALL actionable results during dry-run for later application.
			if ( $dry_run && in_array( $result[ 'action' ], array( 'assign', 'create' ), true ) ) {
				$dryrun_cache[] = $result;
			}
		}

		// Update pending results.
		update_option( self::PENDING_RESULTS_OPTION, $pending_results, false );

		// Update dry-run cache.
		if ( $dry_run ) {
			update_option( self::DRYRUN_CACHE_OPTION, $dryrun_cache, false );
		}

		// Update progress.
		$new_processed = $progress[ 'processed' ] + count( $batch_ids );
		$all_results   = array_merge( $progress[ 'results' ] ?? array(), $batch_results );

		// Keep only last 100 results in progress for memory efficiency.
		if ( count( $all_results ) > 100 ) {
			$all_results = array_slice( $all_results, -100 );
		}

		$this->update_progress(
			array(
				'processed' => $new_processed,
				'results'   => $all_results,
			)
		);

		// Check for cancellation before scheduling next batch.
		$final_progress = $this->get_progress();
		if ( 'running' !== $final_progress[ 'status' ] ) {
			// Scan was cancelled, don't schedule next batch.
			return;
		}

		// Schedule next batch.
		as_schedule_single_action(
			time(),
			'vmfa_process_media_batch',
			array(
				'batch_number' => $batch_number + 1,
				'batch_size'   => $batch_size,
				'dry_run'      => $dry_run,
			),
			'vmfa-ai-organizer'
		);
	}

	/**
	 * Apply all pending assignments.
	 *
	 * @return void
	 */
	public function apply_assignments(): void {
		$pending_results = get_option( self::PENDING_RESULTS_OPTION, array() );
		$progress        = $this->get_progress();

		$applied = 0;
		$failed  = 0;

		foreach ( $pending_results as $result ) {
			$success = $this->analysis_service->apply_result( $result );
			if ( $success ) {
				++$applied;
			} else {
				++$failed;
			}
		}

		// Update progress with application results.
		$this->update_progress(
			array(
				'applied' => $applied,
				'failed'  => $failed,
			)
		);

		// Clean up pending results.
		delete_option( self::PENDING_RESULTS_OPTION );

		// Schedule finalization.
		as_schedule_single_action(
			time(),
			'vmfa_finalize_scan',
			array(),
			'vmfa-ai-organizer'
		);
	}

	/**
	 * Apply cached dry-run results.
	 *
	 * This allows applying previously cached dry-run results without re-running the AI analysis.
	 *
	 * @param string $mode The scan mode for progress tracking.
	 * @return array{success: bool, message: string, applied?: int, failed?: int}
	 */
	public function apply_cached_results( string $mode ): array {
		$cached_results = get_option( self::DRYRUN_CACHE_OPTION, array() );

		if ( empty( $cached_results ) ) {
			return array(
				'success' => false,
				'message' => __( 'No cached dry-run results found. Please run a preview first.', 'vmfa-ai-organizer' ),
			);
		}

		$already_running_error = $this->get_already_running_error();
		if ( null !== $already_running_error ) {
			return $already_running_error;
		}

		if ( ! $this->is_valid_mode( $mode ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid scan mode.', 'vmfa-ai-organizer' ),
			);
		}

		$this->run_reorganize_all_preflight_for_apply_cached( $mode );

		$this->initialize_progress( $mode, false, count( $cached_results ) );

		$applied = 0;
		$failed  = 0;
		$results = array();

		foreach ( $cached_results as $result ) {
			$success   = $this->analysis_service->apply_result( $result );
			$results[] = $result;

			if ( $success ) {
				++$applied;
			} else {
				++$failed;
			}
		}

		// Keep only last 100 results for memory efficiency.
		if ( count( $results ) > 100 ) {
			$results = array_slice( $results, -100 );
		}

		// Update progress with final results.
		$this->update_progress(
			array(
				'status'       => 'completed',
				'processed'    => count( $cached_results ),
				'results'      => $results,
				'applied'      => $applied,
				'failed'       => $failed,
				'completed_at' => time(),
			)
		);

		// Clean up cached results and temporary data.
		delete_option( self::DRYRUN_CACHE_OPTION );
		delete_option( 'vmfa_scan_attachment_ids' );
		delete_option( self::PENDING_RESULTS_OPTION );

		/**
		 * Fires when cached results are applied.
		 *
		 * @param int $applied Number of successfully applied results.
		 * @param int $failed  Number of failed results.
		 */
		do_action( 'vmfa_cached_results_applied', $applied, $failed );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: 1: applied count, 2: failed count */
				__( 'Applied %1$d assignments (%2$d failed).', 'vmfa-ai-organizer' ),
				$applied,
				$failed
			),
			'applied' => $applied,
			'failed'  => $failed,
		);
	}

	/**
	 * Check if a mode is valid.
	 *
	 * @param string $mode Scan mode.
	 * @return bool
	 */
	private function is_valid_mode( string $mode ): bool {
		return in_array( $mode, self::VALID_MODES, true );
	}

	/**
	 * Get an error array if a scan is already running.
	 *
	 * @return array{success: bool, message: string}|null
	 */
	private function get_already_running_error(): ?array {
		$progress = $this->get_progress();
		if ( 'running' !== ( $progress[ 'status' ] ?? '' ) ) {
			return null;
		}

		return array(
			'success' => false,
			'message' => __( 'A scan is already in progress. Please wait for it to complete or cancel it.', 'vmfa-ai-organizer' ),
		);
	}

	/**
	 * Get attachment IDs for a given scan mode.
	 *
	 * @param string $mode Scan mode.
	 * @return array<int>
	 */
	private function get_attachment_ids_for_mode( string $mode ): array {
		if ( 'organize_unassigned' === $mode ) {
			return $this->analysis_service->get_unassigned_media_ids();
		}

		return $this->analysis_service->get_all_media_ids();
	}

	/**
	 * Initialize scan progress.
	 *
	 * @param string $mode    Scan mode.
	 * @param bool   $dry_run Whether this is a dry run.
	 * @param int    $total   Total items.
	 * @return void
	 */
	private function initialize_progress( string $mode, bool $dry_run, int $total ): void {
		$this->update_progress(
			array(
				'status'       => 'running',
				'mode'         => $mode,
				'dry_run'      => $dry_run,
				'total'        => $total,
				'processed'    => 0,
				'results'      => array(),
				'started_at'   => time(),
				'completed_at' => null,
				'error'        => null,
			)
		);
	}

	/**
	 * Get configured batch size.
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		return (int) Plugin::get_instance()->get_setting( 'batch_size', 20 );
	}

	/**
	 * Schedule actions for starting a scan.
	 *
	 * @param string $mode       Scan mode.
	 * @param bool   $dry_run    Whether this is a dry run.
	 * @param int    $batch_size Batch size.
	 * @return void
	 */
	private function schedule_scan_start_actions( string $mode, bool $dry_run, int $batch_size ): void {
		// For reorganize_all mode, schedule folder cleanup first (only when not previewing).
		if ( 'reorganize_all' === $mode && ! $dry_run ) {
			as_schedule_single_action(
				time(),
				'vmfa_cleanup_folders',
				array(),
				'vmfa-ai-organizer'
			);
			return;
		}

		$this->schedule_first_batch( $dry_run, $batch_size );
	}

	/**
	 * Schedule the first batch processing action.
	 *
	 * @param bool $dry_run    Whether this is a dry run.
	 * @param int  $batch_size Batch size.
	 * @return void
	 */
	private function schedule_first_batch( bool $dry_run, int $batch_size ): void {
		as_schedule_single_action(
			time(),
			'vmfa_process_media_batch',
			array(
				'batch_number' => 0,
				'batch_size'   => $batch_size,
				'dry_run'      => $dry_run,
			),
			'vmfa-ai-organizer'
		);
	}

	/**
	 * Schedule scan completion step based on dry-run.
	 *
	 * @param bool $dry_run Whether this is a dry run.
	 * @return void
	 */
	private function schedule_completion( bool $dry_run ): void {
		if ( ! $dry_run ) {
			as_schedule_single_action(
				time(),
				'vmfa_apply_assignments',
				array(),
				'vmfa-ai-organizer'
			);
			return;
		}

		as_schedule_single_action(
			time(),
			'vmfa_finalize_scan',
			array(),
			'vmfa-ai-organizer'
		);
	}

	/**
	 * Preflight for reorganize_all when starting a scan.
	 *
	 * @param string $mode    Scan mode.
	 * @param bool   $dry_run Whether this is a dry run.
	 * @return void
	 */
	private function run_reorganize_all_preflight_for_scan_start( string $mode, bool $dry_run ): void {
		if ( 'reorganize_all' === $mode && ! $dry_run ) {
			$this->backup_service->export();
		}
	}

	/**
	 * Preflight for reorganize_all when applying cached dry-run results.
	 *
	 * @param string $mode Scan mode.
	 * @return void
	 */
	private function run_reorganize_all_preflight_for_apply_cached( string $mode ): void {
		if ( 'reorganize_all' !== $mode ) {
			return;
		}

		$this->backup_service->export();

		// Remove all existing folders (this also removes all media assignments).
		$this->backup_service->remove_all_folders();

		// Clear session-suggested folders for fresh start.
		delete_option( 'vmfa_session_suggested_folders' );
	}

	/**
	 * Get the number of cached dry-run results.
	 *
	 * @return int
	 */
	public function get_cached_results_count(): int {
		$cached = get_option( self::DRYRUN_CACHE_OPTION, array() );
		return count( $cached );
	}

	/**
	 * Get all cached dry-run results.
	 *
	 * @return array
	 */
	public function get_cached_results(): array {
		return get_option( self::DRYRUN_CACHE_OPTION, array() );
	}

	/**
	 * Finalize the scan.
	 *
	 * @return void
	 */
	public function finalize_scan(): void {
		$progress = $this->get_progress();

		$this->update_progress(
			array(
				'status'       => 'completed',
				'completed_at' => time(),
			)
		);

		// Clean up temporary data.
		delete_option( 'vmfa_scan_attachment_ids' );
		delete_option( self::PENDING_RESULTS_OPTION );

		// For successful reorganize_all, clean up backup after some time.
		// Keep backup for manual restore if needed.

		/**
		 * Fires when a scan is completed.
		 *
		 * @param array $progress Final progress data.
		 */
		do_action( 'vmfa_scan_completed', $progress );
	}

	/**
	 * Cancel an in-progress scan.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function cancel_scan(): array {
		$progress = $this->get_progress();

		if ( 'running' !== $progress[ 'status' ] ) {
			return array(
				'success' => false,
				'message' => __( 'No scan is currently running.', 'vmfa-ai-organizer' ),
			);
		}

		// Unschedule all pending actions.
		as_unschedule_all_actions( 'vmfa_process_media_batch', array(), 'vmfa-ai-organizer' );
		as_unschedule_all_actions( 'vmfa_apply_assignments', array(), 'vmfa-ai-organizer' );
		as_unschedule_all_actions( 'vmfa_finalize_scan', array(), 'vmfa-ai-organizer' );
		as_unschedule_all_actions( 'vmfa_cleanup_folders', array(), 'vmfa-ai-organizer' );

		// Cancel in-progress and failed actions for our group.
		$this->cleanup_action_scheduler_group();

		// Update progress.
		$this->update_progress(
			array(
				'status'       => 'cancelled',
				'cancelled_at' => time(),
			)
		);

		// Clean up.
		delete_option( 'vmfa_scan_attachment_ids' );
		delete_option( self::PENDING_RESULTS_OPTION );

		return array(
			'success' => true,
			'message' => __( 'Scan cancelled successfully.', 'vmfa-ai-organizer' ),
		);
	}

	/**
	 * Cleanup all Action Scheduler actions for our group.
	 *
	 * This marks in-progress actions as cancelled and deletes failed actions
	 * to prevent stale actions from blocking new scans.
	 *
	 * @return void
	 */
	private function cleanup_action_scheduler_group(): void {
		if ( ! class_exists( 'ActionScheduler_Store' ) ) {
			return;
		}

		$store = \ActionScheduler_Store::instance();

		// Get all in-progress and pending actions for our group.
		$statuses = array(
			\ActionScheduler_Store::STATUS_PENDING,
			\ActionScheduler_Store::STATUS_RUNNING,
		);

		foreach ( $statuses as $status ) {
			$action_ids = $store->query_actions(
				array(
					'group'    => 'vmfa-ai-organizer',
					'status'   => $status,
					'per_page' => 100,
				)
			);

			foreach ( $action_ids as $action_id ) {
				// Mark as cancelled by deleting the action.
				$store->delete_action( $action_id );
			}
		}

		// Also clean up failed actions to start fresh.
		$failed_action_ids = $store->query_actions(
			array(
				'group'    => 'vmfa-ai-organizer',
				'status'   => \ActionScheduler_Store::STATUS_FAILED,
				'per_page' => 100,
			)
		);

		foreach ( $failed_action_ids as $action_id ) {
			$store->delete_action( $action_id );
		}
	}

	/**
	 * Get current scan progress.
	 *
	 * @return array{
	 *     status: string,
	 *     mode: string,
	 *     dry_run: bool,
	 *     total: int,
	 *     processed: int,
	 *     results: array,
	 *     started_at: int|null,
	 *     completed_at: int|null,
	 *     current_item: int|null,
	 *     current_title: string|null,
	 *     error: string|null
	 * }
	 */
	public function get_progress(): array {
		$defaults = array(
			'status'        => 'idle',
			'mode'          => '',
			'dry_run'       => false,
			'total'         => 0,
			'processed'     => 0,
			'results'       => array(),
			'started_at'    => null,
			'completed_at'  => null,
			'current_item'  => null,
			'current_title' => null,
			'applied'       => 0,
			'failed'        => 0,
			'error'         => null,
		);

		$progress = get_option( self::PROGRESS_OPTION, array() );

		return wp_parse_args( $progress, $defaults );
	}

	/**
	 * Update scan progress.
	 *
	 * @param array<string, mixed> $updates Progress updates to merge.
	 * @return void
	 */
	private function update_progress( array $updates ): void {
		$progress = $this->get_progress();
		$progress = array_merge( $progress, $updates );
		update_option( self::PROGRESS_OPTION, $progress, false );
	}

	/**
	 * Reset scan progress to idle state.
	 *
	 * @return void
	 */
	public function reset_progress(): void {
		update_option(
			self::PROGRESS_OPTION,
			array(
				'status'    => 'idle',
				'mode'      => '',
				'dry_run'   => false,
				'total'     => 0,
				'processed' => 0,
				'results'   => array(),
			),
			false
		);
	}

	/**
	 * Get the analysis service.
	 *
	 * @return AIAnalysisService
	 */
	public function get_analysis_service(): AIAnalysisService {
		return $this->analysis_service;
	}

	/**
	 * Get the backup service.
	 *
	 * @return BackupService
	 */
	public function get_backup_service(): BackupService {
		return $this->backup_service;
	}
}
