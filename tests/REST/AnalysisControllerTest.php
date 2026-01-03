<?php
/**
 * Tests for AnalysisController REST route args.
 *
 * @package VmfaAiOrganizer
 */

declare(strict_types=1);

namespace {
	// Minimal WordPress REST stubs for unit tests.
	if ( ! class_exists( 'WP_REST_Controller' ) ) {
		class WP_REST_Controller {
		}
	}

	if ( ! class_exists( 'WP_REST_Server' ) ) {
		class WP_REST_Server {
			public const CREATABLE = 'POST';
			public const READABLE  = 'GET';
			public const DELETABLE = 'DELETE';
		}
	}
}

namespace VmfaAiOrganizer\Tests\REST {
	use Brain\Monkey\Functions;
	use VmfaAiOrganizer\REST\AnalysisController;
	use VmfaAiOrganizer\Services\MediaScannerService;
	use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;

	class AnalysisControllerTest extends BrainMonkeyTestCase {

		public function test_register_routes_mode_enum_matches_scanner_modes(): void {
			$calls = array();

			Functions\when( 'register_rest_route' )->alias(
				static function ( $namespace, $route, $args ) use ( &$calls ) {
					$calls[] = array(
						'namespace' => $namespace,
						'route'     => $route,
						'args'      => $args,
					);
					return true;
				}
			);

			$controller = new AnalysisController();
			$controller->register_routes();

			$expected_modes = ( new MediaScannerService() )->get_valid_modes();

			$scan_call  = $this->find_route_call( $calls, 'vmfa/v1', '/scan' );
			$apply_call = $this->find_route_call( $calls, 'vmfa/v1', '/scan/apply-cached' );

			$this->assertNotNull( $scan_call, 'Expected /scan route to be registered.' );
			$this->assertNotNull( $apply_call, 'Expected /scan/apply-cached route to be registered.' );

			$scan_mode_enum = $scan_call[ 'args' ][ 0 ][ 'args' ][ 'mode' ][ 'enum' ] ?? null;
			$this->assertSame( $expected_modes, $scan_mode_enum );

			$apply_mode_enum = $apply_call[ 'args' ][ 0 ][ 'args' ][ 'mode' ][ 'enum' ] ?? null;
			$this->assertSame( $expected_modes, $apply_mode_enum );
		}

		/**
		 * @param array<int, array{namespace: mixed, route: mixed, args: mixed}> $calls
		 */
		private function find_route_call( array $calls, string $namespace, string $route ): ?array {
			foreach ( $calls as $call ) {
				if ( $call[ 'namespace' ] === $namespace && $call[ 'route' ] === $route ) {
					return $call;
				}
			}
			return null;
		}
	}
}
