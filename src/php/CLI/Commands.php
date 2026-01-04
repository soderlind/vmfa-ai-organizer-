<?php
/**
 * WP-CLI Commands for VMFA AI Organizer.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\CLI;

use VmfaAiOrganizer\AI\ProviderFactory;
use VmfaAiOrganizer\Plugin;
use VmfaAiOrganizer\Services\AIAnalysisService;
use VmfaAiOrganizer\Services\BackupService;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Organize media library using AI-powered folder suggestions.
 *
 * ## EXAMPLES
 *
 *     # Start a preview scan of unassigned media
 *     $ wp vmfa-ai scan
 *
 *     # Watch scan progress with live updates
 *     $ wp vmfa-ai scan status --watch
 *
 *     # Apply the previewed changes
 *     $ wp vmfa-ai scan apply
 *
 *     # Analyze a single image
 *     $ wp vmfa-ai analyze 123
 *
 *     # Test the configured AI provider
 *     $ wp vmfa-ai provider test
 *
 * @package VmfaAiOrganizer\CLI
 */
class Commands {

	/**
	 * CLI parameter overrides for settings.
	 *
	 * @var array<string, mixed>
	 */
	private static array $cli_overrides = array();

	/**
	 * VMF folder taxonomy name.
	 */
	private const TAXONOMY = 'vmfo_folder';

	/**
	 * Set a CLI override value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Override value.
	 * @return void
	 */
	public static function set_override( string $key, mixed $value ): void {
		self::$cli_overrides[ $key ] = $value;
	}

	/**
	 * Get a CLI override value.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null Value or null if not set.
	 */
	public static function get_override( string $key ): mixed {
		return self::$cli_overrides[ $key ] ?? null;
	}

	/**
	 * Check if a CLI override exists.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function has_override( string $key ): bool {
		return array_key_exists( $key, self::$cli_overrides );
	}

	/**
	 * Clear all CLI overrides.
	 *
	 * @return void
	 */
	public static function clear_overrides(): void {
		self::$cli_overrides = array();
	}

