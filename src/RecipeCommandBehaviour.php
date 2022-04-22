<?php

namespace SilverStripe\RecipePlugin;

use Composer\Command\RequireCommand;
use Composer\Command\UpdateCommand;
use Composer\Composer;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

trait RecipeCommandBehaviour
{
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
            return $requires[$recipe]->getPrettyConstraint();
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
        if (preg_match('#^[~^]#', $existingVersion ?? '')) {
            return $existingVersion;
        }

        // Existing version is already a dev constraint
        if (stristr($existingVersion ?? '', 'dev') !== false) {
            return $existingVersion;
        }

        // Numeric-only version maps to semver constraint
        if (preg_match('#^([\d.]+)$#', $existingVersion ?? '')) {
            return "^{$existingVersion}";
        }

        // Cannot guess; Let composer choose (equivalent to `composer require vendor/library`)
        return null;
    }

    /**
     * Install or update a recipe with a given constraint and current version
     *
     * @param OutputInterface $output
     * @param string $recipe
     * @param string $constraint
     * @param string $installedVersion
     * @return int
     */
    protected function installRecipe(OutputInterface $output, $recipe, $constraint, $installedVersion)
    {
        if ($installedVersion) {
            if ($constraint) {
                $output->writeln(
                    "Updating existing recipe from <info>{$installedVersion}</info> to <info>{$constraint}</info>"
                );
            } else {
                // Show a guessed constraint
                $constraint = $this->findBestConstraint($installedVersion);
                if ($constraint) {
                    $output->writeln(
                        "Updating existing recipe from <info>{$installedVersion}</info> to <info>{$constraint}</info> "
                        . "(auto-detected constraint)"
                    );
                } else {
                    $output->writeln(
                        "Updating existing recipe from <info>{$installedVersion}</info> to latest version"
                    );
                }
            }
        }

        // Ensure composer require includes this recipe
        $returnCode = $this->requireRecipe($output, $recipe, $constraint);
        if ($returnCode) {
            return $returnCode;
        }

        // inline all dependencies inline into composer.json
        $this->modifyComposer(function ($composerData) use ($output, $recipe, $installedVersion) {
            // Check previously installed, and currently installed modules
            $require = isset($composerData['require']) ? $composerData['require'] : [];
            $previouslyInstalled = isset($composerData['extra'][RecipePlugin::PROJECT_DEPENDENCIES_INSTALLED])
                ? $composerData['extra'][RecipePlugin::PROJECT_DEPENDENCIES_INSTALLED]
                : [];

            // Get composer data for both root and newly installed recipe
            $installedRecipe = $this
                ->getComposer()
                ->getRepositoryManager()
                ->getLocalRepository()
                ->findPackage($recipe, '*');
            if ($installedRecipe) {
                $output->writeln("Inlining all dependencies for recipe <info>{$recipe}</info>:");
                foreach ($installedRecipe->getRequires() as $requireName => $requireConstraint) {
                    $requireVersion = $requireConstraint->getPrettyConstraint();

                    // If already installed, upgrade
                    if (isset($require[$requireName])) {
                        // Check if upgrade or not
                        $requireInstalledVersion = $require[$requireName];
                        if ($requireInstalledVersion === $requireVersion) {
                            // No need to upgrade
                            $output->writeln(
                                "  - Skipping <info>{$requireName}</info> "
                                . "(Already installed as <comment>{$requireVersion}</comment>)"
                            );
                        } else {
                            // Upgrade obsolete version
                            $output->writeln(
                                "  - Inlining <info>{$requireName}</info> "
                                . "(Updated to <comment>{$requireVersion}</comment> from "
                                . "<comment>{$requireInstalledVersion}</comment>)"
                            );
                            $require[$requireName] = $requireVersion;
                        }
                    } elseif (isset($previouslyInstalled[$requireName])) {
                        // Old module, manually removed
                        $output->writeln(
                            "  - Skipping <info>{$requireName}</info> (Manually removed from recipe)"
                        );
                    } else {
                        // New module
                        $output->writeln(
                            "  - Inlining <info>{$requireName}</info> (<comment>{$requireVersion}</comment>)"
                        );
                        $require[$requireName] = $requireVersion;
                    }

                    // note dependency as previously installed
                    $previouslyInstalled[$requireName] = $requireVersion;
                }
            }

            // Add new require / extra-installed
            $composerData['require'] = $require;
            if ($previouslyInstalled) {
                if (!isset($composerData['extra'])) {
                    $composerData['extra'] = [];
                }
                ksort($previouslyInstalled);
                $composerData['extra'][RecipePlugin::PROJECT_DEPENDENCIES_INSTALLED] = $previouslyInstalled;
            }

            // Move recipe from 'require' to 'provide'
            $installedVersion = $this->findInstalledVersion($recipe) ?: $installedVersion;
            unset($composerData['require'][$recipe]);
            if (!isset($composerData['provide'])) {
                $composerData['provide'] = [];
            }
            $composerData['provide'][$recipe] = $installedVersion;
            return $composerData;
        });

        // Update synchronise composer.lock
        return $this->updateProject($output);
    }

    /**
     * callback to safely modify composer.json data
     *
     * @param callable $callable Callable which will safely take and return the composer data.
     * This should return false if no content changed, or the updated data
     */
    protected function modifyComposer($callable)
    {
        // Begin modification of composer.json
        $composerFile = new JsonFile(Factory::getComposerFile(), null, $this->getIO());
        $composerData = $composerFile->read();

        // Note: Respect call by ref $composerData
        $result = $callable($composerData);
        if ($result === false) {
            return;
        }
        if ($result) {
            $composerData = $result;
        }

        // Update composer.json and refresh local composer instance
        $composerFile->write($composerData);
        $this->resetComposer();
    }
}
