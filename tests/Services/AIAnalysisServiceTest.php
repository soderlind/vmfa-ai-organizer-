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
}
