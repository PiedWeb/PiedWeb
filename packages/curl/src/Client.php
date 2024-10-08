<?php

namespace PiedWeb\Curl;

class Client
{
    private ?\CurlHandle $handle = null;

    protected int $error = 0;

    protected string $errorMessage = '';

    /** @psalm-suppress PropertyNotSetInConstructor */
    protected string $target;

    protected ?Response $response = null;

    public function __construct(?string $target = null)
    {
        if (null !== $target) {
            $this->setTarget($target);
        }
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;
        $this->setOpt(\CURLOPT_URL, $target);

        return $this;
    }

    protected function getHandle(): \CurlHandle
    {
        if (null === $this->handle) {
            $this->handle = \Safe\curl_init();

            curl_setopt($this->handle, \CURLOPT_HEADER, 1);
            $this->setOpt(\CURLOPT_RETURNTRANSFER, 1);
        }

        return $this->handle;
    }

    public function setOpt(int $option, mixed $value): static
    {
        if (\CURLOPT_HEADER === $option) {
            throw new \Exception("can't change CURLOPT_HEADER (always true)");
        }

        curl_setopt($this->getHandle(), $option, $value);

        return $this;
    }

    /**
     * Get information regarding the request.
     *
     * @param int $opt This may be one of the following constants:
     *                 http://php.net/manual/en/function.curl-getinfo.php
     */
    public function getCurlInfo(int $opt): mixed
    {
        return curl_getinfo($this->getHandle(), $opt);
    }

    /**
     * @return array<string, array<int, array<string, string>>|float|int|string|null> an associative array with the following elements (which correspond to opt): "url" "content_type" "http_code" "header_size" "request_size" "filetime" "ssl_verify_result" "redirect_count" "total_time" "namelookup_time" "connect_time" "pretransfer_time" "size_upload" "size_download" "speed_download" "speed_upload" "download_content_length" "upload_content_length" "starttransfer_time" "redirect_time"
     *
     * @psalm-suppress InvalidArgument (for $handle)
     */
    public function getCurlInfos(): array
    {
        $curlInfo = curl_getinfo($this->getHandle());

        return \is_array($curlInfo) ? $curlInfo : throw new \Exception();
    }

    /**
     * Close the connexion
     * Call curl_reset function.
     */
    public function close(): void
    {
        curl_reset($this->getHandle());
    }

    /**
     * Return the last error number (curl_errno).
     *
     * @return int the error number or 0 (zero) if no error occurred
     */
    public function getError(): int
    {
        return 0 === $this->error ? curl_errno($this->getHandle()) : $this->error;
    }

    /**
     * Return a string containing the last error for the current session (curl_error).
     *
     * @return string the error message or '' (the empty string) if no error occurred
     */
    public function getErrorMessage(): string
    {
        return '' === $this->errorMessage ? curl_error($this->getHandle()) : $this->errorMessage;
    }

    public function resetError(): void
    {
        $this->error = 0;
        $this->errorMessage = '';
    }

    public function request(?string $target = null): bool
    {
        if (null !== $target) {
            $this->setTarget($target);
        }

        $this->response = Response::createFromClient($this, curl_exec($this->getHandle()));

        return 0 === $this->getError();
    }

    public function getResponse(): Response
    {
        return $this->response ?? throw new \Exception('You must use request() before');
    }
}
