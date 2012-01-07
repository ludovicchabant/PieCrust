
PieCrust is a static website generator and lightweight CMS that's all managed
with text files. No complex setup, databases, or administrative panels.
Simple, beautiful, and yummy.

For more information, along with the complete documentation, visit [the
official website](http://bolt80.com/piecrust/).


Quickstart
==========

If you want to quickly give it a spin:

    _piecrust/chef serve --root website

It should start your default browser and show you the sample website. You can
then edit files and see what changes.

When you're happy, run `_piecrust/chef bake --root website` to generate the
final static website, which you'll find in `website/_counter`.


Branches
========

There are 2 branches:

* `default` (in `mercurial`) or `master` (in `git): that's the development
  branch.  Everything in there is the very latest stuff, which means it may be
  broken, it may have backwards incompatible changes, and probably has secret
  undocumented features.

* `stable` (in `mercurial`) or `git-stable` (in `git`): that's the, well,
  stable branch. It has all the latest bug-fixes, but new features and
  breaking changes are only introduced after some time being tested in the
  main branch. Also, this branch has version tags whenever changes are
  committed.


Breaking Changes
================

These are the latest breaking changes.

Stable Branch
-------------

* __Baking__: the `skip_patterns` setting is now applied to the relative path
  of files instead of the filename. This means that patterns like `/^blah/`
  will only skip files and directories starting with `blah` sitting in the
  root directory. A file called `somedir/blah.html` would still be baked,
  unless you change the pattern to `/\bblah/` or `/\/?blah/`.

* __Baking__: the `templates_dir` parameter given to `chef` has been
  deprecated. You can now more naturally specify additional templates
  directories in the configuration file with the `site/templates_dirs`
  setting.


Main Branch
-----------

* __Plugins__: PieCrust now supports plugins. The PhamlP formatter and
  template engine, along with the Dwoo template engine, have been removed from
  the main PieCrust repo and moved to their own plugins, [PieCrust_PhamlP][1]
  and [PieCrust_Dwoo][2].

* __Chef__: Chef now behaves like `git` or `hg`: it knows when you're inside a
  PieCrust directory by scanning the parent directory hierarchy for a
  `_content` folder. This makes it simper to use it (no need to specify the
  root directory anymore), but changed the options and arguments to most
  commands. Also, it makes it possible to use chef commands declared in
  site-specific plugins.

  [1]: https://bitbucket.org/ludovicchabant/piecrust-phamlp
  [2]: https://bitbucket.org/ludovicchabant/piecrust-dwoo

