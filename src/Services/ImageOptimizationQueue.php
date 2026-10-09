<?php namespace Seiger\sGallery\Services;

use Illuminate\Support\Facades\Log;
use Seiger\sGallery\Workers\ImageOptimizationWorker;

/**
 * Coordinate optional sTask jobs and remember transformations that exceeded the web budget.
 *
 * Shared storage markers survive releases. A nonblocking per-job lock prevents concurrent
 * front requests from creating duplicate tasks; failed jobs have a five-minute retry cooldown.
 * @since 1.6.0
 */
class ImageOptimizationQueue
{
    public const IDENTIFIER = 'sgallery.image-optimization';

    /** Check required runtime contracts and recent runner invocation. @since 1.6.0 */
    public function available(): bool
    {
        foreach ([\Seiger\sTask\Facades\sTask::class, \Seiger\sTask\sTask::class,
            \Seiger\sTask\Workers\BaseWorker::class, \Seiger\sTask\Models\sTaskModel::class,
            \Seiger\sTask\Models\sWorker::class] as $class) {
            if (!class_exists($class)) {
                return false;
            }
        }
        if (!interface_exists(\Seiger\sTask\Contracts\TaskInterface::class) ||
            !method_exists(\Seiger\sTask\sTask::class, 'heartbeat')) {
            return false;
        }
        try {
            return \Seiger\sTask\Facades\sTask::heartbeat();
        } catch (\Throwable $error) {
            Log::warning('sGallery cannot check the sTask heartbeat: ' . $error->getMessage());
            return false;
        }
    }

    /** Return a stable storage prefix for a source version and transformation. @since 1.6.0 */
    private function prefix(array $job): string
    {
        return storage_path('sgallery/optimization/' . hash('sha256', json_encode($job, JSON_THROW_ON_ERROR)));
    }

    /** Check whether this exact source version and transformation previously exceeded its budget. @since 1.6.0 */
    public function heavy(array $job): bool
    {
        return is_file($this->prefix($job) . '.slow');
    }

    /**
     * Remember slow processing and enqueue only when optional sTask is operational.
     *
     * Existing inactive worker records are respected. Only a missing worker is created active.
     * Returns false on unavailable storage, stale heartbeat, cooldown or registration failure.
     * @param array<string, mixed> $job Scalar source/transform descriptor produced by the builder
     * @return bool Whether an active task exists or was created
     * @since 1.6.0
     */
    public function defer(array $job): bool
    {
        $lock = null;
        try {
            $prefix = $this->prefix($job);
            $directory = dirname($prefix);
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                return false;
            }
            if (!@touch($prefix . '.slow') || !$this->available()) {
                return false;
            }
            $lock = @fopen($prefix . '.lock', 'c');
            if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
                return false;
            }

            $worker = \Seiger\sTask\Models\sWorker::firstOrCreate(['identifier' => self::IDENTIFIER], [
                'scope' => 'sgallery', 'class' => ImageOptimizationWorker::class,
                'active' => true, 'position' => 0, 'settings' => [], 'hidden' => 0,
            ]);
            if (!$worker->active || $worker->class !== ImageOptimizationWorker::class) {
                return false;
            }
            $duplicate = \Seiger\sTask\Models\sTaskModel::findActiveDuplicate(self::IDENTIFIER, 'optimize', $job);
            if ($duplicate) {
                return true;
            }
            clearstatcache();
            $lastAttempt = @filemtime($prefix . '.attempt');
            if ($lastAttempt !== false && time() - $lastAttempt < 300) {
                return false;
            }
            \Seiger\sTask\Facades\sTask::create(self::IDENTIFIER, 'optimize', $job);
            @touch($prefix . '.attempt');
            return true;
        } catch (\Throwable $error) {
            Log::warning('sGallery could not enqueue image optimization: ' . $error->getMessage());
            return false;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
