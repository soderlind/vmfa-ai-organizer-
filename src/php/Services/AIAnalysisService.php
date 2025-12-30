<?php
/**
 * AI Analysis Service.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\Services;

use VmfaAiOrganizer\AI\ProviderFactory;
use VmfaAiOrganizer\AI\ProviderInterface;
use VmfaAiOrganizer\Plugin;

/**
 * Service for analyzing media and suggesting folder assignments.
 */
class AIAnalysisService {

	/**
	 * VMF folder taxonomy name.
	 */
	private const TAXONOMY = 'vmfo_folder';

	/**
	 * AI provider instance.
	 *
	 * @var ProviderInterface|null
	 */
	private ?ProviderInterface $provider = null;

	/**
	 * Cached folder paths.
	 *
	 * @var array<string, int>|null
	 */
	private ?array $folder_paths = null;

	/**
	 * Get the AI provider.
	 *
	 * @return ProviderInterface
	 */
	public function get_provider(): ProviderInterface {
		if ( null === $this->provider ) {
			$this->provider = ProviderFactory::get_current_provider();
		}
		return $this->provider;
	}

	/**
	 * Set the AI provider (for testing).
	 *
	 * @param ProviderInterface $provider Provider instance.
	 * @return void
	 */
	public function set_provider( ProviderInterface $provider ): void {
		$this->provider = $provider;
	}

	/**
	 * Analyze a media attachment and suggest folder assignment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{
	 *     action: string,
	 *     folder_id: int|null,
	 *     new_folder_path: string|null,
	 *     confidence: float,
	 *     reason: string,
	 *     attachment_id: int
	 * }
	 */
	public function analyze_media( int $attachment_id ): array {
		$metadata      = $this->get_media_metadata( $attachment_id );
		$folder_paths  = $this->get_folder_paths();
		$max_depth     = (int) Plugin::get_instance()->get_setting( 'max_folder_depth', 3 );
		$allow_new     = (bool) Plugin::get_instance()->get_setting( 'allow_new_folders', false );

		$result = $this->get_provider()->analyze( $metadata, $folder_paths, $max_depth, $allow_new );

		$result['attachment_id'] = $attachment_id;

		return $result;
	}

	/**
	 * Get metadata for a media attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function get_media_metadata( int $attachment_id ): array {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return array();
		}

		$metadata = array(
			'filename'    => basename( get_attached_file( $attachment_id ) ?: '' ),
			'mime_type'   => $attachment->post_mime_type,
			'alt'         => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption'     => $attachment->post_excerpt,
			'description' => $attachment->post_content,
			'title'       => $attachment->post_title,
		);

		// Get EXIF data for images.
		if ( str_starts_with( $attachment->post_mime_type, 'image/' ) ) {
			$image_meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $image_meta['image_meta'] ) ) {
				$exif = array();

				if ( ! empty( $image_meta['image_meta']['camera'] ) ) {
					$exif['camera'] = $image_meta['image_meta']['camera'];
				}

				if ( ! empty( $image_meta['image_meta']['created_timestamp'] ) ) {
					$exif['date'] = gmdate( 'Y-m-d', (int) $image_meta['image_meta']['created_timestamp'] );
				}

				if ( ! empty( $image_meta['image_meta']['keywords'] ) ) {
					$exif['keywords'] = implode( ', ', (array) $image_meta['image_meta']['keywords'] );
				}

				if ( ! empty( $image_meta['image_meta']['title'] ) ) {
					$exif['title'] = $image_meta['image_meta']['title'];
				}

				$metadata['exif'] = $exif;
			}

			// Add dimensions.
			if ( ! empty( $image_meta['width'] ) && ! empty( $image_meta['height'] ) ) {
				$metadata['dimensions'] = "{$image_meta['width']}x{$image_meta['height']}";
			}
		}

		return $metadata;
	}

	/**
	 * Get all folder paths mapped to term IDs.
	 *
	 * @param bool $refresh Force refresh cache.
	 * @return array<string, int>
	 */
	public function get_folder_paths( bool $refresh = false ): array {
		if ( null !== $this->folder_paths && ! $refresh ) {
			return $this->folder_paths;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$this->folder_paths = array();
			return $this->folder_paths;
		}

		$max_depth = (int) Plugin::get_instance()->get_setting( 'max_folder_depth', 3 );

		$this->folder_paths = array();

		foreach ( $terms as $term ) {
			$path  = $this->build_term_path( $term, $terms );
			$depth = substr_count( $path, '/' ) + 1;

			// Only include folders within max depth.
			if ( $depth <= $max_depth ) {
				$this->folder_paths[ $path ] = $term->term_id;
			}
		}

		return $this->folder_paths;
	}

