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
	 * Get the request timeout in seconds.
	 * Override in subclasses to provide a configurable timeout.
	 *
	 * @return int
	 */
	protected function get_request_timeout(): int {
		return static::REQUEST_TIMEOUT;
	}

	/**
	 * Get the system prompt for folder organization with vision capabilities.
	 *
	 * @return string The system prompt with language instruction.
	 */
	protected function get_system_prompt(): string {
		$locale        = get_locale();
		$language_name = $this->get_language_name( $locale );

		return <<<PROMPT
You are a media organization assistant with vision capabilities. Your PRIMARY task is to ANALYZE THE IMAGE CONTENT to determine the most appropriate folder.

## LANGUAGE REQUIREMENT
You MUST respond with folder names in {$language_name}. All folder_path values in your response must use {$language_name} words.

## Analysis Priority (highest to lowest):
1. **IMAGE CONTENT**: What objects, scenes, people, activities, or subjects are visible?
2. **EXIF/Metadata**: Camera info, date taken, GPS location, keywords
3. **Text metadata**: Title, alt text, caption, description
4. **Filename**: Only as a last resort hint

## Folder Creation Guidelines

### When to REUSE an existing/suggested folder:
- If a folder already exists that matches the image content, use it
- Check the "Folders Already Suggested in This Session" list and reuse if applicable

### When to CREATE a new folder:
- If no existing folder fits the image content well
- Create descriptive folders based on what you SEE in the image
- Be specific enough to be useful, but general enough to group similar images

### Folder Naming Rules:
- Use Title Case in {$language_name}
- Keep names concise: 1-3 words per level
- Spaces are allowed (e.g., "Street Art", "Birthday Party")
- Create hierarchies when it makes sense (e.g., "Animals/Birds", "Food/Desserts")
- Maximum 3 levels deep

### Avoid These Mistakes:
- Don't create synonymous folders (if "Animals" exists, don't create "Wildlife")
- Don't invert existing hierarchies (if "Events/Outdoor" exists, don't create "Outdoor/Events")
- Don't be overly specific (prefer "Food/Desserts" over "Food/Chocolate_Cake_With_Sprinkles")

## Example Categories (use as inspiration, not restrictions):
Animals, Nature, People, Buildings, Food, Travel, Events, Art, Sports, Technology, 
Transportation, Water, Plants, Music, Fashion, Documents, Videos

## Rules:
- ALWAYS analyze what you SEE in the image first
- Base your folder decision primarily on visual content
- Use metadata only to supplement your visual analysis
- When uncertain, choose a broader category

