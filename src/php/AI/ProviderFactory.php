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
	 * @return ProviderInterface|null Null if no provider configured.
	 */
	public static function get_current_provider(): ?ProviderInterface {
		// Check for CLI override first.
		$provider_name = null;
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( \VmfaAiOrganizer\CLI\Commands::class) ) {
			$provider_name = \VmfaAiOrganizer\CLI\Commands::get_override( 'ai_provider' );
		}

		if ( empty( $provider_name ) ) {
			$provider_name = Plugin::get_instance()->get_setting( 'ai_provider', '' );
		}

		if ( empty( $provider_name ) || ! isset( self::$providers[ $provider_name ] ) ) {
			return null;
		}
		return self::get_provider( $provider_name );
	}

	/**
	 * Get a provider by name.
	 *
	 * @param string $name Provider name.
	 * @return ProviderInterface|null
	 */
	public static function get_provider( string $name ): ?ProviderInterface {
		if ( ! isset( self::$providers[ $name ] ) ) {
			return null;
		}
		$class = self::$providers[ $name ];
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
			$instance           = new $class();
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
		if ( is_subclass_of( $class, ProviderInterface::class) ) {
			self::$providers[ $name ] = $class;
		}
	}
}
