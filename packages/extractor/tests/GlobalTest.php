<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\HrefLangExtractor;
use PiedWeb\Extractor\TextData;
use PiedWeb\Extractor\Url;
use PiedWeb\TextAnalyzer\CleanText;
use Symfony\Component\DomCrawler\Crawler;

final class GlobalTest extends TestCase
{
    /** Response[] */
    private array $response = [];

    public function getPage(string $url = 'https://piedweb.com'): string
    {
        if (isset($this->response[$url])) {
            return $this->response[$url];
        }

        $client = new ExtendedClient($url);
        $client
            ->setDefaultSpeedOptions()
            ->fakeBrowserHeader()
            ->setNoFollowRedirection()
            ->setMaximumResponseSize()
            ->setDownloadOnlyIf(Helper::checkStatusCode(...))
            ->setMobileUserAgent();
        //  if ($this->proxy) { $client->setProxy($this->proxy); }
        $client->request();

        if ($client->getError() > 0) {
            throw new Exception();
        }

        return $this->response[$url] = $client->getResponse()->getBody();
    }

    public function testEncoding(): void
    {
        $toTests = [
            mb_convert_encoding('Nous n’avons pas encore', 'ISO-8859-1') => "Nous n'avons pas encore",
            '&raquo; L’ombre  &laquo;' => '" L\'ombre "',
            iconv('UTF-8', 'ISO-8859-1', 'supér\'') => 'supér\'',
            iconv('UTF-8', 'ISO-8859-1', 'supér') => 'supér',
            iconv('UTF-8', 'ISO-8859-1', 'supér  &nbsp;&laquo;') => 'supér "',
            iconv('UTF-8', 'ISO-8859-15', 'supér') => 'supér',
            mb_convert_encoding('supér',  'UTF-16LE') => 'supér',
            'L’ombre' => "L'ombre",
            'L’ombre&nbsp;&nbsp;' => "L'ombre",
            'L&#39;ombre' => "L'ombre",
            'L&apos;ombre' => "L'ombre",
            'L&#x27;ombre' => "L'ombre",
            'L&ocirc;mbre' => 'Lômbre',
        ];
        foreach ($toTests as $toFix => $same) {
            $this->assertSame($same, CleanText::fixEncoding($toFix));
        }
    }

    public function testCanonical(): void
    {
        $url = new Url('https://piedweb.com');
        $canonical = new CanonicalExtractor($url, new Crawler($this->getPage()));
        $this->assertTrue($canonical->isCanonicalCorrect());
        $this->assertTrue($canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect());

        $html = str_replace('rel="canonical" href="https://piedweb.com/"', 'rel="canonical" href="/"', $this->getPage('https://piedweb.com/'));
        $crawler = new Crawler($html);
        $canonical = new CanonicalExtractor($url, $crawler);
        $this->assertFalse($canonical->isCanonicalCorrect());
        $this->assertTrue($canonical->isCanonicalPartiallyCorrect());
        $this->assertTrue($canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect());

        $canonical = new CanonicalExtractor($url, new Crawler(str_replace('rel="canonical" href="https://piedweb.com/"', ' ', $this->getPage('https://piedweb.com/'))));
        $this->assertTrue($canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect());

        $canonical = new CanonicalExtractor($url, new Crawler(str_replace('rel="canonical" href="https://piedweb.com/"', 'rel="canonical" href="/other-page"', $this->getPage('https://piedweb.com/'))));
        $this->assertFalse($canonical->ifCanonicalExistsIsItCorrectOrPartiallyCorrect());
    }

    public function testTextDataExtractor(): void
    {
        $rawHtml = $this->getPage('https://piedweb.com/');
        $crawler = new Crawler($rawHtml);

        $textData = new TextData($rawHtml, $crawler);

        $this->assertSame('title', array_values($textData->getFlatContent())[0]);
        $this->assertGreaterThan(10, $textData->getWordCount());
        $this->assertGreaterThan(7, $textData->getRatioTxtCode());
        // dump($textData->getTextAnalysis()->getExpressions(2));
        $this->assertArrayHasKey('web', $textData->getTextAnalysis()->getExpressions());
    }

    public function testHrefLangExtractor(): void
    {
        $rawHtml = $this->getPage('https://altimood.com/');

        $extractor = new HrefLangExtractor(new Crawler($rawHtml));
        $list = $extractor->getHrefLangList();

        $this->assertContains('https://altimood.com/en', $list);
    }
}
