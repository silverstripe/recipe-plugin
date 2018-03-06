<?php

namespace SilverStripe\Test\RecipePlugin;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Util\Filesystem;
use PHPUnit\Framework\TestCase;
use SilverStripe\RecipePlugin\RecipeInstaller;

class RecipeInstallerTest extends TestCase
{

    public function testInstallProjectFilesFresh()
    {
        $recipeName = 'test';
        $sourceRoot = '/source';
        $destinationRoot = '/destination';
        $registrationKey = 'key';
        $projectName = 'test project';

        $messages = [];
        $io = $this->getMockBuilder(IOInterface::class)
            ->setMethods([])
            ->getMock();
        $io->expects($this->exactly(2))->method('write')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods([
                'getConfig',
            ])->getMock();
        $composer->method('getConfig')->willReturn(new Config());

        $filesystem = $this->getMockBuilder(Filesystem::class)->setMethods([])->getMock();
        $filesystem->expects($this->once())->method('ensureDirectoryExists')->with(
            $destinationRoot
        );
        $filesystem->expects($this->once())->method('copy')->with(
            $sourceRoot . '/file.php.tmpl',
            $destinationRoot . '/file.php'
        );

        $mockInstaller = $this->getMockBuilder(RecipeInstaller::class)
            ->setConstructorArgs([
                $io,
                $composer,
                null,
                $filesystem,
            ])
            ->setMethods([
                'getFileIterator',
                'getInstalledFiles',
                'fileExists',
                'getComposerFile',
            ])
            ->getMock();
        $mockInstaller->method('getFileIterator')->willReturn([
            $sourceRoot . '/file.php.tmpl' => [],
        ]);
        $mockInstaller->method('fileExists')->willReturn(false);
        $mockInstaller->method('getInstalledFiles')->willReturn([]);
        $mockInstaller->method('getComposerFile')->willReturn(
            $jsonFile = $this->getMockBuilder(JsonFile::class)
                ->disableOriginalConstructor()
                ->setMethods([])
                ->getMock()
        );

        $jsonFile->expects($this->once())->method('write')->willReturnCallback(function ($data) use ($registrationKey) {
            $this->assertArrayHasKey('extra', $data);
            $this->assertArrayHasKey($registrationKey, $data['extra']);
            $this->assertCount(1, $data['extra'][$registrationKey]);
            $this->assertContains('file.php', $data['extra'][$registrationKey]);
        });

        $reflectionClass = new \ReflectionClass($mockInstaller);
        $reflectionMethod = $reflectionClass->getMethod('installProjectFiles');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($mockInstaller, [
            $recipeName,
            $sourceRoot,
            $destinationRoot,
            '*.php',
            $registrationKey,
            $projectName,
        ]);

        // perhaps theses tests are needlessly tightly coupled to the output
        $this->assertCount(2, $messages);
        $this->assertContains(sprintf('Installing %s files for recipe <info>%s</info>', $projectName, $recipeName), $messages[0]);
        $this->assertContains('Copying <info>file.php</info>', $messages[1]);
    }

    public function testInstallProjectFilesExistsSame()
    {
        $recipeName = 'test';
        $sourceRoot = '/source';
        $destinationRoot = '/destination';
        $registrationKey = 'key';
        $projectName = 'test project';

        $messages = [];
        $io = $this->getMockBuilder(IOInterface::class)
            ->setMethods([])
            ->getMock();
        $io->expects($this->exactly(2))->method('write')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods([
                'getConfig',
            ])->getMock();
        $composer->method('getConfig')->willReturn(new Config());

        $filesystem = $this->getMockBuilder(Filesystem::class)->setMethods([])->getMock();
        $filesystem->expects($this->never())->method('copy');

        $mockInstaller = $this->getMockBuilder(RecipeInstaller::class)
            ->setConstructorArgs([
                $io,
                $composer,
                null,
                $filesystem,
            ])
            ->setMethods([
                'getFileIterator',
                'getInstalledFiles',
                'fileExists',
                'fileGetContents',
                'getComposerFile',
            ])
            ->getMock();
        $mockInstaller->method('getFileIterator')->willReturn([
            $sourceRoot . '/file.php.tmpl' => [],
        ]);
        $mockInstaller->method('fileExists')->willReturn(true);
        $mockInstaller->expects($this->exactly(2))->method('fileGetContents')->willReturn('contents');
        $mockInstaller->method('getInstalledFiles')->willReturn([]);
        $mockInstaller->method('getComposerFile')->willReturn(
            $jsonFile = $this->getMockBuilder(JsonFile::class)
                ->disableOriginalConstructor()
                ->setMethods([])
                ->getMock()
        );

        $jsonFile->expects($this->once())->method('write')->willReturnCallback(function ($data) use ($registrationKey) {
            $this->assertArrayHasKey('extra', $data);
            $this->assertArrayHasKey($registrationKey, $data['extra']);
            $this->assertCount(1, $data['extra'][$registrationKey]);
            $this->assertContains('file.php', $data['extra'][$registrationKey]);
        });

        $reflectionClass = new \ReflectionClass($mockInstaller);
        $reflectionMethod = $reflectionClass->getMethod('installProjectFiles');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($mockInstaller, [
            $recipeName,
            $sourceRoot,
            $destinationRoot,
            '*.php',
            $registrationKey,
            $projectName,
        ]);

        // perhaps theses tests are needlessly tightly coupled to the output
        $this->assertCount(2, $messages);
        $this->assertContains(sprintf('Installing %s files for recipe <info>%s</info>', $projectName, $recipeName), $messages[0]);
        $this->assertContains('Skipping <info>file.php</info> (<comment>existing, but unchanged</comment>)', $messages[1]);
    }

