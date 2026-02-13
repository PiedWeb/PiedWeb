<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PiedWeb\Curl\ExtendedClient;
use PiedWeb\Curl\Helper;
use PiedWeb\Curl\Response;
use PiedWeb\Extractor\CanonicalExtractor;
use PiedWeb\Extractor\HrefLangExtractor;
use PiedWeb\Extractor\HtmlIsValid;
use PiedWeb\Extractor\Link;
use PiedWeb\Extractor\LinksExtractor;
use PiedWeb\Extractor\TextData;
use PiedWeb\Extractor\Url;
use PiedWeb\TextAnalyzer\CleanText;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

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
            ->setDownloadOnlyIf(static fn (string $line): bool => Helper::checkStatusCode($line) || Helper::checkStatusCode($line, 206))
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
            // mb_convert_encoding('Nous n’avons pas encore', 'ISO-8859-1') => "Nous n'avons pas encore",
            'Nous n’avons pas encore' => "Nous n'avons pas encore",
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

    public function testTextFlatContent(): void
    {
        $rawHtml = '<body><header><h1>ExampleH1</h1></header><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><p>test</p><footer><div><p>Conditions de vente</p></div></footer><div id="off-canvas">offCanvas</div></body>';
        $crawler = new Crawler($rawHtml);

        $textData = new TextData($rawHtml, $crawler);

        $flatContent = implode(' ', array_keys($textData->getFlatContent()));
        $this->assertNotEmpty($flatContent);
        $this->assertStringNotContainsString('Conditions de vente', $flatContent);
        $this->assertStringNotContainsString('offCanvas', $flatContent);
        $this->assertStringContainsString('ExampleH1', $flatContent);
    }

    public function testHrefLangExtractor(): void
    {
        $rawHtml = $this->getPage('https://altimood.com/');

        $extractor = new HrefLangExtractor(new Crawler($rawHtml));
        $list = $extractor->getHrefLangList();

        $this->assertContains('https://us.altimood.com/', $list);
    }

    public function testLinkExtractor(): void
    {
        $rawHtml = $this->getPage('https://piedweb.com/');
        $rawHtml .= '<a href="http://127.0.0.1:8000/" class="btn btn-1 btn-back not-target">Retourner à la liste</a>';

        $url = new Url('https://piedweb.com');
        $extractor = new LinksExtractor($url, new Crawler($rawHtml), '');
        $list = $extractor->get();
        $lastItem = $list[count($list) - 1];

        $this->assertSame('http://127.0.0.1:8000/', $lastItem->getTo());
    }

    public function testLinkSerializationWithSymfony(): void
    {
        // Create test data
        $parentUrl = new Url('https://example.com');
        $link = new Link('https://example.com/page', $parentUrl);

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $propertyAccessor = new PropertyAccessor();
        $serializer = new Serializer([new ObjectNormalizer($classMetadataFactory, null, $propertyAccessor)], [new JsonEncoder()]);
        $json = $serializer->serialize($link, 'json');
        $unserializedFromJson = $serializer->deserialize($json, Link::class, 'json');

        // Assertions
        $this->assertEquals($link->url, $unserializedFromJson->url);
        $this->assertEquals($link->internal, $unserializedFromJson->internal);
    }

    public function testHtmlIsValid(): void
    {
        $validHtml = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello</p></body></html>';
        $validator = new HtmlIsValid($validHtml);
        $this->assertTrue($validator->isValid());
        $this->assertSame([], $validator->getInvalidReasonList());
    }

    public function testHtmlIsValidMissingDoctype(): void
    {
        $html = '<html><head><title>Test</title></head><body><p>Hello</p></body></html>';
        $validator = new HtmlIsValid($html);
        $this->assertFalse($validator->isValid());
        $this->assertContains(HtmlIsValid::INVALID_REASONS['no doctype'], $validator->getInvalidReasonList());
        $this->assertContains('no doctype', $validator->getInvalidReasonLabels());
    }

    public function testHtmlIsValidMissingClosingTags(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello</p>';
        $validator = new HtmlIsValid($html);
        $this->assertFalse($validator->isValid());
        $this->assertContains(HtmlIsValid::INVALID_REASONS['no closing html'], $validator->getInvalidReasonList());
        $this->assertContains(HtmlIsValid::INVALID_REASONS['no closing body'], $validator->getInvalidReasonList());
    }

    public function testHtmlIsValidUnclosedTags(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><div><p>Hello</p></body></html>';
        $validator = new HtmlIsValid($html);
        $this->assertFalse($validator->isValid());
        $this->assertContains(HtmlIsValid::INVALID_REASONS['unclosed tags'], $validator->getInvalidReasonList());
    }

    public function testHtmlIsValidReasonLabel(): void
    {
        $this->assertSame('no doctype', HtmlIsValid::invalidReasonLabel(4));
        $this->assertSame('unknown', HtmlIsValid::invalidReasonLabel(999));
    }
}