	/**
	 * Build the full path for a term.
	 *
	 * @param \WP_Term          $term  Term object.
	 * @param array<\WP_Term>   $terms All terms for lookup.
	 * @return string
	 */
	private function build_term_path( \WP_Term $term, array $terms ): string {
		$path   = $term->name;
		$parent = $term->parent;

		while ( $parent > 0 ) {
			foreach ( $terms as $parent_term ) {
				if ( $parent_term->term_id === $parent ) {
					$path   = $parent_term->name . '/' . $path;
					$parent = $parent_term->parent;
					break;
				}
			}
		}

		return $path;
	}

	/**
	 * Create a new folder from a path.
	 *
	 * @param string $path Folder path (e.g., "Photos/Events/2024").
	 * @return int|null Created term ID or null on failure.
	 */
	public function create_folder_from_path( string $path ): ?int {
		$parts     = explode( '/', trim( $path, '/' ) );
		$parent_id = 0;

		foreach ( $parts as $part ) {
			$part = sanitize_text_field( trim( $part ) );
			if ( empty( $part ) ) {
				continue;
			}

			// Check if this folder already exists.
			$existing = get_term_by( 'name', $part, self::TAXONOMY );

			if ( $existing && $existing->parent === $parent_id ) {
				$parent_id = $existing->term_id;
				continue;
			}

			// Create the folder.
			$result = wp_insert_term(
				$part,
				self::TAXONOMY,
				array( 'parent' => $parent_id )
			);

			if ( is_wp_error( $result ) ) {
				// Term might exist with different parent, try to find it.
				if ( $existing ) {
					$parent_id = $existing->term_id;
					continue;
				}
				return null;
			}

			$parent_id = $result['term_id'];
		}

		// Clear folder cache.
		$this->folder_paths = null;

		return $parent_id > 0 ? $parent_id : null;
	}

	/**
	 * Assign media to a folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $folder_id     Folder term ID.
	 * @return bool
	 */
	public function assign_to_folder( int $attachment_id, int $folder_id ): bool {
		$result = wp_set_object_terms( $attachment_id, array( $folder_id ), self::TAXONOMY );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Clear any AI suggestion metadata.
		delete_post_meta( $attachment_id, '_vmfo_folder_suggestions' );
		delete_post_meta( $attachment_id, '_vmfo_suggestions_dismissed' );

		return true;
	}

	/**
	 * Apply an AI analysis result.
	 *
	 * @param array{
	 *     action: string,
	 *     folder_id: int|null,
	 *     new_folder_path: string|null,
	 *     confidence: float,
	 *     reason: string,
	 *     attachment_id: int
	 * } $result Analysis result.
	 * @return bool
	 */
	public function apply_result( array $result ): bool {
		$attachment_id = $result['attachment_id'] ?? 0;

		if ( empty( $attachment_id ) ) {
			return false;
		}

		if ( 'assign' === $result['action'] && ! empty( $result['folder_id'] ) ) {
			return $this->assign_to_folder( $attachment_id, $result['folder_id'] );
		}

		if ( 'create' === $result['action'] && ! empty( $result['new_folder_path'] ) ) {
			$folder_id = $this->create_folder_from_path( $result['new_folder_path'] );
			if ( $folder_id ) {
				return $this->assign_to_folder( $attachment_id, $folder_id );
			}
		}

		return false;
	}

	/**
	 * Get unassigned media attachment IDs.
	 *
	 * @param int $limit Maximum number of IDs to return.
	 * @return array<int>
	 */
	public function get_unassigned_media_ids( int $limit = -1 ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT p.ID 
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'attachment'
			AND p.post_status = 'inherit'
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s AND tr.object_id = p.ID
			)
			ORDER BY p.ID ASC",
			self::TAXONOMY
		);

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared above.
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Get all media attachment IDs.
	 *
	 * @param int $limit Maximum number of IDs to return.
	 * @return array<int>
	 */
	public function get_all_media_ids( int $limit = -1 ): array {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		return get_posts( $args );
	}
}
