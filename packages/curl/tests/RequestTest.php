<?php

declare(strict_types=1);

namespace PiedWeb\Curl\Test;

use PiedWeb\Curl\ExtendedClient as Client;
use PiedWeb\Curl\MultipleCheckInHeaders;
use PiedWeb\Curl\Response;
use PiedWeb\Curl\ResponseFromCache;

class RequestTest extends \PHPUnit\Framework\TestCase
{
    public function testDownloadIfHtml()
    {
        $url = 'https://piedweb.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf(function ($line) {
                return 0 === stripos(trim($line), 'content-type') && false !== stripos($line, 'text/html');
            })
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $result = $request->request();

        $this->assertSame(200, $result->getStatusCode());

        $headers = $result->getHeaders();
        $this->assertTrue(\is_array($headers));

        $this->assertSame('text/html; charset=UTF-8', $result->getContentType());
        $this->assertTrue(\strlen($result->getContent()) > 10);
        $this->assertStringContainsString('200', $result->getHeaders()[0]);
        $this->assertStringContainsString('200', $result->getHeaderLine('0'));
        $this->assertStringContainsString('200', $result->getHeader('0'));
        $this->assertNull($result->getCookies());
    }

    public function testNotDownload()
    {
        $url = 'https://piedweb.com/assets/img/xl/bg.jpg';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf('PiedWeb\Curl\Helper::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $result = $request->request();

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('', $result->getContent());
    }

    public function testEffectiveUrl()
    {
        $url = 'http://www.piedweb.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf('PiedWeb\Curl\Helper::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $result = $request->request();

        $this->assertSame('https://piedweb.com/', $result->getUrl());
        $this->assertSame($url, $request->getTarget());
        $this->assertTrue(\strlen($result->getContent()) > 10);
    }

    public function testCurlError()
    {
        $url = 'http://www.readze'.rand(100000, 99999999).'.com/';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $result = $request->request();

        $this->assertSame(6, $result->getError());
    }

    public function test404()
    {
        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDownloadOnlyIf('PiedWeb\Curl\Helper::checkContentType')
            ->setDesktopUserAgent()
            ->setEncodingGzip()
        ;
        $result = $request->request();

        $this->assertSame(404, $result->getStatusCode());
    }

    public function testAllMethods()
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
            ->setDownloadOnlyIf([$checkHeaders, 'check'])
            ->setLanguage('en-US,en;q=0.9')
        ;

        $result = $request->request();

        $this->assertSame($request->getTarget(), $url);
        $this->assertSame($request->getUserAgent(), $request->lessJsUserAgent);

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('text/html', $result->getMimeType());

        $headers = $result->getHeaders();
        $this->assertTrue(\is_array($headers));

        $this->assertSame('text/html; charset=UTF-8', $result->getContentType());

        $this->assertTrue(\strlen($result->getContent()) > 100);
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
Content-Length: 0', trim(strip_tags($result->getBody())));
    }

    public function testMultipleCheckInHeaders()
    {
        $checkHeaders = new MultipleCheckInHeaders();

        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setDefaultGetOptions()
            ->setDefaultSpeedOptions()
            ->setUserAgent('Hello :)')
            ->setDownloadOnlyIf([$checkHeaders, 'check'])
            ->setPost('testpost')
        ;

        $result = $request->request();

        $this->assertSame(92832, $result->getError());
        $this->assertSame(404, $result->getInfo('http_code'));
    }

    public function testProxy()
    {
        $url = 'https://piedweb.com/404-error';
        $request = new Client($url);
        $request
            ->setProxy('75.157.242.104:59190')
            ->setNoFollowRedirection()
            ->setOpt(\CURLOPT_CONNECTTIMEOUT, 1)
            ->setOpt(\CURLOPT_TIMEOUT, 1)
        ;

        $result = $request->request();

        $this->assertTrue($result->getError() > 0);
        $this->assertStringContainsString('timed out', $result->getErrorMessage());
    }

    public function testAbortIfTooBig()
    {
        $url = 'https://piedweb.com';
        $request = new Client($url);
        $request->setMaximumResponseSize(1);

        $result = $request->request();
        $this->assertSame($result->getError(), 42);
    }

    public function testDownloadOnlyFirstBytes()
    {
        $url = 'https://piedweb.com';
        $request = new Client($url);
        $request->setDownloadOnly('0-199');

        $result = $request->request();

        $this->assertTrue(\strlen($result->getContent()) < 300);
    }

    public function testResponseFromCache()
    {
        $response = new ResponseFromCache(
            'HTTP/1.1 200 OK'.\PHP_EOL.\PHP_EOL.'<!DOCTYPE html><html><body><p>Tests</p></body>',
            'https://piedweb.com/',
            ['content_type' => 'text/html; charset=UTF-8']
        );

        $this->assertTrue($response instanceof Response);
        $this->assertSame($response->getMimeType(), 'text/html');
        $this->assertSame($response->getContent(), '<!DOCTYPE html><html><body><p>Tests</p></body>');
    }
}
