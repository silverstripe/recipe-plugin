<?php

namespace SilverStripe\RecipePlugin;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider;
use SilverStripe\RecipePlugin\Command\RequireRecipe;
use SilverStripe\RecipePlugin\Command\UnpackCommand;
use SilverStripe\RecipePlugin\Command\UpdateRecipe;

class RecipeCommandProvider implements CommandProvider
{
    /**
     * Retrieves an array of commands
     *
     * @return BaseCommand[]
     */
    public function getCommands()
    {
        return [
            new RequireRecipe(),
            new UpdateRecipe(),
            new UnpackCommand(),
        ];
    }
}
