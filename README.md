# PiedWeb's Monorepo

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/PiedWeb.svg?style=flat&label=release)](https://github.com/PiedWeb/PiedWeb/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/curl.svg?style=flat)](https://packagist.org/packages/piedweb/curl)

## Documentation

- [Rison](packages/rison/README.md)
- [ComposerSymlink](packages/composer-symlink/README.md)
- [Curl](packages/curl/README.md)
- [Extractor](packages/extractor/README.md)
- [Crawler](packages/crawler/README.md)
- [Google](packages/google/README.md)
- [TextAnalyzer](packages/text-analyzer/README.md)
- [MethodDocBlockGenerator](packages/method-doc-block-generator/README.md)
- [RenderHtmlAttribute](packages/render-html-attribute/README.md)

## Development

```bash
composer install
composer test
composer testf testCurlMobile
composer stan
composer format
```

The default suite excludes live Google tests. Text, hreflang and HTTP authentication
use controlled fixtures; other integration tests still require network access.
Scrutinizer runs the default suite and analysis on PHP 8.5. No external coverage
upload is configured.

SeoStatus maintains its production SERP pipeline in its own `src/Google/` and
`assets/puppeteer/`, documented in `docs/SerpExtractor.md` in that repository.

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/PiedWeb/PiedWeb/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

<p align="center"><a href="https://dev.piedweb.com" rel="dofollow">
<img src="https://raw.githubusercontent.com/Pushword/Pushword/f5021f4c5d5d3ab3f2858ec2e4bdd70818806c6a/packages/admin/src/Resources/assets/logo.svg" width="200" height="200" alt="PHP Packages Open Source" />
</a></p>
