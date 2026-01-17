<?php
/**
 * Plugin Name:       Virtual Media Folders - AI Organizer
 * Plugin URI:        https://github.com/soderlind/vmfa-ai-organizer
 * Description:       AI-powered media organization add-on for Virtual Media Folders. Scans the WordPress Media Library and uses AI to place media in suitable virtual folders.
 * Version:           0.5.1
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Requires Plugins:  virtual-media-folders
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vmfa-ai-organizer
 * Domain Path:       /languages
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer;

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'VMFA_AI_ORGANIZER_VERSION', '0.5.1' );
define( 'VMFA_AI_ORGANIZER_FILE', __FILE__ );
define( 'VMFA_AI_ORGANIZER_PATH', plugin_dir_path( __FILE__ ) );
define( 'VMFA_AI_ORGANIZER_URL', plugin_dir_url( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( VMFA_AI_ORGANIZER_PATH . 'vendor/autoload.php' ) ) {
	require_once VMFA_AI_ORGANIZER_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	// Initialize Action Scheduler if not already loaded.
	if ( ! class_exists( 'ActionScheduler' ) && file_exists( VMFA_AI_ORGANIZER_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
		require_once VMFA_AI_ORGANIZER_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	}
	// Update checker via GitHub releases.
	Update\GitHubPluginUpdater::create_with_assets(
		'https://github.com/soderlind/vmfa-ai-organizer',
		__FILE__,
		'vmfa-ai-organizer',
		'/vmfa-ai-organizer\.zip/',
		'main'
	);
	// Boot the plugin.
	Plugin::get_instance()->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );

/**
 * Activation hook.
 *
 * @return void
 */
function activate(): void {
	// Set default options if not exists.
	if ( false === get_option( 'vmfa_ai_organizer_settings' ) ) {
		update_option(
			'vmfa_ai_organizer_settings',
			array(
				'ai_provider'       => '',
				'max_folder_depth'  => 3,
				'allow_new_folders' => false,
				'batch_size'        => 20,
			)
		);
	}

	// Initialize scan progress option.
	if ( false === get_option( 'vmfa_scan_progress' ) ) {
		update_option(
			'vmfa_scan_progress',
			array(
				'status'    => 'idle',
				'mode'      => '',
				'total'     => 0,
				'processed' => 0,
				'results'   => array(),
			)
		);
	}
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function deactivate(): void {
	// Unschedule all pending actions.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'vmfa_process_media_batch' );
		as_unschedule_all_actions( 'vmfa_apply_assignments' );
		as_unschedule_all_actions( 'vmfa_finalize_scan' );
	}

	// Reset scan progress.
	update_option(
		'vmfa_scan_progress',
		array(
			'status'    => 'idle',
			'mode'      => '',
			'total'     => 0,
			'processed' => 0,
			'results'   => array(),
		)
	);
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
