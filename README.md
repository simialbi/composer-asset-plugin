NPM/Bower Dependency Manager for Composer v2
============================================

[![Latest Stable Version](https://poser.pugx.org/simialbi/composer-asset-plugin/v/stable?format=flat-square)](https://packagist.org/packages/simialbi/composer-asset-plugin)
[![Total Downloads](https://poser.pugx.org/simialbi/composer-asset-plugin/downloads?format=flat-square)](https://packagist.org/packages/simialbi/composer-asset-plugin)
[![License](https://poser.pugx.org/simialbi/composer-asset-plugin/license?format=flat-square)](https://packagist.org/packages/simialbi/composer-asset-plugin)
[![build](https://github.com/simialbi/composer-asset-plugin/actions/workflows/build.yml/badge.svg)](https://github.com/simialbi/composer-asset-plugin/actions/workflows/build.yml)

The Composer Asset Plugin allows you to manage project assets (css, js, etc.) in your `composer.json`
without installing NPM or Bower.

This plugin works by transposing package information from NPM or Bower to a compatible version for Composer.
This allows you to manage asset dependencies in a PHP based project very easily.

> **Important:**
>
> ⚠ This plugin is based on [François Pluchino](https://github.com/francoispluchino)'s [Version for composer 1](https://github.com/fxpio/composer-asset-plugin).
> This v2 is not maintained by [François Pluchino](https://github.com/francoispluchino).
> 
> The next official major version of Composer Asset Plugin by [François Pluchino](https://github.com/francoispluchino) is so different, but also incompatible with the current version,
> that it became a new project named [Foxy](https://github.com/fxpio/foxy).

##### Features include:

- Works with native management system versions of VCS repository of composer
- Works with public and private VCS repositories
- Lazy loader of asset package definitions to improve performance
- Import filter with the dependencies of the root package and the installed packages, for increased dramatically the performance for the update
- Automatically get and create an Asset VCS repository defined in:
  - [NPM Registry](https://www.npmjs.org)
  - [Bower Registry](http://bower.io/search)
  - [Private Bower Registry](https://github.com/Hacklone/private-bower)
- Automatically get and create the Asset VCS repositories of dependencies defined
  in each asset package (dev dependencies included)
- Mapping conversion of asset package to composer package for:
  - [NPM Package](https://www.npmjs.org/doc/package.json.html) - [package.json](resources/doc/schema.md#npm-mapping)
  - [Bower Package](http://bower.io/docs/creating-packages) - [bower.json](resources/doc/schema.md#bower-mapping)
- Conversion of [Semver version](resources/doc/schema.md#verison-conversion) to the composer version
- Conversion of [Semver range version](resources/doc/schema.md#range-verison-conversion) to the composer range version
- Conversion of [dependencies with URL](resources/doc/schema.md#url-range-verison-conversion) to the composer dependencies with the creation of VCS repositories
- Conversion of [multiple versions of the same dependency](resources/doc/schema.md#multiple-versions-of-a-dependency-in-the-same-project) to different dependencies of composer
- Add manually the [multiple versions of a same dependency in the project](resources/doc/index.md#usage-with-multiple-versions-of-the-same-dependency)
- Add a [custom config of VCS Repository](resources/doc/index.md#usage-with-vcs-repository)
- Override the [config of VCS Repository](resources/doc/index.md#overriding-the-config-of-a-vcs-repository) defined by the asset registry directly in config section of root composer
- VCS drivers for:
  - [Git](resources/doc/index.md#usage-with-vcs-repository)
  - [GitHub](resources/doc/index.md#usage-with-vcs-repository) (compatible with repository redirects)
  - [Git Bitbucket](resources/doc/index.md#usage-with-vcs-repository)
  - [Mercurial](resources/doc/index.md#usage-with-vcs-repository)
  - [SVN](resources/doc/index.md#usage-with-vcs-repository)
  - [Perforce](resources/doc/index.md#usage-with-vcs-repository)
- Local cache system for:
  - package versions
  - package contents
  - repository redirects
- Custom asset installers configurable in the root file `composer.json`
- For Bower, all files defined in the section `ignore` will not be installed
- Disable or replace the deleting of the ignore files for Bower
- Enable manually the deleting of the ignore files for NPM
- Use the Ignore Files Manager in the Composer scripts
- Configure the plugin per project, globally or with the environment variables
- Compatible with all commands, including:
  - `depends`
  - `diagnose`
  - `licenses`
  - `remove`
  - `require`
  - `search`
  - `show`
  - `status`

##### Why this plugin?

There already are several possibilities for managing assets in a PHP project:

1. Install Node.js and use NPM or Bower command line in addition to Composer command line
2. Do #1, but add Composer scripts to automate the process
3. Include assets directly in the project (not recommended)
4. Create a repository with all assets and include the `composer.json` file (and use Packagist or an VCS Repository)
5. Add a package repository in `composer.json` with a direct download link
6. Create a Satis or Packagist server
7. Other?

It goes without saying that each javascript, CSS, etc. library should be developed with the usual tools for that
language, which front-end developers know well. However, in the case of a complete project in PHP, it shouldn't 
be necessary to use several tools (PHP, Nodejs, Composer, NPM, Bower, Grunt, etc.) to simply install
these assets in your project.

This plugin has been created to address these issues. Additionally, most developers will not add a `composer.json`
file to their projects just to support php based projects, especially when npm and/or bower already exist and are
widely used.

Documentation
-------------

The bulk of the documentation is located in `Resources/doc/index.md`:

[Read the Documentation](resources/doc/index.md)

[Read the FAQs](resources/doc/faqs.md)

[Read the Release Notes](https://github.com/simialbi/composer-asset-plugin/releases)

Installation
------------

All the installation instructions are located in [documentation](resources/doc/index.md).

License
-------

This composer plugin is under the MIT license. See the complete license in:

[LICENSE](LICENSE)

About
-----

Fxp Composer Asset Plugin is a [François Pluchino](https://github.com/francoispluchino) initiative.
See also the list of [contributors](https://github.com/simialbi/composer-asset-plugin/contributors).

Reporting an issue or a feature request
---------------------------------------

Issues and feature requests are tracked in the [Github issue tracker](https://github.com/simialbi/composer-asset-plugin/issues).
