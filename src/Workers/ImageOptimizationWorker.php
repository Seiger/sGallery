<?php namespace Seiger\sGallery\Workers;

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

// Optional integration: discovery must not fail when sTask is not installed.
if (!class_exists(BaseWorker::class) || !interface_exists(\Seiger\sTask\Contracts\TaskInterface::class)) {
    return;
}

/** Execute deferred image transformations without the frontend deadline. @since 1.6.0 */
class ImageOptimizationWorker extends BaseWorker
{
    /** Return the immutable queue identifier. @since 1.6.0 */
    public function identifier(): string { return ImageOptimizationQueue::IDENTIFIER; }

    /** Group this worker with the gallery package. @since 1.6.0 */
    public function scope(): string { return 'sgallery'; }

    /** Return the manager icon identifier. @since 1.6.0 */
    public function icon(): string { return 'photo'; }

    /** Return the translated manager title. @since 1.6.0 */
    public function title(): string { return __('sGallery::manager.image_optimization_worker'); }

    /** Explain the worker's background processing role. @since 1.6.0 */
    public function description(): string { return __('sGallery::manager.image_optimization_description'); }

    /**
     * Replay a trusted scalar descriptor and finish only after a valid cache was published.
     *
     * Exceptions propagate to sTask's failure handling; originals are never task success.
     * @param sTaskModel $task Current task record
     * @param array<string, mixed> $options Source version and transformation descriptor
     * @return void
     * @since 1.6.0
     */
    public function taskOptimize(sTaskModel $task, array $options = []): void
    {
        if (PHP_SAPI === 'cli') {
            set_time_limit(0);
        }
        $result = (new sGalleryBuilder())->processOptimization($options);
        // Remove cached pages that may contain the temporary original URL.
        evo()->clearCache();
        $task->progress = 100;
        $this->markFinished($task, $result);
    }
}
