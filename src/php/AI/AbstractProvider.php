<?php
/**
 * Abstract AI Provider.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

use VmfaAiOrganizer\Plugin;

/**
 * Abstract base class for AI providers.
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Default request timeout in seconds.
	 */
	protected const REQUEST_TIMEOUT = 30;

	/**
	 * System prompt for folder organization.
	 */
	protected const SYSTEM_PROMPT = <<<'PROMPT'
You are a media organization assistant. Your task is to analyze media file metadata and suggest the most appropriate folder for organizing the file.

You will receive:
1. Media metadata (filename, alt text, caption, description, MIME type, EXIF data)
2. A list of existing folder paths

Rules:
- Prefer placing media in existing folders when there's a good match
- Consider the file type, content description, and any contextual clues
- Be specific but not overly granular
- If no existing folder is suitable and new folders are allowed, suggest a new folder path
- Keep folder names concise and descriptive
- Use proper capitalization (Title Case for folder names)

Respond with valid JSON only, no markdown formatting:
{
    "action": "assign" or "create",
    "folder_path": "path/to/folder",
    "confidence": 0.0 to 1.0,
    "reason": "brief explanation"
}
PROMPT;

	/**
	 * Make an HTTP POST request.
	 *
	 * @param string               $url     Request URL.
	 * @param array<string, mixed> $body    Request body.
	 * @param array<string, string> $headers Request headers.
	 * @return array{success: bool, data: mixed, error: string|null}
	 */
	protected function make_request( string $url, array $body, array $headers = array() ): array {
		$default_headers = array(
			'Content-Type' => 'application/json',
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array_merge( $default_headers, $headers ),
				'body'    => wp_json_encode( $body ),
				'timeout' => static::REQUEST_TIMEOUT,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = $data['error']['message'] ?? $data['error'] ?? "HTTP {$status_code} error";
			return array(
				'success' => false,
				'data'    => $data,
				'error'   => $error_message,
			);
		}

		return array(
			'success' => true,
			'data'    => $data,
			'error'   => null,
		);
	}

	/**
	 * Build the user prompt for media analysis.
	 *
	 * @param array<string, mixed> $media_metadata   Media metadata.
	 * @param array<string, int>   $folder_paths     Available folder paths.
	 * @param int                  $max_depth        Maximum folder depth.
	 * @param bool                 $allow_new_folders Whether new folders are allowed.
	 * @return string
	 */
	protected function build_user_prompt(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders
	): string {
		$folders_list = empty( $folder_paths )
			? 'No existing folders.'
			: implode( "\n", array_map( fn( $path ) => "- {$path}", array_keys( $folder_paths ) ) );

		$metadata_text = $this->format_metadata( $media_metadata );

		$new_folders_text = $allow_new_folders
			? "You MAY suggest creating a new folder (max depth: {$max_depth})."
			: 'You must ONLY use existing folders. Do not suggest new folders.';

		return <<<PROMPT
Analyze this media file and suggest a folder:

## Media Metadata
{$metadata_text}

## Available Folders
{$folders_list}

## Constraints
{$new_folders_text}

Respond with JSON only.
PROMPT;
	}

	/**
	 * Format media metadata for the prompt.
	 *
	 * @param array<string, mixed> $metadata Media metadata.
	 * @return string
	 */
	protected function format_metadata( array $metadata ): string {
		$lines = array();

		if ( ! empty( $metadata['filename'] ) ) {
			$lines[] = "Filename: {$metadata['filename']}";
		}

		if ( ! empty( $metadata['mime_type'] ) ) {
			$lines[] = "Type: {$metadata['mime_type']}";
		}

		if ( ! empty( $metadata['alt'] ) ) {
			$lines[] = "Alt text: {$metadata['alt']}";
		}

		if ( ! empty( $metadata['caption'] ) ) {
			$lines[] = "Caption: {$metadata['caption']}";
		}

		if ( ! empty( $metadata['description'] ) ) {
			$lines[] = "Description: {$metadata['description']}";
		}

		if ( ! empty( $metadata['exif'] ) && is_array( $metadata['exif'] ) ) {
			$exif_items = array();
			foreach ( $metadata['exif'] as $key => $value ) {
				if ( ! empty( $value ) && is_string( $value ) ) {
					$exif_items[] = "{$key}: {$value}";
				}
			}
			if ( ! empty( $exif_items ) ) {
				$lines[] = 'EXIF: ' . implode( ', ', $exif_items );
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Parse the AI response into a structured result.
	 *
	 * @param string             $response      Raw AI response text.
	 * @param array<string, int> $folder_paths  Available folder paths.
	 * @return array{
	 *     action: string,
	 *     folder_id: int|null,
	 *     new_folder_path: string|null,
	 *     confidence: float,
	 *     reason: string
	 * }
	 */
	protected function parse_response( string $response, array $folder_paths ): array {
		$default = array(
			'action'          => 'skip',
			'folder_id'       => null,
			'new_folder_path' => null,
			'confidence'      => 0.0,
			'reason'          => 'Failed to parse AI response',
		);

		// Clean up response - remove markdown code blocks if present.
		$response = preg_replace( '/^```(?:json)?\s*/m', '', $response );
		$response = preg_replace( '/```\s*$/m', '', $response );
		$response = trim( $response );

		$data = json_decode( $response, true );

		if ( ! is_array( $data ) ) {
			return $default;
		}

		$action      = $data['action'] ?? 'assign';
		$folder_path = $data['folder_path'] ?? '';
		$confidence  = (float) ( $data['confidence'] ?? 0.5 );
		$reason      = $data['reason'] ?? '';

		// Validate and map folder path to ID.
		if ( 'assign' === $action && isset( $folder_paths[ $folder_path ] ) ) {
			return array(
				'action'          => 'assign',
				'folder_id'       => $folder_paths[ $folder_path ],
				'new_folder_path' => null,
				'confidence'      => $confidence,
				'reason'          => $reason,
			);
		}

		if ( 'create' === $action && ! empty( $folder_path ) ) {
			return array(
				'action'          => 'create',
				'folder_id'       => null,
				'new_folder_path' => $folder_path,
				'confidence'      => $confidence,
				'reason'          => $reason,
			);
		}

		// Fallback: try to find a partial match.
		foreach ( $folder_paths as $path => $id ) {
			if ( stripos( $path, $folder_path ) !== false || stripos( $folder_path, $path ) !== false ) {
				return array(
					'action'          => 'assign',
					'folder_id'       => $id,
					'new_folder_path' => null,
					'confidence'      => $confidence * 0.8,
					'reason'          => $reason . ' (partial match)',
				);
			}
		}

		return $default;
	}

	/**
	 * Get a setting value with config resolution.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	protected function get_setting( string $key ): mixed {
		return Plugin::get_instance()->get_setting( $key );
	}
}
