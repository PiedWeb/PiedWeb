<?php

namespace PiedWeb\ComposerSymlink\Test;

use PHPUnit\Framework\TestCase;
use PiedWeb\ComposerSymlink\ComposerSymlink;
use Symfony\Component\Filesystem\Filesystem;

class ComposerSymlinkTest extends TestCase
{
    private string $workDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->workDir = \Safe\realpath(sys_get_temp_dir()).'/composer-symlink-'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->workDir);
    }

    public function testSharesASingleCopyBetweenProjectsUsingTheSameVersion(): void
    {
        $projectA = $this->createProject('projectA', ['acme/lib' => '1.0.0']);
        $projectB = $this->createProject('projectB', ['acme/lib' => '1.0.0']);

        (new ComposerSymlink([$projectA, $projectB], $this->globalVendorDir()))->exec();

        $this->assertSame(
            $this->globalVendorDir().'/acme/lib-1.0.0',
            readlink($projectA.'/vendor/acme/lib')
        );
        $this->assertSame(
            $this->globalVendorDir().'/acme/lib-1.0.0',
            readlink($projectB.'/vendor/acme/lib')
        );
        $this->assertSame(['lib-1.0.0'], $this->globalPackageList('acme'));
    }

    public function testKeepsEachVersionInItsOwnDirectory(): void
    {
        $projectA = $this->createProject('projectA', ['acme/lib' => '1.0.0'], 'one');
        $projectB = $this->createProject('projectB', ['acme/lib' => '2.0.0'], 'two');

        (new ComposerSymlink([$projectA, $projectB], $this->globalVendorDir()))->exec();

        $this->assertSame(['lib-1.0.0', 'lib-2.0.0'], $this->globalPackageList('acme'));
        $this->assertSame('one', $this->readPackageFile($projectA, 'acme/lib'));
        $this->assertSame('two', $this->readPackageFile($projectB, 'acme/lib'));
    }

    public function testIsIdempotent(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0'], 'payload');
        $composerSymlink = new ComposerSymlink([$project], $this->globalVendorDir());

        $composerSymlink->exec();
        $composerSymlink->exec();
        $composerSymlink->exec();

        $this->assertSame(['lib-1.0.0'], $this->globalPackageList('acme'));
        $this->assertSame('payload', $this->readPackageFile($project, 'acme/lib'));
    }

    public function testKeepsThePackageStillTargetedWhenComposerLockMovedOn(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0'], 'payload');
        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        // composer.lock bumped without vendor/ being reinstalled, e.g. `composer update --lock`
        $this->writeComposerLock($project, ['acme/lib' => '1.1.0']);
        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertSame(['lib-1.0.0'], $this->globalPackageList('acme'));
        $this->assertSame('payload', $this->readPackageFile($project, 'acme/lib'));
    }

    public function testLeavesPackageMissingFromComposerLockUntouched(): void
    {
        $projectA = $this->createProject('projectA', ['acme/lib' => null], 'from-a');
        $projectB = $this->createProject('projectB', ['acme/lib' => null], 'from-b');

        (new ComposerSymlink([$projectA, $projectB], $this->globalVendorDir()))->exec();

        $this->assertFalse(is_link($projectA.'/vendor/acme/lib'));
        $this->assertFalse(is_link($projectB.'/vendor/acme/lib'));
        $this->assertSame('from-a', $this->readPackageFile($projectA, 'acme/lib'));
        $this->assertSame('from-b', $this->readPackageFile($projectB, 'acme/lib'));
        $this->assertSame([], $this->globalPackageList('acme'));
    }

    public function testLeavesSymlinkPointingOutsideTheGlobalVendorDirAlone(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0']);
        $localPackage = $this->workDir.'/local-package';
        $this->filesystem->dumpFile($localPackage.'/File.php', 'local');

        // a composer path repository already symlinks vendor/ outside the shared directory
        $this->filesystem->remove($project.'/vendor/acme/lib');
        $this->filesystem->symlink($localPackage, $project.'/vendor/acme/lib');

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertSame($localPackage, readlink($project.'/vendor/acme/lib'));
        $this->assertSame('local', $this->readPackageFile($project, 'acme/lib'));
        $this->assertSame([], $this->globalPackageList('acme'));
    }

    public function testSurvivesADanglingSymlink(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0']);
        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        // the state an interrupted run, or a version prior to the prune fix, leaves behind
        $this->filesystem->remove($this->globalVendorDir().'/acme/lib-1.0.0');

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertTrue(is_link($project.'/vendor/acme/lib'));
        $this->assertSame([], $this->globalPackageList('acme'));
    }

    public function testSymlinksEveryPackageOfAVendor(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0', 'acme/other' => '2.0.0']);

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertSame(['lib-1.0.0', 'other-2.0.0'], $this->globalPackageList('acme'));
        $this->assertTrue(is_link($project.'/vendor/acme/lib'));
        $this->assertTrue(is_link($project.'/vendor/acme/other'));
    }

    public function testAcceptsAComposerLockWithoutPackageSection(): void
    {
        $project = $this->workDir.'/project';
        $this->filesystem->dumpFile($project.'/vendor/acme/lib/File.php', 'content');
        $this->filesystem->dumpFile($project.'/composer.lock', '{}');

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertFalse(is_link($project.'/vendor/acme/lib'));
        $this->assertSame([], $this->globalPackageList('acme'));
    }

    public function testPrunesPackageNoLongerUsed(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0']);
        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        // composer reinstalled the package at a new version, replacing the symlink by a real directory
        $this->filesystem->remove($project.'/vendor/acme/lib');
        $this->filesystem->dumpFile($project.'/vendor/acme/lib/File.php', 'content');
        $this->writeComposerLock($project, ['acme/lib' => '1.1.0']);

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertSame(['lib-1.1.0'], $this->globalPackageList('acme'));
    }

    public function testKeepsEveryPackageWhenPruneIsDisabled(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0']);
        (new ComposerSymlink([$project], $this->globalVendorDir(), false))->exec();

        $this->filesystem->remove($project.'/vendor/acme/lib');
        $this->filesystem->dumpFile($project.'/vendor/acme/lib/File.php', 'content');
        $this->writeComposerLock($project, ['acme/lib' => '1.1.0']);

        (new ComposerSymlink([$project], $this->globalVendorDir(), false))->exec();

        $this->assertSame(['lib-1.0.0', 'lib-1.1.0'], $this->globalPackageList('acme'));
    }

    public function testIgnoresComposerInternalsAndBinaries(): void
    {
        $project = $this->createProject('project', ['acme/lib' => '1.0.0']);
        $this->filesystem->dumpFile($project.'/vendor/composer/installed.json', '{}');
        $this->filesystem->dumpFile($project.'/vendor/bin/phpunit', '#!/usr/bin/env php');
        $this->filesystem->dumpFile($project.'/vendor/autoload.php', '<?php');

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertFileExists($project.'/vendor/composer/installed.json');
        $this->assertFileExists($project.'/vendor/bin/phpunit');
        $this->assertFileExists($project.'/vendor/autoload.php');
        $this->assertSame(['acme'], $this->globalVendorList());
    }

    public function testTakesDevPackagesIntoAccount(): void
    {
        $project = $this->workDir.'/project';
        $this->filesystem->dumpFile($project.'/vendor/acme/lib/File.php', 'content');
        $this->filesystem->dumpFile($project.'/composer.lock', \Safe\json_encode([
            'packages' => [],
            'packages-dev' => [['name' => 'acme/lib', 'version' => '1.0.0']],
        ]));

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();

        $this->assertSame(['lib-1.0.0'], $this->globalPackageList('acme'));
    }

    public function testAcceptsGlobalVendorDirWithOrWithoutTrailingSlash(): void
    {
        $projectA = $this->createProject('projectA', ['acme/lib' => '1.0.0']);
        $projectB = $this->createProject('projectB', ['acme/lib' => '1.0.0']);

        (new ComposerSymlink([$projectA], $this->workDir.'/globalvendor'))->exec();
        (new ComposerSymlink([$projectA, $projectB], $this->workDir.'/globalvendor/'))->exec();

        $this->assertSame(['lib-1.0.0'], $this->globalPackageList('acme'));
        $this->assertDirectoryDoesNotExist($this->workDir.'/globalvendoracme');
    }

    public function testRejectsRelativeGlobalVendorDir(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ComposerSymlink([], 'globalvendor');
    }

    public function testThrowsWhenProjectHasNoVendorDirectory(): void
    {
        $this->expectExceptionMessage('not found');

        (new ComposerSymlink([$this->workDir.'/nowhere'], $this->globalVendorDir()))->exec();
    }

    public function testThrowsWhenProjectHasNoComposerLock(): void
    {
        $project = $this->workDir.'/project';
        $this->filesystem->dumpFile($project.'/vendor/acme/lib/File.php', 'content');

        $this->expectExceptionMessage('no composer.lock');

        (new ComposerSymlink([$project], $this->globalVendorDir()))->exec();
    }

    private function globalVendorDir(): string
    {
        return $this->workDir.'/globalvendor';
    }

    /**
     * @param array<string, ?string> $packageVersionList package name => version, null leaves the
     *                                                   package out of composer.lock
     */
    private function createProject(string $name, array $packageVersionList, string $content = 'content'): string
    {
        $projectPath = $this->workDir.'/'.$name;
        foreach (array_keys($packageVersionList) as $packageName) {
            $this->filesystem->dumpFile($projectPath.'/vendor/'.$packageName.'/File.php', $content);
        }

        $this->writeComposerLock($projectPath, $packageVersionList);

        return $projectPath;
    }

    /** @param array<string, ?string> $packageVersionList */
    private function writeComposerLock(string $projectPath, array $packageVersionList): void
    {
        $packages = [];
        foreach ($packageVersionList as $packageName => $version) {
            if (null !== $version) {
                $packages[] = ['name' => $packageName, 'version' => $version];
            }
        }

        $this->filesystem->dumpFile($projectPath.'/composer.lock', \Safe\json_encode(['packages' => $packages]));
    }

    private function readPackageFile(string $projectPath, string $packageName): string
    {
        return \Safe\file_get_contents($projectPath.'/vendor/'.$packageName.'/File.php');
    }

    /** @return list<string> */
    private function globalPackageList(string $vendorName): array
    {
        return $this->scandir($this->globalVendorDir().'/'.$vendorName);
    }

    /** @return list<string> */
    private function globalVendorList(): array
    {
        return $this->scandir($this->globalVendorDir());
    }

    /** @return list<string> */
    private function scandir(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        return array_values(array_diff(\Safe\scandir($path), ['.', '..']));
    }
}
