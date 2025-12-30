<?php
/**
 * Exo AI Provider (distributed local LLM).
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Exo-based folder suggestion provider for distributed local LLM inference.
 */
class ExoProvider extends AbstractProvider {

	/**
	 * Default Exo API URL.
	 */
	private const DEFAULT_URL = 'http://localhost:52415';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'exo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Exo (Distributed Local)', 'vmfa-ai-organizer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders,
		?array $image_data = null
	): array {
		if ( ! $this->is_configured() ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'Exo is not configured or not running.', 'vmfa-ai-organizer' ),
			);
		}

		$base_url = $this->get_setting( 'exo_url' ) ?: self::DEFAULT_URL;
		$model    = $this->get_setting( 'exo_model' ) ?: 'llama-3.2-3b';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders );

		// Exo uses OpenAI-compatible API.
		// Note: Vision support depends on the model being used.
		$user_content = $user_prompt;
		if ( null !== $image_data && ! empty( $image_data['base64'] ) ) {
			$user_content = array(
				array(
					'type' => 'text',
					'text' => $user_prompt,
				),
				array(
					'type'      => 'image_url',
					'image_url' => array(
						'url' => 'data:' . $image_data['mime_type'] . ';base64,' . $image_data['base64'],
					),
				),
			);
		}

		$response = $this->make_request(
			rtrim( $base_url, '/' ) . '/v1/chat/completions',
			array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => $this->get_system_prompt(),
					),
					array(
						'role'    => 'user',
						'content' => $user_content,
					),
				),
				'max_tokens'  => 500,
				'temperature' => 0.3,
			)
		);

		if ( ! $response['success'] ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => sprintf(
					/* translators: %s: error message */
					__( 'Exo error: %s', 'vmfa-ai-organizer' ),
					$response['error']
				),
			);
		}

		$content = $response['data']['choices'][0]['message']['content'] ?? '';

		return $this->parse_response( $content, $folder_paths );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		$base_url = $settings['exo_url'] ?? self::DEFAULT_URL;

		// Check if Exo is running.
		$response = wp_remote_get(
			rtrim( $base_url, '/' ) . '/v1/models',
			array( 'timeout' => 5 )
		);

		if ( is_wp_error( $response ) ) {
			return sprintf(
				/* translators: %s: Exo URL */
				__( 'Cannot connect to Exo at %s. Is it running?', 'vmfa-ai-organizer' ),
				$base_url
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return __( 'Exo is not responding correctly.', 'vmfa-ai-organizer' );
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$base_url = $this->get_setting( 'exo_url' ) ?: self::DEFAULT_URL;

		// Quick check if Exo is running.
		$response = wp_remote_get(
			rtrim( $base_url, '/' ) . '/v1/models',
			array( 'timeout' => 2 )
		);

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'llama-3.2-3b'  => 'Llama 3.2 (3B)',
			'llama-3.2-1b'  => 'Llama 3.2 (1B)',
			'llama-3.1-8b'  => 'Llama 3.1 (8B)',
			'mistral-7b'    => 'Mistral (7B)',
			'deepseek-r1'   => 'DeepSeek R1',
		);
	}
}
