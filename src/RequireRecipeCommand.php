<?php

namespace SilverStripe\RecipePlugin;

use BadMethodCallException;
use Composer\Command\BaseCommand;
use Composer\Command\RequireCommand;
use Composer\Command\UpdateCommand;
use MongoDB\Driver\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RequireRecipeCommand extends BaseCommand
{
    public function configure()
    {
        $this->setName('require-recipe');
        $this->setDescription('Invoke this command to inline a recipe into your root composer.json');
        $this->addArgument(
            'recipe',
            InputArgument::REQUIRED,
            'Recipe name to require inline'
        );
        $this->addArgument(
            'version',
            InputArgument::OPTIONAL,
            'Version or constraint to require'
        );
        $this->addUsage('silverstripe/recipe-blogging 1.0.0');
        $this->setHelp(
            <<<HELP
Running this command will inline any given recipe into your composer.json, allowing you to
modify it as though these dependencies were natively part of your own.

Running command <info>composer require-recipe silverstripe/recipe-blogging 1.0.0</info> adds the following:

<comment>
    "require": {
        "silverstripe/blog": "3.0.0",
        "silverstripe/lumberjack": "3.0.1",
        "silverstripe/comments": "2.1.0"
    },
    "provide": {
        "silverstripe/recipe-blogging": "1.0.0"
    }
</comment>
HELP
        );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get args and existing composer data
        $recipe = $input->getArgument('recipe');
        $constraint = $input->getArgument('version');

        // Check if this is already installed
        $installedVersion = $this->findInstalledVersion($recipe);

        // Notify users of which version is being updated
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

        // Get composer data for both root and newly installed recipe
        $composerData = $this->loadComposer(getcwd() .'/composer.json');
        $recipeData = $this->loadComposer(getcwd().'/vendor/'.$recipe.'/composer.json');

        // Promote all dependencies
        if (!empty($recipeData['require'])) {
            $output->writeln("Inlining all dependencies for recipe <info>{$recipe}</info>:");
            foreach ($recipeData['require'] as $dependencyName => $dependencyVersion) {
                $output->writeln(
                    " * Inline dependency <info>{$dependencyName}</info> as <info>{$dependencyVersion}</info>"
                );
                $composerData['require'][$dependencyName] = $dependencyVersion;
            }
        }

        // Move recipe from 'require' to 'provide'
        $installedVersion = $this->findInstalledVersion($recipe) ?: $installedVersion;
        unset($composerData['require'][$recipe]);
        if (!isset($composerData['provide'])) {
            $composerData['provide'] = [];
        }
        $composerData['provide'][$recipe] = $installedVersion;

        // Update composer.json and synchronise composer.lock
        $this->saveComposer(getcwd(), $composerData);
        return $this->updateProject($output);
    }

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
        return $returnCode;
    }

    /**
     * @param string $recipe
     * @return string
     */
    protected function findInstalledVersion($recipe)
    {
        // Check composer.lock file
        $lockData = $this->loadComposer(getcwd() . '/composer.lock', []);
        if (isset($lockData['packages'])) {
            foreach ($lockData['packages'] as $package) {
                // Get version of installed file
                if (isset($package['name']) && isset($package['version']) && $package['name'] === $recipe) {
                    $version = $package['version'];
                    // Trim leading `v` from `v1.0.0`
                    if (preg_match('#v([\d.]+)#i', $version)) {
                        return substr($version, 1);
                    }
                    return $version;
                }
            }
        }

        // Check composer.json
        $composerData = $this->loadComposer(getcwd() . '/composer.json');

        // Check provide for previously inlined recipe
        if (isset($composerData['provide'][$recipe])) {
            return $composerData['provide'][$recipe];
        }

        // Check existing constraints, or installed version
        if (isset($composerData['require'][$recipe])) {
            return $composerData['require'][$recipe];
        }
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
