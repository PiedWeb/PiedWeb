<?php

namespace PiedWeb\ComposerSymlink;

use Symfony\Component\Filesystem\Filesystem;

final class ComposerSymlink
{
    private readonly Filesystem $filesystem;

    /**
     * @param list<string> $projectPathList
     */
    public function __construct(
        public readonly array $projectPathList,
        public readonly string $globalVendorDir
    ) {
        $this->filesystem = new Filesystem();
    }

    public function exec(): void
    {
        $this->listPackageSymlinked();

        foreach ($this->projectPathList as $projectPath) {
            $this->execForProject($projectPath);
        }

        $this->deleteUnusedPackage();
    }

    /** @var list<array{name: string, version: string}> */
    private array $packageListFromProject = [];

    private function execForProject(string $projectPath): void
    {
        $vendorBaseDir = $projectPath.'/vendor/';
        if (! file_exists($vendorBaseDir)) {
            throw new \Exception(\sprintf('Project %s not found', $projectPath));
        }

        /** @var list<string> */
        $vendorDirList = array_diff(\Safe\scandir($vendorBaseDir), ['.', '..', 'bin']);
        /** @var array{packages: ?list<array{name: string, version: string}>, packages-dev: ?list<array{name: string, version: string}>} */
        $composerLockData = json_decode(\Safe\file_get_contents($projectPath.'/composer.lock'), true);
        $this->packageListFromProject = array_merge($composerLockData['packages'] ?? [], $composerLockData['packages-dev'] ?? []);
        foreach ($vendorDirList as $vendorName) {
            $this->symlinkVendorPackages($vendorBaseDir, $vendorName);
        }
    }

    /** @var array<string, bool> */
    private array $globalPackageList = [];

    private function listPackageSymlinked(): void
    {
        if (! file_exists($this->globalVendorDir)) {
            return;
        }

        /** @var list<string> */
        $vendorList = array_diff(\Safe\scandir($this->globalVendorDir), ['.', '..']);
        foreach ($vendorList as $vendor) {
            if (! is_dir($this->globalVendorDir.$vendor)) {
                continue;
            }

            /** @var list<string> */
            $packageNameAndVersionList = array_diff(\Safe\scandir($this->globalVendorDir.$vendor), ['.', '..']);
            foreach ($packageNameAndVersionList as $packageNameAndVersion) {
                $packagePath = $this->globalVendorDir.$vendor.'/'.$packageNameAndVersion;
                if (! is_dir($packagePath)) {
                    continue;
                }

                $this->globalPackageList[$packagePath] = false;
            }
        }
    }

    private function deleteUnusedPackage(): void
    {
        foreach ($this->globalPackageList as $packagePath => $used) {
            if (true === $used) {
                continue;
            }

            // exec('rm -rf '.escapeshellarg($packagePath));
            $this->filesystem->remove($packagePath);
        }
    }

    private function symlinkVendorPackages(string $vendorBaseDir, string $vendorName): void
    {
        if (! is_dir($vendorBaseDir.$vendorName) || 'composer' === $vendorName) {
            return;
        }

        /** @var list<string> https://github.com/thecodingmachine/safe/issues/272 */
        $packageDirList = array_diff(\Safe\scandir($vendorBaseDir.$vendorName), ['.', '..']);
        // @mkdir($this->globalVendorDir.$vendorName, 0755, true);
        $this->filesystem->mkdir($this->globalVendorDir.$vendorName, 0755);
        foreach ($packageDirList as $packageName) {
            $this->symlinkPackage($packageName, $vendorName, $vendorBaseDir);
        }
    }

    private function symlinkPackage(string $packageName, string $vendorName, string $vendorBaseDir): void
    {
        $packagePath = $vendorBaseDir.$vendorName.'/'.$packageName;
        $globalPackagePath = $this->globalVendorDir.$vendorName.'/'.$packageName;
        $packageVersion = array_values(array_filter($this->packageListFromProject, static fn (array $pkg): bool => $pkg['name'] === $vendorName.'/'.$packageName))[0]['version'] ?? 'unknow';
        $globalPackagePath .= '-'.$packageVersion;
        $this->globalPackageList[$globalPackagePath] = true;

        if (is_link($packagePath)) {
            return;
        }

        if (! file_exists($globalPackagePath)) {
            // exec('cp -r '.escapeshellarg($packagePath).' '.escapeshellarg($globalPackagePath));
            $this->filesystem->mirror($packagePath, $globalPackagePath);
        }

        // exec('rm -rf '.escapeshellarg($packagePath));
        $this->filesystem->remove($packagePath);

        symlink($globalPackagePath, $packagePath);
    }
}
