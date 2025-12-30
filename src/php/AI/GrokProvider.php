<?php
/**
 * Grok (xAI) AI Provider.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Grok-based folder suggestion provider.
 */
class GrokProvider extends AbstractProvider {

	/**
	 * xAI API endpoint.
	 */
	private const API_URL = 'https://api.x.ai/v1/chat/completions';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'grok';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Grok (xAI)', 'vmfa-ai-organizer' );
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
				'reason'          => __( 'Grok API key not configured.', 'vmfa-ai-organizer' ),
			);
		}

		$api_key = $this->get_setting( 'grok_key' );
		$model   = $this->get_setting( 'grok_model' ) ?: 'grok-beta';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders );

		// Note: Grok vision API format is OpenAI-compatible.
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
			self::API_URL,
			array(
				'model'       => $model,
				'messages'    => array(
					array(
						'role'    => 'system',
						'content' => self::SYSTEM_PROMPT,
					),
					array(
						'role'    => 'user',
						'content' => $user_content,
					),
				),
				'max_tokens'  => 500,
				'temperature' => 0.3,
			),
			array(
				'Authorization' => "Bearer {$api_key}",
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
					__( 'Grok API error: %s', 'vmfa-ai-organizer' ),
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
		$api_key = $settings['grok_key'] ?? '';
		$model   = $settings['grok_model'] ?? 'grok-beta';

		if ( empty( $api_key ) ) {
			return __( 'Grok API key is required.', 'vmfa-ai-organizer' );
		}

		$response = $this->make_request(
			self::API_URL,
			array(
				'model'      => $model,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'Say "OK" if you can read this.',
					),
				),
				'max_tokens' => 10,
			),
			array(
				'Authorization' => "Bearer {$api_key}",
			)
		);

		if ( ! $response['success'] ) {
			return $response['error'];
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		$api_key = $this->get_setting( 'grok_key' );
		return ! empty( $api_key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'grok-beta'  => 'Grok Beta',
			'grok-2'     => 'Grok 2',
			'grok-2-mini' => 'Grok 2 Mini',
		);
	}
}
