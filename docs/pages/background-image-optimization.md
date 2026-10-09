---
layout: page
title: Background image optimization
description: Optional sTask processing for slow image transformations
permalink: /background-image-optimization/
---

## Fast and slow transformations

sGallery first processes uncached images synchronously. The default cooperative
budget is five seconds, configurable through
`seiger.settings.sGallery.optimizationBudgetSeconds`. Cached nonempty images are
returned immediately; empty caches are regenerated.

Automatic WebP quality selection keeps the original 15 candidates:
`99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80`.
It selects the first candidate meeting the size target, or the last candidate if
none meets it. An explicit `quality()` remains a single requested quality.
The existing WebP upper-bound normalization to 99 still applies.
Only the final nonempty WebP is atomically published from a temporary file.

The budget is checked between transformations and encoding attempts. It cannot
interrupt a native encoder already executing: the first encounter with an image
can exceed five seconds or an existing PHP timeout. Once a transformation is
identified as slow, shared storage remembers it and subsequent requests skip
synchronous processing. Temporary files from abandoned attempts are never
served as completed images.

## Optional sTask integration

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

## Job identity and recovery

Jobs preserve the source path, source size and modification time, resolved output
format, transform parameters and explicit-quality settings. Remote sources are
not enqueued. Source replacements detected while queued are rejected rather than
processed using stale parameters. Front requests use a nonblocking per-job lock
and sTask's active-task deduplication. Failed/completed jobs without a usable
cache have a five-minute enqueue retry cooldown; existing active tasks are reused.

State files live in `storage/sgallery/optimization`. Keep storage shared between
release deployments and writable by both the frontend and CLI users. The CLI
worker disables PHP's execution-time limit for image jobs; infrastructure timeouts
and memory limits still apply. Interrupted jobs retain no completed WebP cache.

## Verification

Run the integration suite against an installed Evolution Composer runtime with
sTask and its heartbeat API, GD/WebP support and SQLite:

```sh
SGALLERY_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/image-optimization.php
php tests/optional-stask.php
```
