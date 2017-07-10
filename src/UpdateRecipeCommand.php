<?php

namespace SilverStripe\RecipePlugin;

use BadMethodCallException;
use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateRecipeCommand extends BaseCommand
{
    use RecipeCommandBehaviour;

    public function configure()
    {
        $this->setName('update-recipe');
        $this->setDescription('Invoke this command to update an existing recipe');
        $this->addArgument(
            'recipe',
            InputArgument::REQUIRED,
            'Recipe name to require inline'
        );
        $this->addArgument(
            'version',
            InputArgument::OPTIONAL,
            'Version or constraint to update to'
        );
        $this->addUsage('silverstripe/recipe-blogging');
        $this->setHelp(
            <<<HELP
This command will detect any recipe that is installed, whether inline or required directly, and update to
the latest version based on stability settings.
HELP
        );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $recipe = $input->getArgument('recipe');
        $constraint = $input->getArgument('version');

        // Check if this is already installed and notify users
        $installedVersion = $this->findInstalledVersion($recipe);
        if (!$installedVersion) {
            throw new BadMethodCallException(
                "Recipe {$recipe} is not installed. Please install with require or require-recipe first"
            );
        }

        // Update recipe
        return $this->installRecipe($output, $recipe, $constraint, $installedVersion);
    }
}
