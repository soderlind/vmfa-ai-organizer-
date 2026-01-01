<?php
/**
 * REST controller for Exo AI provider endpoints.
 *
 * Provides endpoints to check Exo health and list available models
 * for the settings page dynamic UI.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace VmfaAiOrganizer\REST;

/**
 * REST controller for Exo health check and model listing.
 */
class ExoController {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'vmfa/v1';

	/**
	 * Register Exo endpoints.
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
			'/exo-health',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_health' ),
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

		register_rest_route(
			$this->namespace,
			'/exo-models',
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
	 * Check Exo health by pinging the models endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function check_health( \WP_REST_Request $request ): \WP_REST_Response {
		$endpoint = $request->get_param( 'endpoint' );

		if ( empty( $endpoint ) ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => __( 'Endpoint is required.', 'vmfa-ai-organizer' ),
				),
				400
			);
		}

		$url      = rtrim( $endpoint, '/' ) . '/v1/models';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => $response->get_error_message(),
				),
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => sprintf(
						/* translators: %d: HTTP status code */
						__( 'HTTP %d: Unable to connect to Exo', 'vmfa-ai-organizer' ),
						$code
					),
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'status'  => 'ok',
				'message' => __( 'Connected', 'vmfa-ai-organizer' ),
			),
			200
		);
	}

	/**
	 * Get available models from Exo cluster.
	 *
	 * Fetches models from /v1/models and cross-references with /state
	 * to show only currently running/loaded models.
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

		// Fetch available models.
		$models_response = wp_remote_get(
			$base_url . '/v1/models',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $models_response ) ) {
			return new \WP_REST_Response(
				array(
					'error'  => $models_response->get_error_message(),
					'models' => array(),
				),
				200
			);
		}

		$code = wp_remote_retrieve_response_code( $models_response );
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

		$body = wp_remote_retrieve_body( $models_response );
		$data = json_decode( $body, true );

		$models = array();

		if ( isset( $data[ 'data' ] ) && is_array( $data[ 'data' ] ) ) {
			// OpenAI-compatible format.
			foreach ( $data[ 'data' ] as $model ) {
				if ( isset( $model[ 'id' ] ) ) {
					$models[] = array(
						'id'   => $model[ 'id' ],
						'name' => $model[ 'id' ],
					);
				}
			}
		} elseif ( isset( $data[ 'models' ] ) && is_array( $data[ 'models' ] ) ) {
			// Alternative format.
			foreach ( $data[ 'models' ] as $model ) {
				if ( is_string( $model ) ) {
					$models[] = array(
						'id'   => $model,
						'name' => $model,
					);
				} elseif ( isset( $model[ 'id' ] ) ) {
					$models[] = array(
						'id'   => $model[ 'id' ],
						'name' => $model[ 'name' ] ?? $model[ 'id' ],
					);
				}
			}
		}

		// Try to get running models from /state endpoint (Exo-specific).
		$state_response = wp_remote_get(
			$base_url . '/state',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 5,
			)
		);

		if ( ! is_wp_error( $state_response ) && 200 === wp_remote_retrieve_response_code( $state_response ) ) {
			$state_body = wp_remote_retrieve_body( $state_response );
			$state_data = json_decode( $state_body, true );

			// If we have running models from state, filter to only show those.
			if ( isset( $state_data[ 'running_models' ] ) && is_array( $state_data[ 'running_models' ] ) ) {
				$running_ids = array_map(
					function ( $m ) {
						return is_string( $m ) ? $m : ( $m[ 'id' ] ?? '' );
					},
					$state_data[ 'running_models' ]
				);

				if ( ! empty( $running_ids ) ) {
					$models = array_filter(
						$models,
						function ( $m ) use ( $running_ids ) {
							return in_array( $m[ 'id' ], $running_ids, true );
						}
					);
					$models = array_values( $models );
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'models' => $models,
			),
			200
		);
	}
}
