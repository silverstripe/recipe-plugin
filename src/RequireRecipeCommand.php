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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $recipe = $input->getArgument('recipe');
        $constraint = $input->getArgument('version');

        // Check if this is already installed and notify users
        $installedVersion = $this->findInstalledVersion($recipe);

        // Install recipe
        return $this->installRecipe($output, $recipe, $constraint, $installedVersion);
    }
}
