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
1. **IMAGE CONTENT**: What objects, scenes, people, activities, colors, or subjects are visible?
2. **EXIF/Metadata**: Camera info, date taken, GPS location, keywords
3. **Text metadata**: Title, alt text, caption, description
4. **Filename**: Only as a last resort hint

## CRITICAL: Folder Name Consistency
You MUST avoid creating similar or synonymous folder names. Follow these rules strictly:
- **ALWAYS check existing folders first** - if an existing folder covers the same concept, USE IT
- **Use broad, canonical categories** - prefer general terms over specific variations
- **NO synonyms** - if "Animals" exists, do NOT create "Wildlife", "Fauna", "Creatures", etc.
- **NO variations** - if "Nature" exists, do NOT create "Natural", "Outdoors", "Outside", etc.
- **NO near-duplicates** - if "Landscapes" exists, do NOT create "Scenery", "Views", "Vistas", etc.
- **Standardize naming** - use the most common, simple term for each category

### Standard Category Examples (use these exact names, not synonyms):
- Animals (not: Wildlife, Fauna, Creatures, Pets)
- Nature (not: Outdoors, Natural, Environment)
- People (not: Humans, Persons, Portraits, Faces)
- Buildings (not: Architecture, Structures, Constructions)
- Food (not: Cuisine, Meals, Dishes)
- Travel (not: Vacation, Tourism, Trips)
- Events (not: Celebrations, Occasions, Gatherings)
- Art (not: Artwork, Artistic, Creative)
- Sports (not: Athletics, Games, Recreation)
- Technology (not: Tech, Gadgets, Devices, Electronics)

## Rules:
- ALWAYS describe what you SEE in the image first
- Base your folder decision primarily on visual content
- Use metadata only to supplement or confirm your visual analysis
- **STRONGLY prefer existing folders** - only create new if truly no match exists
- Keep folder names concise: one or two words maximum
- Use simple, common vocabulary (Title Case in {$language_name})
- When uncertain between similar folders, choose the more general one

Respond with valid JSON only, no markdown formatting:
{
    "visual_description": "Brief description of what is visible in the image",
    "action": "assign" or "create",
    "folder_path": "path/to/folder (in {$language_name})",
    "confidence": 0.0 to 1.0,
    "reason": "Why this folder based on visual content"
}
PROMPT;
	}

	/**
	 * Get human-readable language name from WordPress locale.
	 *
	 * @param string $locale WordPress locale code (e.g., 'nb_NO', 'en_US').
	 * @return string Human-readable language name.
	 */
	protected function get_language_name( string $locale ): string {
		// Map of common locale codes to language names.
		$language_map = array(
			'en_US' => 'English',
			'en_GB' => 'English',
			'en_AU' => 'English',
			'en_CA' => 'English',
			'nb_NO' => 'Norwegian (Bokmål)',
			'nn_NO' => 'Norwegian (Nynorsk)',
			'sv_SE' => 'Swedish',
			'da_DK' => 'Danish',
			'fi'    => 'Finnish',
			'de_DE' => 'German',
			'de_AT' => 'German',
			'de_CH' => 'German',
			'fr_FR' => 'French',
			'fr_CA' => 'French',
			'fr_BE' => 'French',
			'es_ES' => 'Spanish',
			'es_MX' => 'Spanish',
			'es_AR' => 'Spanish',
			'it_IT' => 'Italian',
			'pt_BR' => 'Portuguese',
			'pt_PT' => 'Portuguese',
			'nl_NL' => 'Dutch',
			'nl_BE' => 'Dutch',
			'pl_PL' => 'Polish',
			'ru_RU' => 'Russian',
			'uk'    => 'Ukrainian',
			'cs_CZ' => 'Czech',
			'sk_SK' => 'Slovak',
			'hu_HU' => 'Hungarian',
			'ro_RO' => 'Romanian',
			'bg_BG' => 'Bulgarian',
			'el'    => 'Greek',
			'tr_TR' => 'Turkish',
			'ar'    => 'Arabic',
			'he_IL' => 'Hebrew',
			'ja'    => 'Japanese',
			'ko_KR' => 'Korean',
			'zh_CN' => 'Chinese (Simplified)',
			'zh_TW' => 'Chinese (Traditional)',
			'th'    => 'Thai',
			'vi'    => 'Vietnamese',
			'id_ID' => 'Indonesian',
			'ms_MY' => 'Malay',
			'hi_IN' => 'Hindi',
		);

		// Check for exact match.
		if ( isset( $language_map[ $locale ] ) ) {
			return $language_map[ $locale ];
		}

		// Try matching just the language code (before underscore).
		$lang_code = explode( '_', $locale )[0];
		foreach ( $language_map as $code => $name ) {
			if ( str_starts_with( $code, $lang_code . '_' ) || $code === $lang_code ) {
				return $name;
			}
		}

		// Fallback: use the locale code itself.
		return $locale;
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
				'timeout'   => static::REQUEST_TIMEOUT,
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
			$suggested_list = implode( "\n", array_map( fn( $path ) => "- {$path}", $suggested_folders ) );
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
