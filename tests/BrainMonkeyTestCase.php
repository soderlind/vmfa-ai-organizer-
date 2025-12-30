<?php
/**
 * Base test case with Brain Monkey setup.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

namespace VmfaAiOrganizer\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery;

/**
 * Base test case for all plugin tests.
 */
abstract class BrainMonkeyTestCase extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define common WordPress functions.
		$this->setup_common_functions();
	}

	/**
	 * Tear down Brain Monkey after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Set up common WordPress functions stubs.
	 */
	protected function setup_common_functions(): void {
		// Escaping functions - return input unchanged.
		Functions\stubs(
			[
				'esc_html'             => static fn( $text ) => $text,
				'esc_attr'             => static fn( $text ) => $text,
				'esc_url'              => static fn( $url ) => $url,
				'esc_sql'              => static fn( $data ) => $data,
				'wp_kses_post'         => static fn( $text ) => $text,
				'sanitize_text_field'  => static fn( $str ) => $str,
				'sanitize_key'         => static fn( $key ) => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ),
				'absint'               => static fn( $maybeint ) => abs( (int) $maybeint ),
				'wp_parse_args'        => static fn( $args, $defaults = [] ) => array_merge( $defaults, is_array( $args ) ? $args : [] ),
				'get_attached_file'    => static fn( $id ) => '/uploads/test-file.jpg',
				'wp_json_encode'       => 'json_encode',
				'wp_remote_retrieve_response_code' => static fn( $response ) => $response['response']['code'] ?? 200,
				'wp_remote_retrieve_body' => static fn( $response ) => $response['body'] ?? '',
			]
		);

		// Translation functions - return first argument.
		Functions\stubs(
			[
				'__'         => static fn( $text, $domain = 'default' ) => $text,
				'_e'         => static fn( $text, $domain = 'default' ) => print $text,
				'_n'         => static fn( $single, $plural, $number, $domain = 'default' ) => $number === 1 ? $single : $plural,
				'_x'         => static fn( $text, $context, $domain = 'default' ) => $text,
				'esc_html__' => static fn( $text, $domain = 'default' ) => $text,
				'esc_attr__' => static fn( $text, $domain = 'default' ) => $text,
			]
		);

		// Plugin functions.
		Functions\stubs(
			[
				'plugin_dir_path' => static fn( $file ) => dirname( $file ) . '/',
				'plugin_dir_url'  => static fn( $file ) => 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/',
				'plugin_basename' => static fn( $file ) => 'vmfa-ai-organizer/' . basename( $file ),
			]
		);
	}

	/**
	 * Stub get_option to return specific values.
	 *
	 * @param array $options Key-value pairs of option names to values.
	 */
	protected function stub_options( array $options ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $options ) {
				return $options[ $name ] ?? $default;
			}
		);
	}

	/**
	 * Expect update_option to be called.
	 *
	 * @param string $option_name Expected option name.
	 * @param mixed  $value       Expected value (optional).
	 */
	protected function expect_update_option( string $option_name, $value = null ): void {
		if ( null !== $value ) {
			Functions\expect( 'update_option' )
				->once()
				->with( $option_name, $value )
				->andReturn( true );
		} else {
			Functions\expect( 'update_option' )
				->once()
				->with( $option_name, Mockery::any() )
				->andReturn( true );
		}
	}

	/**
	 * Create a mock attachment post.
	 *
	 * @param int    $id       Attachment ID.
	 * @param string $filename Filename.
	 * @param string $mime     MIME type.
	 * @return \stdClass Mock post object.
	 */
	protected function create_mock_attachment( int $id, string $filename = 'test.jpg', string $mime = 'image/jpeg' ): \stdClass {
		$post                  = new \stdClass();
		$post->ID              = $id;
		$post->post_title      = pathinfo( $filename, PATHINFO_FILENAME );
		$post->post_content    = '';
		$post->post_excerpt    = '';
		$post->post_type       = 'attachment';
		$post->post_mime_type  = $mime;
		$post->guid            = 'https://example.com/wp-content/uploads/' . $filename;

		return $post;
	}
}
