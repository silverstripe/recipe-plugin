<?php


namespace SilverStripe\RecipePlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider;

/**
 * Register the RecipeInstaller
 *
 * Credit to http://stackoverflow.com/questions/27194348/get-package-install-path-from-composer-script-composer-api
 */
class RecipePlugin implements PluginInterface, EventSubscriberInterface, Capable
{

    /**
     * Recipe type
     */
    const RECIPE = 'silverstripe-recipe';

    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-package-install' => 'installPackage',
        ];
    }

    /**
     * Install resources from an installed or updated package
     *
     * @param PackageEvent $event
     */
    public function installPackage(PackageEvent $event)
    {
        $package = $this->getOperationPackage($event);
        if ($package) {
            $installer = new RecipeInstaller($event->getIO(), $event->getComposer());
            $installer->installLibrary($package);
        }
    }

    /**
     * Get target package from operation
     *
     * @param PackageEvent $event
     * @return PackageInterface
     */
    protected function getOperationPackage(PackageEvent $event)
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }
        if ($operation instanceof InstallOperation) {
            return $operation->getPackage();
        }
        return null;
    }

    public function getCapabilities()
    {
        return [
            CommandProvider::class => RecipeCommandProvider::class
        ];
    }
}
