<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Seiger\sGallery\Controllers\sGalleryController;

function config($key, $default = null) { return $default; }
function evo() {
    return new class {
        /** Authorize an administrator's isolated custom-parent fixture. @since 1.9.0 */
        public function hasPermission($permission, $context = 'mgr'): bool { return true; }
        /** Provide a manager identity without connecting to a site. @since 1.9.0 */
        public function getLoginUserID($context): int { return 1; }
    };
}
function response() {
    return new class {
        /** Return the real JSON response for controller assertions. @since 1.9.0 */
        public function json($data, $status = 200): JsonResponse { return new JsonResponse($data, $status); }
    };
}
function __($key, $replace = []) { return $GLOBALS['messageTranslator']->get($key, $replace); }
require __DIR__ . '/bootstrap.php';
$dependencyNotices = [];
$strictHandler = set_error_handler(static function ($severity, $message, $file, $line) use (&$strictHandler, &$dependencyNotices): bool {
    if ($severity === E_DEPRECATED && str_ends_with(str_replace('\\', '/', $file), '/vendor/illuminate/support/Str.php') &&
        str_starts_with($message, 'Function mb_split() is deprecated since 8.6')) {
        $dependencyNotices[$message] = true;
        return true;
    }
    return $strictHandler($severity, $message, $file, $line);
});
define('EVO_BASE_PATH', sys_get_temp_dir() . '/sgallery-message-unused/');
define('EVO_SITE_URL', 'https://images.test/');
$_SESSION = ['mgrRole' => 1];
$container = new Container();
$database = new Manager($container);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'evo_']);
$database->setAsGlobal(); $database->bootEloquent();
$container->instance('db', $database->getDatabaseManager());
Facade::setFacadeApplication($container);
$database->schema()->create('s_galleries', static function ($table): void {
    $table->increments('id'); $table->integer('parent');
    foreach (['item_type', 'block', 'file', 'type'] as $field) $table->string($field);
    $table->timestamps();
});
$database->schema()->create('s_gallery_fields', static function ($table): void {
    $table->increments('id'); $table->integer('key');
});
$loader = new FileLoader(new Filesystem(), dirname(__DIR__, 2) . '/lang');
$loader->addNamespace('sGallery', dirname(__DIR__, 2) . '/lang');
$messageTranslator = new Translator($loader, 'en');
foreach (['en' => 'Deleted', 'uk' => 'Видалено', 'ru' => 'Видалено'] as $locale => $expected) {
    $messageTranslator->setLocale($locale);
    $id = $database->table('s_galleries')->insertGetId([
        'parent' => 1, 'item_type' => 'catalog', 'block' => '1',
        'file' => 'shared/sample.png', 'type' => 'image',
    ]);
    $response = (new sGalleryController())->delete(Request::create('/', 'POST', ['item' => $id]));
    $data = $response->getData(true);
    if ($response->getStatusCode() !== 200 || $data['success'] !== 1 || $data['message'] !== $expected || $database->table('s_galleries')->where('id', $id)->exists()) {
        throw new RuntimeException("Delete did not return its translated success message in $locale");
    }
    echo "PASS Delete returns the shipped $locale translation and removes its record\n";
}
foreach (array_keys($dependencyNotices) as $notice) echo "DEPENDENCY DEPRECATED $notice\n";
