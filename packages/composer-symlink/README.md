# Composer Symlink

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/ComposerSymlink.svg?style=flat&label=release)](https://github.com/PiedWeb/ComposerSymlink/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/PiedWeb/ComposerSymlink/Tests?label=tests)](https://github.com/PiedWeb/PiedWeb/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Code Coverage](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/PiedWeb/coverage.svg)](https://shepherd.dev/github/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/composer-symlink.svg?style=flat)](https://packagist.org/packages/piedweb/composer-symlink)

Disk efficient composer (fixer || symlinker) ➜ multiple project relying on same package version, why having multiple copy ?

This is a duplicate code killer to win some disk space.

## Install

Via [Packagist](https://img.shields.io/packagist/dt/piedweb/composer-symlink.svg?style=flat)

Create a new project and install the dependency

```bash
mkdir composer-dependencies && cd composer-dependencies

composer install piedweb/composer-symlink
```

## Usage

Create cs.php

```php
<?php

include 'vendor/autoload.php';

(new ComposerSymlink([
    '/path/to/my/project',
    'path/to/my/second/project',
]))->exec();
```

Add it in post-update script

```json

```

## Contributing

Please see [contributing](https://dev.piedweb.com/contributing)

## Credits

- [PiedWeb](https://piedweb.com) ak [Robind4](https://twitter.com/Robind4)
- [All Contributors](https://github.com/PiedWeb/:package_skake/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/PiedWeb.svg?style=flat&label=release)](https://github.com/PiedWeb/PiedWeb/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](https://github.com/PiedWeb/PiedWeb/blob/master/LICENSE)
[![Build Status](https://img.shields.io/travis/PiedWeb/PiedWeb/master.svg?style=flat)](https://travis-ci.org/PiedWeb/PiedWeb)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb/code-structure)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/composer-symlink.svg?style=flat)](https://packagist.org/packages/piedweb/composer-symlink)

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="Open Source Package" />
</a></p>
