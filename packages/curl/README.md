# Curl OOP Wrapper

[![Latest Version](https://img.shields.io/github/tag/PiedWeb/PiedWeb.svg?style=flat&label=release)](https://github.com/PiedWeb/PiedWeb/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat)](LICENSE)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/PiedWeb/PiedWeb/run-tests.yml?branch=main)](https://github.com/PiedWeb/PiedWeb/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/PiedWeb/PiedWeb.svg?style=flat)](https://scrutinizer-ci.com/g/PiedWeb/PiedWeb)
[![Code Coverage](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main/graph/badge.svg)](https://codecov.io/gh/PiedWeb/PiedWeb/branch/main)
[![Type Coverage](https://shepherd.dev/github/PiedWeb/PiedWeb/coverage.svg)](https://shepherd.dev/github/PiedWeb/PiedWeb)
[![Total Downloads](https://img.shields.io/packagist/dt/piedweb/curl.svg?style=flat)](https://packagist.org/packages/piedweb/curl)

Simple PHP Curl OOP wrapper for efficient request.

For a more complex or abstracted curl wrapper, use [Guzzle](https://guzzle.readthedocs.io/en/latest/).

## Install

Via [Packagist](https://img.shields.io/packagist/dt/piedweb/curl.svg?style=flat)

```bash
$ composer require piedweb/curl
```

## Usage

Quick Example :

```php
$url = 'https://piedweb.com';
$request = new \PiedWeb\Curl\ExtendedCliend($url);
$request
    ->setDefaultSpeedOptions(true)
    ->setDownloadOnlyIf('PiedWeb\Curl\Helper::checkContentType') // 'PiedWeb\Curl\Helper::checkStatusCode'
    ->setDesktopUserAgent()
;
$result = $request->request();
if ($result instanceof \PiedWeb\Curl\Response) {
    $content = $this->getContent();
}
```

Static Wrapper Methods :

```php
use PiedWeb\Curl\StaticClient as Client;

Client::request($url); // @return ?string
Client::get(); // @return PiedWeb\Curl\ExtendedClient
Client::reset()

```

All Other Methods :

```php

$r = new PiedWeb\Curl\ExtendedClient(?string $url);
$r
    ->setOpt(CURLOPT_*, mixed 'value')

	// Preselect Options to avoid eternity wait
    ->setDefaultGetOptions($connectTimeOut = 5, $timeOut = 10, $dnsCacheTimeOut = 600, $followLocation = true, $maxRedirs = 5)
    ->setDefaultSpeedOptions() // no header except if setted, 1 redir max, no ssl check
    ->setNoFollowRedirection()
    ->setReturnOnlyHeader()
    ->setCookie(string $cookie)
    ->setReferer(string $url)
    ->fakeBrowserHeader(bool $doIt = true)
    ->setUserAgent(string $ua)
    ->setDesktopUserAgent()
    ->setMobileUserAgent()
    ->setLessJsUserAgent()
        ->getUserAgent() // @return string

    ->setDownloadOnlyIf(callable $func) // @param $ContentType can be a String or an Array
    ->setMaximumResponseSize(int $tooBig = 200000) // @defaut 2Mo
    ->setDownloadOnly($range = '0-500')

    ->setPost(array $post)

    ->setEncodingGzip()

    ->setProxy(string '[scheme]proxy-host:port[:username:passwrd]') // Scheme, username and passwrd are facultatives. Default Scheme is http://

    ->setTarget($url)
        ->getTarget()

    $r->request(); // @return true if request succeed else false (see getError)

$response = $r->getResponse(); // @return PiedWeb\Curl\Response or int corresponding to the curl error

$response->getUrl(); // @return string
$response->getContentType(); // @return string
$response->getContent(); // @return string
$response->getHeaders($returnArray = true); // @return array Response Header (or in a string if $returnArray is set to false)
$response->getCookies(); // @return string
$response->getUrl(); // @return string

$response->getError(); // Equivalent to curl function curl_errno
$response->getErrorMessage(); // .. curl_error


use PiedWeb\Curl\ResponseFromCache;

$response = new ResponseFromCache(  // same methods than Response except getRequest return null
    string $filePathOrContent,
    ?string $url = null,
    array $info = [],
    $headers = PHP_EOL.PHP_EOL
);

```

## Contributing

Please see [contributing](https://dev.piedweb.com/contributing)

## Credits

- [PiedWeb](https://piedweb.com)
- [All Contributors](https://github.com/PiedWeb/:package_skake/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

<p align="center"><a href="https://dev.piedweb.com">
<img src="https://raw.githubusercontent.com/PiedWeb/piedweb-devoluix-theme/master/src/img/logo_title.png" width="200" height="200" alt="Open Source Package" />
</a></p>
