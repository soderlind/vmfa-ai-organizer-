<?php
/**
 * Tests for BackupService.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\Services;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\Services\BackupService;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Backup Service test class.
 */
class BackupServiceTest extends BrainMonkeyTestCase {

	/**
	 * Test has_backup returns true when backup exists.
	 */
	public function test_has_backup_returns_true_when_exists(): void {
		$this->stub_options(
			[
				'vmfo_reorganize_backup' => [
					'folders'     => [],
					'assignments' => [],
					'timestamp'   => time(),
				],
			]
		);

		$service = new BackupService();
		$result  = $service->has_backup();

		$this->assertTrue( $result );
	}

	/**
	 * Test has_backup returns false when no backup.
	 */
	public function test_has_backup_returns_false_when_empty(): void {
		$this->stub_options( [ 'vmfo_reorganize_backup' => false ] );

		$service = new BackupService();
		$result  = $service->has_backup();

		$this->assertFalse( $result );
	}

	/**
	 * Test get_backup_info returns correct structure.
	 */
	public function test_get_backup_info_returns_structure(): void {
		$timestamp = time() - 3600;

		$this->stub_options(
			[
				'vmfo_reorganize_backup' => [
					'folders'     => [
						[ 'term_id' => 1, 'name' => 'Photos' ],
						[ 'term_id' => 2, 'name' => 'Documents' ],
					],
					'assignments' => [
						1 => [ 10, 11 ],
						2 => [ 20 ],
					],
					'timestamp'   => $timestamp,
				],
			]
		);

		$service = new BackupService();
		$info    = $service->get_backup_info();

		$this->assertTrue( $info['exists'] );
		$this->assertEquals( $timestamp, $info['timestamp'] );
		$this->assertEquals( 2, $info['folder_count'] );
	}

	/**
	 * Test cleanup removes backup option.
	 */
	public function test_cleanup_removes_backup(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( 'vmfo_reorganize_backup' )
			->andReturn( true );

		$service = new BackupService();
		$result  = $service->cleanup();

		$this->assertTrue( $result );
	}

	/**
	 * Test service instantiation.
	 */
	public function test_service_instantiation(): void {
		$service = new BackupService();

		$this->assertInstanceOf( BackupService::class, $service );
	}
}
