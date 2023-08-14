<?php

use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    // where are the packages located?
    $mbConfig->packageDirectories([
        __DIR__ . '/packages',
    ]);
    // how to skip packages in loaded directories?
    //$mbConfig->packageDirectoriesExcludes([__DIR__ . '/packages/secret-package']);

};
