<?php
/**
 * Test bootstrap file.
 *
 * @package TheWebSolver\Codegarage\Test
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Stub/PsrStubs.php';

define(
	'CODEGARAGE_PSR_PACKAGE_INSTALLED',
	interface_exists( '\\Psr\\Http\\Server\\MiddlewareInterface' )
);
