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
use Symfony\Component\Console\Output\Output;
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
            InputArgument::REQUIRED,
            'Version to require'
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
        $version = $input->getArgument('version');

        // Get existing composer data
        $composerData = $this->loadComposer(getcwd());
        if (isset($composerData['provide'][$recipe])) {
            $output->writeln("<error>This recipe is already added to provide</error>");
            return -1;
        }

        // Ensure composer require includes this recipe
        $returnCode = $this->requireRecipe($output, $recipe, $version);
        if ($returnCode) {
            return $returnCode;
        }

        // Get composer data for both root and newly installed recipe
        $composerData = $this->loadComposer(getcwd());
        $recipeData = $this->loadComposer(getcwd().'/vendor/'.$recipe);

        // Promote all dependencies
        if (!empty($recipeData['require'])) {
            $output->writeln("Inlining all dependencies for recipe <info>$recipe</info>:");
            foreach ($recipeData['require'] as $dependencyName => $dependencyVersion) {
                $output->writeln(" * Inline dependency <info>$dependencyName</info> as <info>$dependencyVersion</info>");
                $composerData['require'][$dependencyName] = $dependencyVersion;
            }
        }

        // Move recipe from 'require' to 'provide'
        unset($composerData['require'][$recipe]);
        if (!isset($composerData['provide'])) {
            $composerData['provide'] = [];
        }
        $composerData['provide'][$recipe] = $version;

        // Update composer.json and synchronise composer.lock
        $this->saveComposer(getcwd(), $composerData);
        $this->updateProject($output);

        return $returnCode;
    }

    /**
     * Load composer data from the given directory
     *
     * @param string $directory
     * @return array
     */
    protected function loadComposer($directory)
    {
        $path = $directory.'/composer.json';
        if (!file_exists($path)) {
            throw new BadMethodCallException("Could not find composer.json");
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
     * @param $recipe
     * @param $version
     * @return int
     */
    protected function requireRecipe(OutputInterface $output, $recipe, $version)
    {
        /** @var RequireCommand $command */
        $command = $this->getApplication()->find('require');
        $package = $recipe . ':' . $version;
        $arguments = [
            'command' => 'require',
            'packages' => [$package],
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
}
