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
    public const REGEX_SENTENCE = '/[A-Z][^\n\.\!\?…]{4,}[\.\!\?…]/';

    // '/([^\n\.\!\?]{10,}[\.\!\?…])*/';
    /**
     * @var string[]
     */
    public const STOP_WORDS = [
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

        // French Stop words
        'au', 'aux', 'avec', 'ce', 'ces', 'dans', 'de', 'des', 'du', 'elle', 'en', 'et', 'eux', 'il', 'je', 'la',
        'le', 'leur', 'lui', 'plus', 'ma', 'mais', 'me', 'même', 'mes', 'moi', 'mon', 'ne', 'nos', 'notre', 'nous',
        'on', 'ou', 'par', 'pas', 'pour', 'qu', 'que', 'qui', 'sa', 'se', 'ses', 'son', 'sur', 'ta', 'te', 'tes',
        'toi', 'ton', 'tu', 'un', 'une', 'vos', 'votre', 'vous', 'puis', 'aussi', 'comme',

        'c', 'd', 'j', 'l', 'à', 'm', 'n', 's', 't', 'y',

        'ceci', 'cela', 'celà', 'cet', 'cette', 'ici', 'ils', 'les', 'leurs', 'quel', 'quels', 'quelle', 'quelles',
        'sans', 'soi', 'très', 'tout', 'toutes', 'tous', 'bien', 'bonne', 'peu', 'ça', 'car',

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
        $text = Encoding::toUTF8($text);
        $text = html_entity_decode(html_entity_decode(htmlentities($text)));
        $text = htmlspecialchars_decode($text, \ENT_QUOTES);
        $text = str_replace('’', "'", $text); // Unify '
        $text = html_entity_decode(str_replace(['  ', '&nbsp;'], ' ', htmlentities($text)));

        return $text;
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
                    $sentences[] = Helper::preg_replace_str('/\s+/', ' ', $m[0]);
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
        return Helper::preg_replace_str('/,|\.|\(|\[|\]|\)|!|\?|;|…|\{|\}|"|«|»|:|\*|\/|\||>|<| - | + /', ' ', $text);
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
        $html = trim(implode("\n", array_map('trim', explode("\n", Helper::preg_replace_str('/\s+/', ' ', $html)))));

        return $html;
    }

    public static function stripHtmlTags(string $html): string
    {
        // Permit to avoid stick words when span are used like block
        $html = str_replace('<', ' <', $html);
        $html = self::removeSrOnly($html);

        $dom = new Crawler($html);
        if ('' === ($text = $dom->text(''))) { // If we failed to load the html in dom
            $text = self::stripHtmlTagsOldWay($html);
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
