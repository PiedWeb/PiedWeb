<?php

namespace PiedWeb\ComposerSymlink;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class ComposerSymlink
{
    public readonly string $globalVendorDir;

    private readonly Filesystem $filesystem;

    /** @var array<string, bool> global package path => still referenced by one of the projects */
    private array $globalPackageList = [];

    /** @var array<string, string> package name => version, reset for each project */
    private array $packageVersionList = [];

    /**
     * @param list<string> $projectPathList every project sharing $globalVendorDir. A project left out
     *                                      of the list is invisible to the prune, which then deletes
     *                                      the packages it still symlinks: pass them all, or disable
     *                                      $pruneUnused.
     * @param string       $globalVendorDir absolute path of the shared vendor directory
     */
    public function __construct(
        public readonly array $projectPathList,
        string $globalVendorDir,
        public readonly bool $pruneUnused = true,
    ) {
        if (! Path::isAbsolute($globalVendorDir)) {
            throw new \InvalidArgumentException(\sprintf('Global vendor dir must be an absolute path, got %s', $globalVendorDir));
        }

        $this->globalVendorDir = Path::canonicalize($globalVendorDir).'/';
        $this->filesystem = new Filesystem();
    }

    public function exec(): void
    {
        $this->listPackageSymlinked();

        foreach ($this->projectPathList as $projectPath) {
            $this->execForProject($projectPath);
        }

        if ($this->pruneUnused) {
            $this->deleteUnusedPackage();
        }
    }

    private function execForProject(string $projectPath): void
    {
        $vendorBaseDir = $projectPath.'/vendor/';
        if (! file_exists($vendorBaseDir)) {
            throw new \Exception(\sprintf('Project %s not found', $projectPath));
        }

        $composerLockPath = $projectPath.'/composer.lock';
        if (! file_exists($composerLockPath)) {
            throw new \Exception(\sprintf('Project %s has no composer.lock', $projectPath));
        }

        $this->packageVersionList = $this->extractPackageVersionList($composerLockPath);

        /** @var list<string> */
        $vendorDirList = array_diff(\Safe\scandir($vendorBaseDir), ['.', '..', 'bin']);
        foreach ($vendorDirList as $vendorName) {
            $this->symlinkVendorPackages($vendorBaseDir, $vendorName);
        }
    }

    /** @return array<string, string> */
    private function extractPackageVersionList(string $composerLockPath): array
    {
        /** @var array{packages?: list<array{name: string, version: string}>, packages-dev?: list<array{name: string, version: string}>} */
        $composerLockData = json_decode(\Safe\file_get_contents($composerLockPath), true);

        $packageVersionList = [];
        foreach ([...$composerLockData['packages'] ?? [], ...$composerLockData['packages-dev'] ?? []] as $package) {
            $packageVersionList[$package['name']] = $package['version'];
        }

        return $packageVersionList;
    }

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
            if ($used) {
                continue;
            }

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
        foreach ($packageDirList as $packageName) {
            $this->symlinkPackage($packageName, $vendorName, $vendorBaseDir);
        }
    }

    private function symlinkPackage(string $packageName, string $vendorName, string $vendorBaseDir): void
    {
        $packagePath = $vendorBaseDir.$vendorName.'/'.$packageName;

        // Already symlinked: what the link points at is the only reliable version, composer.lock may
        // have moved on without vendor/ being reinstalled.
        if (is_link($packagePath)) {
            $this->keepSymlinkTarget($packagePath);

            return;
        }

        // Absent from composer.lock (path repository, hand-vendored code): no version to key the
        // shared copy on, and every such package would collide under a single fallback name.
        $packageVersion = $this->packageVersionList[$vendorName.'/'.$packageName] ?? null;
        if (null === $packageVersion) {
            return;
        }

        $globalPackagePath = $this->globalVendorDir.$vendorName.'/'.$packageName.'-'.$packageVersion;
        $this->globalPackageList[$globalPackagePath] = true;

        if (! file_exists($globalPackagePath)) {
            $this->filesystem->mirror($packagePath, $globalPackagePath);
        }

        $this->filesystem->remove($packagePath);
        $this->filesystem->symlink($globalPackagePath, $packagePath);
    }

    private function keepSymlinkTarget(string $packagePath): void
    {
        $target = Path::canonicalize(\Safe\readlink($packagePath));
        if (! str_starts_with($target, $this->globalVendorDir)) {
            return;
        }

        $this->globalPackageList[$target] = true;
    }
}
