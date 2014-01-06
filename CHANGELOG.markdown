
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

* CHANGED: The `chef` bootstrap script on Mac/Linux won't try to find your
  PHP installation anymore.
* NEW: Ability to sort pages returned by `site.pages`.
* NEW: Added support for PHP-Sundown.
* MISC: Optimizations for incremental baking.


Frozen changes
--------------

### 1.1.3 (2013-01-01)

* NEW: Added global `--theme` option to `chef` to treat a PieCrust theme like
  a website. This lets the user run things like `chef serve` directly on a
  theme.
* BUG: Fixed incorrect sorting of blog posts published on the same day.
* MISC: Happy new year!

### 1.1.2 (2013-10-31)

* BUG: The baker was incorrectly deleting files from the output directory during
  incremental bakes.
* BUG: The `theme_info.yml` file was incorrectly deployed to the output bake
  folder.

### 1.1.1 (2013-10-28)

* BUG: Fixed a bug where posts written on the same day would not be ordered
  correctly.

### 1.1.0 (2013-10-26)

* BREAKING CHANGE: Removed all options and features that were marked as
  deprecated (_i.e._ they previously triggered a warning message).
* CHANGE: Removed the sample website (it's being turned into a theme).
* CHANGE: Added `.md` and `.textile` as default auto-format extensions
  (respectively for Markdown and Textile formatting).
* CHANGE: The `sortBy` function on page and post iterators has been renamed to
  just `sort`.
* CHANGE: You can now specify the website's root for `chef` by using either
  `--root=/path/to/root` or `--root /path/to/root`.
* CHANGE: You can now specify configuration variants to apply for `chef` by
  using `--config=variant` or `--config variant`. Configuration variants are
  found in the `variants` section of the site configuration. The `bake` and
  `serve` commands will apply variants `variants/server` and `variants/baker`,
  respectively, if found.
* CHANGE: As a result of the previous change, you will get a warning if you use
  the old `--config` option on those commands, or if the old
  `baker/config_variants/default` or `server/config_variants/default`
  variants are found. It should all work as before, however, except for the
  added warning.
* CHANGE: For multi-blogs, a post will try to get its layout from the
  `<blogname>/post` template first, and then the global default `post` template
  if it doesn't exist.
* NEW: Added `selfupdate` command to update an installed (Phar) version of
  PieCrust.
* NEW: Added support for tags when importing content from a Wordpress SQL
  database.
* NEW: Page and post iterators can be filtered with "magic" functions like
  `is_foo('value')` or `has_bar('value')`, to prevent having to write a
  full-blown filter in the page config header.
* NEW: Ability to override the default page or post layout when import a blog
  from Wordpress.
* NEW: Added `pccache` tag to Twig for caching parts of markup during the bake.
* NEW: You can pass a parameter to `all_page_numbers` to limit the number of
  page numbers you get back.
* NEW: Ability to specify an overall `class` and/or `id` for Geshi blocks
  (syntax highlighting).
* NEW: Added the `IBakerAssistant` API for plugins that want to do extra
  processing during the bake.
* NEW: Added the `IDataProvider` API for plugins that want to expose custom
  template data to pages.
* NEW: File-systems (`flat`, etc.) are now extensible through plugins, and
  several of them can be combined by using comma-separated names in the
  `site/posts_fs` configuration setting.
* NEW: Added `--log` option to log `chef` output to a file.
* NEW: Added `dropbox` file-system.
* NEW: Added support for setting parameters on the Markdown formatter.
* NEW: Added access to the `assets` from the pagination data, or any other page
  object from a page iterator.
* BUG: Fixed an issue with encoding when importing content from a Wordpress SQL
  database.
* BUG: Fixed an issue with the `geshi` node in Twig adding extra empty lines.
* BUG: Fixed a bug with sorting siblings/family pages with a sub-property.
* BUG: Baking a website will now delete files from a previous bake that are not
  valid anymore. This fix required a pretty big refactor of the baking process,
  so it may itself introduce a few other bugs :)