    public function testInstallProjectFilesExistsDifferent()
    {
        $recipeName = 'test';
        $sourceRoot = '/source';
        $destinationRoot = '/destination';
        $registrationKey = 'key';
        $projectName = 'test project';

        $messages = [];
        $io = $this->getMockBuilder(IOInterface::class)
            ->setMethods([])
            ->getMock();
        $io->expects($this->exactly(2))->method('write')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods([
                'getConfig',
            ])->getMock();
        $composer->method('getConfig')->willReturn(new Config());

        $filesystem = $this->getMockBuilder(Filesystem::class)->setMethods([])->getMock();
        $filesystem->expects($this->never())->method('copy');

        $mockInstaller = $this->getMockBuilder(RecipeInstaller::class)
            ->setConstructorArgs([
                $io,
                $composer,
                null,
                $filesystem,
            ])
            ->setMethods([
                'getFileIterator',
                'getInstalledFiles',
                'fileExists',
                'fileGetContents',
                'getComposerFile',
            ])
            ->getMock();
        $mockInstaller->method('getFileIterator')->willReturn([
            $sourceRoot . '/file.php.tmpl' => [],
        ]);
        $mockInstaller->method('fileExists')->willReturn(true);
        $mockInstaller->expects($this->exactly(2))->method('fileGetContents')->willReturnOnConsecutiveCalls(
            'contents', 'different contents'
        );
        $mockInstaller->method('getInstalledFiles')->willReturn([]);
        $mockInstaller->method('getComposerFile')->willReturn(
            $jsonFile = $this->getMockBuilder(JsonFile::class)
                ->disableOriginalConstructor()
                ->setMethods([])
                ->getMock()
        );

        $jsonFile->expects($this->once())->method('write')->willReturnCallback(function ($data) use ($registrationKey) {
            $this->assertArrayHasKey('extra', $data);
            $this->assertArrayHasKey($registrationKey, $data['extra']);
            $this->assertCount(1, $data['extra'][$registrationKey]);
            $this->assertContains('file.php', $data['extra'][$registrationKey]);
        });

        $reflectionClass = new \ReflectionClass($mockInstaller);
        $reflectionMethod = $reflectionClass->getMethod('installProjectFiles');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($mockInstaller, [
            $recipeName,
            $sourceRoot,
            $destinationRoot,
            '*.php',
            $registrationKey,
            $projectName,
        ]);

        // perhaps theses tests are needlessly tightly coupled to the output
        $this->assertCount(2, $messages);
        $this->assertContains(sprintf('Installing %s files for recipe <info>%s</info>', $projectName, $recipeName), $messages[0]);
        $this->assertContains('Skipping <info>file.php</info> (<comment>existing and modified in project</comment>)', $messages[1]);
    }

