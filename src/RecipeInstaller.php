<?php

namespace SilverStripe\RecipePlugin;

use Composer\Installer\BinaryInstaller;
use Composer\Installer\LibraryInstaller;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;

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

        // Install recipe skeleton
        var_dump("Package info:\n");
        var_dump($package);
        var_dump($this->getInstallPath($package));
        var_dump($package->getExtra());

        var_dump("Repo info:\n");
        var_dump($repo);
        if(file_exists('composer.json')) {
            var_dump(file_get_contents('composer.json'));
        }

        var_dump("Installer info:\n");
        var_dump($this);
    }
}
