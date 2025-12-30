<?php
/**
 * Anthropic AI Provider.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Anthropic Claude-based folder suggestion provider.
 */
class AnthropicProvider extends AbstractProvider {

	/**
	 * Anthropic API endpoint.
	 */
	private const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version.
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'anthropic';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Anthropic Claude', 'vmfa-ai-organizer' );
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
				'reason'          => __( 'Anthropic API key not configured.', 'vmfa-ai-organizer' ),
			);
		}

		$api_key = $this->get_setting( 'anthropic_key' );
		$model   = $this->get_setting( 'anthropic_model' ) ?: 'claude-3-haiku-20240307';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders );

		// Build user content - with or without image.
		$user_content = $this->build_user_content( $user_prompt, $image_data );

		$response = $this->make_request(
			self::API_URL,
			array(
				'model'      => $model,
				'max_tokens' => 500,
				'system'     => self::SYSTEM_PROMPT,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $user_content,
					),
				),
			),
			array(
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
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
					__( 'Anthropic API error: %s', 'vmfa-ai-organizer' ),
					$response['error']
				),
			);
		}

		$content = $response['data']['content'][0]['text'] ?? '';

		return $this->parse_response( $content, $folder_paths );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		$api_key = $settings['anthropic_key'] ?? '';
		$model   = $settings['anthropic_model'] ?? 'claude-3-haiku-20240307';

		if ( empty( $api_key ) ) {
			return __( 'Anthropic API key is required.', 'vmfa-ai-organizer' );
		}

		$response = $this->make_request(
			self::API_URL,
			array(
				'model'      => $model,
				'max_tokens' => 10,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'Say "OK" if you can read this.',
					),
				),
			),
			array(
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
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
		$api_key = $this->get_setting( 'anthropic_key' );
		return ! empty( $api_key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'claude-3-haiku-20240307'  => 'Claude 3 Haiku (Fast, Affordable)',
			'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Balanced)',
			'claude-3-opus-20240229'   => 'Claude 3 Opus (Powerful)',
			'claude-3-5-sonnet-latest' => 'Claude 3.5 Sonnet (Latest)',
		);
	}

	/**
	 * Build user content for the API request.
	 *
	 * For vision-capable models (Claude 3), this creates content with text and image.
	 * For text-only requests, returns just the text string.
	 *
	 * @param string                   $text_prompt The text prompt.
	 * @param array<string, mixed>|null $image_data  Image data (base64, mime_type).
	 * @return string|array<int, array<string, mixed>> Content for the message.
	 */
	private function build_user_content( string $text_prompt, ?array $image_data ): string|array {
		// If no image data, return plain text.
		if ( null === $image_data || empty( $image_data['base64'] ) ) {
			return $text_prompt;
		}

		// Build Anthropic vision content format.
		return array(
			array(
				'type' => 'text',
				'text' => $text_prompt,
			),
			array(
				'type'   => 'image',
				'source' => array(
					'type'         => 'base64',
					'media_type'   => $image_data['mime_type'],
					'data'         => $image_data['base64'],
				),
			),
		);
	}
}
