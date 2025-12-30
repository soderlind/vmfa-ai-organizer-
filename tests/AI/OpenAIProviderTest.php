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
		$this->stub_options( [ 'vmfa_settings' => [] ] );

		$provider = new OpenAIProvider( [ 'openai_key' => 'test-key' ] );

		$this->assertEquals( 'openai', $provider->get_name() );
	}

	/**
	 * Test provider instantiation.
	 */
	public function test_provider_instantiation(): void {
		$this->stub_options( [ 'vmfa_settings' => [] ] );

		$settings = [
			'openai_key'   => 'test-key',
			'openai_model' => 'gpt-4o-mini',
		];

		$provider = new OpenAIProvider( $settings );

		$this->assertInstanceOf( OpenAIProvider::class, $provider );
	}
}
