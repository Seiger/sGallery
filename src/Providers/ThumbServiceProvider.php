<?php namespace Seiger\sGallery\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/** Register the directive before the frontend loads the gallery provider on demand. */
final class ThumbServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            if (isset($blade->getCustomDirectives()['thumb'])) {
                return;
            }
            $blade->directive('thumb', static function (string $expression): string {
                $file = $expression === '' ? 'null' : $expression;

                return "<?php echo e(app('sGallery')->thumb($file)); ?>";
            });
        });
    }
}
