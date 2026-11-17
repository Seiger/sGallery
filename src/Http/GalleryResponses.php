<?php namespace Seiger\sGallery\Http;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Turn expected controller rejections into manager HTTP responses. @since 1.8.0 */
final class GalleryResponses
{
    /** Preserve status codes on Evolution installations without Laravel's exception renderer. @since 1.8.0 */
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (HttpExceptionInterface $error) {
            $status = $error->getStatusCode();
        } catch (ModelNotFoundException $error) {
            $status = 404;
        }

        $message = match ($status) {
            403 => __('sGallery::manager.access_denied'),
            404 => 'Item not found.',
            default => __('sGallery::manager.invalid_request'),
        };
        return response()->json(['success' => 0, 'error' => $message], $status);
    }
}
