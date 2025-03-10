<?php

// Based on javascript code from https://www.passport.gov.gr/passports/GrElotConverter/GrElotConverter.html

class GreeklishConverter
{
    private $grCaps;
    private static $replacementDictionary;
    private $greekSet;
    private $viSet;
    private $regexPattern;

    public function __construct()
    {
        $this->grCaps = $this->stringToSet("ΑΆΒΓΔΕΈΖΗΉΘΙΊΪΚΛΜΝΞΟΌΠΡΣΤΥΎΫΦΧΨΩΏ");
        if (self::$replacementDictionary === null) {
            self::$replacementDictionary = $this->buildReplacementDictionary();
        }
        $this->greekSet = $this->stringToSet("αάβγδεέζηήθιίϊΐκλμνξοόπρσςτυύϋΰφχψωώ");
        $this->viSet = $this->stringToSet("αάβγδεέζηλιμνορυω");
        $this->regexPattern = '/' . $this->buildRegexPattern() . '/ui'; // 'u' for UTF-8, 'i' for case-insensitive
    }

    public function toGreeklish($text)
    {
        if (empty($text)) {
            return $text;
        }

        return preg_replace_callback($this->regexPattern, function ($matches) use ($text) {
            return $this->replaceMatch($matches[0], $matches['index'], $text);
        }, $text);
    }

    private function replaceMatch($matchedValue, $index, $originalText)
    {
        $lowerMatchedValue = mb_strtolower($matchedValue, 'UTF-8');
        $replacement = self::$replacementDictionary[$lowerMatchedValue];

        if ($replacement['bi'] ?? false) {
            $previousChar = $index > 0 ? $this->getSafeChar($originalText, $index - 1) : '';
            $nextChar = $this->getSafeChar($originalText, $index + mb_strlen($matchedValue, 'UTF-8'));
            $bi = (isset($this->greekSet[mb_strtolower($previousChar, 'UTF-8')]) && isset($this->greekSet[mb_strtolower($nextChar, 'UTF-8')])) ? "mp" : "b";
            return $this->fixCase($bi, $matchedValue);
        } elseif ($replacement['fivi'] ?? false) {
            $c1 = self::$replacementDictionary[mb_strtolower(mb_substr($matchedValue, 0, 1, 'UTF-8'), 'UTF-8')]['greeklish'];
            $nextChar = $this->getSafeChar($originalText, $index + mb_strlen($matchedValue, 'UTF-8'));
            $c2 = isset($this->viSet[mb_strtolower($nextChar, 'UTF-8')]) ? "v" : "f";
            return $this->fixCase($c1 . $c2, $matchedValue);
        } else {
            $nextChar = $this->getSafeChar($originalText, $index + mb_strlen($matchedValue, 'UTF-8'));
            return $this->fixCase($replacement['greeklish'], $matchedValue . $nextChar);
        }
    }

    private function getSafeChar($input, $index)
    {
        return (mb_strlen($input, 'UTF-8') > $index && $index >= 0) ? mb_substr($input, $index, 1, 'UTF-8') : '';
    }

    private function fixCase($text, $mirror)
    {
        if (isset($this->grCaps[mb_substr($mirror, 0, 1, 'UTF-8')])) {
            if (mb_strlen($mirror, 'UTF-8') == 1 || isset($this->grCaps[$this->getSafeChar($mirror, 1)])) {
                return mb_strtoupper($text, 'UTF-8');
            } else {
                return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, null, 'UTF-8');
            }
        } else {
            return $text;
        }
    }

    private function buildReplacementDictionary()
    {
        $replacements = [
            ['greek' => "αι", 'greeklish' => "ai"],
            ['greek' => "αί", 'greeklish' => "ai"],
            ['greek' => "οι", 'greeklish' => "oi"],
            ['greek' => "οί", 'greeklish' => "oi"],
            ['greek' => "ου", 'greeklish' => "ou"],
            ['greek' => "ού", 'greeklish' => "ou"],
            ['greek' => "ει", 'greeklish' => "ei"],
            ['greek' => "εί", 'greeklish' => "ei"],
            ['greek' => "αυ", 'fivi' => true],
            ['greek' => "αύ", 'fivi' => true],
            ['greek' => "ευ", 'fivi' => true],
            ['greek' => "εύ", 'fivi' => true],
            ['greek' => "ηυ", 'fivi' => true],
            ['greek' => "ηύ", 'fivi' => true],
            ['greek' => "ντ", 'greeklish' => "nt"],
            ['greek' => "μπ", 'bi' => true],
            ['greek' => "τσ", 'greeklish' => "ts"],
            ['greek' => "τς", 'greeklish' => "ts"],
            ['greek' => "ΤΣ", 'greeklish' => "ts"],
            ['greek' => "τζ", 'greeklish' => "tz"],
            ['greek' => "γγ", 'greeklish' => "ng"],
            ['greek' => "γκ", 'greeklish' => "gk"],
            ['greek' => "θ", 'greeklish' => "th"],
            ['greek' => "χ", 'greeklish' => "ch"],
            ['greek' => "ψ", 'greeklish' => "ps"],
            ['greek' => "γχ", 'greeklish' => "nch"],
            ['greek' => "γξ", 'greeklish' => "nx"]
        ];

        $dictionary = [];
        foreach ($replacements as $replacement) {
            $dictionary[$replacement['greek']] = $replacement;
        }

        $grLetters = "αάβγδεέζηήθιίϊΐκλμνξοόπρσςτυύϋΰφχψωώ";
        $engLetters = "aavgdeezii.iiiiklmnxooprsstyyyyf..oo";

        for ($i = 0; $i < mb_strlen($grLetters, 'UTF-8'); $i++) {
            $greekChar = mb_substr($grLetters, $i, 1, 'UTF-8');
            if (!isset($dictionary[$greekChar])) {
                $dictionary[$greekChar] = ['greek' => $greekChar, 'greeklish' => mb_substr($engLetters, $i, 1, 'UTF-8')];
            }
        }
        return $dictionary;
    }

    private function buildRegexPattern()
    {
        $patternBuilder = "";
        foreach (self::$replacementDictionary as $replacement) {
            $patternBuilder .= preg_quote($replacement['greek'], '/') . "|";
        }
        if (strlen($patternBuilder) > 0) {
            $patternBuilder = substr($patternBuilder, 0, -1);
        }
        return $patternBuilder;
    }

    private function stringToSet($s)
    {
        $set = [];
        for ($i = 0; $i < mb_strlen($s, 'UTF-8'); $i++) {
            $set[mb_substr($s, $i, 1, 'UTF-8')] = true;
        }
        return $set;
    }
}

$converter = new GreeklishConverter();
$greekText = $_GET['greektext'] ?? null;
if (is_null($greekText)) {
    header('HTTP/1.0 406 Not Acceptable');
}
else {
    $elot743Text = $converter->toGreeklish($greekText);
    if (!isset($_GET['json'])) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $elot743Text;
    }
    else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['greektext'=>$greekText, 'elot743text'=>$elot743Text], JSON_UNESCAPED_UNICODE);
    }
}
