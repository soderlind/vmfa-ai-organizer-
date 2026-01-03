<?php
/**
 * Ollama AI Provider (local).
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\AI;

/**
 * Ollama-based folder suggestion provider for local LLM inference.
 */
class OllamaProvider extends AbstractProvider {

	/**
	 * Default Ollama API URL.
	 */
	private const DEFAULT_URL = 'http://localhost:11434';

	/**
	 * Default timeout in seconds (can be overridden via settings).
	 *
	 * @var int
	 */
	protected const REQUEST_TIMEOUT = 120;

	/**
	 * Cached configuration status for this request.
	 *
	 * @var bool|null
	 */
	private ?bool $is_configured_cache = null;

	/**
	 * Get the request timeout from settings.
	 *
	 * @return int
	 */
	protected function get_request_timeout(): int {
		$timeout = $this->get_setting( 'ollama_timeout' );
		return $timeout ? (int) $timeout : static::REQUEST_TIMEOUT;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'ollama';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Ollama (Local)', 'vmfa-ai-organizer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function analyze(
		array $media_metadata,
		array $folder_paths,
		int $max_depth,
		bool $allow_new_folders,
		?array $image_data = null,
		array $suggested_folders = array()
	): array {
		if ( ! $this->is_configured() ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => __( 'Ollama is not configured or not running.', 'vmfa-ai-organizer' ),
			);
		}

		$base_url = $this->get_setting( 'ollama_url' ) ?: self::DEFAULT_URL;
		$model    = $this->get_setting( 'ollama_model' ) ?: 'llama3.2-vision:latest';

		$user_prompt = $this->build_user_prompt( $media_metadata, $folder_paths, $max_depth, $allow_new_folders, $suggested_folders );

		// Build user message - Ollama vision uses 'images' array.
		$user_message = array(
			'role'    => 'user',
			'content' => $user_prompt,
		);

		// Add images for vision-capable models like llava.
		if ( null !== $image_data && ! empty( $image_data[ 'base64' ] ) ) {
			$user_message[ 'images' ] = array( $image_data[ 'base64' ] );
		}

		// Define JSON schema for structured output - forces valid JSON response.
		$json_schema = array(
			'type'       => 'object',
			'properties' => array(
				'action'          => array(
					'type' => 'string',
					'enum' => array( 'existing', 'new', 'skip' ),
				),
				'folder_id'       => array(
					'type' => array( 'integer', 'null' ),
				),
				'folder_path'     => array(
					'type'      => array( 'string', 'null' ),
					'maxLength' => 200,
				),
				'new_folder_path' => array(
					'type'      => array( 'string', 'null' ),
					'maxLength' => 100,
				),
				'confidence'      => array(
					'type'    => 'number',
					'minimum' => 0,
					'maximum' => 1,
				),
				'reason'          => array(
					'type'        => 'string',
					'maxLength'   => 150,
					'description' => 'Brief explanation, max 2 sentences',
				),
			),
			'required'   => array( 'action', 'folder_id', 'folder_path', 'new_folder_path', 'confidence', 'reason' ),
		);

		$response = $this->make_request(
			rtrim( $base_url, '/' ) . '/api/chat',
			array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'system',
						'content' => $this->get_system_prompt(),
					),
					$user_message,
				),
				'stream'   => false,
				'format'   => $json_schema,
				'options'  => array(
					'temperature' => 0.3,
					'num_predict' => 300,
				),
			)
		);

		if ( ! $response[ 'success' ] ) {
			return array(
				'action'          => 'skip',
				'folder_id'       => null,
				'new_folder_path' => null,
				'confidence'      => 0.0,
				'reason'          => sprintf(
					/* translators: %s: error message */
					__( 'Ollama error: %s', 'vmfa-ai-organizer' ),
					$response[ 'error' ]
				),
			);
		}

		$content = $response[ 'data' ][ 'message' ][ 'content' ] ?? '';

		return $this->parse_response( $content, $folder_paths );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test( array $settings ): ?string {
		$base_url = $settings[ 'ollama_url' ] ?? self::DEFAULT_URL;
		$model    = $settings[ 'ollama_model' ] ?? 'llama3.2-vision:latest';

		// First check if Ollama is running.
		$ping_response = wp_remote_get(
			rtrim( $base_url, '/' ) . '/api/tags',
			array( 'timeout' => 5 )
		);

		if ( is_wp_error( $ping_response ) ) {
			return sprintf(
				/* translators: %s: Ollama URL */
				__( 'Cannot connect to Ollama at %s. Is it running?', 'vmfa-ai-organizer' ),
				$base_url
			);
		}

		$status_code = wp_remote_retrieve_response_code( $ping_response );
		if ( 200 !== $status_code ) {
			return __( 'Ollama is not responding correctly.', 'vmfa-ai-organizer' );
		}

		// Check if model is available.
		$body   = wp_remote_retrieve_body( $ping_response );
		$data   = json_decode( $body, true );
		$models = array_column( $data[ 'models' ] ?? array(), 'name' );

		// Model names may include :latest suffix.
		// Check for exact match or match with common suffixes like ":latest".
		$model_found = false;
		foreach ( $models as $available_model ) {
			// Exact match.
			if ( $available_model === $model ) {
				$model_found = true;
				break;
			}
			// Match without ":latest" suffix (e.g., "llama3.2" matches "llama3.2:latest").
			if ( $available_model === $model . ':latest' ) {
				$model_found = true;
				break;
			}
			// Match base name without tag (e.g., "llama3.2:latest" matches "llama3.2:latest").
			$base_model     = explode( ':', $model )[ 0 ];
			$base_available = explode( ':', $available_model )[ 0 ];
			if ( $base_model === $base_available ) {
				$model_found = true;
				break;
			}
		}

		if ( ! $model_found && ! empty( $models ) ) {
			return sprintf(
				/* translators: 1: Model name, 2: Available models */
				__( 'Model "%1$s" not found. Available models: %2$s', 'vmfa-ai-organizer' ),
				$model,
				implode( ', ', $models )
			);
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_configured(): bool {
		// Return cached result if available (avoid repeated HTTP calls within same request).
		if ( null !== $this->is_configured_cache ) {
			return $this->is_configured_cache;
		}

		$base_url = $this->get_setting( 'ollama_url' ) ?: self::DEFAULT_URL;

		// Quick check if Ollama is running.
		$response = wp_remote_get(
			rtrim( $base_url, '/' ) . '/api/tags',
			array( 'timeout' => 2 )
		);

		$this->is_configured_cache = ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );

		return $this->is_configured_cache;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_available_models(): array {
		return array(
			// Vision-capable models (recommended for image analysis).
			'llama3.2-vision' => 'Llama 3.2 Vision (11B) - Recommended',
			'llava'           => 'LLaVA (7B) - Vision',
			'llava:13b'       => 'LLaVA (13B) - Vision',
			'llava:34b'       => 'LLaVA (34B) - Vision',
			'bakllava'        => 'BakLLaVA (7B) - Vision',
			'moondream'       => 'Moondream (1.8B) - Vision, Lightweight',
			// Text-only models (for metadata-based analysis).
			'llama3.2'        => 'Llama 3.2 (3B) - Text only',
			'llama3.2:1b'     => 'Llama 3.2 (1B) - Text only, Lightweight',
			'llama3.1'        => 'Llama 3.1 (8B) - Text only',
			'mistral'         => 'Mistral (7B) - Text only',
			'mixtral'         => 'Mixtral (8x7B) - Text only',
			'phi3'            => 'Phi-3 (3.8B) - Text only',
			'gemma2'          => 'Gemma 2 (9B) - Text only',
			'qwen2.5'         => 'Qwen 2.5 (7B) - Text only',
		);
	}
}
