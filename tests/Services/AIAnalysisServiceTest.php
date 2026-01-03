<?php
/**
 * Tests for AIAnalysisService.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\Services;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\Services\AIAnalysisService;
use VmfaAiOrganizer\AI\ProviderFactory;
use Brain\Monkey\Functions;
use Mockery;

/**
 * AI Analysis Service test class.
 */
class AIAnalysisServiceTest extends BrainMonkeyTestCase {

	/**
	 * Test service instantiation.
	 */
	public function test_service_instantiation(): void {
		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options( [ 'vmfa_settings' => [] ] );

		$service = new AIAnalysisService( $factory );

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

		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options( [ 'vmfa_settings' => [] ] );

		$service  = new AIAnalysisService( $factory );
		$metadata = $service->get_media_metadata( $attachment_id );

		$this->assertArrayHasKey( 'filename', $metadata );
		$this->assertEquals( 'beach-sunset.jpg', $metadata['filename'] );
		$this->assertArrayHasKey( 'mime_type', $metadata );
		$this->assertEquals( 'image/jpeg', $metadata['mime_type'] );
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

		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options( array( 'vmfa_settings' => array() ) );
		$service = new AIAnalysisService( $factory );

		$term_id = $service->create_folder_from_path( '🌿 Plants/🍂 Leaves' );

		$this->assertSame( 2, $term_id );
		$this->assertCount( 2, $calls );
		$this->assertSame( 'Plants', $calls[0]['name'] );
		$this->assertSame( 'vmfo_folder', $calls[0]['taxonomy'] );
		$this->assertSame( array( 'parent' => 0 ), $calls[0]['args'] );
		$this->assertSame( 'Leaves', $calls[1]['name'] );
		$this->assertSame( 'vmfo_folder', $calls[1]['taxonomy'] );
		$this->assertSame( array( 'parent' => 1 ), $calls[1]['args'] );
	}
}
