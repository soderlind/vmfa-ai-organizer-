<?php
/**
 * Tests for OpenAIProvider.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\AI;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\AI\OpenAIProvider;
use Brain\Monkey\Functions;

/**
 * OpenAI Provider test class.
 */
class OpenAIProviderTest extends BrainMonkeyTestCase {

	/**
	 * Test provider name.
	 */
	public function test_get_name(): void {
		$provider = new OpenAIProvider( [ 'openai_key' => 'test-key' ] );

		$this->assertEquals( 'OpenAI', $provider->get_name() );
	}

	/**
	 * Test successful analysis.
	 */
	public function test_analyze_returns_folder_assignment(): void {
		$settings = [
			'openai_key'   => 'test-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		$media_metadata = [
			'filename'    => 'sunset-beach-photo.jpg',
			'alt_text'    => 'Beautiful sunset at the beach',
			'caption'     => 'Vacation photo from Hawaii',
			'description' => 'A stunning sunset captured during our trip.',
			'mime_type'   => 'image/jpeg',
		];

		$folder_paths = [
			1 => 'Photos/Vacation',
			2 => 'Photos/Nature',
			3 => 'Documents',
		];

		// Mock wp_remote_post to return a successful response.
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'choices' => [
								[
									'message' => [
										'content' => wp_json_encode(
											[
												'action'     => 'assign',
												'folder_id'  => 1,
												'confidence' => 0.92,
												'reason'     => 'Image appears to be a vacation photo based on filename and caption.',
											]
										),
									],
								],
							],
						]
					),
				]
			);

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => $response['body']
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$result = $provider->analyze( $media_metadata, $folder_paths, 3, false );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'action', $result );
		$this->assertEquals( 'assign', $result['action'] );
		$this->assertEquals( 1, $result['folder_id'] );
		$this->assertGreaterThan( 0.9, $result['confidence'] );
	}

	/**
	 * Test analyze with allow new folders.
	 */
	public function test_analyze_can_suggest_new_folder(): void {
		$settings = [
			'openai_key'   => 'test-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		$media_metadata = [
			'filename'  => 'contract-2024.pdf',
			'mime_type' => 'application/pdf',
		];

		$folder_paths = [
			1 => 'Photos/Vacation',
			2 => 'Photos/Nature',
		];

		// Mock wp_remote_post to suggest a new folder.
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
						[
							'choices' => [
								[
									'message' => [
										'content' => wp_json_encode(
											[
												'action'          => 'create',
												'new_folder_path' => 'Documents/Contracts',
												'confidence'      => 0.85,
												'reason'          => 'PDF document appears to be a contract, no suitable folder exists.',
											]
										),
									],
								],
							],
						]
					),
				]
			);

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => $response['body']
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$result = $provider->analyze( $media_metadata, $folder_paths, 3, true );

		$this->assertEquals( 'create', $result['action'] );
		$this->assertEquals( 'Documents/Contracts', $result['new_folder_path'] );
	}

	/**
	 * Test API error handling.
	 */
	public function test_analyze_handles_api_error(): void {
		$settings = [
			'openai_key'   => 'test-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		// Mock wp_remote_post to return an error.
		$wp_error = \Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Connection failed' );

		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn( $wp_error );

		Functions\when( 'is_wp_error' )->justReturn( true );

		$result = $provider->analyze( [], [], 3, false );

		$this->assertEquals( 'skip', $result['action'] );
		$this->assertStringContainsString( 'error', strtolower( $result['reason'] ) );
	}

	/**
	 * Test connection test with valid key.
	 */
	public function test_test_returns_null_on_success(): void {
		$settings = [
			'openai_key'   => 'test-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 200 ],
					'body'     => '{"data":[]}',
				]
			);

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$result = $provider->test( $settings );

		$this->assertNull( $result );
	}

	/**
	 * Test connection test with invalid key.
	 */
	public function test_test_returns_error_on_invalid_key(): void {
		$settings = [
			'openai_key'   => 'invalid-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				[
					'response' => [ 'code' => 401 ],
					'body'     => '{"error":{"message":"Invalid API key"}}',
				]
			);

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 401 );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$result = $provider->test( $settings );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}
}
