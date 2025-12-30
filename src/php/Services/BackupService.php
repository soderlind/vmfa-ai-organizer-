<?php
/**
 * Backup Service.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\Services;

/**
 * Service for backing up and restoring folder structure.
 */
class BackupService {

	/**
	 * VMF folder taxonomy name.
	 */
	private const TAXONOMY = 'vmfo_folder';

	/**
	 * Backup option name.
	 */
	private const BACKUP_OPTION = 'vmfo_reorganize_backup';

	/**
	 * Export current folder structure and assignments.
	 *
	 * @return bool True on success.
	 */
	public function export(): bool {
		global $wpdb;

		// Get all folders with hierarchy.
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		$folders = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$folders[] = array(
					'term_id' => $term->term_id,
					'name'    => $term->name,
					'slug'    => $term->slug,
					'parent'  => $term->parent,
					'order'   => get_term_meta( $term->term_id, 'vmfo_order', true ),
				);
			}
		}

		// Get all media-folder assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tr.object_id, tt.term_id 
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s",
				self::TAXONOMY
			),
			ARRAY_A
		);

		$backup = array(
			'folders'     => $folders,
			'assignments' => $assignments ?: array(),
			'timestamp'   => time(),
			'version'     => VMFA_AI_ORGANIZER_VERSION,
		);

		return update_option( self::BACKUP_OPTION, $backup, false );
	}

	/**
	 * Check if a backup exists.
	 *
	 * @return bool
	 */
	public function has_backup(): bool {
		$backup = get_option( self::BACKUP_OPTION );
		return ! empty( $backup ) && is_array( $backup );
	}

	/**
	 * Get backup information.
	 *
	 * @return array{
	 *     exists: bool,
	 *     timestamp: int|null,
	 *     folder_count: int,
	 *     assignment_count: int,
	 *     version: string|null
	 * }
	 */
	public function get_backup_info(): array {
		$backup = get_option( self::BACKUP_OPTION );

		if ( empty( $backup ) || ! is_array( $backup ) ) {
			return array(
				'exists'           => false,
				'timestamp'        => null,
				'folder_count'     => 0,
				'assignment_count' => 0,
				'version'          => null,
			);
		}

		return array(
			'exists'           => true,
			'timestamp'        => $backup['timestamp'] ?? null,
			'folder_count'     => count( $backup['folders'] ?? array() ),
			'assignment_count' => count( $backup['assignments'] ?? array() ),
			'version'          => $backup['version'] ?? null,
		);
	}

	/**
	 * Restore folder structure from backup.
	 *
	 * @return array{
	 *     success: bool,
	 *     folders_restored: int,
	 *     assignments_restored: int,
	 *     error: string|null
	 * }
	 */
	public function restore(): array {
		$backup = get_option( self::BACKUP_OPTION );

		if ( empty( $backup ) || ! is_array( $backup ) ) {
			return array(
				'success'              => false,
				'folders_restored'     => 0,
				'assignments_restored' => 0,
				'error'                => __( 'No backup found.', 'vmfa-ai-organizer' ),
			);
		}

		// First, remove all current folders and assignments.
		$this->remove_all_folders();

		// Map old term IDs to new term IDs.
		$id_map = array();

		// Restore folders, respecting hierarchy.
		// Sort by parent to ensure parents are created first.
		$folders = $backup['folders'] ?? array();
		usort(
			$folders,
			function ( $a, $b ) {
				return $a['parent'] <=> $b['parent'];
			}
		);

		$folders_restored = 0;
		foreach ( $folders as $folder ) {
			$parent_id = 0;

			// Map old parent ID to new parent ID.
			if ( $folder['parent'] > 0 && isset( $id_map[ $folder['parent'] ] ) ) {
				$parent_id = $id_map[ $folder['parent'] ];
			}

			$result = wp_insert_term(
				$folder['name'],
				self::TAXONOMY,
				array(
					'slug'   => $folder['slug'],
					'parent' => $parent_id,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$new_id                       = $result['term_id'];
				$id_map[ $folder['term_id'] ] = $new_id;

				// Restore order metadata.
				if ( isset( $folder['order'] ) && '' !== $folder['order'] ) {
					update_term_meta( $new_id, 'vmfo_order', $folder['order'] );
				}

				++$folders_restored;
			}
		}

		// Restore assignments.
		$assignments          = $backup['assignments'] ?? array();
		$assignments_restored = 0;

		foreach ( $assignments as $assignment ) {
			$attachment_id = (int) $assignment['object_id'];
			$old_term_id   = (int) $assignment['term_id'];

			// Map old term ID to new term ID.
			if ( ! isset( $id_map[ $old_term_id ] ) ) {
				continue;
			}

			$new_term_id = $id_map[ $old_term_id ];

			$result = wp_set_object_terms( $attachment_id, array( $new_term_id ), self::TAXONOMY, true );

			if ( ! is_wp_error( $result ) ) {
				++$assignments_restored;
			}
		}

		return array(
			'success'              => true,
			'folders_restored'     => $folders_restored,
			'assignments_restored' => $assignments_restored,
			'error'                => null,
		);
	}

	/**
	 * Remove all folder assignments from all media.
	 *
	 * @return int Number of attachments processed.
	 */
	public function remove_all_assignments(): int {
		global $wpdb;

		// Get all attachment IDs that have folder assignments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tr.object_id 
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				WHERE tt.taxonomy = %s",
				self::TAXONOMY
			)
		);

		$count = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			wp_set_object_terms( (int) $attachment_id, array(), self::TAXONOMY );
			++$count;
		}

		return $count;
	}

	/**
	 * Remove all folders.
	 *
	 * @return int Number of folders deleted.
	 */
	public function remove_all_folders(): int {
		// First remove all assignments.
		$this->remove_all_assignments();

		// Get all folder term IDs.
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $terms as $term_id ) {
			// Delete folder order metadata.
			delete_term_meta( $term_id, 'vmfo_order' );

			// Delete the term.
			$result = wp_delete_term( $term_id, self::TAXONOMY );
			if ( $result && ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		// Clear caches.
		wp_cache_delete( 'all_ids', self::TAXONOMY );
		delete_transient( 'vmfo_folder_counts' );

		return $count;
	}

	/**
	 * Delete the backup.
	 *
	 * @return bool
	 */
	public function cleanup(): bool {
		return delete_option( self::BACKUP_OPTION );
	}
}
