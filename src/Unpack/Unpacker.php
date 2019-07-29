<?php

namespace SilverStripe\RecipePlugin\Unpack;

use Composer\Composer;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use SilverStripe\RecipePlugin\PackageResolver;

/**
 * @author Fabien Potencier <fabien@symfony.com> - See README.md for details
 */
class Unpacker
{
    private $composer;
    private $resolver;

    public function __construct(Composer $composer, PackageResolver $resolver)
    {
        $this->composer = $composer;
        $this->resolver = $resolver;
    }

    public function unpack(array $packages): Result
    {
        $result = new Result();
        $json = new JsonFile(Factory::getComposerFile());
        $manipulator = new JsonManipulator(file_get_contents($json->getPath()));
        foreach ($packages as $package) {

            $dev = $package['dev'];
            $package = $package['pkg'];

            // not unpackable or no --unpack flag or empty packs (markers)
            if (
                null === $package ||
                'silverstripe-recipe' !== $package->getType() ||
                0 === \count($package->getRequires()) + \count($package->getDevRequires())
            ) {
                $result->addRequired($package->getName().($package->getVersion() ? ':'.$package->getVersion() : ''));
                continue;
            }

            $result->addUnpacked($package);
            foreach ($package->getRequires() as $link) {
                if ('php' === $link->getTarget()) {
                    continue;
                }

                $constraint = $link->getPrettyConstraint();

                if (!$manipulator->addLink($dev ? 'require-dev' : 'require', $link->getTarget(), $constraint)) {
                    throw new \RuntimeException(sprintf('Unable to unpack package "%s".', $link->getTarget()));
                }
            }
        }

        file_put_contents($json->getPath(), $manipulator->getContents());

        return $result;
    }
}
