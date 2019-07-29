<?php

namespace SilverStripe\RecipePlugin\Unpack;

use Composer\Package\PackageInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com> - See README.md for details
 */
class Result
{
    private $unpacked = [];
    private $required = [];

    public function addUnpacked(PackageInterface $package)
    {
        $this->unpacked[] = $package;
    }

    /**
     * @return PackageInterface[]
     */
    public function getUnpacked(): array
    {
        return $this->unpacked;
    }

    public function addRequired(string $package)
    {
        $this->required[] = $package;
    }

    /**
     * @return string[]
     */
    public function getRequired(): array
    {
        // we need at least one package for the command to work properly
        return $this->required ?: ['silverstripe/recipe-plugin'];
    }
}