Respond with valid JSON only. No markdown formatting, no code blocks:
{
    "action": "existing" (use existing folder), "new" (create new folder), or "skip" (cannot categorize),
    "folder_id": integer ID of existing folder to use, or null if action is "new" or "skip",
    "new_folder_path": "path/to/new/folder (in {$language_name})" if action is "new", otherwise null,
    "confidence": 0.0 to 1.0,
    "reason": "One brief sentence explaining the folder choice (max 20 words)"
}
PROMPT;
	}

	/**
	 * Get human-readable language name from WordPress locale.
	 *
	 * Uses WordPress core format_code_lang() when available (multisite),
	 * otherwise extracts the language code and returns a readable name.
	 *
	 * @param string $locale WordPress locale code (e.g., 'nb_NO', 'en_US').
	 * @return string Human-readable language name.
	 */
	protected function get_language_name( string $locale ): string {
		// Extract the two-letter language code.
		$lang_code = strtolower( substr( $locale, 0, 2 ) );

		// Load ms.php if not already loaded (contains format_code_lang).
		if ( ! function_exists( 'format_code_lang' ) ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
		}

		return format_code_lang( $lang_code );
	}

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
				'headers'   => array_merge( $default_headers, $headers ),
				'body'      => wp_json_encode( $body ),
				'timeout'   => $this->get_request_timeout(),
				'sslverify' => true,
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

		// Sanitize response body to remove control characters before JSON parsing.
		$body = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body );

		$data = json_decode( $body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			// Extract error message from response or use a generic message.
			$error_message = "HTTP {$status_code} error";
			if ( is_array( $data ) ) {
				if ( isset( $data[ 'error' ][ 'message' ] ) ) {
					$error_message = $data[ 'error' ][ 'message' ];
				} elseif ( isset( $data[ 'error' ] ) && is_string( $data[ 'error' ] ) ) {
					$error_message = $data[ 'error' ];
				}
			} elseif ( ! empty( $body ) ) {
				// If JSON parsing failed, use sanitized raw body (truncated).
				$error_message = "HTTP {$status_code}: " . substr( $body, 0, 200 );
			}
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
	 * @param array<string, mixed> $media_metadata     Media metadata.
	 * @param array<string, int>   $folder_paths       Available folder paths.
	 * @param int                  $max_depth          Maximum folder depth.
	 * @param bool                 $allow_new_folders  Whether new folders are allowed.
	 * @param array<string>        $suggested_folders  Folders already suggested in this session.
	 * @return string
	 */
	protected function build_user_prompt(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders,
		array $suggested_folders = array()
	): string {
		$folders_list = empty( $folder_paths )
			? 'No existing folders.'
			: implode( "\n", array_map( fn( $path ) => "- {$path}", array_keys( $folder_paths ) ) );

		$metadata_text = $this->format_metadata( $media_metadata );

		$new_folders_text = $allow_new_folders
			? "You MAY suggest creating a new folder (max depth: {$max_depth})."
			: 'You must ONLY use existing folders. Do not suggest new folders.';

		// Add session suggested folders section.
		$suggested_folders_text = '';
		if ( ! empty( $suggested_folders ) ) {
			$suggested_list         = implode( "\n", array_map( fn( $path ) => "- {$path}", $suggested_folders ) );
			$suggested_folders_text = <<<TEXT

## Folders Already Suggested in This Session (MUST REUSE if applicable)
The following folders have already been suggested during this scan. You MUST use one of these if the media fits the same category. Do NOT create a similar or synonymous folder.
{$suggested_list}
TEXT;
		}

		return <<<PROMPT
Analyze this media file and suggest a folder.

## IMPORTANT: If an image is provided, FIRST describe what you SEE in it.

## Media Metadata (use as supplementary context)
{$metadata_text}

## Available Folders
{$folders_list}
{$suggested_folders_text}

## Constraints
{$new_folders_text}

Respond with JSON only. Include "visual_description" if you analyzed an image.
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

		if ( ! empty( $metadata[ 'filename' ] ) ) {
			$lines[] = "Filename: {$metadata[ 'filename' ]}";
		}

		if ( ! empty( $metadata[ 'mime_type' ] ) ) {
			$lines[] = "Type: {$metadata[ 'mime_type' ]}";
		}

		if ( ! empty( $metadata[ 'alt' ] ) ) {
			$lines[] = "Alt text: {$metadata[ 'alt' ]}";
		}

		if ( ! empty( $metadata[ 'caption' ] ) ) {
			$lines[] = "Caption: {$metadata[ 'caption' ]}";
		}

		if ( ! empty( $metadata[ 'description' ] ) ) {
			$lines[] = "Description: {$metadata[ 'description' ]}";
		}

		if ( ! empty( $metadata[ 'exif' ] ) && is_array( $metadata[ 'exif' ] ) ) {
			$exif_items = array();
			foreach ( $metadata[ 'exif' ] as $key => $value ) {
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
		// Clean up response - remove markdown code blocks if present.
		$original_response = $response;

		// Strip markdown code blocks: ```json ... ``` or ``` ... ```
		// Handle both opening and closing fences.
		if ( preg_match( '/```(?:json)?\s*\n?(.*?)\n?```/s', $response, $matches ) ) {
			$response = $matches[ 1 ];
		} else {
			// Fallback: try to extract just the JSON object/array.
			if ( preg_match( '/(\{[\s\S]*\}|\[[\s\S]*\])/', $response, $matches ) ) {
				$response = $matches[ 1 ];
			}
		}
		$response = trim( $response );

		// Sanitize control characters that break JSON parsing.
		// Remove all control characters except tab, newline, carriage return.
		$response = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $response );
		// Remove soft hyphens and zero-width characters (common in LLM output).
		$response = preg_replace( '/[\x{00AD}\x{00A0}\x{200B}-\x{200D}\x{FEFF}\x{2028}\x{2029}]/u', '', $response );
		// Remove any remaining non-printable characters that might break JSON.
		// This catches extended control characters in Latin-1 supplement.
		$response = preg_replace( '/[\x{0080}-\x{009F}]/u', '', $response );

		// Fix unescaped newlines inside JSON string values.
		// LLMs sometimes return multi-line strings which are invalid JSON.
		$response = $this->fix_json_string_newlines( $response );

		// Fallback: if JSON still has control chars, try aggressive cleanup.
		// Replace all newlines/carriage returns that aren't already escaped.
		$response = preg_replace( '/(?<!\\\\)[\r\n]+/', ' ', $response );

		// Check for empty response.
		if ( empty( $response ) ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'AI returned empty response.', 'vmfa-ai-organizer' ),
			);
		}

		$data       = json_decode( $response, true );
		$json_error = json_last_error();

		// If JSON parsing failed, try to salvage truncated JSON.
		if ( JSON_ERROR_NONE !== $json_error ) {
			$data       = $this->try_fix_truncated_json( $response );
			$json_error = null === $data ? JSON_ERROR_SYNTAX : JSON_ERROR_NONE;
		}

		// Check for JSON parsing errors.
		if ( JSON_ERROR_NONE !== $json_error ) {
			$error_messages = array(
				JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
				JSON_ERROR_STATE_MISMATCH => 'Underflow or mode mismatch',
				JSON_ERROR_CTRL_CHAR      => 'Unexpected control character',
				JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
				JSON_ERROR_UTF8           => 'Malformed UTF-8 characters',
			);
			$error_msg      = $error_messages[ $json_error ] ?? "Unknown error (code: {$json_error})";

			// Find control characters in SANITIZED response for debugging.
			$control_chars = array();
			for ( $i = 0; $i < strlen( $response ); $i++ ) {
				$ord = ord( $response[ $i ] );
				// Control chars are 0-31 (except tab=9, lf=10, cr=13) and 127.
				if ( ( $ord < 32 && ! in_array( $ord, array( 9, 10, 13 ), true ) ) || 127 === $ord ) {
					$control_chars[] = sprintf( 'pos %d: 0x%02X', $i, $ord );
				}
			}
			$control_info = ! empty( $control_chars )
				? ' [SANITIZED has ctrl chars: ' . implode( ', ', array_slice( $control_chars, 0, 5 ) ) . ']'
				: '';

			// Show sanitized response with visible newlines.
			$debug_response = str_replace( array( "\r", "\n", "\t" ), array( '\\r', '\\n', '\\t' ), $response );

			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => sprintf(
					'JSON error: %s.%s SANITIZED: %s',
					$error_msg,
					$control_info,
					$debug_response
				),
			);
		}

		if ( ! is_array( $data ) ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'AI response is not a valid JSON object.', 'vmfa-ai-organizer' ),
			);
		}

		// Handle the new schema format (from structured outputs).
		// New format uses: action: existing/new/skip, folder_id, new_folder_path.
		if ( isset( $data[ 'action' ] ) && in_array( $data[ 'action' ], array( 'existing', 'new', 'skip' ), true ) ) {
			$action          = $data[ 'action' ];
			$folder_id       = isset( $data[ 'folder_id' ] ) ? (int) $data[ 'folder_id' ] : null;
			$new_folder_path = $data[ 'new_folder_path' ] ?? null;
			$confidence      = (float) ( $data[ 'confidence' ] ?? 0.5 );
			$reason          = $data[ 'reason' ] ?? '';

			// Validate folder_id exists for "existing" action.
			if ( 'existing' === $action && $folder_id ) {
				// Verify the folder_id is valid.
				if ( in_array( $folder_id, $folder_paths, true ) ) {
					return array(
						'action'          => 'assign',
						'folder_id'       => $folder_id,
						'new_folder_path' => null,
						'confidence'      => $confidence,
						'reason'          => $reason,
					);
				}
				// Folder ID not found, skip.
				return array(
					'action'          => 'skip',
					'folder_id'       => null,
					'new_folder_path' => null,
					'confidence'      => 0.0,
					'reason'          => sprintf(
						/* translators: %d: folder ID */
						__( 'Folder ID %d not found.', 'vmfa-ai-organizer' ),
						$folder_id
					),
				);
			}

			if ( 'new' === $action && ! empty( $new_folder_path ) ) {
				return array(
					'action'          => 'create',
					'folder_id'       => null,
					'new_folder_path' => $new_folder_path,
					'confidence'      => $confidence,
					'reason'          => $reason,
				);
			}

			// Skip action or fallback.
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => $confidence,
				'reason'          => $reason ?: __( 'AI chose to skip this image.', 'vmfa-ai-organizer' ),
			);
		}

		// Handle the legacy format (from other providers or old prompts).
		// Legacy format uses: action: assign/create, folder_path.
		$action      = $data[ 'action' ] ?? 'assign';
		$folder_path = $data[ 'folder_path' ] ?? '';
		$confidence  = (float) ( $data[ 'confidence' ] ?? 0.5 );
		$reason      = $data[ 'reason' ] ?? '';

		// Check for missing folder_path.
		if ( empty( $folder_path ) ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => sprintf(
					/* translators: %s: AI's reason if provided */
					__( 'AI did not suggest a folder. %s', 'vmfa-ai-organizer' ),
					$reason
				),
			);
		}

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

		// If action is "create" OR action is "assign" but folder doesn't exist,
		// treat it as a create action (the folder path was suggested but doesn't exist).
		if ( ! empty( $folder_path ) && ( 'create' === $action || ! isset( $folder_paths[ $folder_path ] ) ) ) {
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

		// Should not reach here, but if we do, suggest creating the folder.
		return array(
			'action'          => 'create',
			'folder_id'       => null,
			'new_folder_path' => $folder_path,
			'confidence'      => $confidence,
			'reason'          => $reason,
		);
	}

	/**
	 * Fix unescaped newlines inside JSON string values.
	 *
	 * LLMs sometimes return JSON with literal newlines inside string values,
	 * which is invalid JSON. This method escapes them properly.
	 *
	 * @param string $json The potentially malformed JSON string.
	 * @return string The fixed JSON string.
	 */
	protected function fix_json_string_newlines( string $json ): string {
		// Use regex to find string values and escape newlines within them.
		// Match JSON strings: "..." (handling escaped quotes).
		// The 's' flag allows '.' to match newlines.
		$pattern = '/"((?:[^"\\\\]|\\\\.)*)"/s';

		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$content = $matches[ 1 ];
				// Replace literal newlines with escaped versions.
				$content = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $content );
				// Replace literal tabs with escaped versions.
				$content = str_replace( "\t", '\\t', $content );
				return '"' . $content . '"';
			},
			$json
		);
	}

	/**
	 * Try to fix and parse truncated JSON from LLM responses.
	 *
	 * LLMs sometimes run out of tokens and return incomplete JSON.
	 * This attempts to extract usable data from partial responses.
	 *
	 * @param string $json The truncated JSON string.
	 * @return array|null Parsed data or null if unrecoverable.
	 */
	protected function try_fix_truncated_json( string $json ): ?array {
		// Try to extract known fields using regex.
		$data = array();

		// Extract action.
		if ( preg_match( '/"action"\s*:\s*"(existing|new|skip)"/', $json, $matches ) ) {
			$data[ 'action' ] = $matches[ 1 ];
		}

		// Extract folder_id.
		if ( preg_match( '/"folder_id"\s*:\s*(\d+|null)/', $json, $matches ) ) {
			$data[ 'folder_id' ] = 'null' === $matches[ 1 ] ? null : (int) $matches[ 1 ];
		}

		// Extract new_folder_path.
		if ( preg_match( '/"new_folder_path"\s*:\s*"([^"]*)"/', $json, $matches ) ) {
			$data[ 'new_folder_path' ] = $matches[ 1 ];
		} elseif ( preg_match( '/"new_folder_path"\s*:\s*null/', $json ) ) {
			$data[ 'new_folder_path' ] = null;
		}

		// Extract confidence.
		if ( preg_match( '/"confidence"\s*:\s*([\d.]+)/', $json, $matches ) ) {
			$data[ 'confidence' ] = (float) $matches[ 1 ];
		}

		// Extract reason (might be truncated, that's OK).
		if ( preg_match( '/"reason"\s*:\s*"([^"]*)"?/', $json, $matches ) ) {
			$reason = $matches[ 1 ];
			// Clean up truncated reason.
			$reason         = rtrim( $reason, '\\' );
			$data[ 'reason' ] = $reason . ( strlen( $reason ) > 50 ? '...' : '' );
		}

		// Check if we have enough data to proceed.
		if ( isset( $data[ 'action' ] ) && ( isset( $data[ 'folder_id' ] ) || isset( $data[ 'new_folder_path' ] ) ) ) {
			// Fill in defaults for missing fields.
			$data[ 'folder_id' ]       = $data[ 'folder_id' ] ?? null;
			$data[ 'new_folder_path' ] = $data[ 'new_folder_path' ] ?? null;
			$data[ 'confidence' ]      = $data[ 'confidence' ] ?? 0.7;
			$data[ 'reason' ]          = $data[ 'reason' ] ?? 'Response was truncated but folder extracted.';

			return $data;
		}

		return null;
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
