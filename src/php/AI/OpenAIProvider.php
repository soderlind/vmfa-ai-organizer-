<?php
/**
 * OpenAI AI Provider.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * OpenAI-based folder suggestion provider.
 */
class OpenAIProvider extends AbstractProvider {

	/**
	 * OpenAI API endpoint.
	 */
	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Get the API URL based on type (OpenAI or Azure).
	 *
	 * @param string $model Model/deployment name.
	 * @return string API URL.
	 */
	private function get_api_url( string $model ): string {
		$type = $this->get_setting( 'openai_type' ) ?: 'openai';

		if ( 'azure' === $type ) {
			$endpoint    = $this->get_setting( 'azure_endpoint' );
			$api_version = $this->get_setting( 'azure_api_version' ) ?: '2024-02-15-preview';

			return rtrim( $endpoint, '/' ) . '/openai/deployments/' . $model . '/chat/completions?api-version=' . $api_version;
		}

		return self::API_URL;
	}

	/**
	 * Get headers based on type (OpenAI or Azure).
	 *
	 * @param string $api_key API key.
	 * @return array<string, string> Headers.
	 */
	private function get_headers( string $api_key ): array {
		$type = $this->get_setting( 'openai_type' ) ?: 'openai';

		if ( 'azure' === $type ) {
			return array(
				'api-key' => $api_key,
			);
		}

		return array(
			'Authorization' => "Bearer {$api_key}",
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'OpenAI', 'vmfa-ai-organizer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders,
		?array $image_data = null,
		array $suggested_folders = array()
	): array {
		if ( ! $this->is_configured() ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'OpenAI API key not configured.', 'vmfa-ai-organizer' ),
			);
		}

		$api_key = $this->get_setting( 'openai_key' );
		$model   = $this->get_setting( 'openai_model' ) ?: 'gpt-4o-mini';
		$type    = $this->get_setting( 'openai_type' ) ?: 'openai';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders, $suggested_folders );

		// Build user message content - with or without image.
		$user_content = $this->build_openai_compatible_user_content( $user_prompt, $image_data, 'low' );

		// Build request body - Azure doesn't need model in body.
		$body = $this->build_openai_compatible_chat_body( 'openai' === $type ? $model : null, $user_content, 500, 0.3 );

		$response = $this->make_request(
			$this->get_api_url( $model ),
			$body,
			$this->get_headers( $api_key )
		);

		if ( ! $response[ 'success' ] ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => sprintf(
					/* translators: %s: error message */
					__( 'OpenAI API error: %s', 'vmfa-ai-organizer' ),
					$response[ 'error' ]
				),
			);
		}

		$content = $response[ 'data' ][ 'choices' ][ 0 ][ 'message' ][ 'content' ] ?? '';

		return $this->parse_response( $content, $folder_paths );
	}

	// build_user_content() removed: now uses AbstractProvider::build_openai_compatible_user_content().

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		$api_key = $settings[ 'openai_key' ] ?? '';
		$model   = $settings[ 'openai_model' ] ?? 'gpt-4o-mini';
		$type    = $settings[ 'openai_type' ] ?? 'openai';

		if ( empty( $api_key ) ) {
			return __( 'API key is required.', 'vmfa-ai-organizer' );
		}

		if ( 'azure' === $type ) {
			$endpoint = $settings[ 'azure_endpoint' ] ?? '';
			if ( empty( $endpoint ) ) {
				return __( 'Azure endpoint is required.', 'vmfa-ai-organizer' );
			}
			if ( empty( $model ) ) {
				return __( 'Azure deployment name is required.', 'vmfa-ai-organizer' );
			}

			$api_version = $settings[ 'azure_api_version' ] ?? '2024-02-15-preview';
			$url         = rtrim( $endpoint, '/' ) . '/openai/deployments/' . $model . '/chat/completions?api-version=' . $api_version;
			$headers     = array( 'api-key' => $api_key );
		} else {
			$url     = self::API_URL;
			$headers = array( 'Authorization' => "Bearer {$api_key}" );
		}

		$body = array(
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'Say "OK" if you can read this.',
				),
			),
			'max_tokens' => 10,
		);

		if ( 'openai' === $type ) {
			$body[ 'model' ] = $model;
		}

		$response = $this->make_request( $url, $body, $headers );

		if ( ! $response[ 'success' ] ) {
			return $response[ 'error' ];
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$api_key = $this->get_setting( 'openai_key' );
		$type    = $this->get_setting( 'openai_type' ) ?: 'openai';

		if ( empty( $api_key ) ) {
			return false;
		}

		if ( 'azure' === $type ) {
			$endpoint = $this->get_setting( 'azure_endpoint' );
			return ! empty( $endpoint );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'gpt-4o-mini'   => 'GPT-4o Mini (Fast, Affordable)',
			'gpt-4o'        => 'GPT-4o (Balanced)',
			'gpt-4-turbo'   => 'GPT-4 Turbo (Powerful)',
			'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Budget)',
		);
	}
}
