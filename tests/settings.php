<?php
/** Configuration save, permissions, view mapping and full form rendering checks. @since 1.7.0 */
$root = sys_get_temp_dir() . '/sgallery-settings-' . bin2hex(random_bytes(6));
mkdir($root . '/compiled', 0755, true);
define('EVO_BASE_PATH', $root . '/'); define('EVO_SITE_URL', 'https://example.test/');
$config = []; $allowed = true;
function config($key, $default = null) { if (is_array($key)) {foreach($key as $name=>$value) \Illuminate\Support\Arr::set($GLOBALS['config'],$name,$value);return;} return \Illuminate\Support\Arr::get($GLOBALS['config'], $key, $default); }
function evo() {return new class { public function hasPermission($p) {return $GLOBALS['allowed'];} public function getConfig($p,$d=null){return $d;} public function make($name, $parameters = []) {return $GLOBALS['container']->make($name, $parameters);} };}
function csrf_token(){return 'csrf-fixture';}
function route($name) {return '/sgallery/' . $name;}
function response() {return new class {public function json($data,$status=200){return new \Illuminate\Http\JsonResponse($data,$status);} };}
$loader = require getenv('SGALLERY_AUTOLOAD');
class_alias(\Illuminate\Support\Facades\View::class, 'View');
$loader->setPsr4('Seiger\\sGallery\\', dirname(__DIR__) . '/src');
$container = new \Illuminate\Container\Container(); \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$capsule = new \Illuminate\Database\Capsule\Manager($container);
$capsule->addConnection(['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);$capsule->setAsGlobal();$capsule->bootEloquent();
$capsule->schema()->create('site_templates',function($table){$table->integer('id');$table->string('templatename');});
$capsule->table('site_templates')->insert(['id'=>1,'templatename'=>'Home']);
$filesystem = new \Illuminate\Filesystem\Filesystem();
$translationLoader = new \Illuminate\Translation\FileLoader($filesystem, dirname(__DIR__).'/lang');
$translationLoader->addNamespace('sGallery', dirname(__DIR__).'/lang');
$translator = new \Illuminate\Translation\Translator($translationLoader, 'uk');
$container->instance('translator', $translator);
$compiler = new \Illuminate\View\Compilers\BladeCompiler($filesystem, $root . '/compiled');
$resolver = new \Illuminate\View\Engines\EngineResolver();$resolver->register('blade',fn()=>new \Illuminate\View\Engines\CompilerEngine($compiler,$filesystem));
$finder = new \Illuminate\View\FileViewFinder($filesystem,[dirname(__DIR__).'/views']);$finder->addNamespace('sGallery',dirname(__DIR__).'/views');
$factory = new \Illuminate\View\Factory($resolver,$finder,new \Illuminate\Events\Dispatcher($container));$factory->setContainer($container);$factory->share('__env',$factory);$container->instance('view',$factory);
$check=static function($ok,$message){if(!$ok)throw new RuntimeException($message);};
try {
    $check(\Seiger\sGallery\Services\GallerySettings::read()['imageCacheDir']==='assets/cache/','Vendor fallback');
    $path=\Seiger\sGallery\Services\GallerySettings::path();mkdir(dirname($path),0755,true);
    file_put_contents($path,"<?php return [1, 'imageCacheDir'=>'custom/', 'defaultFit'=>'contain', 'manual'=>['keep'=>true], 'optimizationBudgetSeconds'=>7, 'views'=>['partials'=>['image'=>'custom::image']]];");
    $check(!isset(\Seiger\sGallery\Services\GallerySettings::read()['defaultWidth']),'Custom file replaces defaults without merging');
    $_SESSION['sgallery_settings_token']='test-token';
    $input=['imageCacheDir'=>'cache/','optimizationBudgetSeconds'=>5,'defaultWidth'=>240,'defaultHeight'=>120,'quality'=>90,'sharpen'=>0,'defaultFit'=>'crop','format'=>'avif','optimize'=>true,'watermark'=>['file'=>''],'templates'=>[['id'=>1,'blocks'=>'slider, top-sales']],'views'=>[]];
    $controller=new \Seiger\sGallery\Controllers\GallerySettingsController();
    unset($input['defaultFit']);
    $request=static fn($data,$token='test-token')=>\Illuminate\Http\Request::create('/','POST',['token'=>$token,'settings'=>json_encode($data)]);
    $check($controller->save($request($input,'bad'))->getStatusCode()===403,'CSRF enforced');
    $allowed=false;$check($controller->save($request($input))->getStatusCode()===403,'Settings permission enforced');$allowed=true;
    $before=file_get_contents($path);$bad=$input;$bad['format']='auto';
    $check($controller->save($request($bad))->getStatusCode()===422 && file_get_contents($path)===$before,'Unsupported visual format leaves configuration intact');
    $result=$controller->save($request($input));$check($result->getStatusCode()===200,$result->getContent());
    $saved=\Seiger\sGallery\Services\GallerySettings::read();
    $check($saved['manual']['keep']===true,'Unknown manual keys preserved');
    $check($saved['defaultFit'] === 'contain', 'Saving visual settings does not remove unrelated configuration keys');
    $check($saved[1]===['slider','top-sales'],'Resource blocks saved');
    config(['seiger.settings.sGallery'=>$saved]);
    $check(\Seiger\sGallery\Services\GallerySettings::view('partials.image')==='sGallery::partials.image','Package view resolves without configurable mapping');
    $factory->share('app',new class {public function getLocale(){return 'uk';}});
    $html=$factory->make('sGallery::settings',['settings'=>$saved,'resourceTemplates'=>\EvolutionCMS\Models\SiteTemplate::all(),'token'=>'test-token'])->render();
    $legacySettings = $saved;
    unset($legacySettings[1]);
    $legacySettings[] = [1 => ['slider', 'top-sales']];
    $legacyHtml = $factory->make('sGallery::settings', ['settings'=>$legacySettings,'resourceTemplates'=>\EvolutionCMS\Models\SiteTemplate::all(),'token'=>'test-token'])->render();
    $check(str_contains($legacyHtml, 'застарілий') && str_contains($legacyHtml, 'top-sales'), 'Legacy assignments render with deprecation notice');
    $check(str_contains($html,'sgallery-settings') && str_contains($html,'top-sales') && str_contains($html,'Типова якість'),'Complete form renders');
    $check(!str_contains($html, 'data-sg="defaultFit"'), 'Fit method is absent from the settings form');
    $check(preg_match('//u', $html) === 1 && str_contains($html, 'Типовий водяний знак'), 'UTF-8 labels and final controls render');
    foreach (['en' => 'Default watermark', 'ru' => 'Водяной знак по умолчанию'] as $locale => $label) {
        $translator->setLocale($locale);
        $localized = $factory->make('sGallery::settings', ['settings'=>$saved,'resourceTemplates'=>\EvolutionCMS\Models\SiteTemplate::all(),'token'=>'test-token'])->render();
        $check(str_contains($localized, $label) && !str_contains($localized, 'sGallery::settings.'), 'Settings translations render for ' . $locale);
    }
    $translator->setLocale('uk');
    $check(!isset($saved['optimizationBudgetSeconds']) && !isset($saved['views']), 'Obsolete package-controlled settings removed and input overrides ignored');
    $check(!str_contains($html, 'data-sg="optimizationBudgetSeconds"') && !str_contains($html, 'data-sg-view') && !str_contains($html, 'Шаблони відображення'), 'Internal package fields absent from visual form');
    $check(!str_contains($html, 'Обмеження завантаження Evolution') && !str_contains($html, 'Джерело конфігурації'), 'Service sections removed');
    $check(strpos($html, 'data-sg="imageCacheDir"') < strpos($html, 'data-sg="quality"'), 'Cache directory is first field');
    $check(!isset($saved['defaultWidth'], $saved['defaultHeight']) && !str_contains($html, 'data-sg="defaultWidth"') && !str_contains($html, 'data-sg="defaultHeight"'), 'Static thumbnail dimensions absent from configuration and form');
    config(['seiger.settings.sGallery.defaultWidth'=>999, 'seiger.settings.sGallery.defaultHeight'=>999]);
    $gallery = new \Seiger\sGallery\sGallery();
    config(['seiger.settings.sGallery.defaultFit' => 'stretch']);
    $check($gallery->defaultFit() === 'crop', 'Manager thumbnail fit remains static despite legacy configuration');
    $check($gallery->defaultWidth()===240 && $gallery->defaultHeight()===120, 'Thumbnail dimensions remain static despite legacy configuration');
    echo "sGallery settings/permissions/CSRF/preservation/Blade render OK\n";
} finally {
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
