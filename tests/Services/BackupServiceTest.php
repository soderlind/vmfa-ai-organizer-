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

/**
 * Backup Service test class.
 */
class BackupServiceTest extends BrainMonkeyTestCase {

	/**
	 * Test export creates backup data.
	 */
	public function test_export_creates_backup(): void {
		// Mock folder terms.
		Functions\expect( 'get_terms' )
			->once()
			->with(
				Mockery::on(
					static fn( $args ) => 'vmfo_folder' === $args['taxonomy']
				)
			)
			->andReturn(
				[
					(object) [
						'term_id'     => 1,
						'name'        => 'Photos',
						'slug'        => 'photos',
						'description' => '',
						'parent'      => 0,
					],
					(object) [
						'term_id'     => 2,
						'name'        => 'Documents',
						'slug'        => 'documents',
						'description' => '',
						'parent'      => 0,
					],
				]
			);

		Functions\when( 'is_wp_error' )->justReturn( false );

		// Mock get_objects_in_term for assignments.
		Functions\when( 'get_objects_in_term' )->alias(
			static function ( $term_id ) {
				$assignments = [
					1 => [ 10, 11, 12 ],
					2 => [ 20, 21 ],
				];
				return $assignments[ $term_id ] ?? [];
			}
		);

		// Expect update_option to be called with backup data.
		Functions\expect( 'update_option' )
			->once()
			->with(
				'vmfo_reorganize_backup',
				Mockery::on(
					static function ( $data ) {
						return isset( $data['folders'] )
							&& isset( $data['assignments'] )
							&& isset( $data['timestamp'] )
							&& is_array( $data['folders'] )
							&& 2 === count( $data['folders'] );
					}
				)
			)
			->andReturn( true );

		$service = new BackupService();
		$result  = $service->export();

		$this->assertTrue( $result );
	}

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
						[ 'term_id' => 1, 'object_ids' => [ 10, 11 ] ],
						[ 'term_id' => 2, 'object_ids' => [ 20 ] ],
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
		$this->assertEquals( 3, $info['assignment_count'] );
	}

	/**
	 * Test restore recreates folders and assignments.
	 */
	public function test_restore_recreates_structure(): void {
		$backup_data = [
			'folders'     => [
				[
					'term_id'     => 1,
					'name'        => 'Photos',
					'slug'        => 'photos',
					'description' => '',
					'parent'      => 0,
				],
			],
			'assignments' => [
				[
					'term_id'    => 1,
					'object_ids' => [ 10, 11, 12 ],
				],
			],
			'timestamp'   => time() - 3600,
		];

		$this->stub_options( [ 'vmfo_reorganize_backup' => $backup_data ] );

		// Mock term creation.
		Functions\expect( 'wp_insert_term' )
			->once()
			->with( 'Photos', 'vmfo_folder', Mockery::any() )
			->andReturn( [ 'term_id' => 100 ] );

		Functions\when( 'is_wp_error' )->justReturn( false );

		// Mock object assignment.
		Functions\expect( 'wp_set_object_terms' )
			->times( 3 )
			->andReturn( [ 100 ] );

		$service = new BackupService();
		$result  = $service->restore();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'folders_restored', $result );
		$this->assertArrayHasKey( 'assignments_restored', $result );
		$this->assertEquals( 1, $result['folders_restored'] );
		$this->assertEquals( 3, $result['assignments_restored'] );
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
}
