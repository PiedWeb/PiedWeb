<?php

namespace PiedWeb\Rison;

class RisonDecoder extends Rison
{
    protected int $length;

    protected int $index = 0;

    protected bool $eof = false;

    protected string $whitespace = '';

    protected string $idRegex;

    /**
     * @var array<callable>
     */
    protected array $tokens = [];

    /**
     * @var array<string, callable|bool|null>
     */
    protected array $bangs = [];

    public function __construct(protected string $rison)
    {
        $this->length = \strlen((string) $this->rison);

        if (0 === $this->length) {
            throw new \InvalidArgumentException('Empty string');
        }

        $this->init();
    }

    protected function init(): void
    {
        $this->tokens = [
            '!' => $this->parseBang(...),
            '(' => $this->parseObject(...),
            "'" => $this->parseStringLiteral(...),
            '-' => $this->parseNumber(...),
        ];
        $this->tokens += array_fill_keys(range(0, 9), $this->tokens['-']);

        $this->bangs = [
            't' => true,
            'f' => false,
            'n' => null,
            '(' => $this->parseArray(...),
        ];

        $this->idRegex = \sprintf('/[^%s%s][^%s]*/', $this->notIdstart, $this->notIdchar, $this->notIdchar);
    }

    public function decode(): mixed
    {
        $value = $this->parseValue();
        if (false !== $this->next()) {
            $this->parseError('Invalid syntax');
        }

        return $value;
    }

    protected function parseValue(): mixed
    {
        $c = $this->next();
        if (false === $c) {
            $this->parseError('Unexpected end');
        }

        if (isset($this->tokens[$c])) {
            return \call_user_func($this->tokens[$c]);
        }

        $i = $this->index - 1;
        if (! preg_match($this->idRegex, (string) $this->rison, $matches, 0, $i)) {
            $this->parseError(\sprintf("Invalid character '%s'", $c));
        }

        $this->index = $i + \strlen($matches[0]);

        return $matches[0];
    }

    protected function parseBang(): mixed
    {
        $c = $this->next();
        if (false === $c) {
            $this->parseError('! at end of string');
        }

        if (! \array_key_exists($c, $this->bangs)) {
            $this->parseError(\sprintf("Invalid bang '!%s'", $c));
        }

        $value = $this->bangs[$c];

        return $value instanceof \Closure ? $value() : $value;
    }

    /**
     * @return false|array<mixed>
     */
    protected function parseObject(): false|array
    {
        $obj = [];

        while (($c = $this->next()) !== ')') {
            if ([] !== $obj) {
                if (',' !== $c) {
                    $this->parseError('Missing ","');
                }
            } elseif (',' === $c) {
                $this->parseError('Extraneous ","');
            } else {
                --$this->index;
            }

            $key = $this->parseValue();
            if (! $key && $this->eof) {
                return false;
            }

            if (':' !== $this->next()) {
                $this->parseError('Missing ":"');
            }

            $value = $this->parseValue();
            if (! $value && $this->eof) {
                $this->parseError('Unexpected end of string');
            }

            \assert(\is_string($key) || \is_int($key));
            $obj[$key] = $value;
        }

        return $obj;
    }

    /**
     * @return mixed[]
     */
    protected function parseArray(): array
    {
        $array = [];

        while (($c = $this->next()) !== ')') {
            if (false === $c) {
                $this->parseError('Unmatched !(');
            }

            if ([] !== $array) {
                if (',' !== $c) {
                    $this->parseError('Missing ","');
                }
            } elseif (',' === $c) {
                $this->parseError('Extraneous ","');
            } else {
                --$this->index;
            }

            $value = $this->parseValue();
            if (! $value && $this->eof) {
                $this->parseError('Unexpected end of string');
            }

            $array[] = $value;
        }

        return $array;
    }

    protected function parseStringLiteral(): ?string
    {
        $string = null;

        while (($c = $this->next()) !== "'") {
            if (false === $c) {
                $this->parseError('Unmatched "\'"');
            }

            if ('!' === $c) {
                $c = $this->next();
                if (! str_contains("!'", (string) $c)) {
                    $this->parseError(\sprintf("Invalid string escape '!%s'", $c));
                }
            }

            $string .= $c;
        }

        return $string;
    }

    protected function parseNumber(): int|float
    {
        $i = $this->index;
        $start = $i - 1;
        $state = 'int';
        $permittedSigns = '-';

        static $transitions = [
            'int+.' => 'frac',
            'int+e' => 'exp',
            'frac+e' => 'exp',
        ];

        do {
            $c = substr((string) $this->rison, $i++, 1);

            if ('' === $c) {
                break;
            }

            if (ctype_digit($c)) {
                continue;
            }

            if ('' !== $permittedSigns && str_contains($permittedSigns, $c)) {
                $permittedSigns = '';

                continue;
            }

            $state = $state.'+'.strtolower($c); // @phpstan-ignore-line
            $state = $transitions[$state] ?? false; // @phpstan-ignore-line
            if ('exp' === $state) {
                $permittedSigns = '-';
            }
        } while ($state);

        $this->index = --$i;

        $number = substr((string) $this->rison, $start, $i - $start);
        if ('-' === $number) {
            $this->parseError('Invalid number "-"');
        }

        if (! is_numeric($number)) {
            $this->parseError(\sprintf("Invalid number '%s'", $number));
        }

        if (preg_match('/^-?\d+$/', $number)) {
            return (int) $number;
        }

        return (float) $number;
    }

    protected function next(): false|string
    {
        do {
            if ($this->index >= $this->length) {
                $this->eof = true;

                return false;
            }

            $c = $this->rison[$this->index++];
        } while (str_contains((string) $this->whitespace, (string) $c));

        return $c;
    }

    protected function parseError(string $message): never
    {
        throw new RisonParseErrorException($this->rison, $message);
    }
}
