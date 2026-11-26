# sGallery documentation

The canonical guides for each major line live in `docs/<locale>/` on the matching package branch. dDocs reads these guides and `docs.json` from the installed Composer package. The public Docusaurus site builds from the same files. Both use the Ukrainian guides for the Russian locale.

Use relative Markdown links between guides and keep any article assets with the guides. Follow the entrypoint filenames in `docs.json` and update the matching localized guides alongside package changes.

The separate `docs` Git branch contains the site, version registry and GitHub Pages workflow. Its README describes local builds. The package's `docs/audit/` and `docs/testing/` directories contain project records outside the public guide tree.
