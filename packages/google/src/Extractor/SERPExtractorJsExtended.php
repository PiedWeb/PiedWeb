<?php

namespace PiedWeb\Google\Extractor;

use Nesk\Puphpeteer\Resources\Page;
use PiedWeb\Google\Helper\Puphpeteer;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class SERPExtractorJsExtended extends SERPExtractor
{
    private ?Page $browserPage = null;

    public function getBrowserPage(): Page
    {
        if (null !== $this->browserPage) {
            return $this->browserPage;
        }

        $this->browserPage = (new Puphpeteer())->getBrowserPage();
        $this->browserPage->setOfflineMode(true);

        $filePath = __DIR__.'/tmp.html';
        file_put_contents($filePath, $this->html);
        $this->browserPage->goto('file://'.$filePath);
        $this->browserPage->setOfflineMode(false);

        // $this->getBrowserPage()->setContent($html); is failing

        return $this->browserPage;
    }

    protected function getPixelPosFor(string|\DOMNode $element): int
    {
        if (($_ENV['APP_ENV'] ?? 'prod') !== 'test') {
            return $this->getPixelPosForWithoutCache($element);
        }

        $cache = new FilesystemAdapter();

        /** @var int */
        $pixelPos = $cache->get(
            sha1($this->html.'-'.($element instanceof \DOMNode ? $element->getNodePath() : $element)),
            function (ItemInterface $item) use ($element): int {
                $item->expiresAfter(86400);

                return $this->getPixelPosForWithoutCache($element);
            }
        );

        return $pixelPos;
    }

    private function getPixelPosForWithoutCache(string|\DOMNode $element): int
    {
        if ($element instanceof \DOMNode) {
            $element = $element->getNodePath() ?? throw new \LogicException();
        }

        $element = $this->getBrowserPage()->querySelectorXPath($element);
        if (isset($element[0]) && null !== $element[0]->boundingBox()) {
            $boundingBox = $element[0]->boundingBox();
            if (! \is_array($boundingBox)) {
                return 0;
            }

            if (! isset($boundingBox['y'])) {
                return 0;
            }

            if (! \is_int($boundingBox['y'])) {
                return 0;
            }

            return $boundingBox['y'];
        }

        return 0;
    }
}
