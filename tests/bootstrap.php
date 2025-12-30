<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package VmfaAiOrganizer
 */

declare( strict_types=1 );

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Bootstrap Brain Monkey.
require_once __DIR__ . '/BrainMonkeyTestCase.php';
