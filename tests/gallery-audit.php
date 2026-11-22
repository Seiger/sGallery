<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Controllers\sGalleryController;
use Seiger\sGallery\Http\GalleryResponses;
use Seiger\sGallery\Models\sGalleryField;
use Seiger\sGallery\Models\sGalleryModel;
use Seiger\sGallery\Services\GalleryAccess;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Use stand-alone dependencies and a temporary, in-memory CMS fixture. */
require __DIR__ . '/bootstrap.php';
class_alias((new class extends Model {
    protected $table = 'site_content';
})::class, \EvolutionCMS\Models\SiteContent::class);

function config(string $key, mixed $default = null): mixed
{
    return $GLOBALS['gallery_config'][$key] ?? $default;
}

function evo(): object
{
    return new class {
        public function hasPermission(string $permission, string $context): bool
        {
            return $context === 'mgr' && in_array($permission, $GLOBALS['permissions'], true);
        }

        public function getLoginUserID(string $context): int
        {
            return $context === 'mgr' ? (int) ($_SESSION['mgrInternalKey'] ?? 0) : 0;
        }

        public function getConfig(string $key, mixed $default = null): mixed
        {
            return $GLOBALS['cms_config'][$key] ?? $default;
        }
    };
}

function response(): object
{
    return new class {
        public function json(array $data, int $status = 200): JsonResponse
        {
            return new JsonResponse($data, $status);
        }
    };
}

if (!function_exists('__')) {
    function __(string $key, array $parameters = []): string { return $key; }
}

$assert = static function (bool $result, string $message): void {
    if (!$result) {
        throw new RuntimeException($message);
    }
};
$reject = static function (int $status, Closure $call) use ($assert): void {
    try {
        $call();
    } catch (HttpExceptionInterface $error) {
        $assert($error->getStatusCode() === $status, "Expected rejection $status.");
        return;
    }
    throw new RuntimeException("Expected rejection $status.");
};

$root = sys_get_temp_dir() . '/sgallery-audit-' . bin2hex(random_bytes(12));
mkdir($root . '/assets/library', 0755, true);
mkdir($root . '/assets/library-other', 0755, true);
define('EVO_BASE_PATH', $root . '/');
define('EVO_SITE_URL', 'https://gallery.example/');

