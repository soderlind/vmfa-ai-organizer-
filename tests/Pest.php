<?php
/**
 * Pest PHP configuration file.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

use VmfaAiOrganizer\Tests\BrainMonkeyTestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend( BrainMonkeyTestCase::class )->in( 'AI', 'Services' );

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend( 'toBeValidAIResponse', function () {
	return $this->toBeArray()
		->toHaveKey( 'folder' );
} );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a mock attachment post.
 *
 * @param int    $id       Attachment ID.
 * @param string $filename Filename.
 * @param string $mime     MIME type.
 * @return \stdClass Mock post object.
 */
function createMockAttachment( int $id, string $filename = 'test.jpg', string $mime = 'image/jpeg' ): \stdClass {
	$post                 = new \stdClass();
	$post->ID             = $id;
	$post->post_title     = pathinfo( $filename, PATHINFO_FILENAME );
	$post->post_content   = '';
	$post->post_excerpt   = '';
	$post->post_type      = 'attachment';
	$post->post_mime_type = $mime;
	$post->guid           = 'https://example.com/wp-content/uploads/' . $filename;

	return $post;
}
