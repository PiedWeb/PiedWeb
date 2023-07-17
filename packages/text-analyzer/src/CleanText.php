<?php

namespace PiedWeb\TextAnalyzer;

use ForceUTF8\Encoding;
use PiedWeb\Extractor\Helper;
use Symfony\Component\DomCrawler\Crawler;

class CleanText
{
    /**
     * @var string
     */
    final public const REGEX_SENTENCE = '/[A-Z][^\n\.\!\?…]{4,}[\.\!\?…]/';

    // '/([^\n\.\!\?]{10,}[\.\!\?…])*/';
    /**
     * @var string[]
     */
    final public const STOP_WORDS = [
        // English stop words
        'a', 'able', 'about', 'across', 'after', 'all', 'almost', 'also', 'am', 'among', 'an', 'and', 'any', 'are',
        'as', 'at', 'be', 'because', 'been', 'but', 'by', 'can', 'cannot', 'could', 'dear', 'did', 'do', 'does',
        'either', 'else', 'ever', 'every', 'for', 'from', 'get', 'got', 'had', 'has', 'have', 'he', 'her', 'hers',
        'him', 'his', 'how', 'however', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'just', 'least', 'let', 'like',
        'likely', 'may', 'me', 'might', 'most', 'must', 'my', 'neither', 'no', 'nor', 'not', 'of', 'off', 'often',
        'on', 'only', 'or', 'other', 'our', 'own', 'rather', 'said',    'say', 'says', 'she', 'should', 'since', 'so',
        'some', 'than', 'that', 'the', 'their', 'them', 'then', 'there',    'these', 'they', 'this', 'tis', 'to',
        'too', 'twas', 'us', 'wants', 'was', 'we', 'were', 'what', 'when', 'where',    'which', 'while', 'who',
        'whom', 'why', 'will', 'with', 'would', 'yet', 'you', 'your',

        'cookielawinfo', 'checkbox',
        // French Stop words
        'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'elle', 'en', 'et', 'eux', 'il', 'je', 'la',
        'le', 'leur', 'lui', 'plus', 'ma', 'mais', 'me', 'même', 'mes', 'moi', 'mon', 'ne', 'nos', 'notre', 'nous',
        'on', 'ou', 'par', 'pas', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur', 'ta', 'te', 'tes',
        'toi', 'ton', 'tu', 'un', 'une', 'vos', 'votre', 'vous', 'puis', 'aussi', 'comme', 'pourquoi', 'alors', 'si',
        'chaque', 'mentions légales', 'entre', 'autre', 'comment', 'là', 'après', 'principalement',
        'certains', 'parfois', 'ensuite', 'article', 'etc', 'où', 'également', 'site', 'mieux', 'ainsi', 'fois', 'encore',
        'selon', 'afin', 'blog', 'user', 'certaines', 'avoir', 'autres', 'souvent', '★★★★★', '★', 'propose',

        'c', 'd', 'j', 'l', 'à', 'm', 'n', 's', 't', 'y', 'e',

        'ceci', 'cela', 'celà', 'cet', 'cette', 'ici', 'ils', 'les', 'leurs', 'quel', 'quels', 'quelle', 'quelles',
        'sans', 'soi', 'très', 'tout', 'toutes', 'tous', 'bien', 'bonne', 'peu', 'ça', 'car', 'selon', 'lequel',

        'été', 'étée', 'étées', 'étés', 'étant', 'suis', 'es', 'est', 'sommes', 'êtes', 'sont', 'serai', 'seras',
        'sera', 'serons', 'serez', 'seront', 'serais', 'serait', 'serions', 'seriez', 'seraient', 'étais', 'était',
        'étions', 'étiez', 'étaient', 'fus', 'fut', 'fûmes', 'fûtes', 'furent', 'sois', 'soit', 'soyons', 'soyez',
        'soient', 'fusse', 'fusses', 'fût', 'fussions', 'fussiez', 'fussent', 'ayant', 'eu', 'eue', 'eues', 'eus',
        'ai', 'as', 'avons', 'avez', 'ont', 'aurai', 'auras', 'aura', 'aurons', 'aurez', 'auront', 'aurais', 'aurait',
        'aurions', 'auriez', 'auraient', 'avais', 'avait', 'avions', 'aviez', 'avaient', 'eut', 'eûmes', 'eûtes',
        'eurent', 'aie', 'aies', 'ait', 'ayons', 'ayez', 'aient', 'eusse', 'eusses', 'eût', 'eussions', 'eussiez',
        'eussent', 'dit', 'fait', 'peut', 'faire', 'fais',

        'répondre', 'repondre', 'réponses', 'reply', 'bonjour', 'merci', 'supprimer', 'anonyme', 'signaler',
        'icone', 'flèche',
        'similaires', 'fiches', 'voir', 'articles', 'favoris', 'ajouter',

        // Weird thing happen every day
        'http//www', 'https//www',
    ];

