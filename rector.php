<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->parallel();

    $composerConfig = \Safe\json_decode(\Safe\file_get_contents('composer.json'), true);
    /** @var list<string> */
    $paths = array_merge(
        array_values($composerConfig['autoload']['psr-4']), // @phpstan-ignore-line
        array_values($composerConfig['autoload-dev']['psr-4']) // @phpstan-ignore-line
    );
    $rectorConfig->paths(array_map(
        function (string $path) { return __DIR__.'/'.$path; },
        $paths
    ));

    $rectorConfig->skip([
        __DIR__.'packages/*/var/*',
        FlipTypeControlToUseExclusiveTypeRector::class,
    ]);

    // $parameters->rule(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_80);
    // $containerConfigurator->import(SetList::PHP_80);
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        LevelSetList::UP_TO_PHP_80,
        LevelSetList::UP_TO_PHP_81,
        SetList::CODING_STYLE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        // SetList::DEAD_CODE,
        /*
        SetList::NAMING,
        SetList::PRIVATIZATION,
        */
    ]);
};
