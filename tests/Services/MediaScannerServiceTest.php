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
	 * Test service instantiation.
	 */
	public function test_service_instantiation(): void {
		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$backup_service   = Mockery::mock( BackupService::class );

		$service = new MediaScannerService( $analysis_service, $backup_service );

		$this->assertInstanceOf( MediaScannerService::class, $service );
	}

	/**
	 * Test start_scan prevents concurrent scans.
	 */
	public function test_start_scan_prevents_concurrent(): void {
		$this->stub_options(
			[
				'vmfa_scan_progress' => [ 'status' => 'running' ],
				'vmfa_settings'      => [],
			]
		);

		$analysis_service = Mockery::mock( AIAnalysisService::class );
		$backup_service   = Mockery::mock( BackupService::class );

		$service = new MediaScannerService( $analysis_service, $backup_service );
		$result  = $service->start_scan( 'organize_unassigned', false );

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'message', $result );
	}
}
