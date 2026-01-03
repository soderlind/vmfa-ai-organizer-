<?php
/**
 * REST API Controller.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\REST;

use VmfaAiOrganizer\Services\AIAnalysisService;
use VmfaAiOrganizer\Services\BackupService;
use VmfaAiOrganizer\Services\MediaScannerService;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API controller for AI analysis operations.
 */
class AnalysisController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa/v1';

	/**
	 * Media scanner service.
	 *
	 * @var MediaScannerService
	 */
	private MediaScannerService $scanner_service;

	/**
	 * AI analysis service.
	 *
	 * @var AIAnalysisService
	 */
	private AIAnalysisService $analysis_service;

	/**
	 * Backup service.
	 *
	 * @var BackupService
	 */
	private BackupService $backup_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->scanner_service  = new MediaScannerService();
		$this->analysis_service = new AIAnalysisService();
		$this->backup_service   = new BackupService();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$valid_modes = $this->scanner_service->get_valid_modes();

		// Start a scan.
		register_rest_route(
			$this->namespace,
			'/scan',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_scan' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'mode'    => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => $valid_modes,
							'description'       => __( 'Scan mode.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'dry_run' => array(
							'required'    => false,
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Whether to run in preview mode without making changes.', 'vmfa-ai-organizer' ),
						),
					),
				),
			)
		);

		// Get scan status.
		register_rest_route(
			$this->namespace,
			'/scan/status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scan_status' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Cancel scan.
		register_rest_route(
			$this->namespace,
			'/scan/cancel',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'cancel_scan' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Reset scan progress.
		register_rest_route(
			$this->namespace,
			'/scan/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset_scan' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Apply cached dry-run results.
		register_rest_route(
			$this->namespace,
			'/scan/apply-cached',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_cached_results' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'mode' => array(
							'required'          => true,
							'type'              => 'string',
							'enum'              => $valid_modes,
							'description'       => __( 'Original scan mode.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		// Get cached results count.
		register_rest_route(
			$this->namespace,
			'/scan/cached-count',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cached_count' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Get cached dry-run results.
		register_rest_route(
			$this->namespace,
			'/scan/cached-results',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cached_results' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Analyze single media.
		register_rest_route(
			$this->namespace,
			'/analyze/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'analyze_media' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Attachment ID.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Apply analysis result.
		register_rest_route(
			$this->namespace,
			'/apply/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply_result' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'              => array(
							'required'          => true,
							'type'              => 'integer',
							'description'       => __( 'Attachment ID.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'absint',
						),
						'folder_id'       => array(
							'required'          => false,
							'type'              => 'integer',
							'description'       => __( 'Folder ID to assign.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'absint',
						),
						'new_folder_path' => array(
							'required'          => false,
							'type'              => 'string',
							'description'       => __( 'New folder path to create.', 'vmfa-ai-organizer' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Get backup info.
		register_rest_route(
			$this->namespace,
			'/backup',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_backup' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_backup' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Restore from backup.
		register_rest_route(
			$this->namespace,
			'/restore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_backup' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Get statistics.
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Check if user has permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission(): bool|WP_Error {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'vmfa-ai-organizer' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Start a scan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_scan( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$mode    = $request->get_param( 'mode' );
		$dry_run = (bool) $request->get_param( 'dry_run' );

		$result = $this->scanner_service->start_scan( $mode, $dry_run );

		if ( ! $result[ 'success' ] ) {
			return new WP_Error(
				'scan_error',
				$result[ 'message' ],
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result[ 'message' ],
				'total'   => $result[ 'total' ] ?? 0,
			),
			200
		);
	}

	/**
	 * Get scan status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_scan_status(): WP_REST_Response {
		$progress = $this->scanner_service->get_progress();

		// Ensure processed never exceeds total (safety cap).
		$processed = min( $progress[ 'processed' ], $progress[ 'total' ] );

		// Calculate percentage (capped at 100%).
		$percentage = 0;
		if ( $progress[ 'total' ] > 0 ) {
			$percentage = min( 100, round( ( $processed / $progress[ 'total' ] ) * 100, 1 ) );
		}

		return new WP_REST_Response(
			array(
				'status'       => $progress[ 'status' ],
				'mode'         => $progress[ 'mode' ],
				'dry_run'      => $progress[ 'dry_run' ],
				'total'        => $progress[ 'total' ],
				'processed'    => $processed,
				'percentage'   => $percentage,
				'applied'      => $progress[ 'applied' ] ?? 0,
				'failed'       => $progress[ 'failed' ] ?? 0,
				'results'      => $progress[ 'results' ],
				'started_at'   => $progress[ 'started_at' ],
				'completed_at' => $progress[ 'completed_at' ] ?? null,
				'error'        => $progress[ 'error' ],
			),
			200
		);
	}

	/**
	 * Cancel a scan.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_scan(): WP_REST_Response|WP_Error {
		$result = $this->scanner_service->cancel_scan();

		if ( ! $result[ 'success' ] ) {
			return new WP_Error(
				'cancel_error',
				$result[ 'message' ],
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result[ 'message' ],
			),
			200
		);
	}

	/**
	 * Reset scan progress.
	 *
	 * @return WP_REST_Response
	 */
	public function reset_scan(): WP_REST_Response {
		$this->scanner_service->reset_progress();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Scan progress reset.', 'vmfa-ai-organizer' ),
			),
			200
		);
	}

	/**
	 * Apply cached dry-run results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_cached_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$mode = $request->get_param( 'mode' );

		$result = $this->scanner_service->apply_cached_results( $mode );

		if ( ! $result[ 'success' ] ) {
			return new WP_Error(
				'apply_error',
				$result[ 'message' ],
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $result[ 'message' ],
				'applied' => $result[ 'applied' ] ?? 0,
				'failed'  => $result[ 'failed' ] ?? 0,
			),
			200
		);
	}

	/**
	 * Get cached dry-run results count.
	 *
	 * @return WP_REST_Response
	 */
	public function get_cached_count(): WP_REST_Response {
		$count = $this->scanner_service->get_cached_results_count();

		return new WP_REST_Response(
			array(
				'success' => true,
				'count'   => $count,
			),
			200
		);
	}

	/**
	 * Get cached dry-run results.
	 *
	 * @return WP_REST_Response
	 */
	public function get_cached_results(): WP_REST_Response {
		$results = $this->scanner_service->get_cached_results();

		return new WP_REST_Response(
			array(
				'success' => true,
				'results' => $results,
				'count'   => count( $results ),
			),
			200
		);
	}

	/**
	 * Analyze a single media item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function analyze_media( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id = (int) $request->get_param( 'id' );

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Invalid attachment ID.', 'vmfa-ai-organizer' ),
				array( 'status' => 404 )
			);
		}

		$result = $this->analysis_service->analyze_media( $attachment_id );

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Apply an analysis result.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply_result( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$attachment_id   = (int) $request->get_param( 'id' );
		$folder_id       = $request->get_param( 'folder_id' );
		$new_folder_path = $request->get_param( 'new_folder_path' );

		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Invalid attachment ID.', 'vmfa-ai-organizer' ),
				array( 'status' => 404 )
			);
		}

		// Determine action based on provided parameters.
		if ( ! empty( $folder_id ) ) {
			$success = $this->analysis_service->assign_to_folder( $attachment_id, (int) $folder_id );
		} elseif ( ! empty( $new_folder_path ) ) {
			$new_folder_id = $this->analysis_service->create_folder_from_path( $new_folder_path );
			if ( $new_folder_id ) {
				$success = $this->analysis_service->assign_to_folder( $attachment_id, $new_folder_id );
			} else {
				$success = false;
			}
		} else {
			return new WP_Error(
				'missing_folder',
				__( 'Either folder_id or new_folder_path is required.', 'vmfa-ai-organizer' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $success ) {
			return new WP_Error(
				'apply_failed',
				__( 'Failed to apply folder assignment.', 'vmfa-ai-organizer' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Folder assignment applied.', 'vmfa-ai-organizer' ),
			),
			200
		);
	}

	/**
	 * Get backup information.
	 *
	 * @return WP_REST_Response
	 */
	public function get_backup(): WP_REST_Response {
		$info = $this->backup_service->get_backup_info();

		return new WP_REST_Response( $info, 200 );
	}

	/**
	 * Delete backup.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_backup(): WP_REST_Response {
		$this->backup_service->cleanup();

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Backup deleted.', 'vmfa-ai-organizer' ),
			),
			200
		);
	}

	/**
	 * Restore from backup.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_backup(): WP_REST_Response|WP_Error {
		if ( ! $this->backup_service->has_backup() ) {
			return new WP_Error(
				'no_backup',
				__( 'No backup available to restore.', 'vmfa-ai-organizer' ),
				array( 'status' => 404 )
			);
		}

		$result = $this->backup_service->restore();

		if ( ! $result[ 'success' ] ) {
			return new WP_Error(
				'restore_failed',
				$result[ 'error' ],
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response(
			array(
				'success'              => true,
				'message'              => __( 'Backup restored successfully.', 'vmfa-ai-organizer' ),
				'folders_restored'     => $result[ 'folders_restored' ],
				'assignments_restored' => $result[ 'assignments_restored' ],
			),
			200
		);
	}

	/**
	 * Get statistics.
	 *
	 * @return WP_REST_Response
	 */
	public function get_stats(): WP_REST_Response {
		global $wpdb;

		// Total media count.
		$total_media = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		// Unassigned media count.
		$unassigned = count( $this->analysis_service->get_unassigned_media_ids() );

		// Assigned media count.
		$assigned = $total_media - $unassigned;

		// Folder count.
		$folders = wp_count_terms( array( 'taxonomy' => 'vmfo_folder' ) );
		if ( is_wp_error( $folders ) ) {
			$folders = 0;
		}

		return new WP_REST_Response(
			array(
				'total_media' => $total_media,
				'assigned'    => $assigned,
				'unassigned'  => $unassigned,
				'folders'     => (int) $folders,
			),
			200
		);
	}
}
