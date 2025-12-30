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
use Brain\Monkey\Functions;

/**
 * Provider Factory test class.
 */
class ProviderFactoryTest extends BrainMonkeyTestCase {

	/**
	 * Test get_available_providers returns all provider names.
	 */
	public function test_get_available_providers(): void {
		$factory   = new ProviderFactory();
		$providers = $factory->get_available_providers();

		$this->assertIsArray( $providers );
		$this->assertArrayHasKey( 'openai', $providers );
		$this->assertArrayHasKey( 'anthropic', $providers );
		$this->assertArrayHasKey( 'gemini', $providers );
		$this->assertArrayHasKey( 'ollama', $providers );
		$this->assertArrayHasKey( 'grok', $providers );
		$this->assertArrayHasKey( 'exo', $providers );
		$this->assertArrayHasKey( 'heuristic', $providers );
	}

	/**
	 * Test that factory can be instantiated.
	 */
	public function test_factory_instantiation(): void {
		$factory = new ProviderFactory();

		$this->assertInstanceOf( ProviderFactory::class, $factory );
	}
}
