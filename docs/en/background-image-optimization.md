---
id: background-image-optimization
title: Background image optimization
slug: /background-image-optimization/
sidebar_position: 7
---

# Background image optimization

## Fast and slow transformations

sGallery processes uncached images synchronously when they fit the web memory
budget. The default cooperative
budget is five seconds, defined by the package and exposed through
`Seiger\sGallery\sGallery::optimizationBudgetSeconds()`. Cached nonempty images are
returned immediately; empty caches are regenerated.

Automatic WebP quality selection keeps the original 15 candidates:
`99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80`.
It selects the first candidate meeting the size target, or the last candidate if
none meets it. An explicit `quality()` remains a single requested quality.
Explicit WebP quality can use 100. The configured default is capped at 99.
Only the final nonempty WebP is atomically published from a temporary file.

The budget is checked between transformations and encoding attempts. It cannot
interrupt a native encoder already executing: the first encounter with an image
can exceed five seconds or an existing PHP timeout. Once a transformation is
identified as slow, shared storage remembers it and subsequent requests skip
synchronous processing. Temporary files from abandoned attempts are never
served as completed images.

## Optional sTask integration

sTask runs supported transformations through GD or Imagick. Animated GIF and WebP
processing requires Imagick.

The package remains independently installable. Queue delivery requires installed
sTask runtime classes, the `heartbeat()` API, and a current runner heartbeat.
Older sTask versions without this API cannot receive deferred tasks.

The worker is `Seiger\sGallery\Workers\ImageOptimizationWorker`, with identifier
`sgallery.image-optimization` and action `optimize`. The first deferred task
registers a missing worker as active. Existing inactive workers are respected:
enable the worker in the sTask manager to allow processing.

When the budget expires, the frontend returns the original image URL. If sTask
is unavailable, its heartbeat is stale, or enqueueing fails, the original is still
returned; expensive processing is not restarted on every request. A cached page
or already displayed image is not updated in place. After a successful task,
the worker clears Evolution page/view caches so later requests use the completed
derived image. It does not clear the derived-image cache.

## Large images

Image dimensions have no fixed package cap. Before decoding, sGallery estimates
the memory needed for the source, derivative and resize buffers. The web request
uses up to half the headroom left by PHP's `memory_limit`, after reserving
16 MiB for the application. With unlimited web PHP, the estimate uses 128 MiB as
its starting budget. If the estimate exceeds the web budget, sGallery hands off
the job to sTask and returns the original URL. The original also remains available
when the runner cannot accept the job. Existing derivatives are served directly.

## Job identity and recovery

Jobs preserve the source path, source size, modification time and SHA-256 contents, resolved output
format, transform parameters and explicit-quality settings. Remote sources are
not enqueued. Source replacements detected while queued are rejected rather than
processed using stale parameters. Front requests use a nonblocking per-job lock
and sTask's active-task deduplication. Failed/completed jobs without a usable
cache have a five-minute enqueue retry cooldown; existing active tasks are reused.
Stored scalar geometry works with both processing paths. Watermark parameters
are restored in the frontend's cache order after sTask normalizes the metadata,
so a completed job selects the same derivative as the following frontend request.
Changed watermark contents are rejected before publication.

The derivative key includes the full source path and revision, so replacements
with unchanged size/mtime cannot reuse earlier pixels. Fingerprinting streams
the source, including on cache hits. The worker checks the pinned revision
before and after final publication and before reporting success. A detected
publication race removes the just-published stale candidate; a newer revision
uses a different key. Source bytes and earlier valid derivatives are preserved.
Older two-field size/mtime jobs are accepted when those fields match, then pin
the current content hash. They cannot detect a same-stat change that happened
before execution, but render current contents under the current cache key.

State files live in `storage/sgallery/optimization`. Keep storage shared between
release deployments and writable by both the frontend and CLI users.

## Execution by sTask

The standard sTask runner calls the sGallery worker in its PHP process. The
worker needs write access to the image cache and checks the memory estimate again
against the runner's `memory_limit`. When that setting is `-1`, sGallery uses
512 MiB as the starting budget for the estimate.

A completed derivative passes validation before an atomic rename publishes it.
sTask records recoverable errors and continues with the next job. A fatal error
that terminates the runner can interrupt that run. The page cache is invalidated
after a successful job.

## Verification

Run the integration suite against an installed Evolution Composer runtime with
sTask and its heartbeat API, GD/WebP support and SQLite:

```sh
SGALLERY_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/image-optimization.php
php tests/optional-stask.php
```

With `PACKAGE_AUTOLOAD` pointing at the same runtime, also run
`php tests/runtime/worker-test.php`. It uses an
isolated SQLite queue and the real sTask runner to verify deferral, retries,
source replacement, cache identity and recovery after a failed publication.