    public function testInstallProjectFilesRemoved()
    {
        $recipeName = 'test';
        $sourceRoot = '/source';
        $destinationRoot = '/destination';
        $registrationKey = 'key';
        $projectName = 'test project';

        $messages = [];
        $io = $this->getMockBuilder(IOInterface::class)
            ->setMethods([])
            ->getMock();
        $io->expects($this->exactly(2))->method('write')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods([
                'getConfig',
            ])->getMock();
        $composer->method('getConfig')->willReturn(new Config());

        $filesystem = $this->getMockBuilder(Filesystem::class)->setMethods([])->getMock();
        $filesystem->expects($this->never())->method('copy');

        $mockInstaller = $this->getMockBuilder(RecipeInstaller::class)
            ->setConstructorArgs([
                $io,
                $composer,
                null,
                $filesystem,
            ])
            ->setMethods([
                'getFileIterator',
                'getInstalledFiles',
                'fileExists',
                'fileGetContents',
                'getComposerFile',
            ])
            ->getMock();
        $mockInstaller->method('getFileIterator')->willReturn([
            $sourceRoot . '/file.php.tmpl' => [],
        ]);
        $mockInstaller->method('fileExists')->willReturn(false);
        $mockInstaller->expects($this->never())->method('fileGetContents');
        $mockInstaller->method('getInstalledFiles')->willReturn([
            'file.php',
        ]);
        $mockInstaller->method('getComposerFile')->willReturn(
            $jsonFile = $this->getMockBuilder(JsonFile::class)
                ->disableOriginalConstructor()
                ->setMethods([])
                ->getMock()
        );

        $jsonFile->expects($this->once())->method('write')->willReturnCallback(function ($data) use ($registrationKey) {
            $this->assertArrayHasKey('extra', $data);
            $this->assertArrayHasKey($registrationKey, $data['extra']);
            $this->assertCount(1, $data['extra'][$registrationKey]);
            $this->assertContains('file.php', $data['extra'][$registrationKey]);
        });

        $reflectionClass = new \ReflectionClass($mockInstaller);
        $reflectionMethod = $reflectionClass->getMethod('installProjectFiles');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($mockInstaller, [
            $recipeName,
            $sourceRoot,
            $destinationRoot,
            '*.php',
            $registrationKey,
            $projectName,
        ]);

        // perhaps theses tests are needlessly tightly coupled to the output
        $this->assertCount(2, $messages);
        $this->assertContains(sprintf('Installing %s files for recipe <info>%s</info>', $projectName, $recipeName), $messages[0]);
        $this->assertContains('Skipping <info>file.php</info> (<comment>previously installed</comment>)', $messages[1]);
    }

    public function testInstallProjectFilesWithoutTmplExtension()
    {
        $recipeName = 'test';
        $sourceRoot = '/source';
        $destinationRoot = '/destination';
        $registrationKey = 'key';
        $projectName = 'test project';

        $messages = [];
        $io = $this->getMockBuilder(IOInterface::class)
            ->setMethods([])
            ->getMock();
        $io->expects($this->exactly(2))->method('write')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = $message;
        });
        $composer = $this->getMockBuilder(Composer::class)
            ->setMethods([
                'getConfig',
            ])->getMock();
        $composer->method('getConfig')->willReturn(new Config());

        $filesystem = $this->getMockBuilder(Filesystem::class)->setMethods([])->getMock();
        $filesystem->expects($this->once())->method('ensureDirectoryExists')->with(
            $destinationRoot
        );
        $filesystem->expects($this->once())->method('copy')->with(
            $sourceRoot . '/file.php',
            $destinationRoot . '/file.php'
        );

        $mockInstaller = $this->getMockBuilder(RecipeInstaller::class)
            ->setConstructorArgs([
                $io,
                $composer,
                null,
                $filesystem,
            ])
            ->setMethods([
                'getFileIterator',
                'getInstalledFiles',
                'fileExists',
                'getComposerFile',
            ])
            ->getMock();
        $mockInstaller->method('getFileIterator')->willReturn([
            $sourceRoot . '/file.php' => [],
        ]);
        $mockInstaller->method('fileExists')->willReturn(false);
        $mockInstaller->method('getInstalledFiles')->willReturn([]);
        $mockInstaller->method('getComposerFile')->willReturn(
            $jsonFile = $this->getMockBuilder(JsonFile::class)
                ->disableOriginalConstructor()
                ->setMethods([])
                ->getMock()
        );

        $jsonFile->expects($this->once())->method('write')->willReturnCallback(function ($data) use ($registrationKey) {
            $this->assertArrayHasKey('extra', $data);
            $this->assertArrayHasKey($registrationKey, $data['extra']);
            $this->assertCount(1, $data['extra'][$registrationKey]);
            $this->assertContains('file.php', $data['extra'][$registrationKey]);
        });

        $reflectionClass = new \ReflectionClass($mockInstaller);
        $reflectionMethod = $reflectionClass->getMethod('installProjectFiles');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs($mockInstaller, [
            $recipeName,
            $sourceRoot,
            $destinationRoot,
            '*.php',
            $registrationKey,
            $projectName,
        ]);

        // perhaps theses tests are needlessly tightly coupled to the output
        $this->assertCount(2, $messages);
        $this->assertContains(sprintf('Installing %s files for recipe <info>%s</info>', $projectName, $recipeName), $messages[0]);
        $this->assertContains('Copying <info>file.php</info>', $messages[1]);
    }
}
