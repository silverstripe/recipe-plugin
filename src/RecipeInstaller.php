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

class RecipeInstaller extends LibraryInstaller
{
    /**
     * @var bool
     */
    private $hasWrittenFiles = false;

    public function __construct(IOInterface $io, Composer $composer)
    {
        parent::__construct($io, $composer, null);
    }

    /**
     * Install project files in the specified directory
     *
     * @param string $recipe Recipe name
     * @param string $sourceRoot Base of source files (no trailing slash)
     * @param string $destinationRoot Base of destination directory (no trailing slash)
     * @param array $filePatterns List of file patterns in wildcard format (e.g. `code/My*.php`)
     * @param string $registrationKey Registration key for installed files
     * @param string $name Name of project file type being installed
     */
    protected function installProjectFiles(
        $recipe,
        $sourceRoot,
        $destinationRoot,
        $filePatterns,
        $registrationKey,
        $name = 'project'
    ) {
        // load composer json data
        $composerFile = new JsonFile(Factory::getComposerFile(), null, $this->io);
        $composerData = $composerFile->read();
        $installedFiles = isset($composerData['extra'][$registrationKey])
            ? $composerData['extra'][$registrationKey]
            : [];

        // Load all project files
        $fileIterator = $this->getFileIterator($sourceRoot, $filePatterns);
        $any = false;
        foreach ($fileIterator as $path => $info) {
            // Write header on first file
            if (!$any) {
                $this->io->write("Installing {$name} files for recipe <info>{$recipe}</info>:");
                $any = true;
            }

            // Install this file
            $relativePath = $this->installProjectFile($sourceRoot, $destinationRoot, $path, $installedFiles);

            // Add file to installed (even if already exists)
            if (!in_array($relativePath, $installedFiles ?? [])) {
                $installedFiles[] = $relativePath;
            }
        }

        // If any files are written, modify composer.json with newly installed files
        if ($this->hasWrittenFiles) {
            sort($installedFiles);
            if (!isset($composerData['extra'])) {
                $composerData['extra'] = [];
            }
            $composerData['extra'][$registrationKey] = $installedFiles;
            $composerFile->write($composerData);
            // Reset the variable so that we can try this trick again later
            $this->hasWrittenFiles = false;
        }
    }

    /**
     * @param string $sourceRoot Base of source files (no trailing slash)
     * @param string $destinationRoot Base of destination directory (no trailing slash)
     * @param string $sourcePath Full filesystem path to the file to copy
     * @param array $installedFiles List of installed files
     * @return bool|string
     */
    protected function installProjectFile($sourceRoot, $destinationRoot, $sourcePath, $installedFiles)
    {
        // Relative path
        $relativePath = substr($sourcePath ?? '', strlen($sourceRoot ?? '') + 1); // Name path without leading '/'

        // Get destination path
        $destination = $destinationRoot . DIRECTORY_SEPARATOR . $relativePath;

        // Check if file exists
        if (file_exists($destination ?? '')) {
            if (file_get_contents($destination ?? '') === file_get_contents($sourcePath ?? '')) {
                $this->io->write(
                    "  - Skipping <info>$relativePath</info> (<comment>existing, but unchanged</comment>)"
                );
            } else {
                $this->io->write(
                    "  - Skipping <info>$relativePath</info> (<comment>existing and modified in project</comment>)"
                );
            }
        } elseif (in_array($relativePath, $installedFiles ?? [])) {
            // Don't re-install previously installed files that have been deleted
            $this->io->write(
                "  - Skipping <info>$relativePath</info> (<comment>previously installed</comment>)"
            );
        } else {
            $this->io->write("  - Copying <info>$relativePath</info>");
            $this->filesystem->ensureDirectoryExists(dirname($destination ?? ''));
            copy($sourcePath ?? '', $destination ?? '');
            $this->hasWrittenFiles = true;
        }
        return $relativePath;
    }

    /**
     * Get iterator of matching source files to copy
     *
     * @param string $sourceRoot Root directory of sources (no trailing slash)
     * @param array $patterns List of wildcard patterns to match
     * @return Iterator File iterator, where key is path and value is file info object
     */
    protected function getFileIterator($sourceRoot, $patterns)
    {
        // Build regexp pattern
        $expressions = [];
        foreach ($patterns as $pattern) {
            $expressions[] = $this->globToRegexp($pattern);
        }
        $regExp = '#^' . $this->globToRegexp($sourceRoot . '/') . '((' . implode(')|(', $expressions) . '))$#';

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
    protected function globToRegexp($glob)
    {
        $sourceParts = explode('*', $glob ?? '');
        $regexParts = array_map(function ($part) {
            return preg_quote($part ?? '', '#');
        }, $sourceParts ?? []);
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

        // Find recipe base dir
        $recipePath = $this->getInstallPath($package);

        // Find project path
        $projectPath = dirname(realpath(Factory::getComposerFile() ?? '') ?? '');

        // Find public path
        $publicPath = $projectPath . DIRECTORY_SEPARATOR . RecipePlugin::PUBLIC_PATH;

        // Copy project files to root
        $name = $package->getName();
        $extra = $package->getExtra();

        // Install project-files
        if (isset($extra[RecipePlugin::PROJECT_FILES])) {
            $this->installProjectFiles(
                $name,
                $recipePath,
                $projectPath,
                $extra[RecipePlugin::PROJECT_FILES],
                RecipePlugin::PROJECT_FILES_INSTALLED,
                'project'
            );
        }

        // Install public-files
        if (isset($extra[RecipePlugin::PUBLIC_FILES])) {
            $this->installProjectFiles(
                $name,
                $recipePath . '/' . RecipePlugin::PUBLIC_PATH,
                $publicPath,
                $extra[RecipePlugin::PUBLIC_FILES],
                RecipePlugin::PUBLIC_FILES_INSTALLED,
                'public'
            );
        }
    }
}