* BUG: When `lowercase` is part of the slufigy flags (which is the case by
  default), PieCrust now correctly matches tags/categories in the URL regardless
  of the casing.
* BUG: Don't copy special directories like `.hg` or `.git` when installing
  plugins from a file-system source.
* IMPLEMENTATION CHANGES: Did some optimizations to make memory footprint more
  stable during a bake. Also removed PHP's default memory limit when running
  PieCrust with `chef`.

### 1.0.3 (2013-09-02)

* BUG: Fixed some problems, under some versions of PHP, with baking multi-tags.
* BUG: Skip page files that end with `~`.

### 1.0.2 (2013-06-13)

* BUG: Fixed a bug with the `pagination` object returning empty `next_post` and
  `prev_post` if the current post is not part of the latest posts.

### 1.0.1 (2013-06-13)

* BUG: Fixed a bug with the `pagination` object returning the wrong values for
  `total_post_count`, `total_page_count` and `all_page_numbers`.

### 1.0.0 (2013-06-03)

* BUG: Fixed missing new line after a Geshi node.
* BUG: Fixed crash in Geshi with "Go" language.

### 1.0.0-rc4 (2013-05-31)

* ADDED: Ability to enable `keep-alive` in StupidHttp for `chef serve`. This
  seems to fix some occasional problems.
* BUG: Fixed the install script that wasn't making the `chef` bootstrap
  executable.
* BUG: Fixed a bug in the `flat` file-system that was matching incorrect post
  files.
* BUG: Make sure the debug window doesn't inherit text variant properties.

### 1.0.0-rc3 (2013-05-19)

* BUG: Handle files with BOM headers.
* BUG: Fixed crash in Jekyll importer.
* BUG: Fixed a bug with baking tag pages with sub-pages when the tag has special
  or unicode characters.
* BUG: Fixed a problem with having multiple post loops on a single page.

### 1.0.0-rc2 (2013-04-24)

* BUG/CHANGE: Hopefully the last we hear about handling slufigication of tags
  and categories with non-ASCII characters.
* NEW: Added Haml formatter.

### 1.0.0-rc1 (2013-03-14)

* BREAKING CHANGE: Renamed the `xmldate` Twig filter to `atomdate`.
* BREAKING CHANGE: The `pagination.posts` iterator now prevents the user from
  modifying it, which could otherwise result in confusing behaviour.
* BREAKING CHANGE: Global `chef` options like `--root`, `--debug` or `--quiet`
  are now really global, and must be specified before the command name.
* BREAKING BUG FIX: Monthly blog archives were incorrectly ordered
  chronologically, instead of reverse-chronologically.
* NEW: Added `prepare feed` command to create RSS/Atom feeds.
* NEW: Added `plugins update` command to update installed plugins. For now, this
  command is not optimal and will force-update plugins without checking if
  there's a new version.
* NEW: Added support for "auto-formats", specified with the `site/auto_formats`
  configuration setting. This lets the user define a mapping between file
  extensions and text formats, such as `.md` for Markdown.
* NEW: In debug mode, Twig's debugging functions are available. They can also be
  enabled with the `twig/debug` config setting.
* NEW: The LESS processor can optionally run the Javascript command line tool
  instead of using the LessPHP library. This is done by setting `less/use_lessc`
  to `true` in the site configuration.
* NEW: Sass, Compass and YUICompressor processors are now part of the built-in
  processors, instead of being in plug-ins. They have also been improved, with
  Compass support being much better.
* CHANGE: Updated all 3rd-party libraries to their latest version.
* CHANGE: The LESS file processor is now using the 3rd-party library caching
  mechanism.
* CHANGE: Error handling and reporting has been made more consistent. On Mac and
  Linux, `serve` and `bake` will also print pretty colors!
* CHANGE: Any place that returns a list of pages or posts should now be a proper
  pagination iterator, with all the sorting and filtering features.
