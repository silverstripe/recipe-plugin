<?php


namespace SilverStripe\RecipePlugin;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;

/**
 * Register the RecipeInstaller
 *
 * Credit to http://stackoverflow.com/questions/27194348/get-package-install-path-from-composer-script-composer-api
 */
class RecipePlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    /**
     * Type of recipe to check for
     */
    const RECIPE_TYPE = 'silverstripe-recipe';

    /**
     * 'extra' key for project files
     */
    const PROJECT_FILES = 'project-files';

    /**
     * 'extra' key for public files
     */
    const PUBLIC_FILES = 'public-files';

    /**
     * Hard-coded 'public' web-root folder
     */
    const PUBLIC_PATH = 'public';

    /**
     * 'extra' key for list of project files installed
     */
    const PROJECT_FILES_INSTALLED = 'project-files-installed';

    /**
     * 'extra' key for list of public files installed
     */
    const PUBLIC_FILES_INSTALLED = 'public-files-installed';

    /**
     * 'extra' key for project dependencies installed
     */
    const PROJECT_DEPENDENCIES_INSTALLED = 'project-dependencies-installed';

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
        $file = new JsonFile(Factory::getComposerFile());
        $data = $file->read();

        // Remove project and public files from project
        unset($data['extra'][self::PROJECT_FILES]);
        unset($data['extra'][self::PUBLIC_FILES]);

        // Remove redundant empty extra
        if (empty($data['extra'])) {
            unset($data['extra']);
        }

        // Save back to composer.json
        $file->write($data);
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
