<?php
declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

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
$directory = dirname(__DIR__, 2) . '/database/migrations';
$names = array_map(static fn ($file) => basename($file, '.php'), glob($directory . '/*.php'));
require_once $directory . '/' . $names[0] . '.php';
$create = new CreateSGalleryTable();
$block = require $directory . '/' . $names[1] . '.php';
$rename = require $directory . '/' . $names[2] . '.php';
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};
$setup = static function () use ($directory): array {
    $container = new Container();
    $database = new Manager($container);
    $database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'evo_']);
    $database->setAsGlobal();
    $database->bootEloquent();
    $container->instance('db', $database->getDatabaseManager());
    $container->instance('db.schema', $database->schema());
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($container);
    $repository = new DatabaseMigrationRepository($database->getDatabaseManager(), 'migrations');
    $repository->createRepository();
    return [$database, $repository, new Migrator($repository, $database->getDatabaseManager(), new Filesystem())];
};
$seed = static function (Manager $db, string $type = 'item_type'): void {
    $db->table('s_galleries')->insert(['id' => 7, 'parent' => 1, 'file' => 'preserved.png', 'type' => 'image', $type => 'resource']);
    $db->table('s_gallery_fields')->insert(['key' => 7, 'lang' => 'base', 'title' => 'Збережена світлина']);
};
$snapshot = static fn (Manager $db): string => json_encode([
    $db->table('s_galleries')->orderBy('id')->get()->all(),
    $db->table('s_gallery_fields')->orderBy('id')->get()->all(),
], JSON_THROW_ON_ERROR);
$installer = static function (Manager $db, array $migrations): void {
    $db->schema()->create('migrations_install', static function ($table): void {
        $table->increments('id'); $table->string('migration'); $table->integer('batch');
    });
    foreach ($migrations as $name) $db->table('migrations_install')->insert(['migration' => $name, 'batch' => 1]);
};

[$db, $history, $migrator] = $setup();
$migrator->run([$directory]);
$check($db->schema()->hasColumn('s_galleries', 'item_type') && $db->schema()->hasColumn('s_galleries', 'block'), 'A fresh application install creates the current schema');
$seed($db);
$before = $snapshot($db);
$migrator->run([$directory]);
$check(count($history->getRan()) === 3 && $snapshot($db) === $before, 'Repeated migrate keeps rows and does not duplicate history');
$migrator->rollback([$directory]);
$check(!$db->schema()->hasTable('s_galleries') && !$db->schema()->hasTable('s_gallery_fields'), 'Application-created tables retain their normal rollback behavior');
$migrator->run([$directory]);
$check(count($history->getRan()) === 3, 'An application install can migrate again after rollback');

[$db, $history, $migrator] = $setup();
$create->up(); $block->up(); $rename->up();
$installer($db, $names);
$seed($db);
$db->table('s_galleries')->where('id', 7)->update(['block' => 'photos']);
$before = $snapshot($db);
$migrator->run([$directory]);
$check(count($history->getRan()) === 3 && $snapshot($db) === $before, 'Installer tables are adopted without changing records or translations');
$migrator->rollback([$directory]);
$check($db->schema()->hasColumn('s_galleries', 'item_type') && $db->schema()->hasColumn('s_galleries', 'block') && $snapshot($db) === $before, 'Rollback preserves installer-owned tables, columns and data');
$migrator->run([$directory]);
$check(count($history->getRan()) === 3 && $snapshot($db) === $before && $db->table('migrations_install')->count() === 3, 'Repeated adoption preserves both installer history and gallery data');

[$db, $history, $migrator] = $setup();
$create->up(); $block->up();
$installer($db, array_slice($names, 0, 2));
$seed($db, 'resource_type');
$migrator->run([$directory]);
$check($db->table('s_galleries')->value('item_type') === 'resource', 'An older installer schema upgrades resource_type without losing its value');
$migrator->rollback([$directory]);
$check($db->schema()->hasColumn('s_galleries', 'resource_type') && $db->schema()->hasColumn('s_galleries', 'block') && $db->table('s_gallery_fields')->value('title') === 'Збережена світлина', 'Rollback reverses only changes newer than the installer baseline');

foreach (['s_galleries', 's_gallery_fields'] as $missing) {
    [$db, $history, $migrator] = $setup();
    $create->up();
    $seed($db, 'resource_type');
    $kept = $missing === 's_galleries' ? 's_gallery_fields' : 's_galleries';
    $before = json_encode($db->table($kept)->get()->all(), JSON_THROW_ON_ERROR);
    $db->schema()->drop($missing);
    $create->up(); $create->up();
    $check($db->schema()->hasTable($missing) && json_encode($db->table($kept)->get()->all(), JSON_THROW_ON_ERROR) === $before, "A partial install restores $missing without replacing $kept");
}

[$db, $history, $migrator] = $setup();
$installer($db, ['unrelated_package_migration']);
$migrator->run([$directory]);
$migrator->rollback([$directory]);
$check(!$db->schema()->hasTable('s_galleries'), 'An unrelated installer record does not prevent normal rollback');
echo "sGallery migration regressions OK\n";
foreach (array_keys($dependencyNotices) as $notice) echo "DEPENDENCY DEPRECATED $notice\n";
