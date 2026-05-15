<?php

declare(strict_types=1);

use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Signal to Symfony\Component\HttpKernel\Kernel that PHPUnit is the test runner.
// Without this, Kernel::initializeContainer() installs a global deprecation
// error handler that survives kernel shutdown and trips failOnRisky.
// The phpunit-bridge's simple-phpunit binary defines this for you; we use stock phpunit.
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', __DIR__ . '/../vendor/autoload.php');
}

require __DIR__ . '/../vendor/autoload.php';

// symfony/phpunit-bridge ships a bootstrap that wires DeprecationErrorHandler
// based on the SYMFONY_DEPRECATIONS_HELPER env. We gate the include on class
// presence so the file stays valid before the dependency is installed.
if (class_exists(DeprecationErrorHandler::class)) {
    require __DIR__ . '/../vendor/symfony/phpunit-bridge/bootstrap.php';
}
