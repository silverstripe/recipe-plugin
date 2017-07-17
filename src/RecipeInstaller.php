<?php

namespace SilverStripe\RecipePlugin;

use Composer\Factory;
use Composer\Installer\LibraryInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class RecipeInstaller extends LibraryInstaller {

    public function __construct(IOInterface $io, Composer $composer) {
        parent::__construct($io, $composer, null);
    }

    /**
     * Install project files in the specified directory
     *
     * @param string $recipe Recipe name
     * @param string $sourceRoot Base of source files (no trailing slash)
     * @param string $destinationRoot Base of destination directory (no trailing slash)
     * @param array $filePatterns List of file patterns in wildcard format (e.g. `code/My*.php`)
     */
    protected function installProjectFiles($recipe, $sourceRoot, $destinationRoot, $filePatterns)
    {
        // load composer json data
        $composerFile = new JsonFile(Factory::getComposerFile(), null, $this->io);
        $composerData = $composerFile->read();
        $installedFiles = isset($composerData['extra'][RecipePlugin::PROJECT_FILES_INSTALLED])
            ? $composerData['extra'][RecipePlugin::PROJECT_FILES_INSTALLED]
            : [];

        // Load all project files
        $fileIterator = $this->getFileIterator($sourceRoot, $filePatterns);
        $any = false;
        foreach($fileIterator as $path => $info) {
            $destination = $destinationRoot . substr($path, strlen($sourceRoot));
            $relativePath = substr($path, strlen($sourceRoot) + 1); // Name path without leading '/'

            // Write header
            if (!$any) {
                $this->io->write("Installing project files for recipe <info>{$recipe}</info>:");
                $any = true;
            }

            // Check if file exists
            if (file_exists($destination)) {
                if (file_get_contents($destination) === file_get_contents($path)) {
                    $this->io->write(
                        "  - Skipping <info>$relativePath</info> (<comment>existing, but unchanged</comment>)"
                    );
                } else {
                    $this->io->write(
                        "  - Skipping <info>$relativePath</info> (<comment>existing and modified in project</comment>)"
                    );
                }
            } elseif (in_array($relativePath, $installedFiles)) {
                // Don't re-install previously installed files that have been deleted
                $this->io->write(
                    "  - Skipping <info>$relativePath</info> (<comment>previously installed</comment>)"
                );
            } else {
                $any++;
                $this->io->write("  - Copying <info>$relativePath</info>");
                $this->filesystem->ensureDirectoryExists(dirname($destination));
                copy($path, $destination);
            }

            // Add file to installed (even if already exists)
            if (!in_array($relativePath, $installedFiles)) {
                $installedFiles[] = $relativePath;
            }
        }

        // If any files are written, modify composer.json with newly installed files
        if ($installedFiles) {
            sort($installedFiles);
            if (!isset($composerData['extra'])) {
                $composerData['extra'] = [];
            }
            $composerData['extra'][RecipePlugin::PROJECT_FILES_INSTALLED] = $installedFiles;
            $composerFile->write($composerData);
        }
    }

    /**
     * Get iterator of matching source files to copy
     *
     * @param string $sourceRoot Root directory of sources (no trailing slash)
     * @param array $patterns List of wildcard patterns to match
     * @return Iterator File iterator, where key is path and value is file info object
     */
    protected function getFileIterator($sourceRoot, $patterns) {
        // Build regexp pattern
        $expressions = [];
        foreach($patterns as $pattern) {
            $expressions[] = $this->globToRegexp($pattern);
        }
        $regExp = '#^' . $this->globToRegexp($sourceRoot . '/').'(('.implode(')|(', $expressions).'))$#';

        // Build directory iterator
        $directoryIterator = new RecursiveDirectoryIterator(
            $sourceRoot,
            FilesystemIterator::SKIP_DOTS
                | FilesystemIterator::UNIX_PATHS
                | FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
        );

        // Return filtered iterator
        $iterator = new RecursiveIteratorIterator($directoryIterator);
        return new RegexIterator($iterator, $regExp);
    }

    /**
     * Convert glob pattern to regexp
     *
     * @param string $glob
     * @return string
     */
    protected function globToRegexp($glob) {
        $sourceParts = explode('*', $glob);
        $regexParts = array_map(function($part) {
            return preg_quote($part, '#');
        }, $sourceParts);
        return implode('(.+)', $regexParts);
    }

    /**
     * @param PackageInterface $package
     */
    public function installLibrary(PackageInterface $package)
    {
        // Check if silverstripe-recipe type
        if ($package->getType() !== RecipePlugin::RECIPE_TYPE) {
            return;
        }

        // Find project path
        $destinationPath = dirname(realpath(Factory::getComposerFile()));

        // Copy project files to root
        $name = $package->getName();
        $extra = $package->getExtra();
        if (isset($extra[RecipePlugin::PROJECT_FILES])) {
            $this->installProjectFiles(
                $name,
                $this->getInstallPath($package),
                $destinationPath,
                $extra[RecipePlugin::PROJECT_FILES]
            );
        }
    }
}
