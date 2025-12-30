<?php
/**
 * Google Gemini AI Provider.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Google Gemini-based folder suggestion provider.
 */
class GeminiProvider extends AbstractProvider {

	/**
	 * Gemini API base URL.
	 */
	private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Google Gemini', 'vmfa-ai-organizer' );
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
				'reason'          => __( 'Gemini API key not configured.', 'vmfa-ai-organizer' ),
			);
		}

		$api_key = $this->get_setting( 'gemini_key' );
		$model   = $this->get_setting( 'gemini_model' ) ?: 'gemini-1.5-flash';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders, $suggested_folders );
		$full_prompt = $this->get_system_prompt() . "\n\n" . $user_prompt;

		$url = sprintf( '%s/%s:generateContent?key=%s', self::API_BASE_URL, $model, $api_key );

		// Build parts array - with or without image.
		$parts = $this->build_gemini_parts( $full_prompt, $image_data );

		$response = $this->make_request(
			$url,
			array(
				'contents'         => array(
					array(
						'parts' => $parts,
					),
				),
				'generationConfig' => array(
					'maxOutputTokens' => 500,
					'temperature'     => 0.3,
				),
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
					__( 'Gemini API error: %s', 'vmfa-ai-organizer' ),
					$response['error']
				),
			);
		}

		$content = $response['data']['candidates'][0]['content']['parts'][0]['text'] ?? '';

		return $this->parse_response( $content, $folder_paths );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		$api_key = $settings['gemini_key'] ?? '';
		$model   = $settings['gemini_model'] ?? 'gemini-1.5-flash';

		if ( empty( $api_key ) ) {
			return __( 'Gemini API key is required.', 'vmfa-ai-organizer' );
		}

		$url = sprintf( '%s/%s:generateContent?key=%s', self::API_BASE_URL, $model, $api_key );

		$response = $this->make_request(
			$url,
			array(
				'contents' => array(
					array(
						'parts' => array(
							array( 'text' => 'Say "OK" if you can read this.' ),
						),
					),
				),
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
		$api_key = $this->get_setting( 'gemini_key' );
		return ! empty( $api_key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'gemini-1.5-flash'   => 'Gemini 1.5 Flash (Fast, Free Tier)',
			'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash-8B (Fastest)',
			'gemini-1.5-pro'     => 'Gemini 1.5 Pro (Powerful)',
			'gemini-2.0-flash'   => 'Gemini 2.0 Flash (Latest)',
		);
	}

	/**
	 * Build parts array for Gemini API request.
	 *
	 * For vision-capable models, includes both text and inline image data.
	 *
	 * @param string                   $text_prompt The text prompt.
	 * @param array<string, mixed>|null $image_data  Image data (base64, mime_type).
	 * @return array<int, array<string, mixed>> Parts array for Gemini API.
	 */
	private function build_gemini_parts( string $text_prompt, ?array $image_data ): array {
		$parts = array();

		// Add text part.
		$parts[] = array( 'text' => $text_prompt );

		// Add image part if available.
		if ( null !== $image_data && ! empty( $image_data['base64'] ) ) {
			$parts[] = array(
				'inline_data' => array(
					'mime_type' => $image_data['mime_type'],
					'data'      => $image_data['base64'],
				),
			);
		}

		return $parts;
	}
}
