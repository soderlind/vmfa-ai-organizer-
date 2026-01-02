<?php
/**
 * Admin Settings Page.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\Admin;

use VmfaAiOrganizer\AI\ProviderFactory;
use VmfaAiOrganizer\Plugin;

/**
 * Admin settings page integration with Virtual Media Folders.
 */
class SettingsPage {

	/**
	 * Option name for settings.
	 */
	private const OPTION_NAME = 'vmfa_ai_organizer_settings';

	/**
	 * Settings group name.
	 */
	private const SETTINGS_GROUP = 'vmfa_ai_organizer_settings_group';

	/**
	 * Configuration map for settings with environment/constant overrides.
	 *
	 * @var array<string, array{env: string, const: string, default: mixed}>
	 */
	private static array $config_map = array(
		'ai_provider'       => array(
			'env'     => 'VMFA_AI_PROVIDER',
			'const'   => 'VMFA_AI_PROVIDER',
			'default' => '',
		),
		'openai_type'       => array(
			'env'     => 'VMFA_AI_OPENAI_TYPE',
			'const'   => 'VMFA_AI_OPENAI_TYPE',
			'default' => 'openai',
		),
		'openai_key'        => array(
			'env'     => 'VMFA_AI_OPENAI_KEY',
			'const'   => 'VMFA_AI_OPENAI_KEY',
			'default' => '',
		),
		'openai_model'      => array(
			'env'     => 'VMFA_AI_OPENAI_MODEL',
			'const'   => 'VMFA_AI_OPENAI_MODEL',
			'default' => 'gpt-4o-mini',
		),
		'azure_endpoint'    => array(
			'env'     => 'VMFA_AI_AZURE_ENDPOINT',
			'const'   => 'VMFA_AI_AZURE_ENDPOINT',
			'default' => '',
		),
		'azure_api_version' => array(
			'env'     => 'VMFA_AI_AZURE_API_VERSION',
			'const'   => 'VMFA_AI_AZURE_API_VERSION',
			'default' => '2024-02-15-preview',
		),
		'anthropic_key'     => array(
			'env'     => 'VMFA_AI_ANTHROPIC_KEY',
			'const'   => 'VMFA_AI_ANTHROPIC_KEY',
			'default' => '',
		),
		'anthropic_model'   => array(
			'env'     => 'VMFA_AI_ANTHROPIC_MODEL',
			'const'   => 'VMFA_AI_ANTHROPIC_MODEL',
			'default' => 'claude-3-haiku-20240307',
		),
		'gemini_key'        => array(
			'env'     => 'VMFA_AI_GEMINI_KEY',
			'const'   => 'VMFA_AI_GEMINI_KEY',
			'default' => '',
		),
		'gemini_model'      => array(
			'env'     => 'VMFA_AI_GEMINI_MODEL',
			'const'   => 'VMFA_AI_GEMINI_MODEL',
			'default' => 'gemini-1.5-flash',
		),
		'ollama_url'        => array(
			'env'     => 'VMFA_AI_OLLAMA_URL',
			'const'   => 'VMFA_AI_OLLAMA_URL',
			'default' => 'http://localhost:11434',
		),
		'ollama_model'      => array(
			'env'     => 'VMFA_AI_OLLAMA_MODEL',
			'const'   => 'VMFA_AI_OLLAMA_MODEL',
			'default' => 'llama3.2',
		),
		'ollama_timeout'    => array(
			'env'     => 'VMFA_AI_OLLAMA_TIMEOUT',
			'const'   => 'VMFA_AI_OLLAMA_TIMEOUT',
			'default' => 120,
		),
		'grok_key'          => array(
			'env'     => 'VMFA_AI_GROK_KEY',
			'const'   => 'VMFA_AI_GROK_KEY',
			'default' => '',
		),
		'grok_model'        => array(
			'env'     => 'VMFA_AI_GROK_MODEL',
			'const'   => 'VMFA_AI_GROK_MODEL',
			'default' => 'grok-beta',
		),
		'exo_endpoint'      => array(
			'env'     => 'VMFA_AI_EXO_ENDPOINT',
			'const'   => 'VMFA_AI_EXO_ENDPOINT',
			'default' => '',
		),
		'exo_model'         => array(
			'env'     => 'VMFA_AI_EXO_MODEL',
			'const'   => 'VMFA_AI_EXO_MODEL',
			'default' => 'llama-3.2-3b',
		),
		'max_folder_depth'  => array(
			'env'     => 'VMFA_AI_MAX_FOLDER_DEPTH',
			'const'   => 'VMFA_AI_MAX_FOLDER_DEPTH',
			'default' => 3,
		),
		'allow_new_folders' => array(
			'env'     => 'VMFA_AI_ALLOW_NEW_FOLDERS',
			'const'   => 'VMFA_AI_ALLOW_NEW_FOLDERS',
			'default' => false,
		),
		'batch_size'        => array(
			'env'     => 'VMFA_AI_BATCH_SIZE',
			'const'   => 'VMFA_AI_BATCH_SIZE',
			'default' => 20,
		),
	);