	/**
	 * Check if Virtual Media Folders plugin is active.
	 *
	 * @return void
	 */
	public static function check_dependencies(): void {
		// Check if the vmfo_folder taxonomy exists (indicates VMF is active).
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			WP_CLI::error(
				WP_CLI::colorize(
					'%RVirtual Media Folders plugin is required but not active.%n' . "\n" .
					'Install it from: https://wordpress.org/plugins/virtual-media-folders/'
				)
			);
		}
	}

	/**
	 * Apply CLI parameter overrides from associative args.
	 *
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function apply_overrides( array $assoc_args ): void {
		$override_map = array(
			'provider' => 'ai_provider',
			'model'    => null, // Handled specially per provider.
			'api-key'  => null, // Handled specially per provider.
			'endpoint' => null, // Handled specially per provider.
			'timeout'  => null, // Handled specially per provider.
		);

		// Set provider override.
		if ( ! empty( $assoc_args['provider'] ) ) {
			self::set_override( 'ai_provider', $assoc_args['provider'] );
			$provider = $assoc_args['provider'];

			// Set model for the specific provider.
			if ( ! empty( $assoc_args['model'] ) ) {
				self::set_override( "{$provider}_model", $assoc_args['model'] );
			}

			// Set API key for the specific provider.
			if ( ! empty( $assoc_args['api-key'] ) ) {
				$key_field = match ( $provider ) {
					'openai'    => 'openai_key',
					'anthropic' => 'anthropic_key',
					'gemini'    => 'gemini_key',
					'grok'      => 'grok_key',
					default     => null,
				};
				if ( $key_field ) {
					self::set_override( $key_field, $assoc_args['api-key'] );
				}
			}

			// Set endpoint for providers that use it.
			if ( ! empty( $assoc_args['endpoint'] ) ) {
				$endpoint_field = match ( $provider ) {
					'ollama' => 'ollama_url',
					'exo'    => 'exo_endpoint',
					default  => null,
				};
				if ( $endpoint_field ) {
					self::set_override( $endpoint_field, $assoc_args['endpoint'] );
				}
			}

			// Set timeout.
			if ( ! empty( $assoc_args['timeout'] ) ) {
				self::set_override( "{$provider}_timeout", (int) $assoc_args['timeout'] );
			}
		}
	}

	/**
	 * Analyze a single media attachment.
	 *
	 * ## OPTIONS
	 *
	 * <attachment_id>
	 * : The attachment ID to analyze.
	 *
	 * [--apply]
	 * : Apply the suggested folder assignment immediately.
	 *
	 * [--provider=<provider>]
	 * : Override the AI provider.
	 *
	 * [--model=<model>]
	 * : Override the AI model.
	 *
	 * [--api-key=<key>]
	 * : Override the API key.
	 *
	 * [--endpoint=<url>]
	 * : Override the endpoint URL (for ollama/exo).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Analyze attachment 123
	 *     $ wp vmfa-ai analyze 123
	 *
	 *     # Analyze and apply the suggestion
	 *     $ wp vmfa-ai analyze 123 --apply
	 *
	 *     # Use a specific provider for this analysis
	 *     $ wp vmfa-ai analyze 123 --provider=openai --model=gpt-4o
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function analyze( array $args, array $assoc_args ): void {
		self::check_dependencies();
		self::apply_overrides( $assoc_args );

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide an attachment ID.' );
			return;
		}

		$attachment_id = absint( $args[0] );
		$apply         = Utils\get_flag_value( $assoc_args, 'apply', false );
		$format        = $assoc_args['format'] ?? 'table';
		$porcelain     = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		// Verify attachment exists.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			if ( $porcelain ) {
				WP_CLI::line( 'error:not_found' );
			} else {
				WP_CLI::error( "Attachment {$attachment_id} not found." );
			}
			return;
		}

		$analysis_service = new AIAnalysisService();

		if ( ! $porcelain ) {
			WP_CLI::line( WP_CLI::colorize( '%BAnalyzing attachment...%n' ) );
		}

		$result = $analysis_service->analyze_media( $attachment_id );

		if ( 'skip' === $result['action'] && ! empty( $result['reason'] ) ) {
			if ( $porcelain ) {
				WP_CLI::line( 'skip:' . sanitize_key( $result['reason'] ) );
			} else {
				WP_CLI::warning( 'Analysis skipped: ' . $result['reason'] );
			}
			return;
		}

		if ( $porcelain ) {
			WP_CLI::line( sprintf(
				'%s:%s:%.2f',
				$result['action'],
				$result['folder_name'] ?? $result['new_folder_path'] ?? '',
				$result['confidence'] ?? 0
			) );
		} elseif ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
		} elseif ( 'yaml' === $format ) {
			WP_CLI::line( \Spyc::YAMLDump( $result ) );
		} else {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( '%BAnalysis Result%n' ) );
			WP_CLI::line( str_repeat( '─', 50 ) );
			WP_CLI::line( sprintf( 'Attachment:  %d (%s)', $attachment_id, $result['filename'] ?? 'Unknown' ) );
			WP_CLI::line( sprintf( 'Action:      %s', self::format_action( $result['action'] ) ) );
			WP_CLI::line( sprintf( 'Folder:      %s', $result['folder_name'] ?? $result['new_folder_path'] ?? 'N/A' ) );
			WP_CLI::line( sprintf( 'Confidence:  %s', self::format_confidence( $result['confidence'] ?? 0 ) ) );
			WP_CLI::line( sprintf( 'Reason:      %s', $result['reason'] ?? 'N/A' ) );
			WP_CLI::line( '' );
		}

		// Apply if requested.
		if ( $apply && in_array( $result['action'], array( 'assign', 'create' ), true ) ) {
			$success = $analysis_service->apply_result( $result );

			if ( $porcelain ) {
				WP_CLI::line( $success ? 'applied' : 'failed' );
			} elseif ( $success ) {
				WP_CLI::success( 'Folder assignment applied.' );
			} else {
				WP_CLI::error( 'Failed to apply folder assignment.' );
			}
		}
	}

	/**
	 * Manage folder backup and restore.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Backup action to perform.
	 * ---
	 * options:
	 *   - export
	 *   - restore
	 *   - info
	 *   - delete
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation for restore/delete.
	 *
	 * [--format=<format>]
	 * : Output format for info.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create a backup
	 *     $ wp vmfa-ai backup export
	 *
	 *     # Show backup info
	 *     $ wp vmfa-ai backup info
	 *
	 *     # Restore from backup
	 *     $ wp vmfa-ai backup restore --yes
	 *
	 *     # Delete backup
	 *     $ wp vmfa-ai backup delete --yes
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function backup( array $args, array $assoc_args ): void {
		self::check_dependencies();

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify an action: export, restore, info, or delete.' );
			return;
		}

		$action    = $args[0];
		$yes       = Utils\get_flag_value( $assoc_args, 'yes', false );
		$format    = $assoc_args['format'] ?? 'table';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		$backup_service = new BackupService();

		switch ( $action ) {
			case 'export':
				$success = $backup_service->export();
				if ( $porcelain ) {
					WP_CLI::line( $success ? 'exported' : 'error' );
				} elseif ( $success ) {
					WP_CLI::success( 'Backup created successfully.' );
				} else {
					WP_CLI::error( 'Failed to create backup.' );
				}
				break;

			case 'info':
				$info = $backup_service->get_backup_info();

				if ( ! $info['exists'] ) {
					if ( $porcelain ) {
						WP_CLI::line( 'none' );
					} else {
						WP_CLI::warning( 'No backup found.' );
					}
					return;
				}

				if ( $porcelain ) {
					WP_CLI::line( sprintf( '%d:%d:%d', $info['timestamp'], $info['folder_count'], $info['assignment_count'] ) );
				} elseif ( 'json' === $format ) {
					WP_CLI::line( wp_json_encode( $info, JSON_PRETTY_PRINT ) );
				} elseif ( 'yaml' === $format ) {
					WP_CLI::line( \Spyc::YAMLDump( $info ) );
				} else {
					WP_CLI::line( '' );
					WP_CLI::line( WP_CLI::colorize( '%BBackup Info%n' ) );
					WP_CLI::line( str_repeat( '─', 50 ) );
					WP_CLI::line( sprintf( 'Created:     %s', gmdate( 'Y-m-d H:i:s', $info['timestamp'] ) ) );
					WP_CLI::line( sprintf( 'Folders:     %d', $info['folder_count'] ) );
					WP_CLI::line( sprintf( 'Assignments: %d', $info['assignment_count'] ) );
					WP_CLI::line( sprintf( 'Version:     %s', $info['version'] ?? 'N/A' ) );
					WP_CLI::line( '' );
				}
				break;

			case 'restore':
				if ( ! $backup_service->has_backup() ) {
					if ( $porcelain ) {
						WP_CLI::line( 'error:no_backup' );
					} else {
						WP_CLI::error( 'No backup found to restore.' );
					}
					return;
				}

				if ( ! $yes && ! $porcelain ) {
					$info = $backup_service->get_backup_info();
					WP_CLI::line( '' );
					WP_CLI::line( WP_CLI::colorize( '%YRestore Backup%n' ) );
					WP_CLI::line( str_repeat( '─', 50 ) );
					WP_CLI::line( sprintf( 'This will restore %d folders and %d assignments.', $info['folder_count'], $info['assignment_count'] ) );
					WP_CLI::line( WP_CLI::colorize( '%RAll current folders will be deleted!%n' ) );
					WP_CLI::line( '' );
					WP_CLI::confirm( 'Proceed with restore?' );
				}

				$result = $backup_service->restore();

				if ( $porcelain ) {
					if ( $result['success'] ) {
						WP_CLI::line( sprintf( 'restored:%d:%d', $result['folders_restored'], $result['assignments_restored'] ) );
					} else {
						WP_CLI::line( 'error:' . sanitize_key( $result['error'] ?? 'unknown' ) );
					}
				} elseif ( $result['success'] ) {
					WP_CLI::success( sprintf(
						'Restored %d folders and %d assignments.',
						$result['folders_restored'],
						$result['assignments_restored']
					) );
				} else {
					WP_CLI::error( $result['error'] ?? 'Restore failed.' );
				}
				break;

			case 'delete':
				if ( ! $backup_service->has_backup() ) {
					if ( $porcelain ) {
						WP_CLI::line( 'error:no_backup' );
					} else {
						WP_CLI::warning( 'No backup found to delete.' );
					}
					return;
				}

				if ( ! $yes && ! $porcelain ) {
					WP_CLI::confirm( 'Delete the backup? This cannot be undone.' );
				}

				$success = $backup_service->delete_backup();

				if ( $porcelain ) {
					WP_CLI::line( $success ? 'deleted' : 'error' );
				} elseif ( $success ) {
					WP_CLI::success( 'Backup deleted.' );
				} else {
					WP_CLI::error( 'Failed to delete backup.' );
				}
				break;

			default:
				WP_CLI::error( "Unknown action: {$action}. Use export, restore, info, or delete." );
		}
	}

	/**
	 * Manage AI providers.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Provider action to perform.
	 * ---
	 * options:
	 *   - list
	 *   - test
	 *   - info
	 * ---
	 *
	 * [--provider=<provider>]
	 * : Specific provider to test (defaults to configured provider).
	 *
	 * [--model=<model>]
	 * : Override the model for testing.
	 *
	 * [--api-key=<key>]
	 * : Override the API key for testing.
	 *
	 * [--endpoint=<url>]
	 * : Override the endpoint URL for testing.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all available providers
	 *     $ wp vmfa-ai provider list
	 *
	 *     # Test the configured provider
	 *     $ wp vmfa-ai provider test
	 *
	 *     # Test a specific provider
	 *     $ wp vmfa-ai provider test --provider=ollama --endpoint=http://localhost:11434
	 *
	 *     # Show info about current provider
	 *     $ wp vmfa-ai provider info
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function provider( array $args, array $assoc_args ): void {
		self::check_dependencies();
		self::apply_overrides( $assoc_args );

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify an action: list, test, or info.' );
			return;
		}

		$action    = $args[0];
		$format    = $assoc_args['format'] ?? 'table';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		switch ( $action ) {
			case 'list':
				$providers = ProviderFactory::get_available_providers();

				if ( $porcelain ) {
					foreach ( array_keys( $providers ) as $name ) {
						WP_CLI::line( $name );
					}
					return;
				}

				$current = Plugin::get_instance()->get_setting( 'ai_provider', '' );

				$display_data = array();
				foreach ( $providers as $name => $label ) {
					$provider      = ProviderFactory::get_provider( $name );
					$is_configured = $provider ? $provider->is_configured() : false;

					$display_data[] = array(
						'Name'       => $name,
						'Label'      => $label,
						'Configured' => $is_configured ? 'Yes' : 'No',
						'Active'     => $name === $current ? '✓' : '',
					);
				}

				Utils\format_items( $format, $display_data, array( 'Name', 'Label', 'Configured', 'Active' ) );
				break;

			case 'test':
				$provider_name = self::get_override( 'ai_provider' ) ?? Plugin::get_instance()->get_setting( 'ai_provider', '' );

				if ( empty( $provider_name ) ) {
					if ( $porcelain ) {
						WP_CLI::line( 'error:no_provider' );
					} else {
						WP_CLI::error( 'No AI provider configured. Use --provider to specify one or configure it in settings.' );
					}
					return;
				}

				$provider = ProviderFactory::get_provider( $provider_name );

				if ( ! $provider ) {
					if ( $porcelain ) {
						WP_CLI::line( 'error:invalid_provider' );
					} else {
						WP_CLI::error( "Provider '{$provider_name}' not found." );
					}
					return;
				}

				if ( ! $porcelain ) {
					WP_CLI::line( WP_CLI::colorize( "%BTesting {$provider_name} provider...%n" ) );
				}

				$result = $provider->test();

				if ( $porcelain ) {
					WP_CLI::line( $result['success'] ? 'ok' : 'error:' . sanitize_key( $result['message'] ?? 'unknown' ) );
				} elseif ( $result['success'] ) {
					WP_CLI::success( $result['message'] ?? 'Provider test successful!' );
				} else {
					WP_CLI::error( $result['message'] ?? 'Provider test failed.' );
				}
				break;

			case 'info':
				$provider_name = self::get_override( 'ai_provider' ) ?? Plugin::get_instance()->get_setting( 'ai_provider', '' );

				if ( empty( $provider_name ) ) {
					if ( $porcelain ) {
						WP_CLI::line( 'none' );
					} else {
						WP_CLI::warning( 'No AI provider configured.' );
					}
					return;
				}

				$provider = ProviderFactory::get_provider( $provider_name );

				if ( ! $provider ) {
					if ( $porcelain ) {
						WP_CLI::line( 'error:invalid_provider' );
					} else {
						WP_CLI::error( "Provider '{$provider_name}' not found." );
					}
					return;
				}

				$info = array(
					'provider'    => $provider_name,
					'label'       => $provider->get_label(),
					'configured'  => $provider->is_configured(),
				);

				// Add model info based on provider.
				$model_key         = "{$provider_name}_model";
				$info['model']     = Plugin::get_instance()->get_setting( $model_key, '' );

				if ( $porcelain ) {
					WP_CLI::line( sprintf( '%s:%s:%s', $info['provider'], $info['model'], $info['configured'] ? 'yes' : 'no' ) );
				} elseif ( 'json' === $format ) {
					WP_CLI::line( wp_json_encode( $info, JSON_PRETTY_PRINT ) );
				} elseif ( 'yaml' === $format ) {
					WP_CLI::line( \Spyc::YAMLDump( $info ) );
				} else {
					WP_CLI::line( '' );
					WP_CLI::line( WP_CLI::colorize( '%BProvider Info%n' ) );
					WP_CLI::line( str_repeat( '─', 50 ) );
					WP_CLI::line( sprintf( 'Provider:   %s (%s)', $info['provider'], $info['label'] ) );
					WP_CLI::line( sprintf( 'Model:      %s', $info['model'] ?: 'Default' ) );
					WP_CLI::line( sprintf( 'Configured: %s', $info['configured'] ? WP_CLI::colorize( '%GYes%n' ) : WP_CLI::colorize( '%RNo%n' ) ) );
					WP_CLI::line( '' );
				}
				break;

			default:
				WP_CLI::error( "Unknown action: {$action}. Use list, test, or info." );
		}
	}

	/**
	 * Show media library statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Output minimal machine-readable format.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp vmfa-ai stats
	 *     $ wp vmfa-ai stats --format=json
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function stats( array $args, array $assoc_args ): void {
		self::check_dependencies();

		$format    = $assoc_args['format'] ?? 'table';
		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );

		$analysis_service = new AIAnalysisService();

		$unassigned_ids = $analysis_service->get_unassigned_media_ids();
		$all_ids        = $analysis_service->get_all_media_ids();

		$total      = count( $all_ids );
		$unassigned = count( $unassigned_ids );
		$assigned   = $total - $unassigned;

		// Get folder count.
		$folders = get_terms( array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'fields'     => 'count',
		) );
		$folder_count = is_wp_error( $folders ) ? 0 : (int) $folders;

		$stats = array(
			'total_media'      => $total,
			'assigned_media'   => $assigned,
			'unassigned_media' => $unassigned,
			'folder_count'     => $folder_count,
		);

		if ( $porcelain ) {
			WP_CLI::line( sprintf( '%d:%d:%d:%d', $total, $assigned, $unassigned, $folder_count ) );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $stats, JSON_PRETTY_PRINT ) );
			return;
		}

		if ( 'yaml' === $format ) {
			WP_CLI::line( \Spyc::YAMLDump( $stats ) );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BMedia Library Statistics%n' ) );
		WP_CLI::line( str_repeat( '─', 50 ) );
		WP_CLI::line( sprintf( 'Total media:      %d', $total ) );
		WP_CLI::line( sprintf( 'Assigned:         %s', WP_CLI::colorize( '%G' . $assigned . '%n' ) ) );
		WP_CLI::line( sprintf( 'Unassigned:       %s', WP_CLI::colorize( '%Y' . $unassigned . '%n' ) ) );
		WP_CLI::line( sprintf( 'Folders:          %d', $folder_count ) );

		if ( $total > 0 ) {
			$percent = round( ( $assigned / $total ) * 100 );
			WP_CLI::line( sprintf( 'Organization:     %d%%', $percent ) );
			self::render_progress_bar( $assigned, $total );
		}

		WP_CLI::line( '' );
	}

	/**
	 * Render a progress bar.
	 *
	 * @param int $current Current progress.
	 * @param int $total   Total items.
	 * @return void
	 */
	public static function render_progress_bar( int $current, int $total ): void {
		if ( $total <= 0 ) {
			return;
		}

		$width   = 40;
		$percent = $current / $total;
		$filled  = (int) ( $width * $percent );
		$empty   = $width - $filled;

		$bar = WP_CLI::colorize( '%G' . str_repeat( '█', $filled ) . '%n' )
			. WP_CLI::colorize( '%K' . str_repeat( '░', $empty ) . '%n' );

		WP_CLI::line( "           [{$bar}]" );
	}

	/**
	 * Format a duration in seconds to human-readable string.
	 *
	 * @param int $seconds Duration in seconds.
	 * @return string Formatted duration.
	 */
	public static function format_duration( int $seconds ): string {
		if ( $seconds < 60 ) {
			return "{$seconds}s";
		}

		if ( $seconds < 3600 ) {
			$minutes = (int) ( $seconds / 60 );
			$secs    = $seconds % 60;
			return "{$minutes}m {$secs}s";
		}

		$hours   = (int) ( $seconds / 3600 );
		$minutes = (int) ( ( $seconds % 3600 ) / 60 );
		return "{$hours}h {$minutes}m";
	}

	/**
	 * Get human-readable mode label.
	 *
	 * @param string $mode Scan mode.
	 * @return string Mode label.
	 */
	public static function get_mode_label( string $mode ): string {
		return match ( $mode ) {
			'organize_unassigned' => 'Organize Unassigned',
			'reanalyze_all'       => 'Re-analyze All',
			'reorganize_all'      => 'Reorganize All',
			default               => ucfirst( str_replace( '_', ' ', $mode ) ),
		};
	}

	/**
	 * Truncate a string to a maximum length.
	 *
	 * @param string $string     String to truncate.
	 * @param int    $max_length Maximum length.
	 * @return string Truncated string.
	 */
	public static function truncate( string $string, int $max_length ): string {
		if ( mb_strlen( $string ) <= $max_length ) {
			return $string;
		}

		return mb_substr( $string, 0, $max_length - 1 ) . '…';
	}

	/**
	 * Format action for display.
	 *
	 * @param string $action Action name.
	 * @return string Formatted action.
	 */
	public static function format_action( string $action ): string {
		return match ( $action ) {
			'assign' => WP_CLI::colorize( '%GAssign to existing folder%n' ),
			'create' => WP_CLI::colorize( '%CCreate new folder%n' ),
			'skip'   => WP_CLI::colorize( '%YSkip%n' ),
			default  => $action,
		};
	}

	/**
	 * Format confidence for display.
	 *
	 * @param float $confidence Confidence value (0-1).
	 * @return string Formatted confidence.
	 */
	public static function format_confidence( float $confidence ): string {
		$percent = $confidence * 100;
		$color   = $confidence >= 0.8 ? '%G' : ( $confidence >= 0.5 ? '%Y' : '%R' );

		return WP_CLI::colorize( sprintf( '%s%.0f%%%n', $color, $percent ) );
	}
}
