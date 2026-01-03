<?php
/**
 * REST controller for Ollama AI provider endpoints.
 *
 * Provides endpoints to list available models for the settings page dynamic UI.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\REST;

/**
 * REST controller for Ollama model listing.
 */
class OllamaController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'vmfa/v1';

	/**
	 * Register Ollama endpoints.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/ollama-models',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_models' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'endpoint' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get available models from Ollama.
	 *
	 * Fetches models from /api/tags.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_models( \WP_REST_Request $request ): \WP_REST_Response {
		$endpoint = $request->get_param( 'endpoint' );

		if ( empty( $endpoint ) ) {
			return new \WP_REST_Response(
				array(
					'error'  => __( 'Endpoint is required.', 'vmfa-ai-organizer' ),
					'models' => array(),
				),
				400
			);
		}

		$base_url = rtrim( $endpoint, '/' );

		$tags_response = wp_remote_get(
			$base_url . '/api/tags',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $tags_response ) ) {
			return new \WP_REST_Response(
				array(
					'error'  => $tags_response->get_error_message(),
					'models' => array(),
				),
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $tags_response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_REST_Response(
				array(
					'error'  => sprintf(
						/* translators: %d: HTTP status code */
						__( 'HTTP %d: Unable to fetch models', 'vmfa-ai-organizer' ),
						$code
					),
					'models' => array(),
				),
				200
			);
		}

		$body = wp_remote_retrieve_body( $tags_response );
		$data = json_decode( $body, true );

		$names = array();
		if ( isset( $data[ 'models' ] ) && is_array( $data[ 'models' ] ) ) {
			foreach ( $data[ 'models' ] as $model ) {
				if ( is_array( $model ) && isset( $model[ 'name' ] ) && is_string( $model[ 'name' ] ) ) {
					$names[] = $model[ 'name' ];
				}
			}
		}

		$names = array_values( array_unique( array_filter( $names ) ) );
		sort( $names, SORT_NATURAL | SORT_FLAG_CASE );

		$models = array_map(
			static function ( string $name ): array {
				return array(
					'id'   => $name,
					'name' => $name,
				);
			},
			$names
		);

		return new \WP_REST_Response(
			array(
				'models' => $models,
			),
			200
		);
	}
}
