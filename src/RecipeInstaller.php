<?php

namespace SilverStripe\RecipePlugin;

use Composer\Factory;
use Composer\Installer\LibraryInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class RecipeInstaller extends LibraryInstaller {

    /**
     * RecipeInstaller constructor.
     *
     * @param IOInterface $io
     * @param Composer $composer
     * @param string $type
     * @param Filesystem $filesystem
     */
    public function __construct(IOInterface $io, Composer $composer, $type = null, Filesystem $filesystem = null) {
        parent::__construct($io, $composer, $type, $filesystem);
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
    protected function installProjectFiles($recipe, $sourceRoot, $destinationRoot, $filePatterns, $registrationKey, $name = 'project')
    {
        // fetch the installed files from the json data
        $installedFiles = $this->getInstalledFiles($registrationKey);

        // Load all project files
        $fileIterator = $this->getFileIterator($sourceRoot, $filePatterns);
        $any = false;
        foreach($fileIterator as $path => $info) {
            $destination = $destinationRoot . substr($path, strlen($sourceRoot));
            $destinationExt = pathinfo($destination, PATHINFO_EXTENSION);
            if ($destinationExt === 'tmpl') {
                $destination = substr($destination, 0, -5);
            }
            $relativePath = substr($path, strlen($sourceRoot) + 1); // Name path without leading '/'
            $relativePathExt = pathinfo($relativePath, PATHINFO_EXTENSION);
            if ($relativePathExt === 'tmpl') {
                $relativePath = substr($relativePath, 0, -5);
            }

            // Write header
            if (!$any) {
                $this->io->write("Installing {$name} files for recipe <info>{$recipe}</info>:");
                $any = true;
            }

            // Check if file exists
            if ($this->fileExists($destination)) {
                if ($this->fileGetContents($destination) === $this->fileGetContents($path)) {
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
                $this->filesystem->copy($path, $destination);
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
            $composerData['extra'][$registrationKey] = $installedFiles;
            $this->getComposerFile()->write($composerData);
        }
    }

    public function fileExists($filename)
    {
        return file_exists($filename);
    }

    public function fileGetContents($filename, $use_include_path = false, $context = null, $offset = 0, $maxlen = null)
    {
        return file_get_contents($filename, $use_include_path, $context, $offset, $maxlen);
    }

    protected function getComposerFile()
    {
        return new JsonFile(Factory::getComposerFile(), null, $this->io);
    }

    protected function getInstalledFiles($registrationKey)
    {
        // load composer json data
        $composerFile = $this->getComposerFile();
        $composerData = $composerFile->read();
        return isset($composerData['extra'][$registrationKey])
            ? $composerData['extra'][$registrationKey]
            : [];
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

        // Find recipe base dir
        $recipePath = $this->getInstallPath($package);

        // Find project path
        $projectPath = dirname(realpath(Factory::getComposerFile()));

        // Find public path
        $candidatePublicPath = $projectPath . DIRECTORY_SEPARATOR . RecipePlugin::PUBLIC_PATH;
        $publicPath = is_dir($candidatePublicPath) ? $candidatePublicPath : $projectPath;

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
