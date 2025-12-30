<?php
/**
 * AI Provider Interface.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Interface for AI providers.
 */
interface ProviderInterface {

	/**
	 * Get the provider name.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Get the provider display label.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Analyze media and suggest folder assignment.
	 *
	 * @param array<string, mixed> $media_metadata   Media metadata (filename, alt, caption, description, mime_type, exif).
	 * @param array<string, int>   $folder_paths     Available folder paths mapped to term IDs.
	 * @param int                  $max_depth        Maximum folder depth allowed.
	 * @param bool                 $allow_new_folders Whether new folders can be proposed.
	 * @return array{
	 *     action: string,
	 *     folder_id: int|null,
	 *     new_folder_path: string|null,
	 *     confidence: float,
	 *     reason: string
	 * }
	 */
	public function analyze(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders
	): array;

	/**
	 * Test the provider configuration.
	 *
	 * @param array<string, mixed> $settings Provider settings to test.
	 * @return string|null Error message on failure, null on success.
	 */
	public function test( array $settings ): ?string;

	/**
	 * Check if the provider is configured and ready to use.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Get available models for this provider.
	 *
	 * @return array<string, string> Model ID => Display name.
	 */
	public function get_available_models(): array;
}
