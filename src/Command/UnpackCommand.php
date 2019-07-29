<?php

namespace SilverStripe\RecipePlugin\Command;

use Composer\Command\BaseCommand;
use Composer\Config\JsonConfigSource;
use Composer\Factory;
use Composer\Installer;
use Composer\Json\JsonFile;
use Composer\Package\Locker;
use Composer\Package\Version\VersionParser;
use SilverStripe\RecipePlugin\PackageResolver;
use SilverStripe\RecipePlugin\Unpack\Unpacker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com> - See README.md for details
 */
class UnpackCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('silverstripe:unpack')
            ->setAliases(['unpack'])
            ->setDescription('Unpacks a SilverStripe recipe.')
            ->addArgument(
                'packages',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Installed packages to unpack.'
            )
            ->addUsage('silverstripe/recipe-core')
            ->setHelp(
                <<<HELP
This command unpacks a SilverStripe recipe and removes it from your requirements. Useful when you want to eject
from a recipe for more control over version constraints.
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer();
        $io       = $this->getIO();

        $resolver      = new PackageResolver();
        $packages      = $resolver->resolve($input->getArgument('packages'), true);
        $json          = new JsonFile(Factory::getComposerFile());
        $manipulator   = new JsonConfigSource($json);
        $locker        = $composer->getLocker();
        $lockData      = $locker->getLockData();
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();
        $versionParser = new VersionParser();

        $data = [];
        foreach ($versionParser->parseNameVersionPairs($packages) as $package) {
            if (null === $pkg = $installedRepo->findPackage($package['name'], '*')) {
                $io->writeError(sprintf('<error>Package %s is not installed</error>', $package['name']));

                return 1;
            }

            $dev = false;
            foreach ($lockData['packages-dev'] as $p) {
                if ($package['name'] === $p['name']) {
                    $dev = true;

                    break;
                }
            }
            $data[] = [
                'pkg' => $pkg,
                'dev' => $dev,
            ];
        }

        $unpacker = new Unpacker($composer, $resolver);
        $result = $unpacker->unpack($data);


        // remove the packages themselves
        if (!$result->getUnpacked()) {
            $io->writeError('<info>Nothing to unpack</info>');

            return;
        }

        foreach ($result->getUnpacked() as $pkg) {
            $io->writeError(sprintf('<info>Unpacked %s dependencies</info>', $pkg->getName()));
        }

        foreach ($result->getUnpacked() as $package) {
            $manipulator->removeLink('require-dev', $package->getName());
            foreach ($lockData['packages-dev'] as $i => $pkg) {
                if ($package->getName() === $pkg['name']) {
                    unset($lockData['packages-dev'][$i]);
                }
            }
            $manipulator->removeLink('require', $package->getName());
            foreach ($lockData['packages'] as $i => $pkg) {
                if ($package->getName() === $pkg['name']) {
                    unset($lockData['packages'][$i]);
                }
            }
        }
        $lockData['packages']     = array_values($lockData['packages']);
        $lockData['packages-dev'] = array_values($lockData['packages-dev']);
        $lockData['content-hash'] = $locker->getContentHash(file_get_contents($json->getPath()));
        $lockFile                 = new JsonFile(substr($json->getPath(), 0, -4) . 'lock', null, $io);
        $lockFile->write($lockData);

        // force removal of files under vendor/
        $locker = new Locker($io, $lockFile, $composer->getRepositoryManager(), $composer->getInstallationManager(), file_get_contents($json->getPath()));
        $composer->setLocker($locker);
        $install = Installer::create($io, $composer);
        $install
            ->setDevMode(true)
            ->setDumpAutoloader(false)
            ->setRunScripts(false)
            ->setSkipSuggest(true)
            ->setIgnorePlatformRequirements(true);

        return $install->run();
    }
}
