<?php

namespace SilverStripe\RecipePlugin;

use Composer\Package\Version\VersionParser;
use Composer\Repository\PlatformRepository;

/**
 * @author Fabien Potencier <fabien@symfony.com> - See README.md for details
 */
class PackageResolver
{
    private static $aliases =  [
        'core' => 'silverstripe/recipe-core'
    ];

    public function resolve(array $arguments = []): array
    {
        $versionParser = new VersionParser();

        // first pass split on : and = to separate package names and versions
        $explodedArguments = [];
        foreach ($arguments as $argument) {
            if ((false !== $pos = strpos($argument, ':')) || (false !== $pos = strpos($argument, '='))) {
                $explodedArguments[] = substr($argument, 0, $pos);
                $explodedArguments[] = substr($argument, $pos + 1);
            } else {
                $explodedArguments[] = $argument;
            }
        }

        // second pass to resolve package names
        $packages = [];
        foreach ($explodedArguments as $i => $argument) {
            if (false === strpos($argument, '/') && !preg_match(PlatformRepository::PLATFORM_PACKAGE_REGEX, $argument) && !\in_array($argument, ['mirrors', 'nothing'])) {

                if (isset(self::$aliases[$argument])) {
                    $argument = self::$aliases[$argument];
                } else {
                    $versionParser->parseConstraints($argument);
                }
            }

            $packages[] = $argument;
        }

        // third pass to resolve versions
        $requires = [];
        foreach ($versionParser->parseNameVersionPairs($packages) as $package) {
            $requires[] = $package['name'];
        }

        return array_unique($requires);
    }

}
