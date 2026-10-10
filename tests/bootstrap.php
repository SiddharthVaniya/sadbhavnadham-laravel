<?php

/**
 * PHPUnit must not load bootstrap/cache/config.php: that file bakes .env (DB, secrets)
 * and ignores phpunit.xml overrides such as sqlite :memory:.
 */
$cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($cachedConfig) && (getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? 'testing') === 'testing') {
    unlink($cachedConfig);
}

require dirname(__DIR__).'/vendor/autoload.php';