	/**
	 * Initialize the settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add admin notices for validation.
		add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'upload.php',
			__( 'AI Organizer Settings', 'vmfa-ai-organizer' ),
			__( 'AI Organizer', 'vmfa-ai-organizer' ),
			'manage_options',
			'vmfa-ai-organizer',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( 'media_page_vmfa-ai-organizer' !== $hook_suffix ) {
			return;
		}

		$asset_file = VMFA_AI_ORGANIZER_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vmfa-ai-organizer-admin',
			VMFA_AI_ORGANIZER_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'vmfa-ai-organizer-admin',
			VMFA_AI_ORGANIZER_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_localize_script(
			'vmfa-ai-organizer-admin',
			'vmfaAiOrganizer',
			array(
				'restUrl'    => rest_url( 'vmfa/v1/' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'adminNonce' => wp_create_nonce( 'vmfa_admin_nonce' ),
				'providers'  => ProviderFactory::get_available_providers(),
			)
		);

		wp_set_script_translations(
			'vmfa-ai-organizer-admin',
			'vmfa-ai-organizer',
			VMFA_AI_ORGANIZER_PATH . 'languages'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Show save confirmation.
		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			add_settings_error(
				'vmfa_messages',
				'vmfa_message',
				__( 'Settings saved.', 'vmfa-ai-organizer' ),
				'updated'
			);
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'scanner'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'vmfa_messages' ); ?>

			<nav class="nav-tab-wrapper vmfa-nav-tabs">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'scanner', remove_query_arg( 'tab' ) ) ); ?>" 
				   class="nav-tab <?php echo 'scanner' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Media Scanner', 'vmfa-ai-organizer' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'settings', remove_query_arg( 'tab' ) ) ); ?>" 
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'vmfa-ai-organizer' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'provider', remove_query_arg( 'tab' ) ) ); ?>" 
				   class="nav-tab <?php echo 'provider' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'AI Provider', 'vmfa-ai-organizer' ); ?>
				</a>
			</nav>

			<div class="vmfa-tab-content">
				<?php if ( 'scanner' === $active_tab ) : ?>
					<div id="vmfa-ai-organizer-scanner">
						<!-- React component will mount here -->
						<noscript>
							<?php esc_html_e( 'JavaScript is required for the media scanner.', 'vmfa-ai-organizer' ); ?>
						</noscript>
					</div>
				<?php elseif ( 'settings' === $active_tab ) : ?>
					<form method="post" action="options.php" id="vmfa-ai-organizer-settings">
						<?php
						settings_fields( self::SETTINGS_GROUP );
						do_settings_sections( 'vmfa-ai-organizer-settings' );
						submit_button( __( 'Save Settings', 'vmfa-ai-organizer' ) );
						?>
					</form>
				<?php else : ?>
					<form method="post" action="options.php" id="vmfa-ai-organizer-provider">
						<?php
						settings_fields( self::SETTINGS_GROUP );
						do_settings_sections( 'vmfa-ai-organizer-provider' );
						submit_button( __( 'Save AI Settings', 'vmfa-ai-organizer' ) );
						?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_defaults(),
			)
		);

		// AI Provider section.
		add_settings_section(
			'vmfa_ai_provider_section',
			__( 'AI Provider', 'vmfa-ai-organizer' ),
			array( $this, 'render_provider_section' ),
			'vmfa-ai-organizer-provider'
		);

		add_settings_field(
			'ai_provider',
			__( 'Provider', 'vmfa-ai-organizer' ),
			array( $this, 'render_provider_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section'
		);

		// OpenAI settings.
		add_settings_field(
			'openai_type',
			__( 'OpenAI Type', 'vmfa-ai-organizer' ),
			array( $this, 'render_openai_type_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'provider' => 'openai',
			)
		);

		add_settings_field(
			'openai_key',
			__( 'API Key', 'vmfa-ai-organizer' ),
			array( $this, 'render_api_key_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'openai_key',
				'provider' => 'openai',
			)
		);

		add_settings_field(
			'openai_model',
			__( 'Model / Deployment', 'vmfa-ai-organizer' ),
			array( $this, 'render_openai_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'openai_model',
				'provider' => 'openai',
			)
		);

		add_settings_field(
			'azure_endpoint',
			__( 'Azure Endpoint', 'vmfa-ai-organizer' ),
			array( $this, 'render_azure_endpoint_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'provider' => 'openai',
			)
		);

		add_settings_field(
			'azure_api_version',
			__( 'Azure API Version', 'vmfa-ai-organizer' ),
			array( $this, 'render_azure_api_version_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'provider' => 'openai',
			)
		);

		// Anthropic settings.
		add_settings_field(
			'anthropic_key',
			__( 'Anthropic API Key', 'vmfa-ai-organizer' ),
			array( $this, 'render_api_key_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'anthropic_key',
				'provider' => 'anthropic',
			)
		);

		add_settings_field(
			'anthropic_model',
			__( 'Anthropic Model', 'vmfa-ai-organizer' ),
			array( $this, 'render_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'anthropic_model',
				'provider' => 'anthropic',
			)
		);

		// Gemini settings.
		add_settings_field(
			'gemini_key',
			__( 'Gemini API Key', 'vmfa-ai-organizer' ),
			array( $this, 'render_api_key_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'gemini_key',
				'provider' => 'gemini',
			)
		);

		add_settings_field(
			'gemini_model',
			__( 'Gemini Model', 'vmfa-ai-organizer' ),
			array( $this, 'render_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'gemini_model',
				'provider' => 'gemini',
			)
		);

		// Ollama settings.
		add_settings_field(
			'ollama_url',
			__( 'Ollama URL', 'vmfa-ai-organizer' ),
			array( $this, 'render_url_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'ollama_url',
				'provider' => 'ollama',
			)
		);

		add_settings_field(
			'ollama_model',
			__( 'Ollama Model', 'vmfa-ai-organizer' ),
			array( $this, 'render_ollama_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'ollama_model',
				'provider' => 'ollama',
			)
		);

		add_settings_field(
			'ollama_timeout',
			__( 'Ollama Timeout', 'vmfa-ai-organizer' ),
			array( $this, 'render_ollama_timeout_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'ollama_timeout',
				'provider' => 'ollama',
			)
		);

		// Grok settings.
		add_settings_field(
			'grok_key',
			__( 'Grok API Key', 'vmfa-ai-organizer' ),
			array( $this, 'render_api_key_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'grok_key',
				'provider' => 'grok',
			)
		);

		add_settings_field(
			'grok_model',
			__( 'Grok Model', 'vmfa-ai-organizer' ),
			array( $this, 'render_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section',
			array(
				'key'      => 'grok_model',
				'provider' => 'grok',
			)
		);

		// Exo settings.
		add_settings_field(
			'exo_endpoint',
			__( 'Exo Endpoint', 'vmfa-ai-organizer' ),
			array( $this, 'render_exo_endpoint_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section'
		);

		add_settings_field(
			'exo_model',
			__( 'Exo Model', 'vmfa-ai-organizer' ),
			array( $this, 'render_exo_model_field' ),
			'vmfa-ai-organizer-provider',
			'vmfa_ai_provider_section'
		);

		// Organization section.
		add_settings_section(
			'vmfa_organization_section',
			__( 'Organization Settings', 'vmfa-ai-organizer' ),
			array( $this, 'render_organization_section' ),
			'vmfa-ai-organizer-settings'
		);

		add_settings_field(
			'max_folder_depth',
			__( 'Maximum Folder Depth', 'vmfa-ai-organizer' ),
			array( $this, 'render_depth_field' ),
			'vmfa-ai-organizer-settings',
			'vmfa_organization_section'
		);

		add_settings_field(
			'allow_new_folders',
			__( 'Allow New Folders', 'vmfa-ai-organizer' ),
			array( $this, 'render_checkbox_field' ),
			'vmfa-ai-organizer-settings',
			'vmfa_organization_section',
			array(
				'key'         => 'allow_new_folders',
				'description' => __( 'Allow AI to suggest creating new folders when no suitable existing folder is found.', 'vmfa-ai-organizer' ),
			)
		);

		add_settings_field(
			'batch_size',
			__( 'Batch Size', 'vmfa-ai-organizer' ),
			array( $this, 'render_batch_size_field' ),
			'vmfa-ai-organizer-settings',
			'vmfa_organization_section'
		);
	}

	/**
	 * Add settings tab to VMF settings.
	 *
	 * @param array<string, string> $tabs Existing tabs.
	 * @return array<string, string>
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['ai_organizer'] = __( 'AI Organizer', 'vmfa-ai-organizer' );
		return $tabs;
	}

	/**
	 * Render provider section description.
	 *
	 * @return void
	 */
	public function render_provider_section(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Configure the AI provider to use for analyzing and organizing media files.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render organization section description.
	 *
	 * @return void
	 */
	public function render_organization_section(): void {
		?>
		<p class="description">
			<?php esc_html_e( 'Configure how media files should be organized into folders.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render provider selection field.
	 *
	 * @return void
	 */
	public function render_provider_field(): void {
		$settings   = $this->get_settings();
		$value      = $settings['ai_provider'];
		$providers  = ProviderFactory::get_available_providers();
		$is_locked  = $this->is_setting_locked( 'ai_provider' );

		?>
		<select 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ai_provider]" 
			id="vmfa_ai_provider"
			<?php disabled( $is_locked ); ?>
		>
			<?php foreach ( $providers as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->render_locked_badge( 'ai_provider' );
	}

	/**
	 * Render API key field.
	 *
	 * @param array{key: string, provider: string} $args Field arguments.
	 * @return void
	 */
	public function render_api_key_field( array $args ): void {
		$key       = $args['key'];
		$provider  = $args['provider'];
		$settings  = $this->get_settings();
		$value     = $settings[ $key ] ?? '';
		$is_locked = $this->is_setting_locked( $key );

		?>
		<input 
			type="password" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="vmfa_<?php echo esc_attr( $key ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text vmfa-provider-field"
			data-provider="<?php echo esc_attr( $provider ); ?>"
			autocomplete="off"
			<?php disabled( $is_locked ); ?>
		>
		<?php
		$this->render_locked_badge( $key );
	}

	/**
	 * Render OpenAI model/deployment field as text input.
	 *
	 * @param array{key: string, provider: string} $args Field arguments.
	 * @return void
	 */
	public function render_openai_model_field( array $args ): void {
		$key       = $args['key'];
		$provider  = $args['provider'];
		$settings  = $this->get_settings();
		$value     = $settings[ $key ] ?? 'gpt-4o-mini';
		$type      = $settings['openai_type'] ?? 'openai';
		$is_locked = $this->is_setting_locked( $key );

		// For Azure OpenAI: the "model" is actually the deployment name.
		// Azure doesn't support listing deployments via API key auth, so we use a text input.

		?>
		<input 
			type="text" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="vmfa_<?php echo esc_attr( $key ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text vmfa-provider-field"
			data-provider="<?php echo esc_attr( $provider ); ?>"
			placeholder="<?php echo esc_attr( 'azure' === $type ? 'your-deployment-name' : 'gpt-4o-mini' ); ?>"
			<?php disabled( $is_locked ); ?>
		>
		<p class="description">
			<?php if ( 'azure' === $type ) : ?>
				<?php esc_html_e( 'Enter your Azure OpenAI deployment name (found in Azure Portal → Your Resource → Deployments).', 'vmfa-ai-organizer' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'OpenAI model name (e.g., gpt-4o-mini, gpt-4o).', 'vmfa-ai-organizer' ); ?>
			<?php endif; ?>
		</p>
		<?php
		$this->render_locked_badge( $key );
	}

	/**
	 * Render Azure OpenAI deployments helper script (deprecated - Azure doesn't support listing via API key).
	 *
	 * @return void
	 */
	private function render_azure_deployments_script(): void {
		// Azure Resource Manager requires Azure AD auth to list deployments.
		// The data plane API doesn't support listing deployments with just an API key.
		// This function is kept for backwards compatibility but no longer renders anything.
	}

	/**
	 * Render model selection field.
	 *
	 * @param array{key: string, provider: string} $args Field arguments.
	 * @return void
	 */
	public function render_model_field( array $args ): void {
		$key       = $args['key'];
		$provider  = $args['provider'];
		$settings  = $this->get_settings();
		$value     = $settings[ $key ] ?? '';
		$is_locked = $this->is_setting_locked( $key );

		$provider_instance = ProviderFactory::get_provider( $provider );
		$models            = $provider_instance->get_available_models();

		?>
		<select 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="vmfa_<?php echo esc_attr( $key ); ?>"
			class="vmfa-provider-field"
			data-provider="<?php echo esc_attr( $provider ); ?>"
			<?php disabled( $is_locked ); ?>
		>
			<?php foreach ( $models as $model_key => $label ) : ?>
				<option value="<?php echo esc_attr( $model_key ); ?>" <?php selected( $value, $model_key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->render_locked_badge( $key );
	}

	/**
	 * Render URL field.
	 *
	 * @param array{key: string, provider: string} $args Field arguments.
	 * @return void
	 */
	public function render_url_field( array $args ): void {
		$key       = $args['key'];
		$provider  = $args['provider'];
		$settings  = $this->get_settings();
		$value     = $settings[ $key ] ?? '';
		$is_locked = $this->is_setting_locked( $key );

		?>
		<input 
			type="url" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
			id="vmfa_<?php echo esc_attr( $key ); ?>"
			value="<?php echo esc_url( $value ); ?>"
			class="regular-text vmfa-provider-field"
			data-provider="<?php echo esc_attr( $provider ); ?>"
			placeholder="http://localhost:11434"
			<?php disabled( $is_locked ); ?>
		>
		<?php
		$this->render_locked_badge( $key );
	}

	/**
	 * Render Exo endpoint field with health check button.
	 *
	 * @return void
	 */
	public function render_exo_endpoint_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['exo_endpoint'] ?? '';
		$is_locked = $this->is_setting_locked( 'exo_endpoint' );

		?>
		<div style="display: flex; align-items: center; gap: 8px;">
			<input 
				type="url" 
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[exo_endpoint]"
				id="vmfa_exo_endpoint"
				value="<?php echo esc_url( $value ); ?>"
				class="regular-text vmfa-provider-field"
				data-provider="exo"
				placeholder="http://localhost:52415"
				<?php disabled( $is_locked ); ?>
			>
			<span id="vmfa-exo-health-indicator" style="font-size: 18px;"
				title="<?php esc_attr_e( 'Connection status', 'vmfa-ai-organizer' ); ?>"></span>
			<button type="button" id="vmfa-exo-check-connection" class="button button-secondary">
				<?php esc_html_e( 'Check Connection', 'vmfa-ai-organizer' ); ?>
			</button>
		</div>
		<?php $this->render_locked_badge( 'exo_endpoint' ); ?>
		<p class="description">
			<?php esc_html_e( 'Your Exo cluster endpoint URL (e.g., http://localhost:52415). Exo is a distributed local LLM cluster.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Exo model field with dynamic model refresh.
	 *
	 * @return void
	 */
	public function render_exo_model_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['exo_model'] ?? '';
		$is_locked = $this->is_setting_locked( 'exo_model' );

		?>
		<select 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[exo_model]"
			id="vmfa_exo_model"
			class="vmfa-provider-field"
			data-provider="exo"
			<?php disabled( $is_locked ); ?>
		>
			<?php if ( ! empty( $value ) ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php else : ?>
				<option value=""><?php esc_html_e( '— Select a model —', 'vmfa-ai-organizer' ); ?></option>
			<?php endif; ?>
		</select>
		<button type="button" id="vmfa-exo-refresh-models" class="button button-secondary" style="margin-left: 4px;">
			<?php esc_html_e( 'Refresh Models', 'vmfa-ai-organizer' ); ?>
		</button>
		<?php if ( $is_locked ) : ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[exo_model]"
				value="<?php echo esc_attr( $value ); ?>" />
		<?php endif; ?>
		<?php $this->render_locked_badge( 'exo_model' ); ?>
		<p class="description">
			<?php esc_html_e( 'Select a model from your running Exo cluster. Click "Refresh Models" after entering the endpoint.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_exo_scripts();
	}

	/**
	 * Render Ollama model field with dynamic model refresh.
	 *
	 * @return void
	 */
	public function render_ollama_model_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['ollama_model'] ?? '';
		$is_locked = $this->is_setting_locked( 'ollama_model' );

		?>
		<select 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ollama_model]"
			id="vmfa_ollama_model"
			class="vmfa-provider-field"
			data-provider="ollama"
			<?php disabled( $is_locked ); ?>
		>
			<?php if ( ! empty( $value ) ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php else : ?>
				<option value=""><?php esc_html_e( '— Select a model —', 'vmfa-ai-organizer' ); ?></option>
			<?php endif; ?>
		</select>
		<button type="button" id="vmfa-ollama-refresh-models" class="button button-secondary" style="margin-left: 4px;">
			<?php esc_html_e( 'Refresh Models', 'vmfa-ai-organizer' ); ?>
		</button>
		<?php if ( $is_locked ) : ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ollama_model]"
				value="<?php echo esc_attr( $value ); ?>" />
		<?php endif; ?>
		<?php $this->render_locked_badge( 'ollama_model' ); ?>
		<p class="description">
			<?php esc_html_e( 'Select a model from your local Ollama instance. Click "Refresh Models" after entering the Ollama URL.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_ollama_scripts();
	}

	/**
	 * Render Ollama-specific JavaScript for model refresh.
	 *
	 * @return void
	 */
	private function render_ollama_scripts(): void {
		?>
		<script>
			(function() {
				const ollamaUrlField = document.getElementById('vmfa_ollama_url');
				const ollamaModelField = document.getElementById('vmfa_ollama_model');
				const ollamaRefreshBtn = document.getElementById('vmfa-ollama-refresh-models');

				async function refreshOllamaModels() {
					const endpoint = ollamaUrlField ? ollamaUrlField.value.trim() : '';
					if (!endpoint) {
						alert('<?php echo esc_js( __( 'Please enter the Ollama URL first.', 'vmfa-ai-organizer' ) ); ?>');
						return;
					}

					if (ollamaRefreshBtn) ollamaRefreshBtn.disabled = true;

					try {
						const response = await fetch('<?php echo esc_url( rest_url( 'vmfa/v1/ollama-models' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
							},
							body: JSON.stringify({ endpoint: endpoint })
						});
						const data = await response.json();

						if (data.models && Array.isArray(data.models) && ollamaModelField) {
							const currentValue = ollamaModelField.value;
							ollamaModelField.innerHTML = '';

							if (data.models.length === 0) {
								const opt = document.createElement('option');
								opt.value = '';
								opt.textContent = '<?php echo esc_js( __( '— No models available —', 'vmfa-ai-organizer' ) ); ?>';
								ollamaModelField.appendChild(opt);
							} else {
								data.models.forEach(model => {
									const opt = document.createElement('option');
									opt.value = model.id || model;
									opt.textContent = model.name || model.id || model;
									if (opt.value === currentValue) opt.selected = true;
									ollamaModelField.appendChild(opt);
								});
							}
						} else if (data.error) {
							alert('<?php echo esc_js( __( 'Failed to fetch models:', 'vmfa-ai-organizer' ) ); ?> ' + data.error);
						}
					} catch (e) {
						alert('<?php echo esc_js( __( 'Failed to fetch models:', 'vmfa-ai-organizer' ) ); ?> ' + e.message);
					} finally {
						if (ollamaRefreshBtn) ollamaRefreshBtn.disabled = false;
					}
				}

				if (ollamaRefreshBtn) {
					ollamaRefreshBtn.addEventListener('click', refreshOllamaModels);
				}
			})();
		</script>
		<?php
	}

	/**
	 * Render Ollama timeout field.
	 *
	 * @return void
	 */
	public function render_ollama_timeout_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['ollama_timeout'] ?? 120;
		$is_locked = $this->is_setting_locked( 'ollama_timeout' );

		?>
		<input 
			type="number" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[ollama_timeout]"
			id="vmfa_ollama_timeout"
			value="<?php echo esc_attr( (string) $value ); ?>"
			class="small-text vmfa-provider-field"
			data-provider="ollama"
			min="10"
			max="600"
			step="10"
			<?php disabled( $is_locked ); ?>
		>
		<span class="description"><?php esc_html_e( 'seconds', 'vmfa-ai-organizer' ); ?></span>
		<p class="description">
			<?php esc_html_e( 'Request timeout for Ollama. Increase for larger models or slower hardware (default: 120).', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'ollama_timeout' );
	}

	/**
	 * Render Exo-specific JavaScript for health check and model refresh.
	 *
	 * @return void
	 */
	private function render_exo_scripts(): void {
		?>
		<script>
			(function() {
				const exoEndpointField = document.getElementById('vmfa_exo_endpoint');
				const exoModelField = document.getElementById('vmfa_exo_model');
				const exoHealthIndicator = document.getElementById('vmfa-exo-health-indicator');
				const exoCheckBtn = document.getElementById('vmfa-exo-check-connection');
				const exoRefreshBtn = document.getElementById('vmfa-exo-refresh-models');

				async function checkExoHealth() {
					const endpoint = exoEndpointField ? exoEndpointField.value.trim() : '';
					if (!endpoint) {
						if (exoHealthIndicator) exoHealthIndicator.textContent = '';
						return;
					}

					if (exoHealthIndicator) exoHealthIndicator.textContent = '⏳';

					try {
						const response = await fetch('<?php echo esc_url( rest_url( 'vmfa/v1/exo-health' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
							},
							body: JSON.stringify({ endpoint: endpoint })
						});
						const data = await response.json();
						if (exoHealthIndicator) {
							exoHealthIndicator.textContent = data.status === 'ok' ? '✅' : '❌';
							exoHealthIndicator.title = data.status === 'ok' ? 'Connected' : (data.message || 'Connection failed');
						}
					} catch (e) {
						if (exoHealthIndicator) {
							exoHealthIndicator.textContent = '❌';
							exoHealthIndicator.title = 'Connection failed: ' + e.message;
						}
					}
				}

				async function refreshExoModels() {
					const endpoint = exoEndpointField ? exoEndpointField.value.trim() : '';
					if (!endpoint) {
						alert('<?php echo esc_js( __( 'Please enter the Exo endpoint first.', 'vmfa-ai-organizer' ) ); ?>');
						return;
					}

					if (exoRefreshBtn) exoRefreshBtn.disabled = true;

					try {
						const response = await fetch('<?php echo esc_url( rest_url( 'vmfa/v1/exo-models' ) ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>'
							},
							body: JSON.stringify({ endpoint: endpoint })
						});
						const data = await response.json();

						if (data.models && Array.isArray(data.models) && exoModelField) {
							const currentValue = exoModelField.value;
							exoModelField.innerHTML = '';

							if (data.models.length === 0) {
								const opt = document.createElement('option');
								opt.value = '';
								opt.textContent = '<?php echo esc_js( __( '— No models available —', 'vmfa-ai-organizer' ) ); ?>';
								exoModelField.appendChild(opt);
							} else {
								data.models.forEach(model => {
									const opt = document.createElement('option');
									opt.value = model.id || model;
									opt.textContent = model.name || model.id || model;
									if (opt.value === currentValue) opt.selected = true;
									exoModelField.appendChild(opt);
								});
							}

							checkExoHealth();
						} else if (data.error) {
							alert('<?php echo esc_js( __( 'Failed to fetch models:', 'vmfa-ai-organizer' ) ); ?> ' + data.error);
						}
					} catch (e) {
						alert('<?php echo esc_js( __( 'Failed to fetch models:', 'vmfa-ai-organizer' ) ); ?> ' + e.message);
					} finally {
						if (exoRefreshBtn) exoRefreshBtn.disabled = false;
					}
				}

				if (exoCheckBtn) {
					exoCheckBtn.addEventListener('click', checkExoHealth);
				}
				if (exoRefreshBtn) {
					exoRefreshBtn.addEventListener('click', refreshExoModels);
				}
			})();
		</script>
		<?php
	}

	/**
	 * Render depth field.
	 *
	 * @return void
	 */
	public function render_depth_field(): void {
		$settings  = $this->get_settings();
		$value     = (int) ( $settings['max_folder_depth'] ?? 3 );
		$is_locked = $this->is_setting_locked( 'max_folder_depth' );

		?>
		<input 
			type="number" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[max_folder_depth]"
			id="vmfa_max_folder_depth"
			value="<?php echo esc_attr( (string) $value ); ?>"
			min="1"
			max="5"
			class="small-text"
			<?php disabled( $is_locked ); ?>
		>
		<p class="description">
			<?php esc_html_e( 'Maximum depth for folder hierarchy (1-5). Higher values allow more nested folders.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'max_folder_depth' );
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array{key: string, description: string} $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$key         = $args['key'];
		$description = $args['description'];
		$settings    = $this->get_settings();
		$value       = (bool) ( $settings[ $key ] ?? false );
		$is_locked   = $this->is_setting_locked( $key );

		?>
		<label>
			<input 
				type="checkbox" 
				name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]"
				id="vmfa_<?php echo esc_attr( $key ); ?>"
				value="1"
				<?php checked( $value ); ?>
				<?php disabled( $is_locked ); ?>
			>
			<?php echo esc_html( $description ); ?>
		</label>
		<?php
		$this->render_locked_badge( $key );
	}

	/**
	 * Render batch size field.
	 *
	 * @return void
	 */
	public function render_batch_size_field(): void {
		$settings  = $this->get_settings();
		$value     = (int) ( $settings['batch_size'] ?? 20 );
		$is_locked = $this->is_setting_locked( 'batch_size' );

		?>
		<input 
			type="number" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[batch_size]"
			id="vmfa_batch_size"
			value="<?php echo esc_attr( (string) $value ); ?>"
			min="10"
			max="100"
			step="10"
			class="small-text"
			<?php disabled( $is_locked ); ?>
		>
		<p class="description">
			<?php esc_html_e( 'Number of media files to process in each batch (10-100). Lower values are safer for shared hosting.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'batch_size' );
	}

	/**
	 * Render OpenAI type selection field.
	 *
	 * @return void
	 */
	public function render_openai_type_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['openai_type'] ?? 'openai';
		$is_locked = $this->is_setting_locked( 'openai_type' );

		?>
		<select 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[openai_type]"
			id="vmfa_openai_type"
			class="vmfa-provider-field vmfa-openai-type-selector"
			data-provider="openai"
			<?php disabled( $is_locked ); ?>
		>
			<option value="openai" <?php selected( $value, 'openai' ); ?>>
				<?php esc_html_e( 'OpenAI', 'vmfa-ai-organizer' ); ?>
			</option>
			<option value="azure" <?php selected( $value, 'azure' ); ?>>
				<?php esc_html_e( 'Azure OpenAI', 'vmfa-ai-organizer' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select OpenAI or Azure OpenAI as your provider.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'openai_type' );
	}

	/**
	 * Render Azure endpoint field.
	 *
	 * @return void
	 */
	public function render_azure_endpoint_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['azure_endpoint'] ?? '';
		$is_locked = $this->is_setting_locked( 'azure_endpoint' );

		?>
		<input 
			type="url" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[azure_endpoint]"
			id="vmfa_azure_endpoint"
			value="<?php echo esc_url( $value ); ?>"
			class="regular-text vmfa-provider-field vmfa-azure-field"
			data-provider="openai"
			placeholder="https://your-resource.openai.azure.com"
			<?php disabled( $is_locked ); ?>
		>
		<p class="description">
			<?php esc_html_e( 'Your Azure OpenAI resource endpoint URL.', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'azure_endpoint' );
	}

	/**
	 * Render Azure API version field.
	 *
	 * @return void
	 */
	public function render_azure_api_version_field(): void {
		$settings  = $this->get_settings();
		$value     = $settings['azure_api_version'] ?? '2024-02-15-preview';
		$is_locked = $this->is_setting_locked( 'azure_api_version' );

		?>
		<input 
			type="text" 
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[azure_api_version]"
			id="vmfa_azure_api_version"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text vmfa-provider-field vmfa-azure-field"
			data-provider="openai"
			placeholder="2024-02-15-preview"
			<?php disabled( $is_locked ); ?>
		>
		<p class="description">
			<?php esc_html_e( 'Azure OpenAI API version (e.g., 2024-02-15-preview).', 'vmfa-ai-organizer' ); ?>
		</p>
		<?php
		$this->render_locked_badge( 'azure_api_version' );
	}

	/**
	 * Render locked badge if setting is overridden.
	 *
	 * @param string $key Setting key.
	 * @return void
	 */
	private function render_locked_badge( string $key ): void {
		$source = $this->get_override_source( $key );

		if ( $source ) {
			?>
			<span class="vmfa-locked-badge" title="<?php esc_attr_e( 'This setting is overridden', 'vmfa-ai-organizer' ); ?>">
				<?php
				if ( 'const' === $source ) {
					esc_html_e( '🔒 Constant', 'vmfa-ai-organizer' );
				} else {
					esc_html_e( '🔒 Environment', 'vmfa-ai-organizer' );
				}
				?>
			</span>
			<?php
		}
	}

	/**
	 * Check if a setting is locked by constant or environment variable.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	private function is_setting_locked( string $key ): bool {
		return null !== $this->get_override_source( $key );
	}

	/**
	 * Get the override source for a setting.
	 *
	 * @param string $key Setting key.
	 * @return string|null 'const', 'env', or null.
	 */
	private function get_override_source( string $key ): ?string {
		if ( ! isset( self::$config_map[ $key ] ) ) {
			return null;
		}

		$config = self::$config_map[ $key ];

		if ( defined( $config['const'] ) ) {
			return 'const';
		}

		$env_value = getenv( $config['env'] );
		if ( false !== $env_value && '' !== $env_value ) {
			return 'env';
		}

		return null;
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		return Plugin::get_instance()->get_settings();
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_defaults(): array {
		$defaults = array();

		foreach ( self::$config_map as $key => $config ) {
			$defaults[ $key ] = $config['default'];
		}

		return $defaults;
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string, mixed> $input Input settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Provider.
		if ( isset( $input['ai_provider'] ) ) {
			$sanitized['ai_provider'] = sanitize_key( $input['ai_provider'] );
			if ( ! ProviderFactory::provider_exists( $sanitized['ai_provider'] ) ) {
				$sanitized['ai_provider'] = '';
			}
		}

		// API keys.
		$key_fields = array( 'openai_key', 'anthropic_key', 'gemini_key', 'grok_key' );
		foreach ( $key_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		// OpenAI type (openai or azure).
		if ( isset( $input['openai_type'] ) ) {
			$sanitized['openai_type'] = in_array( $input['openai_type'], array( 'openai', 'azure' ), true )
				? $input['openai_type']
				: 'openai';
		}

		// Azure endpoint.
		if ( isset( $input['azure_endpoint'] ) ) {
			$sanitized['azure_endpoint'] = esc_url_raw( $input['azure_endpoint'] );
		}

		// Azure API version.
		if ( isset( $input['azure_api_version'] ) ) {
			$sanitized['azure_api_version'] = sanitize_text_field( $input['azure_api_version'] );
		}

		// Models.
		$model_fields = array( 'openai_model', 'anthropic_model', 'gemini_model', 'ollama_model', 'grok_model', 'exo_model' );
		foreach ( $model_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		// URLs.
		if ( isset( $input['ollama_url'] ) ) {
			$sanitized['ollama_url'] = esc_url_raw( $input['ollama_url'] );
		}
		if ( isset( $input['exo_endpoint'] ) ) {
			$sanitized['exo_endpoint'] = esc_url_raw( $input['exo_endpoint'] );
		}

		// Numeric fields.
		if ( isset( $input['max_folder_depth'] ) ) {
			$sanitized['max_folder_depth'] = max( 1, min( 5, absint( $input['max_folder_depth'] ) ) );
		}

		if ( isset( $input['batch_size'] ) ) {
			$sanitized['batch_size'] = max( 10, min( 100, absint( $input['batch_size'] ) ) );
		}

		if ( isset( $input['ollama_timeout'] ) ) {
			$sanitized['ollama_timeout'] = max( 10, min( 600, absint( $input['ollama_timeout'] ) ) );
		}

		// Checkbox.
		$sanitized['allow_new_folders'] = ! empty( $input['allow_new_folders'] );

		// Validate AI configuration if provider is set.
		if ( ! empty( $sanitized['ai_provider'] ) ) {
			$this->validate_ai_configuration( $sanitized );
		}

		// Require Azure endpoint + key when using Azure OpenAI.
		$effective_provider    = $sanitized['ai_provider'] ?? Plugin::get_instance()->get_setting( 'ai_provider', '' );
		$effective_openai_type = $sanitized['openai_type'] ?? Plugin::get_instance()->get_setting( 'openai_type', 'openai' );
		if ( 'openai' === $effective_provider && 'azure' === $effective_openai_type ) {
			$effective_endpoint = $sanitized['azure_endpoint'] ?? Plugin::get_instance()->get_setting( 'azure_endpoint', '' );
			$effective_key      = $sanitized['openai_key'] ?? Plugin::get_instance()->get_setting( 'openai_key', '' );

			if ( empty( $effective_endpoint ) || empty( $effective_key ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'azure_required',
					__( 'Azure OpenAI requires both an endpoint and an API key.', 'vmfa-ai-organizer' ),
					'error'
				);
				// Prevent saving an invalid configuration.
				return Plugin::get_instance()->get_settings();
			}
		}

		return $sanitized;
	}

	/**
	 * Validate AI configuration and add admin notice if invalid.
	 *
	 * @param array<string, mixed> $settings Settings to validate.
	 * @return void
	 */
	private function validate_ai_configuration( array $settings ): void {
		$provider_name = $settings['ai_provider'] ?? '';

		if ( empty( $provider_name ) ) {
			return;
		}

		$provider = ProviderFactory::get_provider( $provider_name );
		if ( null === $provider ) {
			return;
		}
		$error = $provider->test( $settings );

		if ( null !== $error ) {
			add_settings_error(
				self::OPTION_NAME,
				'ai_validation_error',
				sprintf(
					/* translators: %s: error message */
					__( 'AI Configuration Warning: %s', 'vmfa-ai-organizer' ),
					$error
				),
				'warning'
			);
		}
	}

	/**
	 * Show admin notices.
	 *
	 * @return void
	 */
	public function show_admin_notices(): void {
		settings_errors( self::OPTION_NAME );
	}
}
