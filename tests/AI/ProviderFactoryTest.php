<?php
/**
 * Tests for ProviderFactory.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests\AI;

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;
use VmfaAiOrganizer\AI\ProviderFactory;
use VmfaAiOrganizer\AI\ProviderInterface;
use VmfaAiOrganizer\AI\OpenAIProvider;
use VmfaAiOrganizer\AI\AnthropicProvider;
use VmfaAiOrganizer\AI\GeminiProvider;
use VmfaAiOrganizer\AI\OllamaProvider;
use VmfaAiOrganizer\AI\GrokProvider;
use VmfaAiOrganizer\AI\ExoProvider;
use VmfaAiOrganizer\AI\HeuristicProvider;

/**
 * Provider Factory test class.
 */
class ProviderFactoryTest extends BrainMonkeyTestCase {

	/**
	 * Test that create returns OpenAI provider.
	 */
	public function test_create_returns_openai_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'openai_key' => 'test-key' ];

		$provider = $factory->create( 'openai', $settings );

		$this->assertInstanceOf( ProviderInterface::class, $provider );
		$this->assertInstanceOf( OpenAIProvider::class, $provider );
	}

	/**
	 * Test that create returns Anthropic provider.
	 */
	public function test_create_returns_anthropic_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'anthropic_key' => 'test-key' ];

		$provider = $factory->create( 'anthropic', $settings );

		$this->assertInstanceOf( AnthropicProvider::class, $provider );
	}

	/**
	 * Test that create returns Gemini provider.
	 */
	public function test_create_returns_gemini_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'gemini_key' => 'test-key' ];

		$provider = $factory->create( 'gemini', $settings );

		$this->assertInstanceOf( GeminiProvider::class, $provider );
	}

	/**
	 * Test that create returns Ollama provider.
	 */
	public function test_create_returns_ollama_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'ollama_host' => 'http://localhost:11434' ];

		$provider = $factory->create( 'ollama', $settings );

		$this->assertInstanceOf( OllamaProvider::class, $provider );
	}

	/**
	 * Test that create returns Grok provider.
	 */
	public function test_create_returns_grok_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'grok_key' => 'test-key' ];

		$provider = $factory->create( 'grok', $settings );

		$this->assertInstanceOf( GrokProvider::class, $provider );
	}

	/**
	 * Test that create returns Exo provider.
	 */
	public function test_create_returns_exo_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [ 'exo_host' => 'http://localhost:52415' ];

		$provider = $factory->create( 'exo', $settings );

		$this->assertInstanceOf( ExoProvider::class, $provider );
	}

	/**
	 * Test that create returns Heuristic provider as fallback.
	 */
	public function test_create_returns_heuristic_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [];

		$provider = $factory->create( 'heuristic', $settings );

		$this->assertInstanceOf( HeuristicProvider::class, $provider );
	}

	/**
	 * Test that unknown provider name returns heuristic fallback.
	 */
	public function test_create_returns_heuristic_for_unknown_provider(): void {
		$factory  = new ProviderFactory();
		$settings = [];

		$provider = $factory->create( 'unknown_provider', $settings );

		$this->assertInstanceOf( HeuristicProvider::class, $provider );
	}

	/**
	 * Test get_available_providers returns all provider names.
	 */
	public function test_get_available_providers(): void {
		$factory   = new ProviderFactory();
		$providers = $factory->get_available_providers();

		$expected = [ 'openai', 'anthropic', 'gemini', 'ollama', 'grok', 'exo', 'heuristic' ];

		$this->assertEquals( $expected, $providers );
	}
}
