<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Services/ImageOptimizationQueue.php';
require dirname(__DIR__) . '/src/Workers/ImageOptimizationWorker.php';

if ((new \Seiger\sGallery\Services\ImageOptimizationQueue())->available() ||
    class_exists(\Seiger\sGallery\Workers\ImageOptimizationWorker::class, false)) {
    throw new RuntimeException('sGallery must stay loadable without sTask.');
}
echo "sGallery optional sTask OK\n";
