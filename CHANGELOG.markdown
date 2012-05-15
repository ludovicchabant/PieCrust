
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


Frozen changes
--------------

### 0.7.1 (2012-05-15)

* BUG: Fixed a bug with the `hierarchy` file-system when several posts are
  created on the same day.

### 0.7.0 (2012-05-14)

* NEW: The full list of posts is exposed through `blog.posts` (if you have
  multiple blogs, replace `blog` with your blog's name). This list doesn't have
  any effect on pagination, unlike `pagination.posts`.
* NEW: Categories and tags are exposed through `blog.categories` and `blog.tags`
  (same remark as previously for multiple-blog sites).
* NEW: Added a `striptag` filter in Twig to strip HTML tags from the start and
  end of some text.
* NEW: The `pagination` template data object now also has `next_post` and
  `prev_post` properties to get the next and previous posts if the current page
  is a post.
* NEW: The `chef plugins` command can now install plugins from the internet.
* NEW: Added the `textfrom(path)` Twig function to include text from an
  arbitrary file from outside the website.
* NEW: Added the `chef find` command to find all pages, posts and templates in
  the website with optional filtering features.
* NEW: Added the `chef showconfig` command to print parts or all of the website
  configuration settings.
* NEW: Added the `twig/auto_escape` configuration setting to disable Twig's
  auto-escaping of HTML content.
* NEW: Added a Jekyll importer.
* NEW: Added a Joomla importer.
* NEW: File processors can now be chained (e.g.: process a Sass stylesheet into
  CSS and then through the YUI compressor).
* CHANGED: The cache is now invalidated if anything changes in the website's
  configuration file.
* CHANGED: The `chef import` command's options are now mandatory arguments.
* CHANGED: Pages are now cached differently: the parsed configuration and
  content segments are cached, but not the rendered/formatted content segments.
  Those will be re-rendered on demand so that even the most advanced Twig
  use-cases work.
* CHANGED: PieCrust now creates directories with `0755` as the permission set.
* CHANGED: The `bakeinfo.json` file is now saved in the cache directory instead
  of the bake destination directory.
* CHANGED: Updated the Markdown and Less PHP libraries to their latest version.
* IMPROVED: The chef server will now start faster in most cases.
* BUG: Fixed a rare bug when both the linker and the pagination are accessed
  together.
* BUG: Updated to a new version of `Stupid_Http` to fix a bug with serving static
  files requested with a query parameter.
* BUG: Fixed a bug when specifying excluded file processors.
* MINOR: Slightly better formatting for the debug window.

### 0.6.4 (2012-05-14)

* BUG: Fixed a `chef` bug when running with PHP 5.4+.

### 0.6.3 (2012-04-24)

* BUG: Fixed a bug in the Wordpress importer when using the default SQL table
  prefix.

### 0.6.2 (2012-03-11)

* BUG: Fixed a bug with `chef prepare` not creating directories with the correct
  permissions.

### 0.6.1 (2012-03-08)

* NEW: The `chef prepare` command now has a `--blog` option to specify which
  blog to create a post for.
* CHANGE: Updated LessPHP to 0.3.3.
* CHANGE: Changed `wordcount` into a Twig filter.
* BUG: Fixed a bug with `chef prepare` where a post would be created in the
  wrong place for websites with several blogs.


### 0.6.0 (2012-03-06)

* BREAKING CHANGE: Chef's `--debug` option now only changes the verbosity of the
  command. Before, it also enabled PieCrust's "debug" mode.
* NEW: Added the `wordcount` Twig function.
* NEW: Added the ability for PieCrust plugins to expose Twig extensions.
* NEW: Added Twig filters: `markdown`, `textile` and `formatwith`.
* NEW: Added `--nocache` parameter to Chef.
* NEW: Added `prepare` command in Chef.
* CHANGE: Looping over the page linker now returns both files and directories.
* CHANGE: The page linker now exposes the name of directories.
* CHANGE: Chef server doesn't use the cache for the requested page's contents.
* CHANGE: Updated to Twig 1.6.
* CHANGE: Updated to LessPHP 0.3.2.
* BUG: Fixed a harmless crash in `chef init`.
* BUG: Fixed crash when baking with caching disabled.
* BUG: Fixed a bug with page asset URLs being incorrect when baking in some rare
  occasions.
* BUG: Fixed a bug with posts published on the same day not being correctly
  sorted by time.
* BUG: Fixed a bug with the linker messing up the pagination for the current
  page.
* BUG: Fixed a crash in the baker.
* BUG: Fixed a bug where the 404 page wasn't used when it should have.
* BUG: Files with spaces in their name now work in the Chef server.

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

