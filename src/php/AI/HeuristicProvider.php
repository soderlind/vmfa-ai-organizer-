<?php
/**
 * Heuristic AI Provider (fallback).
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Heuristic-based folder suggestion provider.
 *
 * Uses pattern matching on filenames, MIME types, and metadata
 * to suggest folders without requiring an AI API.
 */
class HeuristicProvider extends AbstractProvider {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'heuristic';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Heuristic (No AI - Free)', 'vmfa-ai-organizer' );
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
		$filename    = strtolower( $media_metadata['filename'] ?? '' );
		$mime_type   = $media_metadata['mime_type'] ?? '';
		$alt         = strtolower( $media_metadata['alt'] ?? '' );
		$caption     = strtolower( $media_metadata['caption'] ?? '' );
		$description = strtolower( $media_metadata['description'] ?? '' );

		// Combine all text for matching.
		$all_text = "{$filename} {$alt} {$caption} {$description}";

		// Score each existing folder.
		$best_match = null;
		$best_score = 0;

		foreach ( $folder_paths as $path => $folder_id ) {
			$score = $this->calculate_match_score( $path, $all_text, $mime_type );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_match = array(
					'path' => $path,
					'id'   => $folder_id,
				);
			}
		}

		// If we have a good match, return it.
		if ( null !== $best_match && $best_score >= 0.3 ) {
			return array(
				'action'          => 'assign',
				'folder_id'       => $best_match['id'],
				'new_folder_path' => null,
				'confidence'      => min( $best_score, 1.0 ),
				'reason'          => sprintf(
					/* translators: %s: folder path */
					__( 'Matched folder "%s" based on filename and metadata patterns.', 'vmfa-ai-organizer' ),
					$best_match['path']
				),
			);
		}

		// If no match and new folders allowed, suggest based on content.
		if ( $allow_new_folders ) {
			$suggested_folder = $this->suggest_new_folder( $media_metadata, $max_depth );
			if ( $suggested_folder ) {
				return array(
					'action'          => 'create',
					'folder_id'       => null,
					'new_folder_path' => $suggested_folder,
					'confidence'      => 0.5,
					'reason'          => __( 'Suggested new folder based on file type and content.', 'vmfa-ai-organizer' ),
				);
			}
		}

		// No suitable match found.
		return array(
			'action'          => 'skip',
			'folder_id'       => null,
			'new_folder_path' => null,
			'confidence'      => 0.0,
			'reason'          => __( 'No suitable folder match found.', 'vmfa-ai-organizer' ),
		);
	}

	/**
	 * Calculate match score between folder path and media text.
	 *
	 * @param string $folder_path Folder path.
	 * @param string $text        Combined media text.
	 * @param string $mime_type   MIME type.
	 * @return float Score between 0 and 1.
	 */
	private function calculate_match_score( string $folder_path, string $text, string $mime_type ): float {
		$score       = 0.0;
		$folder_name = strtolower( basename( $folder_path ) );
		$folder_path = strtolower( $folder_path );

		// Direct folder name match in text.
		if ( str_contains( $text, $folder_name ) ) {
			$score += 0.5;
		}

		// MIME type category matching.
		$mime_categories = array(
			'image'       => array( 'images', 'photos', 'pictures', 'graphics', 'media' ),
			'video'       => array( 'videos', 'movies', 'clips', 'media' ),
			'audio'       => array( 'audio', 'music', 'sounds', 'podcasts', 'media' ),
			'application' => array( 'documents', 'files', 'docs', 'pdfs' ),
		);

		$mime_category = explode( '/', $mime_type )[0] ?? '';
		if ( isset( $mime_categories[ $mime_category ] ) ) {
			foreach ( $mime_categories[ $mime_category ] as $category_word ) {
				if ( str_contains( $folder_path, $category_word ) ) {
					$score += 0.3;
					break;
				}
			}
		}

		// Common content patterns.
		$patterns = array(
			'screenshot' => array( 'screenshots', 'screen-captures', 'screens' ),
			'logo'       => array( 'logos', 'branding', 'brand' ),
			'icon'       => array( 'icons', 'ui', 'interface' ),
			'banner'     => array( 'banners', 'headers', 'hero' ),
			'product'    => array( 'products', 'shop', 'store', 'inventory' ),
			'team'       => array( 'team', 'staff', 'people', 'employees' ),
			'event'      => array( 'events', 'occasions', 'gatherings' ),
			'blog'       => array( 'blog', 'posts', 'articles' ),
		);

		foreach ( $patterns as $pattern => $folder_matches ) {
			if ( str_contains( $text, $pattern ) ) {
				foreach ( $folder_matches as $folder_match ) {
					if ( str_contains( $folder_path, $folder_match ) ) {
						$score += 0.4;
						break 2;
					}
				}
			}
		}

		// Year/date patterns.
		if ( preg_match( '/\b(20\d{2})\b/', $text, $matches ) ) {
			$year = $matches[1];
			if ( str_contains( $folder_path, $year ) ) {
				$score += 0.3;
			}
		}

		return $score;
	}

	/**
	 * Suggest a new folder based on media metadata.
	 *
	 * @param array<string, mixed> $metadata  Media metadata.
	 * @param int                  $max_depth Maximum folder depth.
	 * @return string|null Suggested folder path or null.
	 */
	private function suggest_new_folder( array $metadata, int $max_depth ): ?string {
		$mime_type = $metadata['mime_type'] ?? '';
		$filename  = $metadata['filename'] ?? '';

		// Determine base category from MIME type.
		$mime_category = explode( '/', $mime_type )[0] ?? 'misc';
		$base_folders  = array(
			'image'       => 'Images',
			'video'       => 'Videos',
			'audio'       => 'Audio',
			'application' => 'Documents',
		);

		$base_folder = $base_folders[ $mime_category ] ?? 'Media';

		if ( 1 === $max_depth ) {
			return $base_folder;
		}

		// Try to detect subcategory from filename.
		$subcategories = array(
			'screenshot' => 'Screenshots',
			'logo'       => 'Logos',
			'icon'       => 'Icons',
			'banner'     => 'Banners',
			'photo'      => 'Photos',
			'product'    => 'Products',
			'background' => 'Backgrounds',
		);

		$filename_lower = strtolower( $filename );
		foreach ( $subcategories as $pattern => $subcategory ) {
			if ( str_contains( $filename_lower, $pattern ) ) {
				return "{$base_folder}/{$subcategory}";
			}
		}

		return $base_folder;
	}

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		// Heuristic provider doesn't need configuration.
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			'heuristic' => __( 'Pattern Matching (Default)', 'vmfa-ai-organizer' ),
		);
	}
}
