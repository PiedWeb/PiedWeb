<?php

namespace PiedWeb\Curl;

class StaticClient
{
    private static ?ExtendedClient $client = null;

    public static function reset(): void
    {
        self::$client = null;
    }

    public static function get(): ?ExtendedClient
    {
        return self::$client;
    }

    public static function request(string $url): string
    {
        self::$client = self::$client ?? new ExtendedClient();

        self::$client
            ->setTarget($url);
        self::$client->setDefaultGetOptions()
            ->setDefaultSpeedOptions()
            ->setNoFollowRedirection()
            ->setDesktopUserAgent();

        if (! self::$client->request()) {
            return '';
        }

        return self::$client->getResponse()
            ->getBody();
    }
}
