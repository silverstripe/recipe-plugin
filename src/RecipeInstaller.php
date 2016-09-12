<?php

namespace SilverStripe\RecipePlugin;

use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use FilesystemIterator;
use Iterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class RecipeInstaller extends LibraryInstaller {
    public function __construct(
        IOInterface $io,
        Composer $composer,
        $type = 'silverstripe-recipe',
        Filesystem $filesystem = null,
        BinaryInstaller $binaryInstaller = null
    ) {
        parent::__construct($io, $composer, $type, $filesystem, $binaryInstaller);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        // Copy project files to root
        $destinationPath = getcwd();
        $name = $package->getName();
        $extra = $package->getExtra();
        if (isset($extra['project-files'])) {
            $this->installProjectFiles($name, $this->getInstallPath($package), $destinationPath, $extra['project-files']);
        }
    }

    /**
     * Install project files in the specified directory
     *
     * @param string $recipe Recipe name
     * @param string $sourceRoot Base of source files (no trailing slash)
     * @param string $destinationRoot Base of destination directory (no trailing slash)
     * @param array $filePatterns List of file patterns in wildcard format (e.g. `code/My*.php`)
     */
    public function installProjectFiles($recipe, $sourceRoot, $destinationRoot, $filePatterns)
    {
        $fileIterator = $this->getFileIterator($sourceRoot, $filePatterns);
        foreach($fileIterator as $path => $info) {
            $relativePath = substr($path, strlen($sourceRoot));
            $destination = $destinationRoot . $relativePath;

            // Only copy non-existent files
            if (file_exists($destination)) {
                continue;
            }
            $this->io->write("Installing recipe <info>$recipe</info> file <info>$relativePath</info>");
            $this->filesystem->ensureDirectoryExists(dirname($destination));
            file_put_contents($destination, file_get_contents($path));
        }
    }

    /**
     * Get iterator of matching source files to copy
     *
     * @param string $sourceRoot Root directory of sources (no trailing slash)
     * @param array $patterns List of wildcard patterns to match
     * @return Iterator File iterator, where key is path and value is file info object
     */
    public function getFileIterator($sourceRoot, $patterns) {
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
}
