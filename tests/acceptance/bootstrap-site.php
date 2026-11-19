<?php
declare(strict_types=1);

$site = rtrim(getenv('SGALLERY_ACCEPTANCE_SITE') ?: '', '/');
if (!str_starts_with($site, '/lab/') || !is_file($site . '/core/config/database/connections/default.php')) {
    throw new RuntimeException('Install an isolated Evolution site under /lab before running acceptance.');
}
define('EVO_API_MODE', true);
define('EVO_CLI', true);
define('IN_INSTALL_MODE', false);
define('IN_MANAGER_MODE', true);
define('EVO_SITE_URL', 'https://images.test/');
$_SERVER['HTTP_HOST'] = 'images.test';
$_SERVER['SERVER_NAME'] = 'images.test';
$_SERVER['DOCUMENT_ROOT'] = $site;
require $site . '/core/bootstrap.php';
evo();
