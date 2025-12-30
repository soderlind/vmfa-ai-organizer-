<?php
/**
 * AI Provider Factory.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

use VmfaAiOrganizer\Plugin;

/**
 * Factory for creating AI provider instances.
 */
class ProviderFactory {

	/**
	 * Available providers.
	 *
	 * @var array<string, class-string<ProviderInterface>>
	 */
	private static array $providers = array(
		'heuristic' => HeuristicProvider::class,
		'openai'    => OpenAIProvider::class,
		'anthropic' => AnthropicProvider::class,
		'gemini'    => GeminiProvider::class,
		'ollama'    => OllamaProvider::class,
		'grok'      => GrokProvider::class,
		'exo'       => ExoProvider::class,
	);

	/**
	 * Get the currently configured provider.
	 *
	 * @return ProviderInterface
	 */
	public static function get_current_provider(): ProviderInterface {
		$provider_name = Plugin::get_instance()->get_setting( 'ai_provider', 'heuristic' );
		return self::get_provider( $provider_name );
	}

	/**
	 * Get a provider by name.
	 *
	 * @param string $name Provider name.
	 * @return ProviderInterface
	 */
	public static function get_provider( string $name ): ProviderInterface {
		$class = self::$providers[ $name ] ?? self::$providers['heuristic'];
		return new $class();
	}

	/**
	 * Get all available providers.
	 *
	 * @return array<string, string> Provider name => Display label.
	 */
	public static function get_available_providers(): array {
		$providers = array();

		foreach ( self::$providers as $name => $class ) {
			$instance            = new $class();
			$providers[ $name ] = $instance->get_label();
		}

		return $providers;
	}

	/**
	 * Check if a provider exists.
	 *
	 * @param string $name Provider name.
	 * @return bool
	 */
	public static function provider_exists( string $name ): bool {
		return isset( self::$providers[ $name ] );
	}

	/**
	 * Register a custom provider.
	 *
	 * @param string                         $name  Provider name.
	 * @param class-string<ProviderInterface> $class Provider class.
	 * @return void
	 */
	public static function register_provider( string $name, string $class ): void {
		if ( is_subclass_of( $class, ProviderInterface::class ) ) {
			self::$providers[ $name ] = $class;
		}
	}
}