    public static function fixEncoding(string $text): string
    {
        // fix encoding
        $text = str_replace(mb_convert_encoding('’', 'ISO-8859-1'), "'", $text);
        /** @var string $text */
        $text = Encoding::toUTF8($text);
        $text = html_entity_decode(htmlentities((string) $text), 0, 'UTF-8');
        $text = preg_replace('#[\x00-\x1F\x7F\xA0]#u', '', $text) ?? throw new \Exception();
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_XML1 | \ENT_HTML5, 'UTF-8');
        $text = str_replace(['™', '©', '®'], ' ', $text);
        $text = str_replace(['„', '”', '“', '»', '«'], '"', $text);
        $text = str_replace(['’'], "'", $text);
        $text = str_replace(['…'], '...', $text);
        $text = str_replace(['–', '—', '\xC2\xAD'], ' - ', $text);
        $text = preg_replace('#(,|\.|\(|\[|\]|\)|!|\?|;|\{|\}|"|:|\*|\/|\||>|<|-|\+)#', ' $0 ', $text) ?? throw new \Exception(preg_last_error_msg());
        $text = preg_replace('#(\xE2\x80\xAF|\xC2\xAD|\xC2\xA0)+#', ' ', $text) ?? throw new \Exception(preg_last_error_msg());
        $text = preg_replace('#\s+#', ' ', $text) ?? throw new \Exception(preg_last_error_msg());

        $text = str_replace('', "'", $text);

        return trim($text);
    }

    /**
     * @return string[]
     */
    public static function getSentences(string $text): array
    {
        $sentences = [];
        if (preg_match_all(self::REGEX_SENTENCE, $text, $matches, \PREG_SET_ORDER, 0)) {
            foreach ($matches as $m) {
                if (\count(explode(' ', $m[0])) < 30) { // We keep only sentence with less than 30 words
                    $sentences[] = self::fixEncoding($m[0]);
                }
            }
        }

        return $sentences;
    }

    public static function keepOnlySentence(string $text): string
    {
        return implode(' ', self::getSentences($text));
    }

    public static function removePunctuation(string $text): string
    {
        return Helper::preg_replace_str('/ ?(,|\.|\(|\[|\]|\)|!|\?|;|\{|\}|"|:|\*|\/|\||>|<|-|\+) ?/', ' ', $text);
    }

    public static function removeEmail(string $text): string
    {
        return Helper::preg_replace_str('/([a-zA-Z0-9._-]+(@|.at.)[a-zA-Z0-9._-]+\.[a-zA-Z0-9_-]+)/i', ' ', $text);
    }

    public static function removeDate(string $text): string
    {
        $month = '(janvier|january|février|february|mars|march|avril|april|mai|may|juin|june|juillet|july|août|august'
                .'|septembre|september|octobre|october|novembre|november|décembre|december|jan|fev|feb|mar|avr|apr|jui'
                .'|jun|juil|jul|aoû|aug|aout|aou|sept|oct|nov|dec|decembre)';

        // french format
        $text = Helper::preg_replace_str('/([0-3]?[0-9]\s+)?'.$month.'\s+(20)?[0-3][0-9]/i', ' ', $text);

        return $text;
    }

    public static function removeStopWords(string $text): string
    {
        $text = str_replace("'", ' ', $text);
        $text = str_replace(explode('|', ' '.implode(' | ', self::STOP_WORDS).' '), ' ', $text);

        return trim($text);
    }

    public static function removeStopWordsAtExtremity(string $text): string
    {
        $text = trim($text);
        $text = str_replace("'", ' ', $text);
        $text = Helper::preg_replace_str('@^'.implode(' |^', self::STOP_WORDS).' @', '', $text);
        $text = Helper::preg_replace_str('@'.implode('$| ', self::STOP_WORDS).'$@', '', $text);

        return trim($text);
    }

    public static function stripHtmlTagsOldWay(string $html): string
    {
        // Often error because of limitation of JIT
        $regex = '@<(script|style|head|iframe|noframe|noscript|object|embed|noembed)[^>]*?>((?!<\1).)*<\/\1>@si';
        $textWithoutInvisible = Helper::preg_replace_str($regex, ' ', $html);
        if (\PREG_NO_ERROR === preg_last_error()) {
            // var_dump(array_flip(get_defined_constants(true)['pcre'])[preg_last_error()]); die();
            $html = $textWithoutInvisible;
        }

        $html = Helper::preg_replace_str('/\s+/', ' ', $html);
        $html = Helper::preg_replace_str('@</(div|p)>@si', "$0 \n\n", $html);
        $html = Helper::preg_replace_str('@<br[^>]*>@si', "$0 \n", $html);
        $html = strip_tags($html);
        $html = Helper::preg_replace_str("/[\t\n\r]+/", "\n", $html);

        return trim(implode("\n", array_map('trim', explode("\n", Helper::preg_replace_str('/\s+/', ' ', $html)))));
    }

    public static function stripHtmlTags(string $html): string
    {
        // Permit to avoid stick words when span are used like block
        $html = str_replace('<', ' <', $html);
        $html = self::removeSrOnly($html);

        $dom = new Crawler($html);
        if ('' === ($text = $dom->text(''))) { // If we failed to load the html in dom
            return self::stripHtmlTagsOldWay($html);
        }

        return $text;
    }

    /**
     * Not very good... avoid Jit error.
     */
    public static function removeSrOnly(string $html): string
    {
        $regex = '/<span[^>]+class="[^>]*(screen-reader-only|sr-only)[^>]*"[^>]*>[^<]*<\/span>/si';

        return Helper::preg_replace_str($regex, ' ', $html);
    }
}
