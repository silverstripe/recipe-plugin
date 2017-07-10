<?php


namespace SilverStripe\RecipePlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use LogicException;

/**
 * Register the RecipeInstaller
 *
 * Credit to http://stackoverflow.com/questions/27194348/get-package-install-path-from-composer-script-composer-api
 */
class RecipePlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    public function activate(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-create-project-cmd' => 'cleanupProject',
            'post-package-update' => 'installPackage',
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
     * Cleanup the root package on create-project
     *
     * @param Event $event
     */
    public function cleanupProject(Event $event)
    {
        $path = getcwd() . '/composer.json';

        // Load composer data
        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LogicException("Invalid composer.json with error: " . json_last_error_msg());
        }

        // Remove project-files from project
        if (isset($data['extra']['project-files'])) {
            unset($data['extra']['project-files']);
        }

        // Clean empty extra key
        if (empty($data['extra'])) {
            unset($data['extra']);
        }

        // Save back to composer.json
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (json_last_error()) {
            throw new LogicException("Invalid composer.json data with error: " . json_last_error_msg());
        }
        file_put_contents($path, $content);
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