* CHANGE: The `baker/trailing_slash` setting is obsolete, replaced by
  `site/trailing_slash`. This setting also now affects the URLs in a preview
  server (`chef serve`) as well as during the bake.
* CHANGE: It is now possible to specify `posts_filters` on category and tag
  listing pages. Those filters will be combined with an `AND` boolean clause.
* CHANGE: Removed useless banner and `--info-only` option from `chef bake`.
* CHANGE: Renamed some template data: `asset` is now `assets`, `link` is now
  `siblings`, and a new `family` gives recursive access to all sibling and
  children pages (basically a sub-set of `site.pages`). The old names are still
  usable for backwards compatibility.
* BUG: Generate unique footnote IDs with Markdown-Extra when those footnotes are
  in posts and the current page lists them.
* BUG: Fixed some incorrect behaviour when a page/post iterator is iterated
  several times on a page.
* BUG: Fixed a crash when using a `has_xxx` filter on a setting that's not an
  array.

### 0.9.2 (2013-02-04)

* NEW: Added `site.pages` template data to list all the pages in the website
  recursively.
* NEW: The `sitemap` processor can now auto-generate entries for pages and/or
  posts.
* NEW: Themes can declare plugin dependencies that will be installed along with
  the theme.
* IMPROVEMENT: Slightly improved baking times.
* BUG: Assets (images, JS, CSS, etc.) were unnecessarily re-baked when the cache
  was invalidated.
* BUG: Fixed some bugs with tags and categories with spaces in them when
  previewing or baking a website.

### 0.9.1 (2012-12-04)

* BREAKING CHANGE: Removed buggy `lastmod` options for the sitemap processor.
  There's now just `now` or a hard-coded date.

### 0.9.0 (2012-12-03)

* BREAKING CHANGE: Templates directories added with the `site/templates_dirs`
  settings are now searched _before_ the default `_content/templates` directory.
* BREAKING CHANGE: Changed the Twig filter `striptag` to `stripoutertag` to
  avoid confusion with the existing `striptags` filter.
* BREAKING CHANGE: The `paginator` object now returns full URLs for `next_page`,
  `this_page` and `prev_page`. To prevent most cases of broken links after
  updating to the latest PieCrust, see the first "improvement" below.
* NEW: Added support for themes.
* NEW: Added file-system based repository for plugins and themes.
* NEW: Add new Twig filters: `stripslash`, `titlecase` and `xmldate`.
* NEW: Added an option to `chef serve` to specify the IP address to listen on.
* IMPROVEMENT: The `pcurl()` family of Twig functions won't do anything if a
  given URL is already absolute. This prevents errors where the site's root is
  added twice to an URL.
* IMPROVEMENT: The BitBucket repository now caches web requests for an hour.
* IMPROVEMENT: The Windows bootstrap script can better search for the PHP
  executable.
* BUG: Fixed problems when running in XAMPP on Windows.
* BUG: Fixed a bug with the `pagination` object which could generate
  double-slashes in navigation links.
* BUG: Fixed a bug with enabling the SmartyPants formatter that could result in
  an application crash.
* BUG: Change spaces into hyphens for tags and categories in URLs.

### 0.8.7 (2012-10-05)

* BUG: Fixed a crash when using `textfrom()` on an invalid path.
* BUG: Fixed a bug with posts dates when posts have a `date` configuration
  setting. This setting can now override the date provided by the file-system.
* BUG: Fixed some obscure bug in the unit-tests.

### 0.8.6 (2012-09-11)

* NEW: The `single_page` setting gives simpler output bake paths when baking
  with pretty URLs enabled. For example, `feed.xml` will map to `feed.xml`
  instead of `feed.xml/index.html`.

### 0.8.5 (2012-09-04)

* BUG: Fixed a problem with error handling always showing internal error details
  in dynamic mode, even with `display_errors` disabled.

