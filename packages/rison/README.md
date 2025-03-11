<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="Open Source Package" />
</a></p>

# PHP Rison encoder - decoder

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/Rison.svg?style=flat&label=release)](https://github.com/PiedWeb/Rison/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/PiedWeb/Rison/Tests?label=tests)](https://github.com/PiedWeb/Rison/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/Rison.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/Rison)
[![Code Coverage](https://codecov.io/gh/PiedWeb/Rison/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/Rison/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/Rison/coverage.svg)](https://shepherd.dev/github/PiedWeb/Rison)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/rison.svg?style=flat)](https://packagist.org/packages/piedweb/rison)

Rison is a compact data format optimized for URIs, a slight variation of JSON. This is a port from JS rison, forked from [Marmelatze](https://github.com/Marmelatze/Kunststube-Rison)

## Install

Via [Packagist](https://img.shields.io/packagist/dt/piedweb/rison.svg?style=flat)

```bash
$ composer require piedweb/rison
```

## Usage

```php

use \PiedWeb\Rison\...; 

```

## About rison 

JSON:
```json
{"a":0,"b":"foo","c":"23skidoo"}
```

URI-encoded JSON:

```
%7B%22a%22:0,%22b%22%3A%22foo%22%2C%22c%22%3A%2223skidoo%22%7D
```

Rison:

```
(a:0,b:foo,c:'23skidoo')
```
URI-encoded Rison:
```
(a:0,b:foo,c:'23skidoo')
```

Learn more about Rison : 

- [Mirror of original rison specs and js/python port](https://github.com/Nanonid/rison?tab=readme-ov-file)
- [ESModule Rison port](https://github.com/othree/rison-esm)
- [Rison playground](https://rison.dev)
 
## Contributing

Please see [contributing](https://dev.piedweb.com/contributing)

## Credits

- Original version by [Marmelatze](https://github.com/Marmelatze/Kunststube-Rison)
- Forked and updated to modern PHP by [Robin Delattre (Pied Web)](https://piedweb.com)
- [All Contributors](https://github.com/PiedWeb/:package_skake/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
