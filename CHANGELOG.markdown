
CHANGELOG
=========

This is the changelog for [PieCrust][].

The first section deals with the latest changes in the development branch
(`default` in Mercurial, `master` in Git).

The second section deals with changes in the stable branch (`stable` in
Mercurial, `git-stable` in Git), but those changes are also included in the
development branch (the development branch is ahead of the stable branch).


Fresh changes
-------------

* NEW: PieCrust is now using Twig version 1.6.
* NEW: Added the `wordcount` Twig function.
* NEW: Added the ability for PieCrust plugins to expose Twig extensions.
* NEW: Added Twig filters: `markdown`, `textile` and `formatwith`.
* NEW: Added `--nocache` parameter to Chef.
* NEW: Added `prepare` command in Chef.
* CHANGE: Looping over the page linker now returns both files and directories.
* BUG: Fixed a harmless crash in `chef init`.
* BUG: Fixed crash when baking with caching disabled.
* BUG: Fixed a bug with page asset URLs being incorrect when baking in some rare
  occasions.
* BUG: Fixed a bug with posts published on the same day not being correctly
  sorted by time.


Frozen changes
--------------

### 0.5.2 (2012-02-17)

* BUG: Fixed a bug where the site's index file URL was not handled correctly in
  some parsing functions.

### 0.5.1 (2012-02-09)

* BUG: Fixed some problems with how `chef serve` caches baked files. The bug
  would most of time result in incorrectly rendered pages and an error message
  stating that the `server_cache` directory doesn't exist.

### 0.5.0 (2012-02-09)

* NEW: Added a `--quiet` option to `chef`.
* NEW: Added a `chef purge` command to clean the cache directory.

### 0.4.1 (2012-02-09)

* IMPROVED: The Windows bootstrap script for `chef` is now a bit better at
  finding the `php.exe` executable.
* BUG: Fixed crash bug with `chef import`.

### 0.4.0 (2012-02-04)

* BREAKING CHANGE: `chef` now behaves like `hg` or `git` in that it knows if it
  is being run inside a PieCrust website. As a result, most commands' syntaxes
  have been changed.
* BREAKING CHANGE: PieCrust now supports plugins. As a result, the Dwoo and Haml
  template engines, along with the Sass processor, have been removed and turned
  into plugins.
* BREAKING CHANGE: The `pagination` data exposed inside a layout is now the same
  one as for the page inserted into that layout -- this means that depending on
  the case, `pagination.posts` would not always return the same thing. You can
  use `pagination.posts.all` and other filtering functions to ensure you always
  get the same results (_e.g._ if generating an "archive" sidebar).
* NEW: New `chef` commands: `root`, `plugins`, `stats`, `tags` and `categories`.
* NEW: You can now specify "`* -foo -bar`" in the `baker/processors` site
  configuration setting to use all processors _except_ `foo` and `bar`.
* IMPROVED: PieCrust now displays an appropriate error message if the user tries
  to use page asset sub-directories.
* IMPROVED: The output for `chef bake` is better, simpler and unified.
* IMPROVED: The `chef` server is better at handling file processors.
* IMPROVED: Better performance when generating a page's templating data.
* IMPROVED: Better error handling and error messages in all kinds of situations.
* BUG: Fixed a bug with `pagination` data.
* BUG: Fixed path parsing bug with paths starting with "`~`".
* BUG: Fixed Unix/Mac `chef` bootstrap to support symbolically linked
  situations.







  [piecrust]: http://bolt80.com/piecrust/