### 0.8.4 (2012-08-24)

* NEW: By setting `baker/trailing_slash` to `true` in the site configuration,
  one can get trailing slashes for pretty-URL-type links on a baked site.
* BUG: Fixed a debug message incorrectly printed as a warning during bake.

### 0.8.3 (2012-08-21)

* BUG: Fixed a bug where incorrect page URIs wouldn't correctly be handled as
  errors.

### 0.8.2 (2012-08-13)

* BUG: Fixed an error with installing plugins on Windows.

### 0.8.1 (2012-07-20)

* BUG: In some situations, an incremental bake (_i.e._ a bake without the `-f`
  option) could miss some pages that needed to be re-processed.
* BUG: Fixed a bug where the PHP include path could grow insane when previewing
  a site with `chef serve` that has plugins loaded.
* BUG: Fixed a bug where some files inside `_content/pages` were incorrectly
  treated as pages.

### 0.8.0 (2012-07-18)

* BREAKING CHANGE: The filenames created by the baker are now more consistent.
  When `pretty_urls` are disabled, pages that create pagination have the same
  kind of URL as the ones that don't (_i.e._ `foo/bar.html`). Sub-pages are
  baked into `foo/bar/n.html` (where `n` is the page number).
* BREAKING CHANGE: Added support for extensions other than `.html` for source
  files -- and that extension is carried to the output file when baking. This
  means that `content_type` is back to being used only for setting HTML headers,
  as it should be. For now, the old behaviour is also supported, with a warning
  message.
* BREAKING CHANGE: Chef syntax changed to use hyphenated long option names
  everywhere (it was not 100% consistent until now). A warning message is
  printed when using the old syntax.
* BREAKING CHANGE: Some options from `chef bake` were removed because they are
  useless now that PieCrust supports config variants.
* NEW: PieCrust is now compilable into a `.phar` file.
* NEW: When using `chef serve`, an error triggered by a file processor will be
  rendered as an error page.
* NEW: One can change the current format in the middle of a page's content
  segment with the `<--formatter_name-->` syntax.
* NEW: Content segments can be written with a custom formatter by appending
  `:formatter_name` to the segment name (_e.g_: `---segment:format---`).
* NEW: Posts and pages obtained from `pagination` or `link` now also provide
  access to other content segments than the main one.
* NEW: Added a `sortBy` function to `pagination` and `link`.
* NEW: Added `all_page_numbers` and `page(i)` functions to `pagination`.
* NEW: Added `site/enabled_debug_info` site configuration setting to let users
  disable `debug_info` on production websites hosted as a CMS.
* CHANGE: The folder structure has been changed to make it look more like an
  application project. For users, this means the `chef` executable has been
  moved to the `/bin` folder (although, for backwards compatibility, a copy is
  still available in `/_piecrust/chef`, but it prints a warning message and will
  be removed after version 0.9).
* CHANGE: The `--fileurls` option from `chef bake` is deprecated and replaced by
  the `--portable` option. This creates a "portable" website where all URLs are
  specified relatively to the root (with `../../`-type paths).
* CHANGE: A new version of StupidHttp is used for `chef serve`.
* CHANGE: `chef plugins find` now lists all plugins from known repositories when
  no search pattern is given.
* CHANGE: The default website, created by `chef init`, is now better.
* CHANGE: An error message is displayed when a missing page asset is accessed.
* BUG: Fixed a bug with `pcurl` and the root URL when `pretty_urls` are
  disabled.
* BUG: Fixed a bug with `debug_info` being possibly incorrectly aligned.
* BUG: Fixed a bug with page slugs having incorrect slashes on Windows.
* BUG: Fixed a typo in the help text of `chef prepare`.
* BUG: Fixed some bugs in the Wordpress importer.

### 0.7.2 (2012-05-23)

* MINOR: Removed a call to a PHP 5.3.6-only method to prevent raising the
  minimum required version to run PieCrust.

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

