<?php
/**
 * Tests for MediaScannerService.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\Services;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\Services\MediaScannerService;
use VmfaAiOrganizer\Services\AIAnalysisService;
use VmfaAiOrganizer\Services\BackupService;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Media Scanner Service test class.
 */
class MediaScannerServiceTest extends BrainMonkeyTestCase {

	/**
	 * Test start_scan prevents concurrent scans.
	 */
	public function test_start_scan_prevents_concurrent(): void {
		$this->stub_options(
			[
				'vmfa_scan_progress' => [ 'status' => 'running' ],
			]
		);

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$backup_service   = Mockery::mock( BackupService::class );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$result  = $service->start_scan( 'organize_unassigned', false );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'already running', strtolower( $result['error'] ) );
	}

	/**
	 * Test start_scan creates backup for reorganize mode.
	 */
	public function test_start_scan_creates_backup_for_reorganize(): void {
		$this->stub_options(
			[
				'vmfa_scan_progress' => [ 'status' => 'idle' ],
				'vmfa_settings'      => [ 'batch_size' => 20 ],
			]
		);

		// Mock get_posts for unassigned media.
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( [] );

		$analysis_service = Mockery::mock( AIAnalysisService::class );

		$backup_service = Mockery::mock( BackupService::class );
		$backup_service->shouldReceive( 'export' )
			->once()
			->andReturn( true );

		Functions\expect( 'update_option' )
			->atLeast()
			->once()
			->andReturn( true );

		// Mock Action Scheduler.
		Functions\expect( 'as_enqueue_async_action' )
			->andReturn( 1 );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$result  = $service->start_scan( 'reorganize_all', false );

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test get_status returns current progress.
	 */
	public function test_get_status_returns_progress(): void {
		$progress = [
			'status'     => 'running',
			'mode'       => 'organize_unassigned',
			'dry_run'    => false,
			'total'      => 100,
			'processed'  => 25,
			'results'    => [],
			'started_at' => time() - 60,
		];

		$this->stub_options( [ 'vmfa_scan_progress' => $progress ] );

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$backup_service   = Mockery::mock( BackupService::class );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$status  = $service->get_status();

		$this->assertEquals( 'running', $status['status'] );
		$this->assertEquals( 25, $status['percentage'] );
		$this->assertEquals( 100, $status['total'] );
		$this->assertEquals( 25, $status['processed'] );
	}

	/**
	 * Test cancel_scan updates status.
	 */
	public function test_cancel_scan_updates_status(): void {
		$this->stub_options(
			[
				'vmfa_scan_progress' => [ 'status' => 'running' ],
			]
		);

		Functions\expect( 'update_option' )
			->once()
			->with(
				'vmfa_scan_progress',
				Mockery::on(
					static fn( $data ) => 'cancelled' === $data['status']
				)
			)
			->andReturn( true );

		// Mock Action Scheduler cancel.
		Functions\expect( 'as_unschedule_all_actions' )
			->times( 3 )
			->andReturn( null );

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$backup_service   = Mockery::mock( BackupService::class );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$result  = $service->cancel_scan();

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Test process_batch processes items and schedules next.
	 */
	public function test_process_batch_processes_items(): void {
		$progress = [
			'status'      => 'running',
			'mode'        => 'organize_unassigned',
			'dry_run'     => true,
			'total'       => 10,
			'processed'   => 0,
			'media_ids'   => [ 1, 2, 3, 4, 5 ],
			'results'     => [],
			'started_at'  => time(),
		];

		$this->stub_options(
			[
				'vmfa_scan_progress' => $progress,
				'vmfa_settings'      => [ 'batch_size' => 2 ],
			]
		);

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$analysis_service->shouldReceive( 'analyze_media' )
			->times( 2 )
			->andReturn(
				[
					'action'     => 'assign',
					'folder_id'  => 1,
					'confidence' => 0.9,
					'reason'     => 'Test',
				]
			);

		$backup_service = Mockery::mock( BackupService::class );

		Functions\expect( 'update_option' )
			->atLeast()
			->once()
			->andReturn( true );

		// Should schedule next batch.
		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with( 'vmfa_process_media_batch', [], 'vmfa-scanner' )
			->andReturn( 1 );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$service->process_batch();

		// The test passes if we get here without exceptions.
		$this->assertTrue( true );
	}

	/**
	 * Test dry run does not apply assignments.
	 */
	public function test_dry_run_does_not_apply(): void {
		$progress = [
			'status'      => 'running',
			'mode'        => 'organize_unassigned',
			'dry_run'     => true,
			'total'       => 1,
			'processed'   => 0,
			'media_ids'   => [ 1 ],
			'results'     => [],
			'started_at'  => time(),
		];

		$this->stub_options(
			[
				'vmfa_scan_progress' => $progress,
				'vmfa_settings'      => [ 'batch_size' => 10 ],
			]
		);

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$analysis_service->shouldReceive( 'analyze_media' )
			->once()
			->andReturn(
				[
					'action'     => 'assign',
					'folder_id'  => 1,
					'confidence' => 0.9,
					'reason'     => 'Test',
				]
			);

		$backup_service = Mockery::mock( BackupService::class );

		Functions\expect( 'update_option' )
			->atLeast()
			->once()
			->andReturn( true );

		// Should schedule finalize, not apply.
		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with( 'vmfa_finalize_scan', [], 'vmfa-scanner' )
			->andReturn( 1 );

		// wp_set_object_terms should NOT be called for dry run.
		Functions\expect( 'wp_set_object_terms' )
			->never();

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$service->process_batch();

		$this->assertTrue( true );
	}
}
