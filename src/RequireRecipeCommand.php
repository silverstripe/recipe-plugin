<?php

namespace SilverStripe\RecipePlugin;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides the 'require-recipe' command which allows a new recipe to be installed, but also
 * soft-updates any existing recipe.
 */
class RequireRecipeCommand extends BaseCommand
{
    use RecipeCommandBehaviour;

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
        $recipe = $input->getArgument('recipe');
        $constraint = $input->getArgument('version');

        // Check if this is already installed and notify users
        $installedVersion = $this->findInstalledVersion($recipe);
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

        // Begin modification of composer.json
        $composerData = $this->loadComposer(getcwd() .'/composer.json');

        // Get composer data for both root and newly installed recipe
        $installedRecipe = $this
            ->getComposer()
            ->getRepositoryManager()
            ->getLocalRepository()
            ->findPackage($recipe, '*');
        if ($installedRecipe) {
            $output->writeln("Inlining all dependencies for recipe <info>{$recipe}</info>:");
            foreach ($installedRecipe->getRequires() as $requireName => $require) {
                $requireVersion = $require->getPrettyConstraint();
                $output->writeln(
                    " * Inline dependency <info>{$requireName}</info> as <info>{$requireVersion}</info>"
                );
                $composerData['require'][$requireName] = $requireVersion;
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
}
