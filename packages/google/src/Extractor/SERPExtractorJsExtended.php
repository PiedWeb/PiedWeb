<?php

namespace PiedWeb\Google\Extractor;

use Nesk\Puphpeteer\Resources\Page;
use PiedWeb\Google\Helper\Puphpeteer;

class SERPExtractorJsExtended extends SERPExtractor
{
    private ?Page $browserPage = null;

    public function __construct(public string $html)
    {
        parent::__construct($html);

        $filePath = __DIR__.'/tmp.html';
        file_put_contents($filePath, $html);
        $this->getBrowserPage()->goto('file://'.$filePath);
        // $this->getBrowserPage()->setContent($html); is failing
    }

    private function getBrowserPage(): Page
    {
        if (null !== $this->browserPage) {
            return $this->browserPage;
        }

        $this->browserPage = (new Puphpeteer())->getBrowserPage();
        $this->browserPage->setOfflineMode(true);

        return $this->browserPage;
    }

    protected function getPixelPosFor(string $elementXpath): int
    {
        $element = $this->getBrowserPage()->querySelectorXPath($elementXpath);
        if (isset($element[0]) && null !== $element[0]->boundingBox()) {
            return $element[0]->boundingBox()['y']; // @phpstan-ignore-line
        }

        return 0;
    }
}
