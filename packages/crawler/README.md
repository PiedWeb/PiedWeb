<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="Open Source Package" />
</a></p>

# CLI Seo Pocket Crawler

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/Crawler.svg?style=flat&label=release)](https://github.com/PiedWeb/Crawler/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/PiedWeb/Crawler/Tests?label=tests)](https://github.com/PiedWeb/PiedWeb/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Code Coverage](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/PiedWeb/coverage.svg)](https://shepherd.dev/github/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/crawler.svg?style=flat)](https://packagist.org/packages/piedweb/crawler)

Web Crawler to check few SEO basics.

Use the collected data in your favorite spreadsheet software or retrieve them via your favorite language.

French documentation available :
https://piedweb.com/seo/crawler

## Install

Via [Packagist](https://img.shields.io/packagist/dt/piedweb/crawler.svg?style=flat)

```bash
$ composer create-project piedweb/crawler
```

## Usage

### Crawler CLI

```bash
$ bin/console crawler:go $start
```

#### Arguments:

```
  start                            Define where the crawl start. Eg: https://piedweb.com
                                   You can specify an id from a previous crawl. Other options will not be listen.
                                   You can use `last` to continue the last crawl (just stopped)
```

#### Options:

```
  -l, --limit=LIMIT                Define where a depth limit [default: 5]
  -i, --ignore=IGNORE              Virtual Robots.txt to respect (could be a string or an URL).
  -u, --user-agent=USER-AGENT      Define the user-agent used during the crawl. [default: "SEO Pocket Crawler - PiedWeb.com/seo/crawler"]
  -w, --wait=WAIT                  In Microseconds, the time to wait between 2 requests. Default 0,1s. [default: 100000]
  -c, --cache-method=CACHE-METHOD  In Microseconds, the time to wait between two request. Default : 100000 (0,1s). [default: 2]
  -r, --restart=RESTART            Permit to restart a previous crawl. Values 1 = fresh restart, 2 = restart from cache
  -h, --help                       Display this help message
  -q, --quiet                      Do not output any message
  -V, --version                    Display this application version
      --ansi                       Force ANSI output
      --no-ansi                    Disable ANSI output
  -n, --no-interaction             Do not ask any interactive question
  -v|vv|vvv, --verbose             Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug



```

### Extract All External Links in 1s from a previous crawl

```bash
$ bin/console crawler:external $id [--host]
```

```
    --id
        id from a previous crawl
        You can use  `last` too show external links from the last crawl.

    --host -ho
        flag permitting to get only host
```

### Calcul Page Rank

Will update the previous `data.csv` generated. Then you can explore your website with the PoC `pagerank.html`
(in a server `npx http-server -c-1 --port 3000`).

```bash
$ bin/console crawler:pagerank $id
```

```
    --id
        id from a previous crawl
        You can use `last` too calcul page rank from the last crawl.
```

## Testing

```bash
$ composer test
```

## Todo

- [ ] Better Links Harvesting and Recording (record context (list, nav, sentence...))
- [ ] Transform the PoC (Page Rank Visualizer)
- [ ] Complex Page Rank Calculator (with 301, canonical, nofollow, etc.)

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
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/crawler.svg?style=flat)](https://packagist.org/packages/piedweb/crawler)
