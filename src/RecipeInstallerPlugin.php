<?php


namespace SilverStripe\RecipeInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Register the RecipeInstaller
 */
class RecipeInstallerPlugin implements PluginInterface
{

    /**
     * Apply plugin modifications to Composer
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new RecipeInstaller($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }
}