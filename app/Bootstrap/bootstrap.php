<?php

declare(strict_types=1);

use MyTube\Config\DatabaseConfig;
use MyTube\Config\Environment;

$projectRoot = dirname(__DIR__, 2);
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

require_once __DIR__ . '/../Config/Environment.php';
require_once __DIR__ . '/../Config/DatabaseConfig.php';
require_once __DIR__ . '/../Infrastructure/DatabaseConnection.php';

Environment::load($projectRoot);

if (!defined('MYTUBE_DB_CONFIG')) {
    define('MYTUBE_DB_CONFIG', DatabaseConfig::fromEnvironment());
}
