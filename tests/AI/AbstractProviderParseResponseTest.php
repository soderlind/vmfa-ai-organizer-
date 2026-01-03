<?php
/**
 * Tests for AbstractProvider::parse_response.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\AI;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\AI\AbstractProvider;

/**
 * @covers \VmfaAiOrganizer\AI\AbstractProvider
 */
class AbstractProviderParseResponseTest extends BrainMonkeyTestCase {

	/**
	 * Test new-schema existing action falls back to folder_path.
	 */
	public function test_new_schema_existing_falls_back_to_folder_path(): void {
		$provider = new class() extends AbstractProvider {
			public function get_name(): string { return 'test'; }
			public function get_label(): string { return 'Test'; }
			public function analyze( array $media_metadata, array $folder_paths, int $max_depth, bool $allow_new_folders, ?array $image_data = null, array $suggested_folders = array() ): array { return array(); }
			public function test( array $settings ): ?string { return null; }
			public function is_configured(): bool { return true; }
			public function get_available_models(): array { return array(); }
			public function parse_for_test( string $response, array $folder_paths ): array { return $this->parse_response( $response, $folder_paths ); }
		};

		$folder_paths = array(
			'Vacation 2025' => 14,
		);

		$response = wp_json_encode(
			array(
				'action'       => 'existing',
				'folder_id'    => 13,
				'folder_path'  => 'Vacation 2025',
				'confidence'   => 1.0,
				'reason'       => 'Matches vacation photos.',
				'new_folder_path' => null,
			)
		);

		$result = $provider->parse_for_test( (string) $response, $folder_paths );

		$this->assertSame( 'assign', $result['action'] );
		$this->assertSame( 14, $result['folder_id'] );
	}

	/**
	 * Test folder_path suffix "(ID: N)" is stripped.
	 */
	public function test_new_schema_existing_strips_id_suffix_in_folder_path(): void {
		$provider = new class() extends AbstractProvider {
			public function get_name(): string { return 'test'; }
			public function get_label(): string { return 'Test'; }
			public function analyze( array $media_metadata, array $folder_paths, int $max_depth, bool $allow_new_folders, ?array $image_data = null, array $suggested_folders = array() ): array { return array(); }
			public function test( array $settings ): ?string { return null; }
			public function is_configured(): bool { return true; }
			public function get_available_models(): array { return array(); }
			public function parse_for_test( string $response, array $folder_paths ): array { return $this->parse_response( $response, $folder_paths ); }
		};

		$folder_paths = array(
			'Vacation 2025' => 14,
		);

		$response = wp_json_encode(
			array(
				'action'       => 'existing',
				'folder_id'    => 13,
				'folder_path'  => 'Vacation 2025 (ID: 14)',
				'confidence'   => 0.9,
				'reason'       => 'Matches vacation photos.',
				'new_folder_path' => null,
			)
		);

		$result = $provider->parse_for_test( (string) $response, $folder_paths );

		$this->assertSame( 'assign', $result['action'] );
		$this->assertSame( 14, $result['folder_id'] );
	}
}
