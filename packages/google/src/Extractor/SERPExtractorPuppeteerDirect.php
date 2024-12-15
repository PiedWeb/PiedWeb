<?php

namespace PiedWeb\Google\Extractor;

class SERPExtractorPuppeteerDirect extends SERPExtractor
{
    public function __construct(
        private string $wsEndpoint,
        string $html,
        int $extractedAt = 0
    ) {
        parent::__construct($html, $extractedAt);
    }

    protected function getPixelPosFor(?string $xpath): int
    {
        if (\in_array($xpath, ['', null], true)) {
            return 0;
        }

        $cmd = 'PUPPETEER_WS_ENDPOINT='.escapeshellarg($this->wsEndpoint).' '
            .'node '.escapeshellarg(__DIR__.'/../Puppeteer/pixelPos.js').' '.escapeshellarg($xpath);
        \Safe\exec($cmd, $output);

        return \intval($output[0] ?? throw new \Exception());
    }
}
