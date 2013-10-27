
[![build status](https://secure.travis-ci.org/ludovicchabant/PieCrust.png?branch=master)](http://travis-ci.org/ludovicchabant/PieCrust)

PieCrust is a static website generator and lightweight CMS that's all managed
with text files. No complex setup, databases, or administrative panels.
Simple, beautiful, and yummy.

For more information, along with the complete documentation, visit [the
official website](http://bolt80.com/piecrust/).


Quickstart
==========

If you want to quickly give it a spin:

    bin/chef init mywebsite
    cd mywebsite
    ../bin/chef serve

It should create a new empty site in a `mywebsite` folder, and then start your
default browser to show it to you. Use `chef prepare page` and `chef prepare
post` to create pages and posts, and edit those in your favorite text editor.

When you're happy, run `../bin/chef bake` to generate the final static website,
which you'll find in `_counter`.


Branches
========

There are 2 branches:

* `default` (in `mercurial`) or `master` (in `git`): that's the development
  branch.  Everything in there is the very latest stuff, which means it may be
  broken, it may have backwards incompatible changes, and probably has secret
  undocumented features.

* `stable` (in `mercurial`) or `git-stable` (in `git`): that's the, well,
  stable branch. It has all the latest bug-fixes, but new features and
  breaking changes are only introduced after some time being tested in the
  main branch. Also, this branch has version tags whenever changes are
  committed.


Changes
=======

Check out the CHANGELOG file for new features, bug fixes and breaking changes. 


  [1]: https://bitbucket.org/ludovicchabant/piecrust-phamlp
  [2]: https://bitbucket.org/ludovicchabant/piecrust-dwoo

