<?php

declare(strict_types=1);

namespace PiedWeb\Curl\Test;

use PiedWeb\Curl\ExtendedClient as Client;
use PiedWeb\Curl\MultipleCheckInHeaders;
use PiedWeb\Curl\ResponseFromCache;

class RequestTest extends \PHPUnit\Framework\TestCase
{
    public function testDownloadIfHtml(): void
    {
        $url = 'https://piedweb.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf(static fn ($line): bool => 0 === stripos(trim((string) $line), 'content-type') && false !== stripos((string) $line, 'text/html'))
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $request->request();

        $this->assertSame(200, $request->getResponse()->getStatusCode());

        $headers = $request->getResponse()->getHeaders();
        $this->assertTrue(\is_array($headers));

        $this->assertSame('text/html; charset=UTF-8', $request->getResponse()->getContentType());
        $this->assertGreaterThan(10, \strlen($request->getResponse()->getContent()));
        $this->assertStringContainsString('200',  $request->getResponse()->getHeaders()[0] ?? ''); // @phpstan-ignore-line
        $this->assertStringContainsString('200', (string) $request->getResponse()->getHeaderLine('0'));
        $this->assertStringContainsString('200', $request->getResponse()->getHeader('0')); // @phpstan-ignore-line
        $this->assertNull($request->getResponse()->getCookies());
    }

    public function testNotDownload(): void
    {
        $url = 'https://altimood.com/media/default/rando-alpine-coucher-de-soleil.jpg';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf(\PiedWeb\Curl\Helper::class.'::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $request->request();

        $this->assertSame(200, $request->getResponse()->getStatusCode());
        $this->assertSame('', $request->getResponse()->getContent());
    }

    public function testEffectiveUrl(): void
    {
        $url = 'http://www.piedweb.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf(\PiedWeb\Curl\Helper::class.'::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $request->request();
        // dump($request->getCurlInfos());
        $this->assertSame('https://piedweb.com/', $request->getResponse()->getUrl());
        $this->assertSame($url, $request->getTarget());
        $this->assertGreaterThan(10, \strlen($request->getResponse()->getContent()));
    }

    public function testCurlError(): void
    {
        $url = 'http://www.readze'.random_int(100000, 99_999_999).'.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $request->request();

        $this->assertSame(6, $request->getResponse()->getError());
    }

    public function test404(): void
    {
        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf(\PiedWeb\Curl\Helper::class.'::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $request->request();

        $this->assertSame(404, $request->getResponse()->getStatusCode());
    }

    public function testAllMethods(): void
    {
        $checkHeaders = new MultipleCheckInHeaders();

        $url = 'https://test.piedweb.com/headers.php';
        $request = new Client($url);
        $request
            ->fakeBrowserHeader()
            ->setDefaultGetOptions()
            ->setDefaultSpeedOptions()
            ->setCookie('CONSENT=YES+')
            ->setReferer('https://test.piedweb.com/')
            ->setUserAgent('Hello :)')
            ->setDesktopUserAgent()
            ->setMobileUserAgent()
            ->setLessJsUserAgent()
            ->setTarget($url)
            ->setDownloadOnlyIf($checkHeaders->check(...))
            ->setLanguage('en-US,en;q=0.9')
        ;

        $request->request();

        $this->assertSame($request->getTarget(), $url);
        $this->assertSame($request->getUserAgent(), $request->lessJsUserAgent);

        $this->assertSame(200, $request->getResponse()->getStatusCode());
        $this->assertSame('text/html', $request->getResponse()->getMimeType());

        $headers = $request->getResponse()->getHeaders();
        $this->assertTrue(\is_array($headers));

        $this->assertSame('text/html; charset=UTF-8', $request->getResponse()->getContentType());

        $this->assertGreaterThan(100, \strlen($request->getResponse()->getContent()));
        $this->assertSame('Upgrade-Insecure-Requests: 1
User-Agent: '.$request->lessJsUserAgent.'
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9
Sec-Fetch-Site: same-origin
Sec-Fetch-Mode: navigate
Sec-Fetch-User: ?1
Sec-Fetch-Dest: document
Referer: https://test.piedweb.com/
Accept-Encoding: gzip, deflate, br
Accept-Language: en-US,en;q=0.9
Cookie: CONSENT=YES+
Host: test.piedweb.com
Content-Length: 0', trim(strip_tags($request->getResponse()->getBody())));
    }

    public function testMultipleCheckInHeaders(): void
    {
        $checkHeaders = new MultipleCheckInHeaders();

        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDefaultSpeedOptions()
            ->setUserAgent('Hello :)')
            ->setDownloadOnlyIf($checkHeaders->check(...))
            ->setPost('testpost')
        ;

        $request->request();

        $this->assertSame(92832, $request->getResponse()->getError());
        $this->assertSame(404, $request->getResponse()->getInfo('http_code'));
    }

    public function testProxy(): void
    {
        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setProxy('75.157.242.104:59190')
            ->setNoFollowRedirection()
            ->setOpt(\CURLOPT_CONNECTTIMEOUT, 1)
            ->setOpt(\CURLOPT_TIMEOUT, 1)
        ;

        $request->request();

        $this->assertGreaterThan(0, $request->getResponse()->getError());
        $this->assertStringContainsString('timed out', $request->getResponse()->getErrorMessage());
    }

    public function testAbortIfTooBig(): void
    {
        $url = 'https://piedweb.com';
        $request = new Client($url);
        $request->setMaximumResponseSize(1);
        $request->request();
        $this->assertSame($request->getResponse()->getError(), 42);
    }

    public function testDownloadOnlyFirstBytes(): void
    {
        $url = 'https://piedweb.com';
        $request = new Client($url);
        $request->setDownloadOnly('0-199');
        $request->request();

        $this->assertLessThan(300, \strlen($request->getResponse()->getContent()));
    }

    public function testResponseFromCache(): void
    {
        $response = new ResponseFromCache(
            'HTTP/1.1 200 OK'.\PHP_EOL.\PHP_EOL.'<!DOCTYPE html><html><body><p>Tests</p></body>',
            'https://piedweb.com/',
            ['content_type' => 'text/html; charset=UTF-8']
        );

        $this->assertInstanceOf(\PiedWeb\Curl\Response::class, $response);
        $this->assertSame($response->getMimeType(), 'text/html');
        $this->assertSame($response->getContent(), '<!DOCTYPE html><html><body><p>Tests</p></body>');
    }
}
