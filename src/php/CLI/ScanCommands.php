<?php
/**
 * WP-CLI Scan Commands for VMFA AI Organizer.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\CLI;

use VmfaAiOrganizer\Services\MediaScannerService;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage media scanning operations.
 *
 * ## EXAMPLES
 *
 *     # Start a preview scan
 *     $ wp vmfa-ai scan start
 *
 *     # Watch scan progress
 *     $ wp vmfa-ai scan status --watch
 *
 *     # Apply previewed changes
 *     $ wp vmfa-ai scan apply
 *
 * @package VmfaAiOrganizer\CLI
 */
class ScanCommands {

	/**
	 * Start a new media scan in preview mode.
	 *
	 * Uses Action Scheduler for background processing. By default, scans run
	 * in preview mode. Use 'scan status --watch' to monitor progress, then
	 * 'scan apply' to apply the previewed changes.
	 *
	 * ## OPTIONS
	 *
	 * [--mode=<mode>]
	 * : Scan mode.
	 * ---
	 * default: organize_unassigned
	 * options:
	 *   - organize_unassigned
	 *   - reanalyze_all
	 *   - reorganize_all
	 * ---
	 *
	 * [--provider=<provider>]
	 * : Override the AI provider (openai, anthropic, gemini, ollama, grok, exo).
	 *
	 * [--model=<model>]
	 * : Override the AI model.
	 *
	 * [--api-key=<key>]
	 * : Override the API key.
	 *
	 * [--endpoint=<url>]
	 * : Override the endpoint URL (for ollama/exo).
	 *
	 * [--timeout=<seconds>]
	 * : Override request timeout in seconds.
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Start a preview scan of unassigned media
	 *     $ wp vmfa-ai scan start
	 *
	 *     # Scan all media with a specific provider
	 *     $ wp vmfa-ai scan start --mode=reanalyze_all --provider=ollama --model=llava
	 *
	 *     # Reorganize all media (deletes existing folders)
	 *     $ wp vmfa-ai scan start --mode=reorganize_all
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function start( array $args, array $assoc_args ): void {
		Commands::check_dependencies();
		Commands::apply_overrides( $assoc_args );

		$mode      = $assoc_args[ 'mode' ] ?? 'organize_unassigned';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		$scanner = new MediaScannerService();

		// Check if a scan is already running.
		$progress = $scanner->get_progress();
		if ( 'running' === $progress[ 'status' ] ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:already_running' );
			} else {
				WP_CLI::error( 'A scan is already in progress. Use "wp vmfa-ai scan status" to check progress or "wp vmfa-ai scan cancel" to cancel it.' );
			}
			return;
		}

		// Start the scan in dry-run (preview) mode.
		$result = $scanner->start_scan( $mode, true );

		if ( ! $result[ 'success' ] ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:' . sanitize_key( $result[ 'message' ] ) );
			} else {
				WP_CLI::error( $result[ 'message' ] );
			}
			return;
		}

		if ( $porcelain ) {
			WP_CLI::line( 'started:' . ( $result[ 'total' ] ?? 0 ) );
		} else {
			$mode_labels = array(
				'organize_unassigned' => 'Organize Unassigned',
				'reanalyze_all'       => 'Re-analyze All',
				'reorganize_all'      => 'Reorganize All',
			);

			WP_CLI::success(
				sprintf(
					'Started %s scan with %d media files.',
					WP_CLI::colorize( '%G' . ( $mode_labels[ $mode ] ?? $mode ) . '%n' ),
					$result[ 'total' ] ?? 0
				)
			);
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%YNext steps:%n' ) );
			WP_CLI::line( '  • Monitor progress: wp vmfa-ai scan status --watch' );
			WP_CLI::line( '  • Apply changes:    wp vmfa-ai scan apply' );
			WP_CLI::line( '  • Cancel scan:      wp vmfa-ai scan cancel' );
		}
	}

	/**
	 * Show scan status and progress.
	 *
	 * ## OPTIONS
	 *
	 * [--watch]
	 * : Continuously watch progress with live updates.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check current scan status
	 *     $ wp vmfa-ai scan status
	 *
	 *     # Watch progress with live updates
	 *     $ wp vmfa-ai scan status --watch
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		Commands::check_dependencies();

		$watch     = Utils\get_flag_value( $assoc_args, 'watch', false );
		$format    = $assoc_args[ 'format' ] ?? 'table';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		$scanner = new MediaScannerService();

		if ( $watch ) {
			$this->watch_progress( $scanner, $porcelain );
			return;
		}

		$this->show_status( $scanner, $format, $porcelain );
	}

	/**
	 * Show current scan status.
	 *
	 * @param MediaScannerService $scanner   Scanner service.
	 * @param string              $format    Output format.
	 * @param bool                $porcelain Minimal output.
	 * @return void
	 */
	private function show_status( MediaScannerService $scanner, string $format, bool $porcelain ): void {
		$progress = $scanner->get_progress();

		if ( $porcelain ) {
			WP_CLI::line( sprintf(
				'%s:%d:%d',
				$progress[ 'status' ],
				$progress[ 'processed' ],
				$progress[ 'total' ]
			) );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $progress, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::line( \Spyc::YAMLDump( $progress ) );
			return;
		}

		// Table format.
		$status_colors = array(
			'idle'      => '%Y',
			'running'   => '%C',
			'completed' => '%G',
			'cancelled' => '%R',
		);

		$status_color = $status_colors[ $progress[ 'status' ] ] ?? '%n';

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BScan Status%n' ) );
		WP_CLI::line( str_repeat( '─', 50 ) );
		WP_CLI::line( sprintf( 'Status:    %s', WP_CLI::colorize( $status_color . ucfirst( $progress[ 'status' ] ) . '%n' ) ) );
		WP_CLI::line( sprintf( 'Mode:      %s', $progress[ 'mode' ] ?: 'N/A' ) );
		WP_CLI::line( sprintf( 'Preview:   %s', $progress[ 'dry_run' ] ? 'Yes' : 'No' ) );
		WP_CLI::line( sprintf( 'Progress:  %d / %d', $progress[ 'processed' ], $progress[ 'total' ] ) );

		if ( $progress[ 'total' ] > 0 ) {
			$percent = round( ( $progress[ 'processed' ] / $progress[ 'total' ] ) * 100 );
			WP_CLI::line( sprintf( 'Complete:  %d%%', $percent ) );
			Commands::render_progress_bar( $progress[ 'processed' ], $progress[ 'total' ] );
		}

		if ( $progress[ 'started_at' ] ) {
			WP_CLI::line( sprintf( 'Started:   %s', gmdate( 'Y-m-d H:i:s', $progress[ 'started_at' ] ) ) );

			if ( 'running' === $progress[ 'status' ] && $progress[ 'processed' ] > 0 ) {
				$elapsed     = time() - $progress[ 'started_at' ];
				$rate        = $progress[ 'processed' ] / max( 1, $elapsed );
				$remaining   = $progress[ 'total' ] - $progress[ 'processed' ];
				$eta_seconds = $rate > 0 ? (int) ( $remaining / $rate ) : 0;
				WP_CLI::line( sprintf( 'ETA:       %s', Commands::format_duration( $eta_seconds ) ) );
			}
		}

		if ( $progress[ 'completed_at' ] ) {
			WP_CLI::line( sprintf( 'Completed: %s', gmdate( 'Y-m-d H:i:s', $progress[ 'completed_at' ] ) ) );
		}

		if ( 'completed' === $progress[ 'status' ] && $progress[ 'dry_run' ] ) {
			$cached_count = $scanner->get_cached_results_count();
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%GPreview complete!%n' ) );
			WP_CLI::line( sprintf( 'Pending assignments: %d', $cached_count ) );
			WP_CLI::line( '' );
			WP_CLI::line( 'Run "wp vmfa-ai scan apply" to apply these changes.' );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Watch scan progress with live updates.
	 *
	 * @param MediaScannerService $scanner   Scanner service.
	 * @param bool                $porcelain Minimal output.
	 * @return void
	 */
	private function watch_progress( MediaScannerService $scanner, bool $porcelain ): void {
		$first_render   = true;
		$spinner_frames = array( '⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏' );
		$spinner_index  = 0;

		while ( true ) {
			// Run pending Action Scheduler tasks to process the scan.
			$this->run_pending_actions();

			$progress = $scanner->get_progress();

			if ( $porcelain ) {
				WP_CLI::line( sprintf(
					'%s:%d:%d',
					$progress[ 'status' ],
					$progress[ 'processed' ],
					$progress[ 'total' ]
				) );

				if ( 'running' !== $progress[ 'status' ] ) {
					break;
				}

				sleep( 2 );
				continue;
			}

			// Clear screen for live updates (only after first render).
			if ( ! $first_render ) {
				// Move cursor to top and clear screen.
				echo "\033[2J\033[H";
			}
			$first_render = false;

			// Header.
			$spinner = $spinner_frames[ $spinner_index % count( $spinner_frames ) ];
			++$spinner_index;

			WP_CLI::line( '' );
			if ( 'running' === $progress[ 'status' ] ) {
				WP_CLI::line( WP_CLI::colorize( "%B{$spinner} Scan in Progress%n" ) );
			} elseif ( 'completed' === $progress[ 'status' ] ) {
				WP_CLI::line( WP_CLI::colorize( '%G✓ Scan Complete%n' ) );
			} elseif ( 'cancelled' === $progress[ 'status' ] ) {
				WP_CLI::line( WP_CLI::colorize( '%R✗ Scan Cancelled%n' ) );
			} else {
				WP_CLI::line( WP_CLI::colorize( '%YScan Status: ' . ucfirst( $progress[ 'status' ] ) . '%n' ) );
			}

			WP_CLI::line( str_repeat( '═', 70 ) );

			// Progress info.
			$percent = $progress[ 'total' ] > 0 ? round( ( $progress[ 'processed' ] / $progress[ 'total' ] ) * 100 ) : 0;
			WP_CLI::line( sprintf(
				'Mode: %s | Preview: %s | Progress: %d/%d (%d%%)',
				Commands::get_mode_label( $progress[ 'mode' ] ),
				$progress[ 'dry_run' ] ? 'Yes' : 'No',
				$progress[ 'processed' ],
				$progress[ 'total' ],
				$percent
			) );

			// Progress bar.
			Commands::render_progress_bar( $progress[ 'processed' ], $progress[ 'total' ] );

			// ETA.
			if ( 'running' === $progress[ 'status' ] && $progress[ 'processed' ] > 0 && $progress[ 'started_at' ] ) {
				$elapsed     = time() - $progress[ 'started_at' ];
				$rate        = $progress[ 'processed' ] / max( 1, $elapsed );
				$remaining   = $progress[ 'total' ] - $progress[ 'processed' ];
				$eta_seconds = $rate > 0 ? (int) ( $remaining / $rate ) : 0;
				WP_CLI::line( sprintf( 'Elapsed: %s | ETA: %s', Commands::format_duration( $elapsed ), Commands::format_duration( $eta_seconds ) ) );
			}

			WP_CLI::line( '' );

			// Results table (last results from progress).
			$results = $progress[ 'results' ] ?? array();
			if ( ! empty( $results ) ) {
				WP_CLI::line( WP_CLI::colorize( '%BRecent Results:%n' ) );
				WP_CLI::line( str_repeat( '─', 70 ) );

				// Show last 10 results.
				$display_results = array_slice( $results, -10 );

				foreach ( $display_results as $result ) {
					$action_icon = match ( $result[ 'action' ] ?? '' ) {
						'assign' => WP_CLI::colorize( '%G→%n' ),
						'create' => WP_CLI::colorize( '%C+%n' ),
						'skip'   => WP_CLI::colorize( '%Y-%n' ),
						default  => ' ',
					};

					$folder = $result[ 'folder_name' ] ?? $result[ 'new_folder_path' ] ?? 'N/A';
					$title  = Commands::truncate( $result[ 'filename' ] ?? 'Unknown', 30 );

					$confidence       = $result[ 'confidence' ] ?? 0;
					$confidence_color = $confidence >= 0.8 ? '%G' : ( $confidence >= 0.5 ? '%Y' : '%R' );
					$confidence_str   = WP_CLI::colorize( sprintf( '%s%.0f%%%n', $confidence_color, $confidence * 100 ) );

					WP_CLI::line( sprintf(
						'  %s %-30s → %-25s %s',
						$action_icon,
						$title,
						Commands::truncate( $folder, 25 ),
						$confidence_str
					) );
				}

				WP_CLI::line( '' );
			}

			// Check if complete.
			if ( 'running' !== $progress[ 'status' ] ) {
				if ( 'completed' === $progress[ 'status' ] && $progress[ 'dry_run' ] ) {
					$cached_count = $scanner->get_cached_results_count();
					WP_CLI::line( WP_CLI::colorize( '%GPreview complete!%n' ) );
					WP_CLI::line( sprintf( 'Pending assignments: %d', $cached_count ) );
					WP_CLI::line( '' );
					WP_CLI::line( 'Run "wp vmfa-ai scan apply" to apply these changes.' );
					WP_CLI::line( 'Run "wp vmfa-ai scan results" to see all results.' );
				}
				break;
			}

			sleep( 2 );
		}
	}

	/**
	 * Apply previewed scan results.
	 *
	 * Applies the cached dry-run results from a previous preview scan.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Apply with confirmation
	 *     $ wp vmfa-ai scan apply
	 *
	 *     # Apply without confirmation
	 *     $ wp vmfa-ai scan apply --yes
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function apply( array $args, array $assoc_args ): void {
		Commands::check_dependencies();

		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$yes       = Utils\get_flag_value( $assoc_args, 'yes', false );

		$scanner      = new MediaScannerService();
		$cached_count = $scanner->get_cached_results_count();

		if ( 0 === $cached_count ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:no_cached_results' );
			} else {
				WP_CLI::error( 'No cached preview results found. Run "wp vmfa-ai scan start" first to preview changes.' );
			}
			return;
		}

		// Get mode from last scan.
		$progress = $scanner->get_progress();
		$mode     = $progress[ 'mode' ] ?? 'organize_unassigned';

		if ( ! $yes && ! $porcelain ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%YApply Preview Results%n' ) );
			WP_CLI::line( str_repeat( '─', 50 ) );
			WP_CLI::line( sprintf( 'Mode: %s', Commands::get_mode_label( $mode ) ) );
			WP_CLI::line( sprintf( 'Pending assignments: %d', $cached_count ) );

			if ( 'reorganize_all' === $mode ) {
				WP_CLI::line( '' );
				WP_CLI::line( WP_CLI::colorize( '%R⚠ WARNING: This will delete all existing folders!%n' ) );
			}

			WP_CLI::line( '' );
			WP_CLI::confirm( 'Apply these changes?' );
		}

		$result = $scanner->apply_cached_results( $mode );

		if ( ! $result[ 'success' ] ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:' . sanitize_key( $result[ 'message' ] ) );
			} else {
				WP_CLI::error( $result[ 'message' ] );
			}
			return;
		}

		if ( $porcelain ) {
			WP_CLI::line( sprintf( 'applied:%d:%d', $result[ 'applied' ] ?? 0, $result[ 'failed' ] ?? 0 ) );
		} else {
			WP_CLI::success( sprintf(
				'Applied %s assignments (%s failed).',
				WP_CLI::colorize( '%G' . ( $result[ 'applied' ] ?? 0 ) . '%n' ),
				WP_CLI::colorize( '%R' . ( $result[ 'failed' ] ?? 0 ) . '%n' )
			) );
		}
	}

	/**
	 * Cancel the current scan.
	 *
	 * ## OPTIONS
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp vmfa-ai scan cancel
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function cancel( array $args, array $assoc_args ): void {
		Commands::check_dependencies();

		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$scanner   = new MediaScannerService();

		$result = $scanner->cancel_scan();

		if ( ! $result[ 'success' ] ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:no_scan_running' );
			} else {
				WP_CLI::warning( $result[ 'message' ] );
			}
			return;
		}

		if ( $porcelain ) {
			WP_CLI::line( 'cancelled' );
		} else {
			WP_CLI::success( 'Scan cancelled.' );
		}
	}

	/**
	 * Reset scan progress.
	 *
	 * Clears all scan state without cancelling scheduled actions.
	 * Use this if the scan status appears stuck.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp vmfa-ai scan reset --yes
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function reset( array $args, array $assoc_args ): void {
		Commands::check_dependencies();

		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$yes       = Utils\get_flag_value( $assoc_args, 'yes', false );

		if ( ! $yes && ! $porcelain ) {
			WP_CLI::confirm( 'This will clear all scan progress and cached results. Continue?' );
		}

		$scanner = new MediaScannerService();
		$scanner->reset_progress();

		// Also clear cached results.
		delete_option( 'vmfa_scan_dryrun_cache' );
		delete_option( 'vmfa_scan_attachment_ids' );
		delete_option( 'vmfa_scan_pending_results' );

		if ( $porcelain ) {
			WP_CLI::line( 'reset' );
		} else {
			WP_CLI::success( 'Scan progress reset.' );
		}
	}

	/**
	 * Show cached preview results.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output only attachment IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp vmfa-ai scan results
	 *     $ wp vmfa-ai scan results --format=json
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function results( array $args, array $assoc_args ): void {
		Commands::check_dependencies();

		$format    = $assoc_args[ 'format' ] ?? 'table';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		$scanner = new MediaScannerService();
		$results = $scanner->get_cached_results();

		if ( empty( $results ) ) {
			if ( $porcelain ) {
				return;
			}
			WP_CLI::warning( 'No cached preview results found.' );
			return;
		}

		if ( $porcelain ) {
			foreach ( $results as $result ) {
				WP_CLI::line( $result[ 'attachment_id' ] ?? '' );
			}
			return;
		}

		// Format for display.
		$display_data = array_map( function ( $result ) {
			return array(
				'ID'         => $result[ 'attachment_id' ] ?? '',
				'Filename'   => Commands::truncate( $result[ 'filename' ] ?? 'Unknown', 30 ),
				'Action'     => $result[ 'action' ] ?? '',
				'Folder'     => $result[ 'folder_name' ] ?? $result[ 'new_folder_path' ] ?? '',
				'Confidence' => sprintf( '%.0f%%', ( $result[ 'confidence' ] ?? 0 ) * 100 ),
			);
		}, $results );

		Utils\format_items( $format, $display_data, array( 'ID', 'Filename', 'Action', 'Folder', 'Confidence' ) );
	}

	/**
	 * Run pending Action Scheduler actions for the scan.
	 *
	 * @return void
	 */
	private function run_pending_actions(): void {
		if ( ! class_exists( 'ActionScheduler' ) ) {
			return;
		}

		// Run pending vmfa actions.
		$store  = \ActionScheduler::store();
		$runner = new \ActionScheduler_QueueRunner( $store );

		// Process up to 5 actions per iteration to keep the loop responsive.
		$runner->run( 'CLI' );
	}
}
