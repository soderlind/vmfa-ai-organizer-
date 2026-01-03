<?php
/**
 * Tests for AIAnalysisService.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\Tests\Services;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\Services\AIAnalysisService;
use Brain\Monkey\Functions;

/**
 * AI Analysis Service test class.
 */
class AIAnalysisServiceTest extends BrainMonkeyTestCase {

	/**
	 * Test double for AIAnalysisService.
	 */
	private function make_test_service( array $metadata, array $folder_paths ): AIAnalysisService {
		return new class ($metadata, $folder_paths) extends AIAnalysisService {
			/** @var array<string, mixed> */
			private array $test_metadata;
			/** @var array<string, int> */
			private array $test_folder_paths;

			public function __construct( array $metadata, array $folder_paths ) {
				$this->test_metadata     = $metadata;
				$this->test_folder_paths = $folder_paths;
			}

			public function get_media_metadata( int $attachment_id ): array {
				return $this->test_metadata;
			}

			public function get_folder_paths( bool $refresh = false ): array {
				return $this->test_folder_paths;
			}
		};
	}

	/**
	 * Test service instantiation.
	 */
	public function test_service_instantiation(): void {
		$service = new AIAnalysisService();

		$this->assertInstanceOf( AIAnalysisService::class, $service );
	}

	/**
	 * Test get_media_metadata extracts filename.
	 */
	public function test_get_media_metadata_extracts_filename(): void {
		$attachment_id = 123;
		$post          = $this->create_mock_attachment( $attachment_id, 'beach-sunset.jpg' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'wp_get_attachment_metadata' )->justReturn( [] );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/beach-sunset.jpg' );

		$service  = new AIAnalysisService();
		$metadata = $service->get_media_metadata( $attachment_id );

		$this->assertArrayHasKey( 'filename', $metadata );
		$this->assertEquals( 'beach-sunset.jpg', $metadata[ 'filename' ] );
		$this->assertArrayHasKey( 'mime_type', $metadata );
		$this->assertEquals( 'image/jpeg', $metadata[ 'mime_type' ] );
	}

	/**
	 * Test create_folder_from_path strips emojis/emoticons.
	 */
	public function test_create_folder_from_path_strips_emojis(): void {
		Functions\when( 'get_terms' )->justReturn( array() );
		Functions\when( 'is_wp_error' )->alias( static fn( $value ) => false );
		Functions\when( 'update_term_meta' )->justReturn( true );

		$calls = array();
		Functions\when( 'wp_insert_term' )->alias(
			static function ( $name, $taxonomy, $args ) use ( &$calls ) {
				$calls[] = array(
					'name'     => $name,
					'taxonomy' => $taxonomy,
					'args'     => $args,
				);
				return array( 'term_id' => count( $calls ) );
			}
		);

		$service = new AIAnalysisService();

		$term_id = $service->create_folder_from_path( '🌿 Plants/🍂 Leaves' );

		$this->assertSame( 2, $term_id );
		$this->assertCount( 2, $calls );
		$this->assertSame( 'Plants', $calls[ 0 ][ 'name' ] );
		$this->assertSame( 'vmfo_folder', $calls[ 0 ][ 'taxonomy' ] );
		$this->assertSame( array( 'parent' => 0 ), $calls[ 0 ][ 'args' ] );
		$this->assertSame( 'Leaves', $calls[ 1 ][ 'name' ] );
		$this->assertSame( 'vmfo_folder', $calls[ 1 ][ 'taxonomy' ] );
		$this->assertSame( array( 'parent' => 1 ), $calls[ 1 ][ 'args' ] );
	}

	/**
	 * Test that PDF files are always routed to Documents before any AI logic.
	 */
	public function test_analyze_media_routes_pdf_to_documents(): void {
		$this->stub_options( array( 'vmfa_scan_progress' => array( 'mode' => 'organize_unassigned' ) ) );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/report.pdf' );

		$service = $this->make_test_service(
			array(
				'mime_type' => 'application/pdf',
				'filename'  => 'report.pdf',
			),
			array()
		);

		$result = $service->analyze_media( 123 );

		$this->assertSame( 'create', $result[ 'action' ] );
		$this->assertSame( 'Documents', $result[ 'new_folder_path' ] );
		$this->assertSame( 'Documents', $result[ 'folder_name' ] );
		$this->assertSame( 'report.pdf', $result[ 'filename' ] );
	}

	/**
	 * Test that reorganize_all preview (simulated empty folders) forces video routing to create Videos,
	 * even if a Videos folder exists in the database.
	 */
	public function test_analyze_media_reorganize_all_preview_creates_videos_folder(): void {
		$this->stub_options( array( 'vmfa_scan_progress' => array( 'mode' => 'reorganize_all' ) ) );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/clip.mp4' );

		$service = $this->make_test_service(
			array(
				'mime_type' => 'video/mp4',
				'filename'  => 'clip.mp4',
			),
			array(
				'Videos' => 55,
			)
		);

		$result = $service->analyze_media( 124 );

		$this->assertSame( 'create', $result[ 'action' ] );
		$this->assertSame( 'Videos', $result[ 'new_folder_path' ] );
		$this->assertSame( 'Videos', $result[ 'folder_name' ] );
		$this->assertSame( 'clip.mp4', $result[ 'filename' ] );
	}
}
