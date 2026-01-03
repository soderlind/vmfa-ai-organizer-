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
	 * Scan progress option key.
	 */
	private const PROGRESS_OPTION = 'vmfa_scan_progress';

	/**
	 * VMF folder taxonomy name.
	 */
	private const TAXONOMY = 'vmfo_folder';

	/**
	 * Option key for session-suggested folders.
	 */
	private const SESSION_FOLDERS_OPTION = 'vmfa_session_suggested_folders';

	/**
	 * Document MIME types that go to Documents folder.
	 *
	 * @var array<string>
	 */
	private const DOCUMENT_MIME_TYPES = array(
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/rtf',
		'text/plain',
		'text/csv',
		'application/zip',
		'application/x-rar-compressed',
		'application/x-7z-compressed',
	);

	/**
	 * Video MIME types that go to Videos folder.
	 *
	 * @var array<string>
	 */
	private const VIDEO_MIME_TYPES = array(
		'video/mp4',
		'video/webm',
		'video/ogg',
		'video/quicktime',
		'video/x-msvideo',
		'video/x-ms-wmv',
		'video/mpeg',
		'video/3gpp',
		'video/x-flv',
	);

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
	 * Cached folder name to paths map.
	 * Maps folder names to arrays of full paths where they appear.
	 *
	 * @var array<string, array<string>>|null
	 */
	private ?array $folder_name_map = null;

	/**
	 * Get the AI provider.
	 *
	 * @return ProviderInterface|null Null if no provider configured.
	 */
	public function get_provider(): ?ProviderInterface {
		if ( null === $this->provider ) {
			$this->provider = ProviderFactory::get_current_provider();
		}
		return $this->provider;
	}

	/**
	 * Check if an AI provider is configured.
	 *
	 * @return bool
	 */
	public function is_provider_configured(): bool {
		$provider = $this->get_provider();
		return null !== $provider && $provider->is_configured();
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
	 *     attachment_id: int,
	 *     filename: string,
	 *     folder_name: string
	 * }
	 */
	public function analyze_media( int $attachment_id ): array {
		$metadata     = $this->get_media_metadata( $attachment_id );
		$folder_paths = $this->get_folder_paths();
		$mime_type    = $metadata[ 'mime_type' ] ?? '';

		// For "reorganize_all" mode (even in dry-run/preview), simulate empty folders.
		// This ensures the preview accurately shows what would happen after folders are deleted.
		$progress          = get_option( self::PROGRESS_OPTION, array() );
		$is_reorganize_all = 'reorganize_all' === ( $progress[ 'mode' ] ?? '' );
		if ( $is_reorganize_all ) {
			$folder_paths = array();
		}

		// Handle documents - assign to Documents folder.
		if ( in_array( $mime_type, self::DOCUMENT_MIME_TYPES, true ) ) {
			return $this->assign_to_type_folder( $attachment_id, 'Documents', $folder_paths );
		}

		// Handle videos - assign to Videos folder.
		if ( in_array( $mime_type, self::VIDEO_MIME_TYPES, true ) || str_starts_with( $mime_type, 'video/' ) ) {
			return $this->assign_to_type_folder( $attachment_id, 'Videos', $folder_paths );
		}

		// For images, require an AI provider.
		$provider = $this->get_provider();
		if ( null === $provider ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'No AI provider configured. Please configure an AI provider in settings.', 'vmfa-ai-organizer' ),
				'attachment_id'   => $attachment_id,
			);
		}

		if ( ! $provider->is_configured() ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'AI provider is not properly configured. Please check your API settings.', 'vmfa-ai-organizer' ),
				'attachment_id'   => $attachment_id,
			);
		}

		$max_depth         = (int) Plugin::get_instance()->get_setting( 'max_folder_depth', 3 );
		$allow_new         = (bool) Plugin::get_instance()->get_setting( 'allow_new_folders', false );
		$suggested_folders = $this->get_session_suggested_folders();

		// For "reorganize_all" mode, always allow creating new folders since we're starting fresh.
		if ( $is_reorganize_all ) {
			$allow_new = true;
		}

		// Get image data for vision-capable providers.
		$image_data = $this->get_image_data( $attachment_id );

		$result = $provider->analyze( $metadata, $folder_paths, $max_depth, $allow_new, $image_data, $suggested_folders );

		// Enforce allow_new_folders at runtime (providers/LLMs may ignore prompt constraints).
		if ( ! $allow_new && 'create' === ( $result[ 'action' ] ?? '' ) ) {
			$result = array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'New folder creation is disabled in settings.', 'vmfa-ai-organizer' ),
			);
		}

		// Sanitize AI-suggested new folder paths (remove emojis/emoticons, normalize whitespace).
		if ( 'create' === ( $result[ 'action' ] ?? '' ) && ! empty( $result[ 'new_folder_path' ] ) ) {
			$original_path  = (string) $result[ 'new_folder_path' ];
			$sanitized_path = $this->sanitize_folder_path( $original_path );

			if ( $sanitized_path !== $original_path ) {
				$result[ 'new_folder_path' ] = $sanitized_path;
				$result[ 'reason' ]          = sprintf(
					/* translators: 1: original reason */
					__( '%1$s (Folder name sanitized)', 'vmfa-ai-organizer' ),
					(string) ( $result[ 'reason' ] ?? '' )
				);
				$result[ 'confidence' ]      = (float) ( $result[ 'confidence' ] ?? 0.0 ) * 0.98;
			}

			if ( '' === $sanitized_path ) {
				$result = array(
					'action'          => 'skip',
					'folder_id'       => null,
					'new_folder_path' => null,
					'confidence'      => 0.0,
					'reason'          => __( 'Suggested folder name was empty after sanitization.', 'vmfa-ai-organizer' ),
				);
			}
		}

		// Check for hierarchy conflicts when creating new folders.
		if ( 'create' === $result[ 'action' ] && ! empty( $result[ 'new_folder_path' ] ) ) {
			$conflict = $this->detect_hierarchy_conflict( $result[ 'new_folder_path' ] );

			if ( $conflict[ 'conflict' ] && ! empty( $conflict[ 'existing_path' ] ) ) {
				// Check if the existing path is in the database or just a session-suggested folder.
				if ( isset( $folder_paths[ $conflict[ 'existing_path' ] ] ) ) {
					// Path exists in database - remap to existing folder.
					$result[ 'action' ]          = 'assign';
					$result[ 'folder_id' ]       = $folder_paths[ $conflict[ 'existing_path' ] ];
					$result[ 'new_folder_path' ] = null;
				} else {
					// Path is session-suggested but not yet created - use that path instead.
					$result[ 'new_folder_path' ] = $conflict[ 'existing_path' ];
				}
				$result[ 'reason' ]     = sprintf(
					/* translators: 1: original reason, 2: existing path */
					__( '%1$s (Auto-remapped to existing folder: %2$s to prevent hierarchy inversion)', 'vmfa-ai-organizer' ),
					$result[ 'reason' ],
					$conflict[ 'existing_path' ]
				);
				$result[ 'confidence' ] = $result[ 'confidence' ] * 0.9; // Slightly reduce confidence.
			}
		}

		// Track the suggested folder for consistency in this scan session.
		if ( ! empty( $result[ 'new_folder_path' ] ) ) {
			$this->add_session_suggested_folder( $result[ 'new_folder_path' ] );
		}

		$result[ 'attachment_id' ] = $attachment_id;
		$result[ 'filename' ]      = $metadata[ 'filename' ] ?? '';
		$result[ 'folder_name' ]   = $result[ 'new_folder_path' ] ?? $this->get_folder_name_by_id( $result[ 'folder_id' ] ?? null );

		return $result;
	}

	/**
	 * Assign media to a type-based folder (Documents, Videos).
	 *
	 * @param int                $attachment_id Attachment ID.
	 * @param string             $folder_name   Folder name (e.g., 'Documents', 'Videos').
	 * @param array<string, int> $folder_paths  Existing folder paths.
	 * @return array{
	 *     action: string,
	 *     folder_id: int|null,
	 *     new_folder_path: string|null,
	 *     confidence: float,
	 *     reason: string,
	 *     attachment_id: int,
	 *     filename: string,
	 *     folder_name: string
	 * }
	 */
	private function assign_to_type_folder( int $attachment_id, string $folder_name, array $folder_paths ): array {
		$filename = basename( get_attached_file( $attachment_id ) ?: '' );

		// Check if folder already exists as exact path.
		if ( isset( $folder_paths[ $folder_name ] ) ) {
			return array(
				'action'          => 'assign',
				'folder_id'       => $folder_paths[ $folder_name ],
				'new_folder_path' => null,
				'confidence'      => 1.0,
				'reason'          => sprintf(
					/* translators: %s: folder name */
					__( 'File type automatically assigned to %s folder.', 'vmfa-ai-organizer' ),
					$folder_name
				),
				'attachment_id'   => $attachment_id,
				'filename'        => $filename,
				'folder_name'     => $folder_name,
			);
		}

		// Try to find folder by name anywhere in the hierarchy (prefers shallowest).
		// IMPORTANT: use the provided folder paths so reorganize_all preview (simulated empty folders)
		// doesn't accidentally match folders that are about to be deleted.
		$found_folder = $this->find_folder_by_name_in_paths( $folder_name, $folder_paths );
		if ( $found_folder[ 'found' ] && ! empty( $found_folder[ 'folder_id' ] ) ) {
			return array(
				'action'          => 'assign',
				'folder_id'       => $found_folder[ 'folder_id' ],
				'new_folder_path' => null,
				'confidence'      => 1.0,
				'reason'          => sprintf(
					/* translators: 1: folder name, 2: full path */
					__( 'File type automatically assigned to %1$s folder (found at %2$s).', 'vmfa-ai-organizer' ),
					$folder_name,
					$found_folder[ 'path' ]
				),
				'attachment_id'   => $attachment_id,
				'filename'        => $filename,
				'folder_name'     => $folder_name,
			);
		}

		// Folder doesn't exist - always create Documents/Videos type folders.
		// These are type-based folders, not AI-suggested, so allow_new_folders setting doesn't apply.
		return array(
			'action'          => 'create',
			'folder_id'       => null,
			'new_folder_path' => $folder_name,
			'confidence'      => 1.0,
			'reason'          => sprintf(
				/* translators: %s: folder name */
				__( 'File type requires %s folder (will be created).', 'vmfa-ai-organizer' ),
				$folder_name
			),
			'attachment_id'   => $attachment_id,
			'filename'        => $filename,
			'folder_name'     => $folder_name,
		);
	}

	/**
	 * Find a folder by name within a provided folder path map.
	 *
	 * This is intentionally separate from {@see find_folder_by_name()} so callers can control
	 * whether to consult the database-backed folder list (e.g., reorganize_all preview mode
	 * simulates an empty folder set).
	 *
	 * @param string           $folder_name  Folder name to find.
	 * @param array<string,int> $folder_paths Folder path => term ID map.
	 * @return array{found: bool, path: string|null, folder_id: int|null}
	 */
	private function find_folder_by_name_in_paths( string $folder_name, array $folder_paths ): array {
		if ( empty( $folder_paths ) ) {
			return array(
				'found'     => false,
				'path'      => null,
				'folder_id' => null,
			);
		}

		$name_lower = mb_strtolower( $folder_name );
		$paths      = array();

		foreach ( array_keys( $folder_paths ) as $path ) {
			$parts = explode( '/', $path );
			foreach ( $parts as $part ) {
				if ( mb_strtolower( $part ) === $name_lower ) {
					$paths[] = $path;
					break;
				}
			}
		}

		if ( empty( $paths ) ) {
			return array(
				'found'     => false,
				'path'      => null,
				'folder_id' => null,
			);
		}

		// Sort by depth (shallowest first).
		usort( $paths, function ( $a, $b ) {
			$depth_a = substr_count( $a, '/' );
			$depth_b = substr_count( $b, '/' );
			return $depth_a - $depth_b;
		} );

		// Prefer paths where the folder is the leaf (last component).
		foreach ( $paths as $path ) {
			$parts = explode( '/', $path );
			if ( mb_strtolower( end( $parts ) ) === $name_lower ) {
				return array(
					'found'     => true,
					'path'      => $path,
					'folder_id' => $folder_paths[ $path ] ?? null,
				);
			}
		}

		// Fall back to first occurrence.
		$first_path = $paths[ 0 ];
		return array(
			'found'     => true,
			'path'      => $first_path,
			'folder_id' => $folder_paths[ $first_path ] ?? null,
		);
	}

	/**
	 * Get folders suggested during this scan session.
	 *
	 * @return array<string> List of folder paths suggested in the current session.
	 */
	public function get_session_suggested_folders(): array {
		return get_option( self::SESSION_FOLDERS_OPTION, array() );
	}

	/**
	 * Add a folder to the session suggested list.
	 *
	 * @param string $folder_path The folder path that was suggested.
	 * @return void
	 */
	public function add_session_suggested_folder( string $folder_path ): void {
		$folder_path = $this->sanitize_folder_path( $folder_path );
		if ( '' === $folder_path ) {
			return;
		}

		$folders = $this->get_session_suggested_folders();
		if ( ! in_array( $folder_path, $folders, true ) ) {
			$folders[] = $folder_path;
			update_option( self::SESSION_FOLDERS_OPTION, $folders, false );
		}
	}

	/**
	 * Clear session suggested folders (called when a new scan starts).
	 *
	 * @return void
	 */
	public function clear_session_suggested_folders(): void {
		delete_option( self::SESSION_FOLDERS_OPTION );
	}

	/**
	 * Get image data for vision API.
	 *
	 * Returns base64-encoded image data for vision-capable AI models.
	 * Images are resized to reduce token usage while maintaining quality.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{base64: string, mime_type: string}|null Image data or null if not available.
	 */
	public function get_image_data( int $attachment_id ): ?array {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		// Only process images.
		$mime_type = $attachment->post_mime_type;
		if ( ! str_starts_with( $mime_type, 'image/' ) ) {
			return null;
		}

		// Skip SVGs and unsupported formats.
		$supported_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $mime_type, $supported_types, true ) ) {
			return null;
		}

		// Get file path.
		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return null;
		}

		// Try to get a smaller size for efficiency (medium or large).
		// This reduces token usage for vision APIs.
		$image_sizes = array( 'medium_large', 'medium', 'large' );
		$image_path  = $file_path;

		foreach ( $image_sizes as $size ) {
			$image_src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image_src ) {
				$sized_path = str_replace(
					wp_basename( $file_path ),
					wp_basename( $image_src[ 0 ] ),
					$file_path
				);
				if ( file_exists( $sized_path ) ) {
					$image_path = $sized_path;
					break;
				}
			}
		}

		// Read and encode image.
		$image_content = file_get_contents( $image_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $image_content ) {
			return null;
		}

		// Check file size (limit to 10MB for API constraints).
		if ( strlen( $image_content ) > 10 * 1024 * 1024 ) {
			return null;
		}

		return array(
			'base64'    => base64_encode( $image_content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'mime_type' => $mime_type,
		);
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
			if ( ! empty( $image_meta[ 'image_meta' ] ) ) {
				$exif = array();

				if ( ! empty( $image_meta[ 'image_meta' ][ 'camera' ] ) ) {
					$exif[ 'camera' ] = $image_meta[ 'image_meta' ][ 'camera' ];
				}

				if ( ! empty( $image_meta[ 'image_meta' ][ 'created_timestamp' ] ) ) {
					$exif[ 'date' ] = gmdate( 'Y-m-d', (int) $image_meta[ 'image_meta' ][ 'created_timestamp' ] );
				}

				if ( ! empty( $image_meta[ 'image_meta' ][ 'keywords' ] ) ) {
					$exif[ 'keywords' ] = implode( ', ', (array) $image_meta[ 'image_meta' ][ 'keywords' ] );
				}

				if ( ! empty( $image_meta[ 'image_meta' ][ 'title' ] ) ) {
					$exif[ 'title' ] = $image_meta[ 'image_meta' ][ 'title' ];
				}

				$metadata[ 'exif' ] = $exif;
			}

			// Add dimensions.
			if ( ! empty( $image_meta[ 'width' ] ) && ! empty( $image_meta[ 'height' ] ) ) {
				$metadata[ 'dimensions' ] = "{$image_meta[ 'width' ]}x{$image_meta[ 'height' ]}";
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

		// Clear folder name map when refreshing paths.
		if ( $refresh ) {
			$this->folder_name_map = null;
		}

		// Use direct database query to bypass all WordPress caching.
		// This is necessary because persistent object caches (Redis, Memcached)
		// don't respect wp_cache_flush_group across separate PHP requests.
		global $wpdb;

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.parent, tt.count, tt.taxonomy
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s",
				self::TAXONOMY
			)
		);

		if ( empty( $terms ) ) {
			$this->folder_paths = array();
			return $this->folder_paths;
		}

		// Convert to term-like objects for build_term_path compatibility.
		$term_objects = array();
		foreach ( $terms as $row ) {
			$term           = new \stdClass();
			$term->term_id  = (int) $row->term_id;
			$term->name     = $row->name;
			$term->slug     = $row->slug;
			$term->parent   = (int) $row->parent;
			$term->count    = (int) $row->count;
			$term->taxonomy = $row->taxonomy;
			$term_objects[] = $term;
		}

		$max_depth = (int) Plugin::get_instance()->get_setting( 'max_folder_depth', 3 );

		$this->folder_paths = array();

		foreach ( $term_objects as $term ) {
			// Skip generic numbered folders (e.g., "Subfolder 01", "Folder 1", "Test Folder 99").
			// These are not meaningful for AI categorization.
			if ( preg_match( '/^(sub)?folder\s*\d+$/i', trim( $term->name ) ) ||
				preg_match( '/^test\s*(sub)?folder[s]?\s*\d*$/i', trim( $term->name ) ) ) {
				continue;
			}

			$path  = $this->build_term_path( $term, $term_objects );
			$depth = substr_count( $path, '/' ) + 1;

			// Only include folders within max depth.
			if ( $depth <= $max_depth ) {
				$this->folder_paths[ $path ] = $term->term_id;
			}
		}

		// Sort folder paths alphabetically.
		uksort( $this->folder_paths, 'strcasecmp' );

		return $this->folder_paths;
	}

	/**
	 * Build the full path for a term.
	 *
	 * @param object          $term  Term object (WP_Term or stdClass with same properties).
	 * @param array<object>   $terms All terms for lookup.
	 * @return string
	 */
	private function build_term_path( object $term, array $terms ): string {
		$path   = $term->name;
		$parent = $term->parent;
		$seen   = array(); // Prevent infinite loops.

		while ( $parent > 0 && ! isset( $seen[ $parent ] ) ) {
			$seen[ $parent ] = true;
			$found           = false;

			foreach ( $terms as $parent_term ) {
				if ( $parent_term->term_id === $parent ) {
					$path   = $parent_term->name . '/' . $path;
					$parent = $parent_term->parent;
					$found  = true;
					break;
				}
			}

			// If parent not found in terms array, stop to prevent infinite loop.
			if ( ! $found ) {
				break;
			}
		}

		return $path;
	}

	/**
	 * Get folder name to paths map with caching.
	 * Maps each folder name to an array of full paths where that name appears.
	 *
	 * @return array<string, array<string>> Folder name => array of full paths.
	 */
	public function get_folder_name_map(): array {
		if ( null !== $this->folder_name_map ) {
			return $this->folder_name_map;
		}

		$folder_paths          = $this->get_folder_paths();
		$this->folder_name_map = array();

		foreach ( array_keys( $folder_paths ) as $path ) {
			$parts = explode( '/', $path );
			foreach ( $parts as $name ) {
				$name_lower = mb_strtolower( $name );
				if ( ! isset( $this->folder_name_map[ $name_lower ] ) ) {
					$this->folder_name_map[ $name_lower ] = array();
				}
				if ( ! in_array( $path, $this->folder_name_map[ $name_lower ], true ) ) {
					$this->folder_name_map[ $name_lower ][] = $path;
				}
			}
		}

		return $this->folder_name_map;
	}

	/**
	 * Detect if a proposed path conflicts with existing hierarchies (inverted order).
	 *
	 * Checks if the proposed path's components exist in any existing path but in a different order.
	 * For example, if "Events/Outdoor" exists and we propose "Outdoor/Events", this is a conflict.
	 * Also checks against session-suggested folders to prevent inversions during batch processing.
	 *
	 * @param string $proposed_path The proposed folder path.
	 * @return array{conflict: bool, existing_path: string|null, message: string|null}
	 */
	public function detect_hierarchy_conflict( string $proposed_path ): array {
		$folder_paths = $this->get_folder_paths();

		// Also include session-suggested folders to prevent inversions during batch processing.
		$session_folders = $this->get_session_suggested_folders();
		$all_paths       = array_merge( array_keys( $folder_paths ), $session_folders );

		// If the path already exists exactly, no conflict.
		if ( isset( $folder_paths[ $proposed_path ] ) || in_array( $proposed_path, $session_folders, true ) ) {
			return array(
				'conflict'      => false,
				'existing_path' => $proposed_path,
				'message'       => null,
			);
		}

		$proposed_parts = array_map( 'mb_strtolower', explode( '/', trim( $proposed_path, '/' ) ) );
		$proposed_count = count( $proposed_parts );

		if ( $proposed_count < 2 ) {
			// Single-level paths cannot have hierarchy inversions.
			return array(
				'conflict'      => false,
				'existing_path' => null,
				'message'       => null,
			);
		}

		// Check each existing and session-suggested path for conflicts.
		foreach ( $all_paths as $existing_path ) {
			$existing_parts = array_map( 'mb_strtolower', explode( '/', $existing_path ) );

			// Find common components between proposed and existing.
			$common = array_intersect( $proposed_parts, $existing_parts );

			// Need at least 2 common components to have a potential inversion.
			if ( count( $common ) < 2 ) {
				continue;
			}

			// Check if the order of common components differs.
			$proposed_order = array();
			$existing_order = array();

			foreach ( $proposed_parts as $index => $part ) {
				if ( in_array( $part, $common, true ) ) {
					$proposed_order[] = $part;
				}
			}

			foreach ( $existing_parts as $index => $part ) {
				if ( in_array( $part, $common, true ) ) {
					$existing_order[] = $part;
				}
			}

			// If the order of common elements differs, this is an inversion.
			if ( $proposed_order !== $existing_order ) {
				return array(
					'conflict'      => true,
					'existing_path' => $existing_path,
					'message'       => sprintf(
						/* translators: 1: proposed path, 2: existing path */
						__( 'Path "%1$s" conflicts with existing hierarchy "%2$s" (inverted order).', 'vmfa-ai-organizer' ),
						$proposed_path,
						$existing_path
					),
				);
			}
		}

		return array(
			'conflict'      => false,
			'existing_path' => null,
			'message'       => null,
		);
	}

	/**
	 * Find a folder by name regardless of its position in the hierarchy.
	 * Prefers shallower paths (top-level first).
	 *
	 * @param string $folder_name Folder name to find.
	 * @return array{found: bool, path: string|null, folder_id: int|null}
	 */
	public function find_folder_by_name( string $folder_name ): array {
		$folder_paths = $this->get_folder_paths();
		$name_lower   = mb_strtolower( $folder_name );
		$name_map     = $this->get_folder_name_map();

		if ( ! isset( $name_map[ $name_lower ] ) ) {
			return array(
				'found'     => false,
				'path'      => null,
				'folder_id' => null,
			);
		}

		$paths = $name_map[ $name_lower ];

		// Sort by depth (shallowest first).
		usort( $paths, function ( $a, $b ) {
			$depth_a = substr_count( $a, '/' );
			$depth_b = substr_count( $b, '/' );
			return $depth_a - $depth_b;
		} );

		// Prefer paths where the folder is the leaf (last component).
		foreach ( $paths as $path ) {
			$parts = explode( '/', $path );
			if ( mb_strtolower( end( $parts ) ) === $name_lower ) {
				return array(
					'found'     => true,
					'path'      => $path,
					'folder_id' => $folder_paths[ $path ] ?? null,
				);
			}
		}

		// Fall back to first occurrence.
		$first_path = $paths[ 0 ];
		return array(
			'found'     => true,
			'path'      => $first_path,
			'folder_id' => $folder_paths[ $first_path ] ?? null,
		);
	}

	/**
	 * Get folder name by ID.
	 *
	 * @param int|null $folder_id Folder term ID.
	 * @return string Folder name or empty string.
	 */
	private function get_folder_name_by_id( ?int $folder_id ): string {
		if ( null === $folder_id ) {
			return '';
		}

		$term = get_term( $folder_id, self::TAXONOMY );
		if ( is_wp_error( $term ) || ! $term ) {
			return '';
		}

		return $term->name;
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
			$part = $this->sanitize_folder_name( $part );
			if ( empty( $part ) ) {
				continue;
			}

			// Check if this folder already exists under the current parent.
			$existing = $this->find_term_by_name_and_parent( $part, $parent_id );

			if ( $existing ) {
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
				// If term exists error, try to get the existing term.
				if ( 'term_exists' === $result->get_error_code() ) {
					$existing_term_id = $result->get_error_data();
					if ( $existing_term_id ) {
						$parent_id = (int) $existing_term_id;
						continue;
					}
				}
				return null;
			}

			$parent_id = $result[ 'term_id' ];

			// Set default order for new folder.
			update_term_meta( $parent_id, 'vmfo_order', 0 );
		}

		// Clear folder cache.
		$this->folder_paths = null;

		return $parent_id > 0 ? $parent_id : null;
	}

	/**
	 * Sanitize an AI-suggested folder path.
	 *
	 * - Removes emojis/emoticons.
	 * - Normalizes whitespace.
	 * - Applies WordPress sanitization per segment.
	 *
	 * @param string $path Folder path (e.g., "Plants/Leaves").
	 * @return string Sanitized path.
	 */
	private function sanitize_folder_path( string $path ): string {
		$path  = trim( $path );
		$path  = trim( $path, '/' );
		$parts = explode( '/', $path );

		$sanitized_parts = array();
		foreach ( $parts as $part ) {
			$part = $this->sanitize_folder_name( $part );
			if ( '' !== $part ) {
				$sanitized_parts[] = $part;
			}
		}

		return implode( '/', $sanitized_parts );
	}

	/**
	 * Sanitize a single folder name segment.
	 *
	 * @param string $name Folder name.
	 * @return string Sanitized name.
	 */
	private function sanitize_folder_name( string $name ): string {
		$name = trim( $name );
		if ( '' === $name ) {
			return '';
		}

		$name = $this->strip_emojis( $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		$name = sanitize_text_field( $name );
		$name = trim( $name );

		return $name;
	}

	/**
	 * Remove emojis/emoticons from a string.
	 *
	 * @param string $text Input string.
	 * @return string String without emojis/emoticons.
	 */
	private function strip_emojis( string $text ): string {
		// Remove common emoji blocks + variation selectors + ZWJ.
		$text = preg_replace(
			'/[\x{1F300}-\x{1F5FF}\x{1F600}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE0F}\x{200D}]/u',
			'',
			$text
		);

		return is_string( $text ) ? $text : '';
	}

	/**
	 * Find a term by name and parent ID.
	 *
	 * @param string $name      Term name.
	 * @param int    $parent_id Parent term ID.
	 * @return \WP_Term|null
	 */
	private function find_term_by_name_and_parent( string $name, int $parent_id ): ?\WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'name'       => $name,
				'parent'     => $parent_id,
				'hide_empty' => false,
				'number'     => 1,
			)
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			return $terms[ 0 ];
		}

		return null;
	}

	/**
	 * Assign media to a folder.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $folder_id     Folder term ID.
	 * @return bool
	 */
	public function assign_to_folder( int $attachment_id, int $folder_id ): bool {
		// Verify the attachment exists.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		// Verify the folder term exists.
		$term = get_term( $folder_id, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		// Use wp_set_object_terms with integer term IDs.
		// Match VMF's approach: append=true to add to existing folders.
		$result = wp_set_object_terms( $attachment_id, array( $folder_id ), self::TAXONOMY, true );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		// Clear any AI suggestion metadata (same as VMF does).
		delete_post_meta( $attachment_id, '_vmfo_folder_suggestions' );
		delete_post_meta( $attachment_id, '_vmfo_suggestions_dismissed' );

		/**
		 * Fires when media is moved to a folder.
		 * This is the same action VMF uses for compatibility.
		 *
		 * @param int   $attachment_id The attachment ID.
		 * @param int   $folder_id     The folder term ID.
		 * @param array $result        The term IDs that were set.
		 */
		do_action( 'vmfo_media_moved', $attachment_id, $folder_id, $result );

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
		$attachment_id = $result[ 'attachment_id' ] ?? 0;

		if ( empty( $attachment_id ) ) {
			return false;
		}

		if ( 'assign' === $result[ 'action' ] && ! empty( $result[ 'folder_id' ] ) ) {
			return $this->assign_to_folder( $attachment_id, $result[ 'folder_id' ] );
		}

		if ( 'create' === $result[ 'action' ] && ! empty( $result[ 'new_folder_path' ] ) ) {
			// Re-check for hierarchy conflicts at apply time (folders may have been created since scan).
			// Refresh folder paths to get latest state.
			$folder_paths = $this->get_folder_paths( true );

			// If the exact path now exists, just assign to it.
			if ( isset( $folder_paths[ $result[ 'new_folder_path' ] ] ) ) {
				return $this->assign_to_folder( $attachment_id, $folder_paths[ $result[ 'new_folder_path' ] ] );
			}

			// Check for hierarchy inversions against now-existing folders.
			$conflict = $this->detect_hierarchy_conflict( $result[ 'new_folder_path' ] );
			if ( $conflict[ 'conflict' ] && ! empty( $conflict[ 'existing_path' ] ) ) {
				// Use the existing path instead to prevent inversion.
				if ( isset( $folder_paths[ $conflict[ 'existing_path' ] ] ) ) {
					return $this->assign_to_folder( $attachment_id, $folder_paths[ $conflict[ 'existing_path' ] ] );
				}
				// If existing path is also not in DB, create that one instead.
				$folder_id = $this->create_folder_from_path( $conflict[ 'existing_path' ] );
				if ( $folder_id ) {
					return $this->assign_to_folder( $attachment_id, $folder_id );
				}
				return false;
			}

			// No conflict, create the folder.
			$folder_id = $this->create_folder_from_path( $result[ 'new_folder_path' ] );
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
