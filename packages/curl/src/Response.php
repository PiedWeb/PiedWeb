<?php

namespace PiedWeb\Curl;

class Response
{
    protected string $headers = '';

    /** @var ?array<int|string, string|string[]> */
    protected ?array $headersParsed = null;

    /** @var string * */
    protected string $content = '';

    /** @var array<string, int|string>  an associative array with the following elements (which correspond to opt): "url" "content_type" "http_code" "header_size" "client_size" "filetime" "ssl_verify_result" "redirect_count" "total_time" "namelookup_time" "connect_time" "pretransfer_time" "size_upload" "size_download" "speed_download" "speed_upload" "download_content_length" "upload_content_length" "starttransfer_time" "redirect_time" */
    protected $info = [];

    protected int $error = 0;

    protected string $errorMessage;

    /**
     * @psalm-suppress InvalidArgument (for $handle)
     */
    public static function createFromClient(Client $client, bool|string $content): self
    {
        $self = new self();
        $self->info = $client->getCurlInfos();
        $self->error = $client->getError();
        $self->errorMessage = $client->getErrorMessage();

        if (\is_bool($content)) {
            return $self;
        }

        $self->headers = substr($content, 0, $sHeaders = (int) $client->getCurlInfo(\CURLINFO_HEADER_SIZE)); // @phpstan-ignore-line
        $self->content = substr($content, $sHeaders);

        return $self;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Return headers's data return by the client.
     *
     * @return ?array<int|string, string|string[]> containing headers's data
     */
    public function getHeaders(): ?array
    {
        if (null !== $this->headersParsed) {
            return $this->headersParsed;
        }

        if ('' === $this->headers) {
            return null;
        }

        $parsed = Helper::httpParseHeaders($this->headers);
        if ([] === $parsed) {
            throw new \Exception('Failed to parse Headers `'.$this->headers.'`');
        }

        return $this->headersParsed = $parsed;
    }

    /**
     * @return string|string[]|null
     */
    public function getHeader(string $name): string|array|null
    {
        return ($headers = $this->getHeaders()) !== null
            && isset($headers[$name]) ? $headers[$name] : null;
    }

    public function getHeaderLine(string $name): ?string
    {
        if (($header = $this->getHeader($name)) === null) {
            return null;
        }

        if (\is_array($header)) {
            return implode(', ', $header);
        }

        return $header;
    }

    public function getRawHeaders(): string
    {
        return $this->headers;
    }

    /**
     * @return string|null requested url
     */
    public function getUrl(): ?string
    {
        return isset($this->info['url']) ? (string) $this->info['url'] : null;
    }

    /**
     * Return the cookie(s).
     *
     * @return string|null containing the cookies
     */
    public function getCookies(): ?string
    {
        $headers = $this->getHeaders();
        $setCookie = null !== $headers
            ? $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? null
            : null;
        if (null === $setCookie) {
            return null;
        }

        if (\is_array($setCookie)) {
            return implode('; ', $setCookie);
        }

        return $setCookie;
    }

    /**
     * Get information (curl info).
     *
     * @param string|null $key to get
     *
     * @return int|string|array<string, string|int>|null
     */
    public function getInfo(string $key = null): int|string|array|null
    {
        return null !== $key && '' !== $key ? ($this->info[$key] ?? null) : $this->info;
    }

    public function getStatusCode(): int
    {
        return (int) $this->info['http_code'];
    }

    public function getContentType(): string
    {
        return (string) $this->info['content_type'];
    }

    public function getMimeType(): string
    {
        $headers = Helper::parseHeader($this->getContentType());

        return $headers[0][0] ?? '';
    }
}
