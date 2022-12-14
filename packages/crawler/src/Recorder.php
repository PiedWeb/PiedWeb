<?php

namespace PiedWeb\Crawler;

use PiedWeb\Curl\Response;
use PiedWeb\Extractor\Link;
use Stringy\Stringy;

final class Recorder
{
    /**
     * @var string
     */
    public const LINKS_DIR = '/links';

    /**
     * @var string
     */
    public const CACHE_DIR = '/cache';

    /**
     * @var int
     */
    public const CACHE_NONE = 0;

    /**
     * @var int
     */
    public const CACHE_ID = 2;

    /**
     * @var int
     */
    public const CACHE_URI = 1;

    public bool $recordLinks = true;

    public function __construct(
        private readonly string $folder,
        private readonly int $cacheMethod = self::CACHE_ID
    ) {
        if (! file_exists($this->folder)) {
            mkdir($this->folder);
        }

        if (! file_exists($folder.self::LINKS_DIR)) {
            mkdir($folder.self::LINKS_DIR);
            $this->initLinksIndex();
        }

        if (! file_exists($this->folder.self::CACHE_DIR)) {
            mkdir($this->folder.self::CACHE_DIR);
        }
    }

    public function cache(mixed $response, Url $url): void
    {
        if (self::CACHE_NONE === $this->cacheMethod) {
            return;
        }

        $filePath = $this->getCacheFilePath($url);
        if (file_exists($filePath)) {
            return;
        }

        if ($response instanceof Response) {
            \Safe\file_put_contents(
                $filePath,
                $response->getRawHeaders().\PHP_EOL.\PHP_EOL.$response->getBody()
            );
            \Safe\file_put_contents($filePath.'---info', \Safe\json_encode($response->getInfo()));

            return;
        }
    }

    public function getCacheFilePath(Url $url): string
    {
        if (self::CACHE_URI === $this->cacheMethod) {
            return $this->getCacheFilePathWithUrlAsFilename($url);
        }

        return $this->getCacheFilePathWithIdAsFilename($url);
    }

    private function getCacheFilePathWithUrlAsFilename(Url $url): string
    {
        $url = trim($url->getUri(), '/').'/';
        $urlPart = explode('/', $url);
        $folder = $this->folder.self::CACHE_DIR;

        $urlPartLenght = \count($urlPart);
        for ($i = 0; $i < $urlPartLenght; ++$i) {
            if ($i === $urlPartLenght - 1) {
                return $folder.'/'.('' === $urlPart[$i] ? 'index.html' : $urlPart[$i]);
            }

            $folder .= '/'.$urlPart[$i];
            if (! file_exists($folder) || ! is_dir($folder)) {
                mkdir($folder);
            }

            $folder .= '/'.$urlPart[$i];
            if (! file_exists($folder) || ! is_dir($folder)) {
                mkdir($folder);
            }
        }

        throw new \LogicException();
    }

    private function getCacheFilePathWithIdAsFilename(Url $url): string
    {
        return $this->folder.self::CACHE_DIR.'/'.(string) $url->getId();
    }

    /**
     * @param array<Url> $urls
     */
    public function record(array $urls): bool
    {
        $dataCsv = fopen($this->folder.'/data.csv', 'w');
        $indexCsv = fopen($this->folder.'/index.csv', 'w');

        if (false !== $dataCsv && false !== $indexCsv) {
            $header = array_map(
                static fn (string $name): Stringy => Stringy::create($name)->underscored(),
                Url::EXPORTABLE
            );
            fputcsv($dataCsv, $header);
            fputcsv($indexCsv, ['id', 'uri']);

            foreach ($urls as $url) {
                fputcsv($dataCsv, array_values($url->toArray()));
                fputcsv($indexCsv, [$url->getId(), $url->getUri()]);
            }

            fclose($dataCsv);

            return true;
        }

        return false;
    }

    public static function removeBase(string $base, string $url): ?string
    {
        return (str_starts_with($url, $base)) ? substr_replace($url, '', 0, \strlen($base)) : null;
    }

    private function initLinksIndex(): void
    {
        if (! file_exists($this->folder.self::LINKS_DIR.'/Index.csv')) {
            file_put_contents($this->folder.self::LINKS_DIR.'/Index.csv', 'From,To'.\PHP_EOL);
        }
    }

    private function recordInboundLink(Link $link, Url $to): void
    {
        \Safe\file_put_contents(
            $this->folder.self::LINKS_DIR.'/To_'.(string) $to->getId().'_'.((int) $link->mayFollow),
            $this->inboundLinkToStr($link).\PHP_EOL, // can use ->relativize to get only /uri
            \FILE_APPEND
        );
    }

    private function inboundLinkToStr(Link $link): string
    {
        return $link->getParentUrl().';'.$link->getAnchor().';'.((int) $link->mayFollow).';'.$link->getType();
    }

    /**
     * @param array<Url|null> $urls
     * @param Link[]          $links
     */
    public function recordLinksIndex(string $base, Url $from, array $urls, array $links): void
    {
        if (! $this->recordLinks) {
            return;
        }

        $everAdded = [];
        $content = '';

        foreach ($links as $link) {
            $content .= $from->getId();
            $uri = self::removeBase($base, (string) $link->getPageUrl());
            if (\in_array($link->getUrl(), $everAdded)) { // like Google, we sould not add duplicate link,
                // so we say the juice is lost -1
                $content .= ',-1'.\PHP_EOL;
            } else {
                $everAdded[] = $link->getUrl();
                $content .= ','.(isset($urls[$uri]) ? $urls[$uri]->getId() : 0).\PHP_EOL; // 0 = external
            }

            if (isset($urls[$uri])) {
                $this->recordInboundLink($link, $urls[$uri]);
            }
        }

        \Safe\file_put_contents($this->folder.self::LINKS_DIR.'/Index.csv', $content, \FILE_APPEND);

        $this->recordOutboundLink($from, $links);
    }

    /**
     * @param Link[] $links
     */
    private function recordOutboundLink(Url $from, array $links): void
    {
        $links = array_map(static fn (Link $link): string => $link->getUrl().';'.$link->getAnchor().';'.((int) $link->mayFollow).';'.$link->getType(), $links);

        \Safe\file_put_contents($this->folder.self::LINKS_DIR.'/From_'.(string) $from->getId(), implode(\PHP_EOL, $links));
    }
}
