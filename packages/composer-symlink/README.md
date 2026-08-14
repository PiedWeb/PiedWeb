<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="Open Source Package" />
</a></p>

# Composer Symlink

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/ComposerSymlink.svg?style=flat&label=release)](https://github.com/PiedWeb/ComposerSymlink/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![Code Coverage](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/PiedWeb/coverage.svg)](https://shepherd.dev/github/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/composer-symlink.svg?style=flat)](https://packagist.org/packages/piedweb/composer-symlink)

Disk efficient composer (fixer || symlinker) ➜ multiple projects relying on the same package version, why having multiple copies ?

This is a duplicate code killer to win some disk space.

Each `vendor/{vendor}/{package}` directory is moved to a shared
`{globalVendorDir}/{vendor}/{package}-{version}` and replaced by a symlink. Projects locking the
same version end up sharing a single copy; projects locking different versions keep their own.

## Install

Via [Packagist](https://packagist.org/packages/piedweb/composer-symlink)

Create a new project and install the dependency

```bash
mkdir composer-dependencies && cd composer-dependencies

composer require piedweb/composer-symlink
```

## Usage

Create `cs.php`

```php
<?php

use PiedWeb\ComposerSymlink\ComposerSymlink;

include 'vendor/autoload.php';

(new ComposerSymlink(
    [
        '/path/to/my/project',
        '/path/to/my/second/project',
    ],
    '/path/to/composer-dependencies/global-vendor'
))->exec();
```

Then run it after each composer update:

```bash
php cs.php
```

You can also wire it in the `post-update-cmd` script of each project:

```json
{
    "scripts": {
        "post-update-cmd": "php /path/to/composer-dependencies/cs.php"
    }
}
```

### Arguments

| Argument | Description |
| --- | --- |
| `$projectPathList` | **Every** project sharing the global vendor directory. Each one needs a `vendor/` directory and a `composer.lock`. |
| `$globalVendorDir` | Absolute path of the shared vendor directory. Created on demand. A relative path throws. |
| `$pruneUnused` | `true` by default: shared packages no longer referenced are deleted. |

### The project list must be exhaustive

Pruning is what reclaims the disk space, and it decides what to delete from the global vendor
directory alone — it has no way to discover a project you did not list. Leaving a project out
deletes the packages it still symlinks, and its `vendor/` is left with dangling links until the
next `composer install`.

So either list every project sharing the directory, or pass `pruneUnused: false` and clean up
by hand:

```php
(new ComposerSymlink([$projectPath], '/path/to/global-vendor', pruneUnused: false))->exec();
```

### What is left alone

- `vendor/composer/`, `vendor/bin/` and the files at the root of `vendor/`.
- Packages absent from `composer.lock` (path repositories, hand-vendored code): without a version
  there is nothing to key a shared copy on.
- Packages already symlinked. The link target wins over `composer.lock`, so a lock bumped without a
  matching `composer install` never invalidates the copy the project actually uses.

## Contributing

Please see [contributing](https://dev.piedweb.com/contributing)

## Credits

- [PiedWeb](https://piedweb.com) ak [Robind4](https://twitter.com/Robind4)
- [All Contributors](https://github.com/PiedWeb/ComposerSymlink/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
