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
	 * Test get_media_metadata extracts all fields.
	 */
	public function test_get_media_metadata_extracts_all_fields(): void {
		$attachment_id = 123;
		$post          = $this->create_mock_attachment( $attachment_id, 'beach-sunset.jpg' );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key, $single ) {
				$meta = [
					'_wp_attachment_image_alt'      => 'Sunset at the beach',
					'_wp_attachment_metadata'       => [
						'width'      => 1920,
						'height'     => 1080,
						'image_meta' => [
							'aperture' => '2.8',
							'camera'   => 'Canon EOS R5',
						],
					],
					'_wp_attached_file'             => '2024/01/beach-sunset.jpg',
				];
				return $meta[ $key ] ?? '';
			}
		);

		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options( [ 'vmfa_settings' => [] ] );

		$service  = new AIAnalysisService( $factory );
		$metadata = $service->get_media_metadata( $attachment_id );

		$this->assertArrayHasKey( 'filename', $metadata );
		$this->assertEquals( 'beach-sunset.jpg', $metadata['filename'] );
		$this->assertArrayHasKey( 'alt_text', $metadata );
		$this->assertEquals( 'Sunset at the beach', $metadata['alt_text'] );
		$this->assertArrayHasKey( 'mime_type', $metadata );
		$this->assertEquals( 'image/jpeg', $metadata['mime_type'] );
	}

	/**
	 * Test build_folder_context returns path hierarchy.
	 */
	public function test_build_folder_context_returns_paths(): void {
		// Mock get_terms to return folder hierarchy.
		Functions\expect( 'get_terms' )
			->once()
			->andReturn(
				[
					(object) [
						'term_id' => 1,
						'name'    => 'Photos',
						'parent'  => 0,
					],
					(object) [
						'term_id' => 2,
						'name'    => 'Vacation',
						'parent'  => 1,
					],
					(object) [
						'term_id' => 3,
						'name'    => 'Documents',
						'parent'  => 0,
					],
				]
			);

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_term' )->alias(
			static function ( $id ) {
				$terms = [
					1 => (object) [
						'term_id' => 1,
						'name'    => 'Photos',
						'parent'  => 0,
					],
					2 => (object) [
						'term_id' => 2,
						'name'    => 'Vacation',
						'parent'  => 1,
					],
					3 => (object) [
						'term_id' => 3,
						'name'    => 'Documents',
						'parent'  => 0,
					],
				];
				return $terms[ $id ] ?? null;
			}
		);

		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options(
			[
				'vmfa_settings' => [ 'max_folder_depth' => 3 ],
			]
		);

		$service = new AIAnalysisService( $factory );
		$context = $service->build_folder_context();

		$this->assertArrayHasKey( 1, $context );
		$this->assertEquals( 'Photos', $context[1] );
		$this->assertArrayHasKey( 2, $context );
		$this->assertEquals( 'Photos/Vacation', $context[2] );
		$this->assertArrayHasKey( 3, $context );
		$this->assertEquals( 'Documents', $context[3] );
	}

	/**
	 * Test max_folder_depth is respected.
	 */
	public function test_folder_depth_limit_is_respected(): void {
		// Create a deep hierarchy.
		Functions\expect( 'get_terms' )
			->once()
			->andReturn(
				[
					(object) [
						'term_id' => 1,
						'name'    => 'Level1',
						'parent'  => 0,
					],
					(object) [
						'term_id' => 2,
						'name'    => 'Level2',
						'parent'  => 1,
					],
					(object) [
						'term_id' => 3,
						'name'    => 'Level3',
						'parent'  => 2,
					],
					(object) [
						'term_id' => 4,
						'name'    => 'Level4',
						'parent'  => 3,
					],
				]
			);

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_term' )->alias(
			static function ( $id ) {
				$terms = [
					1 => (object) [
						'term_id' => 1,
						'name'    => 'Level1',
						'parent'  => 0,
					],
					2 => (object) [
						'term_id' => 2,
						'name'    => 'Level2',
						'parent'  => 1,
					],
					3 => (object) [
						'term_id' => 3,
						'name'    => 'Level3',
						'parent'  => 2,
					],
					4 => (object) [
						'term_id' => 4,
						'name'    => 'Level4',
						'parent'  => 3,
					],
				];
				return $terms[ $id ] ?? null;
			}
		);

		$factory = Mockery::mock( ProviderFactory::class );
		$this->stub_options(
			[
				'vmfa_settings' => [ 'max_folder_depth' => 2 ],
			]
		);

		$service = new AIAnalysisService( $factory );
		$context = $service->build_folder_context();

		// With max depth of 2, we should only have Level1 and Level1/Level2.
		$this->assertCount( 2, $context );
		$this->assertArrayHasKey( 1, $context );
		$this->assertArrayHasKey( 2, $context );
		$this->assertArrayNotHasKey( 3, $context );
		$this->assertArrayNotHasKey( 4, $context );
	}

	/**
	 * Test analyze_media returns result from provider.
	 */
	public function test_analyze_media_uses_provider(): void {
		$attachment_id = 123;
		$post          = $this->create_mock_attachment( $attachment_id );

		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_terms' )->justReturn( [] );
		Functions\when( 'is_wp_error' )->justReturn( false );

		// Mock provider.
		$provider = Mockery::mock( 'VmfaAiOrganizer\AI\ProviderInterface' );
		$provider->shouldReceive( 'analyze' )
			->once()
			->andReturn(
				[
					'action'     => 'skip',
					'reason'     => 'No suitable folder found',
					'confidence' => 0.0,
				]
			);

		$factory = Mockery::mock( ProviderFactory::class );
		$factory->shouldReceive( 'create' )
			->once()
			->andReturn( $provider );

		$this->stub_options(
			[
				'vmfa_settings' => [
					'ai_provider'      => 'openai',
					'max_folder_depth' => 3,
				],
			]
		);

		$service = new AIAnalysisService( $factory );
		$result  = $service->analyze_media( $attachment_id );

		$this->assertEquals( 'skip', $result['action'] );
	}
}
