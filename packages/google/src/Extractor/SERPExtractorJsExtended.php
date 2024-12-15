<?php

namespace PiedWeb\Google\Extractor;

use Nesk\Puphpeteer\Resources\Page;
use PiedWeb\Google\Helper\Puphpeteer;

class SERPExtractorJsExtended extends SERPExtractor
{
    public ?Page $browserPage = null;

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

        return $this->browserPage;
    }

    protected function getPixelPosFor(?string $xpath): int
    {
        if (\in_array($xpath, ['', null], true)) {
            return 0;
        }

        $element = $this->getBrowserPage()->querySelectorAll('::-p-xpath('.$xpath.')');

        if (isset($element[0]) && null !== $element[0]->boundingBox()) {
            $boundingBox = $element[0]->boundingBox();
            if (! \is_array($boundingBox)) {
                return 0;
            }

            if (! isset($boundingBox['y'])) {
                return 0;
            }

            if (! \is_int($boundingBox['y']) && ! \is_float($boundingBox['y'])) {
                return 0;
            }

            $pixelPos = \intval($boundingBox['y']);

            // handle when user has scroll on the page
            if ($pixelPos < 0) {
                /** @psalm-suppress InvalidArgument */
                $this->browserPage?->evaluate('window.scrollTo({top: 0})');  // @phpstan-ignore-line

                return $this->getPixelPosFor($xpath); // potential infinite loop
            }

            return $pixelPos;
        }

        return 0;
    }
}
