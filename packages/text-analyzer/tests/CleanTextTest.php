<?php

namespace PiedWeb\ExpressionHarvester\Test;

use PiedWeb\TextAnalyzer\CleanText;

class CleanTextTest extends \PHPUnit\Framework\TestCase
{
    public function testFixEncoding(): void
    {
        $text = 'une&nbsp;gourde&nbsp;ou ?';
        $text2 = "une&nbsp;gourde\u{a0}ou ?";
        $this->assertSame('une gourde ou ?', CleanText::fixEncoding($text));
        $this->assertSame('une gourde ou ?', CleanText::fixEncoding($text2));
    }

    public function testFixEncodingConvertsLegacyEncodingsToUtf8(): void
    {
        // Latin-1 byte converted to UTF-8
        $this->assertSame('café', CleanText::fixEncoding("caf\xE9"));
        // Already valid UTF-8 is left untouched
        $this->assertSame('café', CleanText::fixEncoding('café'));
        // Windows-1252 only: 0x80 is the euro sign
        $this->assertSame('5 €', CleanText::fixEncoding("5 \x80"));
        // Mixed: valid UTF-8 and lone Latin-1 bytes in the same string
        $this->assertSame('été à Genève', CleanText::fixEncoding("\xC3\xA9t\xE9 \xC3\xA0 Gen\xE8ve"));
        // A Latin-1 no-break space separates words instead of gluing them
        $this->assertSame('caf bar', CleanText::fixEncoding("caf\xA0bar"));
    }

    public function testFixEncodingRepairsDoubleEncodedUtf8(): void
    {
        // UTF-8 read as Windows-1252 then encoded again; à ends with a no-break space
        $this->assertSame("café crème à l'été", CleanText::fixEncoding("cafÃ© crÃ¨me Ã\u{A0} lâ€™Ã©tÃ©"));
        // Other scripts are not downgraded to Latin-1
        $this->assertSame('Привет 日本語 🙂', CleanText::fixEncoding('Привет 日本語 🙂'));
        // A capital A tilde alone is legitimate text
        $this->assertSame('SÃO PAULO', CleanText::fixEncoding('SÃO PAULO'));
        // Legitimate a-circumflex before letters, and capitals followed by capitals
        $this->assertSame('lâcher prise', CleanText::fixEncoding('lâcher prise'));
        $this->assertSame('ÉTÉ À ÂGE', CleanText::fixEncoding('ÉTÉ À ÂGE'));
    }

    public function testSimpleSentences(): void
    {
        $loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

        $this->assertCount(4, CleanText::getSentences($loremIpsum));
    }

    public function testRemoveExtremityStopWords(): void
    {
        $this->assertSame('ferme', CleanText::removeStopWordsAtExtremity('la ferme'));
        $this->assertSame('ferme', CleanText::removeStopWordsAtExtremity('la ferme de'));
        $this->assertSame('ferme', CleanText::removeStopWordsAtExtremity('ferme de'));
    }

    public function testRemoveExtremityStopWords2(): void
    {
        $this->assertSame('savoir', CleanText::removeStopWordsAtExtremity('savoir plus '));
    }

    public function testRemoveStopWords(): void
    {
        $this->assertSame('', CleanText::removeStopWords(' http//www '));
        $this->assertSame('', CleanText::removeStopWords(' ainsi '));
        $this->assertSame('', CleanText::removeStopWords(' chaque '));
    }

    public function testStripTags(): void
    {
        $text = '<label class="u-block" for="js-toggler-menu"><svg aria-labelledby="title" role="img" class="u-icon u-icon-burger" viewbox="0 0 18 18" width="18" height="18"><title lang="fr">Icone menu burger</title><span class="u-visually-hidden" aria-hidden="true">Menu</span></label>';

        $this->assertSame('Icone menu burger Menu', CleanText::stripHtmlTags($text));
        $this->assertSame('Icone menu burger Menu', CleanText::stripHtmlTagsOldWay(str_replace('<', ' <', $text)));

        $text = '<label class="u-block" for="js-toggler-menu"><svg aria-labelledby="title" role="img" class="u-icon u-icon-burger" viewbox="0 0 18 18" width="18" height="18"><title lang="fr">Icone menu burger</title>'."\n".'<span class="u-visually-hidden" aria-hidden="true">Menu</span></label>';

        $this->assertSame('Icone menu burger Menu', CleanText::stripHtmlTags($text));
        $this->assertSame('Icone menu burger Menu', CleanText::stripHtmlTagsOldWay(str_replace('<', ' <', $text)));
    }

    public function testRemovePunctuation(): void
    {
        $this->assertSame('a b c', CleanText::removePunctuation('a, b ; c'));
        $this->assertSame('', CleanText::removePunctuation(''));
    }

    public function testRemoveEmail(): void
    {
        $this->assertSame('write to   now', CleanText::removeEmail('write to jo@x.fr now'));
        $this->assertSame('no email here', CleanText::removeEmail('no email here'));
    }

    public function testRemoveDate(): void
    {
        $this->assertSame('rdv le   ok', CleanText::removeDate('rdv le 12 octobre 2026 ok'));
        $this->assertSame('rien à voir', CleanText::removeDate('rien à voir'));
    }
}
