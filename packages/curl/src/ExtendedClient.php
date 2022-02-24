<?php

namespace PiedWeb\Curl;

use CurlHandle;

class ExtendedClient extends Client
{
    use UserAgentTrait;

    /** @var string contains current UA */
    private string $userAgent;

    /**
     * @var callable
     */
    private $filter;

    private int $optChangeDuringRequest = 0;

    /**
     * A short way to set some classic options to cURL a web page.
     */
    public function setDefaultGetOptions(
        int $connectTimeOut = 5,
        int $timeOut = 10,
        int $dnsCacheTimeOut = 600,
        bool $followLocation = true,
        int $maxRedirs = 5,
        bool $autoReferer = true
    ): self {
        $this
            ->setOpt(\CURLOPT_AUTOREFERER, $autoReferer)
            ->setOpt(\CURLOPT_FOLLOWLOCATION, $followLocation)
            ->setOpt(\CURLOPT_MAXREDIRS, $maxRedirs)
            ->setOpt(\CURLOPT_CONNECTTIMEOUT, $connectTimeOut)
            ->setOpt(\CURLOPT_DNS_CACHE_TIMEOUT, $dnsCacheTimeOut)
            ->setOpt(\CURLOPT_TIMEOUT, $timeOut)
        ;

        return $this;
    }

    /**
     * A short way to set some classic options to cURL a web page quickly
     * (but loosing some data like header, cookie...).
     */
    public function setDefaultSpeedOptions(): self
    {
        $this->setOpt(\CURLOPT_SSL_VERIFYHOST, 0);
        $this->setOpt(\CURLOPT_SSL_VERIFYPEER, 0);
        $this->setDefaultGetOptions(5, 10, 600, true, 1);
        $this->setEncodingGzip();

        return $this;
    }

    /**
     * A short way to not follow redirection.
     */
    public function setNoFollowRedirection(): self
    {
        $this->setOpt(\CURLOPT_FOLLOWLOCATION, false);
        $this->setOpt(\CURLOPT_MAXREDIRS, 0);

        return $this;
    }

    public function setReturnOnlyHeader(): self
    {
        $this->setOpt(\CURLOPT_NOBODY, 1);

        return $this;
    }

    /**
     * An self::setOpt()'s alias to add a cookie to your request.
     */
    public function setCookie(?string $cookie): self
    {
        $this->setOpt(\CURLOPT_COOKIE, $cookie);

        return $this;
    }

    /**
     * An self::setOpt()'s alias to add a referer to your request.
     */
    public function setReferer(string $referer): self
    {
        $this->setOpt(\CURLOPT_REFERER, $referer);

        return $this;
    }

    /**
     * An self::setOpt()'s alias to add an user-agent to your request.
     */
    public function setUserAgent(string $ua): self
    {
        $this->userAgent = $ua;

        $this->setOpt(\CURLOPT_USERAGENT, $ua);

        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * A short way to set post's options to cURL a web page.
     *
     * @param mixed $post if it's an array, will be converted via http build query
     */
    public function setPost($post): self
    {
        $this->setOpt(\CURLOPT_CUSTOMREQUEST, 'POST');
        $this->setOpt(\CURLOPT_POST, 1);
        $this->setOpt(\CURLOPT_POSTFIELDS, \is_array($post) ? http_build_query($post) : $post);

        return $this;
    }

    /**
     * If you want to request the URL and hope get the result gzipped.
     * The output will be automatically uncompress with request();.
     */
    public function setEncodingGzip(): self
    {
        $this->setOpt(\CURLOPT_ENCODING, 'gzip, deflate');

        return $this;
    }

    /**
     * If you want to request the URL with a (http|socks...) proxy (public or private).
     *
     * @param string $proxy [scheme]IP:PORT[:LOGIN:PASSWORD]
     *                      Eg. : socks5://98.023.023.02:1098:cUrlRequestProxId:SecretPassword
     */
    public function setProxy(string $proxy): self
    {
        $scheme = Helper::getSchemeFrom($proxy);
        $proxy = explode(':', $proxy);
        $this->setOpt(\CURLOPT_HTTPPROXYTUNNEL, 1);
        $this->setOpt(\CURLOPT_PROXY, $scheme.$proxy[0].':'.$proxy[1]);
        if (isset($proxy[2])) {
            $this->setOpt(\CURLOPT_PROXYUSERPWD, $proxy[2].':'.$proxy[3]);
        }

        return $this;
    }

    /**
     * @param callable $func function wich must return boolean
     */
    public function setDownloadOnlyIf(callable $func): self
    {
        $this->filter = $func;
        $this->setOpt(\CURLOPT_HEADERFUNCTION, [$this, 'checkHeader']);
        $this->setOpt(\CURLOPT_NOBODY, 1);

        return $this;
    }

    /**
     * @param int $maxBytes Default 2000000 = 2000 Kbytes = 2 Mo
     * @psalm-suppress UnusedClosureParam
     */
    public function setMaximumResponseSize(int $maxBytes = 2000000): self
    {
        //$this->setOpt(CURLOPT_BUFFERSIZE, 128); // more progress info
        $this->setOpt(\CURLOPT_NOPROGRESS, false);
        $this->setOpt(\CURLOPT_PROGRESSFUNCTION, function ($handle, $totalBytes, $receivedBytes) use ($maxBytes) {
            if ($totalBytes > $maxBytes || $receivedBytes > $maxBytes) {
                return 1;
            }
        });

        return $this;
    }

    public function setDownloadOnly(string $range = '0-500'): self
    {
        $this->setOpt(\CURLOPT_RANGE, $range);

        return $this;
    }

    public function checkHeader(CurlHandle $handle, string $line): int
    {
        $this->error = 92832;
        $this->errorMessage = 'Aborted because user check in headers';

        if (\call_user_func($this->filter, $line)) {
            ++$this->optChangeDuringRequest;
            $this->setOpt(\CURLOPT_NOBODY, false);
            $this->resetError();
        }

        return \strlen($line);
    }

    /**
     * Execute the request.
     */
    public function request(?string $url = null): Response
    {
        $response = parent::request($url);

        // Permits to transform HEAD request in GET request
        if (1 === $this->optChangeDuringRequest) {
            return $this->request();
        }

        $this->optChangeDuringRequest = 0;

        if (($effectiveUrl = $response->getUrl()) !== null) {
            $this->setReferer($effectiveUrl);
        }

        if (($cookies = $response->getCookies()) !== null) {
            $this->setCookie($cookies);
        }

        return $response;
    }
}
