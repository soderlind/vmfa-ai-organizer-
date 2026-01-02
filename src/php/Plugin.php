<?php
/**
 * Main plugin class.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer;

use VmfaAiOrganizer\Admin\SettingsPage;
use VmfaAiOrganizer\REST\AnalysisController;
use VmfaAiOrganizer\REST\ExoController;
use VmfaAiOrganizer\REST\OllamaController;
use VmfaAiOrganizer\Services\MediaScannerService;

/**
 * Plugin bootstrap class.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage|null
	 */
	private ?SettingsPage $settings_page = null;

	/**
	 * REST controller instance.
	 *
	 * @var AnalysisController|null
	 */
	private ?AnalysisController $rest_controller = null;

	/**
	 * Exo REST controller instance.
	 *
	 * @var ExoController|null
	 */
	private ?ExoController $exo_controller = null;

	/**
	 * Ollama REST controller instance.
	 *
	 * @var OllamaController|null
	 */
	private ?OllamaController $ollama_controller = null;

	/**
	 * Media scanner service instance.
	 *
	 * @var MediaScannerService|null
	 */
	private ?MediaScannerService $scanner_service = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_services();
		$this->init_hooks();

		// Load textdomain on init hook when locale is set.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'vmfa-ai-organizer',
			false,
			dirname( plugin_basename( VMFA_AI_ORGANIZER_FILE ) ) . '/languages'
		);
	}

	/**
	 * Initialize services.
	 *
	 * @return void
	 */
	private function init_services(): void {
		$this->settings_page   = new SettingsPage();
		$this->rest_controller = new AnalysisController();
		$this->exo_controller    = new ExoController();
		$this->ollama_controller = new OllamaController();
		$this->scanner_service = new MediaScannerService();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Admin hooks.
		if ( is_admin() ) {
			$this->settings_page->init();
		}

		// REST API hooks.
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		$this->exo_controller->register();
		$this->ollama_controller->register();

		// Action Scheduler hooks.
		$this->scanner_service->register_hooks();

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Only load on AI Organizer settings page.
		if ( 'media_page_vmfa-ai-organizer' !== $hook_suffix ) {
			return;
		}

		$asset_file = VMFA_AI_ORGANIZER_PATH . 'build/index.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
		} else {
			$asset = array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ),
				'version'      => VMFA_AI_ORGANIZER_VERSION,
			);
		}

		wp_enqueue_script(
			'vmfa-ai-organizer-admin',
			VMFA_AI_ORGANIZER_URL . 'build/index.js',
			$asset[ 'dependencies' ],
			$asset[ 'version' ],
			true
		);

		wp_enqueue_style(
			'vmfa-ai-organizer-admin',
			VMFA_AI_ORGANIZER_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset[ 'version' ]
		);

		wp_localize_script(
			'vmfa-ai-organizer-admin',
			'vmfaAiOrganizer',
			array(
				'restUrl'   => rest_url( 'vmfa/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'settings'  => $this->get_settings(),
				'hasBackup' => (bool) get_option( 'vmfo_reorganize_backup' ),
			)
		);

		wp_set_script_translations(
			'vmfa-ai-organizer-admin',
			'vmfa-ai-organizer',
			VMFA_AI_ORGANIZER_PATH . 'languages'
		);
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		$defaults = array(
			'ai_provider'       => '',
			'openai_key'        => '',
			'openai_model'      => 'gpt-4o-mini',
			'anthropic_key'     => '',
			'anthropic_model'   => 'claude-3-haiku-20240307',
			'gemini_key'        => '',
			'gemini_model'      => 'gemini-1.5-flash',
			'ollama_url'        => 'http://localhost:11434',
			'ollama_model'      => 'llama3.2',
			'ollama_timeout'    => 120,
			'grok_key'          => '',
			'grok_model'        => 'grok-beta',
			'exo_endpoint'      => '',
			'exo_model'         => '',
			'max_folder_depth'  => 3,
			'allow_new_folders' => false,
			'batch_size'        => 20,
		);

		$settings = get_option( 'vmfa_ai_organizer_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get a specific setting value with config resolution.
	 *
	 * Priority: constant > environment variable > database > default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( string $key, mixed $default = null ): mixed {
		$const_name = 'VMFA_AI_' . strtoupper( $key );
		$env_name   = 'VMFA_AI_' . strtoupper( $key );

		// Check for constant.
		if ( defined( $const_name ) ) {
			return constant( $const_name );
		}

		// Check for environment variable.
		$env_value = getenv( $env_name );
		if ( false !== $env_value && '' !== $env_value ) {
			return $env_value;
		}

		// Get from database.
		$settings = $this->get_settings();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get settings page instance.
	 *
	 * @return SettingsPage
	 */
	public function get_settings_page(): SettingsPage {
		return $this->settings_page;
	}

	/**
	 * Get scanner service instance.
	 *
	 * @return MediaScannerService
	 */
	public function get_scanner_service(): MediaScannerService {
		return $this->scanner_service;
	}

	/**
	 * Get REST controller instance.
	 *
	 * @return AnalysisController
	 */
	public function get_rest_controller(): AnalysisController {
		return $this->rest_controller;
	}
}
