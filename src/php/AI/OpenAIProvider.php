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
		bool $allow_new_folders
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

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders );

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
						'content' => $user_prompt,
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
					__( 'OpenAI API error: %s', 'vmfa-ai-organizer' ),
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
		$api_key = $settings['openai_key'] ?? '';
		$model   = $settings['openai_model'] ?? 'gpt-4o-mini';

		if ( empty( $api_key ) ) {
			return __( 'OpenAI API key is required.', 'vmfa-ai-organizer' );
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
		$api_key = $this->get_setting( 'openai_key' );
		return ! empty( $api_key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'gpt-4o-mini'    => 'GPT-4o Mini (Fast, Affordable)',
			'gpt-4o'         => 'GPT-4o (Balanced)',
			'gpt-4-turbo'    => 'GPT-4 Turbo (Powerful)',
			'gpt-3.5-turbo'  => 'GPT-3.5 Turbo (Budget)',
		);
	}
}
