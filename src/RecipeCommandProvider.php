<?php

namespace SilverStripe\RecipePlugin;

use Composer\Command\BaseCommand;
use Composer\Plugin\Capability\CommandProvider;

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
            new RequireRecipeCommand(),
            new UpdateRecipeCommand(),
        ];
    }
}
