# Silverstripe recipe-plugin

[![CI](https://github.com/silverstripe/recipe-plugin/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/recipe-plugin/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

## Introduction

This plugin enhances composer and allows for the installation of "silverstripe-recipe" packages.
These recipes allow for the following features:

 - The ability to provide project resource files. These are copied to the appropriate project root location
   on install, and can be safely modified by the developer. On subsequent updates to a later recipe,
   composer will inform the user if a project file has been updated, and will ensure new files are
   copied as they are introduced to the recipe.
 - Recipes are composable, so resources or dependencies that are required by multiple recipes can include one another,
   rather than having to duplicate content.
 - Recipes also can be used as a base composer project.
 - A `require-recipe` command to inline a recipe into the root composer.json, allowing the developer to customise the
   recipe dependencies without mandating the inclusion of all requirements directly.
 - An `update-recipe` command to upgrade to a newer version of a recipe.

## Installation

```sh
composer require silverstripe/recipe-plugin
```

## Example output

![example-output](docs/_images/require-usage.png)

## Creating a new project

Recipes can be introduced to any existing project (even if not created on a silverstripe base project)

```sh
composer init
composer require silverstripe/recipe-cms
```

Alternatively you can create a new project based on an existing recipe

```sh
composer create-project silverstripe/recipe-cms ./myssproject
```

## Inlining recipes

You can "inline" either a previously installed recipe, or a new one that you would like to include
dependencies for in your main project. By inlining a recipe, you promote its requirements, as well as
its project files, up into your main project, and remove the recipe itself from your dependencies.

This can be done with either `update-recipe`, which will update a recipe, or `require-recipe` which will
install a new recipe.

Note that if you wish to run this command you must first install either a recipe via normal composer
commands, or install the recipe plugin:

```sh
composer init
composer require silverstripe/recipe-plugin
composer require-recipe silverstripe/recipe-cms
```

or

```sh
composer init
composer require silverstripe/recipe-cms
composer update-recipe silverstripe/recipe-cms
```

## Removing recipe dependencies or files

Any project file installed via a recipe, or any module installed by inlining a recipe, can be easily removed.
Subsequent updates to this recipe will not re-install any of those files or dependencies.

In order to ensure this, a record of all inlined modules, and all installed files are stored in composer.json
as below.

```json
{
    "extra": {
        "project-files-installed": [
            "mysite/code/Page.php",
            "mysite/code/PageController.php"
        ],
        "project-dependencies-installed": {
            "silverstripe/admin": "2.0.x-dev",
            "silverstripe/asset-admin": "2.0.x-dev",
            "silverstripe/campaign-admin": "2.0.x-dev"
        }
    }
}
```

To remove a file, simply delete it from the folder your project is installed in, but don't modify
`project-files-installed` (as this is how composer knows what not to re-install).

Likewise to remove a module, use `composer remove <module>` and it will be removed. As above, don't
modify `project-dependencies-instaleld`, otherwise that module will be re-installed on subsequent
`composer update-recipe`.

## Un-doing a deleted project file / dependency

If you have deleted a module or file and want to re-install it you should remove the appropriate
entry from either 'project-files-installed' or 'project-dependencies-installed' and then run
`composer update-recipe <recipe>` again.

The file or module will be re-installed.

## Removing recipes

As installation of a recipe inlines all dependencies and passes ownership to the root project,
there is no automatic removal process. To remove a recipe, you should manually remove any
required module that is no longer desired via `composer remove <module>`.

The `provide` reference to the recipe can also be safely removed, although it has no practical result
other than to disable future calls to `update-recipe` on this recipe.

## Installing or upgrading recipes without inlining them

If desired, the optional inline behaviour of recipes can be omitted. Simply use the composer commands `require` and
`update` in place of `require-recipe` and `update-recipe` respectively. This will not disable the project files
feature, but will not inline the recipe directly, keeping your root composer.json from getting cluttered.

If you have already inlined a recipe, it will be necessary to manually remove any undesired inlined requirements
manually, and the recipe will need to be included with `require` subsequently.

Note that using this method it's not necessary to include the `silverstripe/recipe-plugin` in the root project
for this to work.

## Recipe composer.json schema

Recipe types should follow the following rules:

 - No mandatory resources, other than project files.
 - Recipes must not rely on `autoload` as this are discarded on inline.
   Likewise any `*-dev` or other root-only options should not be used, as these are ignored outside of the root project.
   The exception to this is when these values are useful as a base project only.
 - The `type` must be `silverstripe-recipe`
 - The `require` must have `silverstripe/recipe-plugin` as a dependency.
 - `extra.project-files` must be declared as a list of wildcard patterns, matching the files in the recipe root
   as they should be copied to the root project. The relative paths of these resources are equivalent.
 - `extra.public-files` must be declared for any files which should be copied to the `public` web folder. If the project
   in question doesn't have any public folder, these will be copied to root instead. Note that all public files
   must be committed to the recipe `public` folder.

An example recipe:

```json
{
    "name": "silverstripe/example-recipe",
    "description": "Example silverstripe recipe",
    "type": "silverstripe-recipe",
    "require": {
        "silverstripe/recipe-plugin": "^1.0",
        "silverstripe/recipe-cms": "^5.0",
        "silverstripe/blog": "^4.0",
        "silverstripe/lumberjack": "^3.0",
    },
    "extra": {
        "project-files": [
            "mysite/_config/*.yml",
            "mysite/code/MyBlogPage.php"
            "client/src/*"
        ],
        "public-files": [
            "client/dist/*"
        ]
    },
    "prefer-stable": true,
    "minimum-stability": "dev"
}
```

The files within this recipe would be organised in the structure:

```
client/
  src/
    blog.scss
mysite/
 _config/
    settings.yml
  code/
    MyBlogPage.php
public/
  client/
    dist/
      blog.css
composer.json
```