try {
    $container = new \Illuminate\Container\Container();
    \Illuminate\Container\Container::setInstance($container);
    Facade::setFacadeApplication($container);
    $db = new Manager($container);
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $db->setAsGlobal();
    $db->bootEloquent();
    $container->instance('db', $db->getDatabaseManager());
    $db->connection()->unprepared(
        'CREATE TABLE site_content (id INTEGER PRIMARY KEY, template INT, deleted INT, privatemgr INT);' .
        'CREATE TABLE document_groups (document INT, document_group INT);' .
        'CREATE TABLE s_galleries (id INTEGER PRIMARY KEY, parent INT, item_type TEXT, block TEXT, file TEXT, type TEXT, position INT DEFAULT 0, created_at TEXT, updated_at TEXT);' .
        'CREATE TABLE s_gallery_fields (id INTEGER PRIMARY KEY, key INT, lang TEXT, alt TEXT, title TEXT, description TEXT, link_text TEXT, link TEXT, created_at TEXT, updated_at TEXT);' .
        'INSERT INTO site_content VALUES (1, 1, 0, 0), (2, 1, 0, 1), (3, 1, 1, 0);' .
        'INSERT INTO document_groups VALUES (2, 55);'
    );

    $gallery_config = ['seiger.settings.sGallery' => [1 => ['photos'], 'itemPolicies' => [
        'product' => static fn (string $action, int $parent, string $block): bool => $action === 'read' && $parent === 4 && $block === '1',
    ]]];
    $cms_config = ['rb_base_dir' => EVO_BASE_PATH . 'assets/library/', 's_lang_config' => 'base,uk'];
    $permissions = ['access_permissions', 'edit_document', 'save_document'];
    $_SESSION = ['mgrRole' => 2, 'mgrInternalKey' => 42, 'mgrDocgroups' => []];

    GalleryAccess::authorize('upload', 'resource', 1, 'photos');
    $reject(403, static fn () => GalleryAccess::authorize('delete', 'resource', 1, 'photos'));
    $reject(403, static fn () => GalleryAccess::authorize('read', 'resource', 1, '1'));
    $reject(403, static fn () => GalleryAccess::authorize('upload', 'resource', 2, 'photos'));
    $reject(403, static fn () => GalleryAccess::authorize('read', 'resource', 3, 'photos'));
    $reject(403, static fn () => GalleryAccess::authorizeLibrary('image'));
    GalleryAccess::authorize('read', 'product', 4, '1');
    $reject(403, static fn () => GalleryAccess::authorize('upload', 'product', 4, '1'));
    $_SESSION['mgrDocgroups'] = [55];
    GalleryAccess::authorize('upload', 'resource', 2, 'photos');

    $fixture = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZyFcAAAAASUVORK5CYII=');
    file_put_contents($root . '/assets/library/small.png', $fixture);
    file_put_contents($root . '/assets/library-other/small.png', $fixture);
    $media = GalleryMedia::classify($root . '/assets/library/small.png', 'PNG', 'media');
    $assert($media === ['type' => 'image', 'extension' => 'png'], 'A real PNG uses its canonical suffix.');
    try {
        GalleryMedia::classify($root . '/assets/library/small.png', 'jpg', 'media');
        throw new LogicException('A different MIME must be rejected.');
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'Unsupported gallery file type.', 'Unexpected media rejection.');
    }

    $assert(GalleryFiles::library('assets/library/small.png', true) !== null, 'Library accepts a file under its root.');
    $assert(GalleryFiles::library('assets/library-other/small.png', true) === null, 'Prefix siblings are outside the library.');
    if (@symlink($root . '/assets/library-other', $root . '/assets/library/alias')) {
        $assert(GalleryFiles::local('assets/library/alias/small.png') === null, 'Directory aliases cannot resolve files.');
    }

    $controller = new sGalleryController();
    $request = static fn (array $input): Request => Request::create('/sgallery/', 'POST', $input);
    $insert = static fn (array $fields): sGalleryModel => sGalleryModel::create($fields + [
        'parent' => 1, 'item_type' => 'resource', 'block' => 'photos', 'type' => 'image',
    ]);
    $shared = $insert(['file' => 'assets/library/small.png']);
    $ownedDir = GalleryFiles::directory(GalleryFiles::uploadDirectory('resource', 1), true);
    file_put_contents($ownedDir . '/owned.png', $fixture);
    $owned = $insert(['file' => 'owned.png']);
    $foreign = $insert(['file' => 'other.png', 'parent' => 2]);

    $permissions[] = 'delete_document';
    $controller->setTranslate($request(['list' => [$owned->id => ['uk' => ['title' => 'Світлина', 'link' => '/gallery?a=4&b=2']]]]));
    $assert(sGalleryField::where('key', $owned->id)->value('title') === 'Світлина', 'An authorized translation is saved.');
    $localized = sGalleryModel::query()->lang('uk')->findOrFail($owned->id);
    $assert((int) $localized->id === (int) $owned->id && $localized->link === '/gallery?a=4&b=2' &&
        str_ends_with($localized->src, '/resource/1/owned.png'), 'Translated collections keep the gallery identity and file source.');
    $reject(422, static fn () => $controller->setTranslate($request(['list' => [$owned->id => ['unknown' => ['title' => 'Invalid']]]])));
    $assert(sGalleryField::where('key', $owned->id)->count() === 1, 'Invalid translations have no side effects.');
    $reject(422, static fn () => $controller->resortGallery($request(['cat' => 1, 'block' => 'photos', 'item' => [$owned->id, $foreign->id]])));
    $assert((int) $owned->fresh()->position === 0, 'Invalid reorder has no side effects.');

    $controller->delete($request(['item' => $shared->id]));
    $assert(is_file($root . '/assets/library/small.png'), 'Detaching a library entry preserves the original.');
    $controller->delete($request(['item' => $owned->id, 'itemType' => 'product']));
    $assert(!is_file($ownedDir . '/owned.png') && !sGalleryModel::find($owned->id), 'Deletion uses the owned item location.');
    $assert(sGalleryField::where('key', $owned->id)->count() === 0, 'Owned deletion removes translations.');

    $_SESSION = [];
    $response = (new GalleryResponses())->handle($request([]), static fn () => GalleryAccess::authorize('read', 'resource', 1, 'photos'));
    $assert($response->getStatusCode() === 403, 'Denied manager requests return the correct status.');

    $assert(GalleryMedia::link('/photos?q=summer&color=blue') && !GalleryMedia::link('//example.test/'), 'Link policy separates local links from scheme-relative links.');
    $legacy = new sGalleryModel();
    $legacy->setRawAttributes(['link' => 'custom:legacy']);
    $assert($legacy->link === '', 'A legacy link outside policy is omitted.');
    $assert(GalleryMedia::preview('unknown') === 'partials.download', 'Fallback preview is fixed.');

    $remote = 'https://images.example.test/cover.png';
    $assert((new sGalleryBuilder())->file($remote)->getFile() === $remote, 'Remote sources only return the browser URL.');
    try {
        (new sGalleryBuilder())->processOptimization(['file' => 'assets/library/small.png', 'params' => ['w' => 20]]);
        throw new LogicException('Partial dimensions were accepted.');
    } catch (RuntimeException $error) {
        $assert(str_contains($error->getMessage(), 'parameters'), 'Replay must reject invalid dimensions before access to the source.');
    }
    $assert(!is_dir($root . '/assets/cache'), 'No invalid or remote call created a cache.');

    echo "sGallery audit regressions OK\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($root);
}
