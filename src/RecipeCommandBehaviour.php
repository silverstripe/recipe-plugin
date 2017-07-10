<?php

namespace SilverStripe\RecipePlugin;

use BadMethodCallException;
use Composer\Command\RequireCommand;
use Composer\Command\UpdateCommand;
use Composer\Composer;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

trait RecipeCommandBehaviour
{
    /**
     * Gets the application instance for this command.
     *
     * @return Application An Application instance
     */
    public abstract function getApplication();

    /**
     * @param  bool              $required
     * @param  bool|null         $disablePlugins
     * @throws \RuntimeException
     * @return Composer
     */
    public abstract function getComposer($required = true, $disablePlugins = null);

    /**
     * Removes the cached composer instance
     */
    public abstract function resetComposer();

    /**
     * Load composer data from the given directory
     *
     * @param string $path
     * @param array|null $default If file doesn't exist use this default. If null, file is mandatory and there is
     * no default
     * @return array
     */
    protected function loadComposer($path, $default = null)
    {
        if (!file_exists($path)) {
            if (isset($default)) {
                return $default;
            }
            throw new BadMethodCallException("Could not find " . basename($path));
        }
        $data = json_decode(file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \LogicException("Invalid composer.json with error: " . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Save the given data to the composer file in the given directory
     *
     * @param string $directory
     * @param array $data
     */
    protected function saveComposer($directory, $data)
    {
        $path = $directory.'/composer.json';
        if (!file_exists($path)) {
            throw new BadMethodCallException("Could not find composer.json");
        }
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Make sure errors are reported
        if (json_last_error()) {
            throw new InvalidArgumentException("Invalid composer.json data with error: " . json_last_error_msg());
        }
        file_put_contents($path, $content);
    }

    /**
     * @param OutputInterface $output
     * @param string $recipe
     * @param string $constraint
     * @return int
     */
    protected function requireRecipe(OutputInterface $output, $recipe, $constraint = null)
    {
        /** @var RequireCommand $command */
        $command = $this->getApplication()->find('require');
        $packages = [$recipe];
        if ($constraint) {
            $packages[] = $constraint;
        }
        $arguments = [
            'command' => 'require',
            'packages' => $packages,
        ];
        $requireInput = new ArrayInput($arguments);
        $returnCode = $command->run($requireInput, $output);

        // Flush modified composer object
        $this->resetComposer();
        return $returnCode;
    }

    /**
     * Update the project
     *
     * @param OutputInterface $output
     * @return int
     */
    protected function updateProject(OutputInterface $output)
    {
        /** @var UpdateCommand $command */
        $command = $this->getApplication()->find('update');
        $arguments = [ 'command' => 'update' ];
        $requireInput = new ArrayInput($arguments);
        $returnCode = $command->run($requireInput, $output);

        // Flush modified composer object
        $this->resetComposer();
        return $returnCode;
    }

    /**
     * Find installed version or constraint
     *
     * @param string $recipe
     * @return string
     */
    protected function findInstalledVersion($recipe)
    {
        // Check locker
        $installed = $this->getComposer()->getLocker()->getLockedRepository()->findPackage($recipe, '*');
        if ($installed) {
            return $installed->getPrettyVersion();
        }

        // Check provides
        $provides = $this->getComposer()->getPackage()->getProvides();
        if (isset($provides[$recipe])) {
            return $provides[$recipe]->getPrettyConstraint();
        }

        // Check requires
        $requires = $this->getComposer()->getPackage()->getRequires();
        if (isset($requires[$recipe])) {
            return $provides[$recipe]->getPrettyConstraint();
        }

        // No existing version
        return null;
    }

    /**
     * Guess constraint to use if not provided
     *
     * @param string $existingVersion Known installed version
     * @return string
     */
    protected function findBestConstraint($existingVersion)
    {
        // Cannot guess without existing version
        if (!$existingVersion) {
            return null;
        }

        // Existing version is already a ^1.0.0 or ~1.0.0 constraint
        if (preg_match('#^[~^]#', $existingVersion)) {
            return $existingVersion;
        }

        // Existing version is already a dev constraint
        if (stristr($existingVersion, 'dev') !== false) {
            return $existingVersion;
        }

        // Numeric-only version maps to semver constraint
        if (preg_match('#^([\d.]+)$#', $existingVersion)) {
            return "^{$existingVersion}";
        }

        // Cannot guess; Let composer choose (equivalent to `composer require vendor/library`)
        return null;
    }
}
