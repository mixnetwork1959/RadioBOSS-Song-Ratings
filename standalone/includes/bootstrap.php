<?php

declare(strict_types=1);

define('RBSR_STANDALONE_VERSION', '1.0.1');
define('RBSR_ROOT', dirname(__DIR__));
define('RBSR_CONFIG_FILE', RBSR_ROOT . '/config/config.php');
define('RBSR_STORAGE', RBSR_ROOT . '/storage');

require_once __DIR__ . '/core.php';
