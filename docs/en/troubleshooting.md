---
id: troubleshooting
title: Troubleshooting
sidebar_position: 9
---

# Troubleshooting

| Symptom | Check |
| --- | --- |
| A resource has no gallery tab | Check its template ID in [sGallery settings](./configuration.md). |
| An editor cannot add or remove media | Check the [action permission and parent access](./configuration.md#manager-gallery-permissions). For a product gallery, check the integration policy for that product. |
| The frontend shows the original | Check the codec and requested output format. For a slow or large image, check [sTask availability and its runner heartbeat](./background-image-optimization.md#optional-stask-integration). |
| An upload is rejected | Check the file content, extension and the CMS upload settings. For a shared library file, check the configured browser directory and permissions. |
| The preview still shows an old image | Reload the page, check that the template requests the current item URL and confirm the source file has changed at that path. |
| Composer cannot install on PHP 8.6 | Check that your Evolution build contains the fix linked in [Getting started](./getting-started.md#install-in-evolution-cms). |
