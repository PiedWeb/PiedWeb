<?php

namespace PiedWeb\Rison;

require_once __DIR__.\DIRECTORY_SEPARATOR.'Rison.php';

class RisonEncoder extends Rison
{
    /**
     * @var array<string, callable>
     */
    protected array $encoders = [];

    private string $idOkRegex;

    public function __construct(protected mixed $value)
    {
        $this->init();
    }

    protected function init(): void
    {
        $this->encoders = [
            'boolean' => $this->encodeBoolean(...),
            'integer' => $this->encodeInteger(...),
            'double' => $this->encodeDouble(...),
            'string' => $this->encodeString(...),
            'array' => $this->encodeArray(...),
            'object' => $this->encodeObject(...),
            'resource' => $this->encodeResource(...),
            'null' => $this->encodeNull(...),
        ];

        $this->idOkRegex = \sprintf('/^[^%s%s][^%s]*$/', $this->notIdstart, $this->notIdchar, $this->notIdchar);
    }

    public function encode(): string
    {
        return $this->encodeValue($this->value);
    }

    protected function encodeValue(mixed $value): string
    {
        $type = strtolower(\gettype($value));
        if (! isset($this->encoders[$type])) {
            throw new \InvalidArgumentException('Cannot encode value of type '.$type);
        }

        return \call_user_func($this->encoders[$type], $value); // @phpstan-ignore-line
    }

    protected function encodeBoolean(bool $boolean): string
    {
        return $boolean ? '!t' : '!f';
    }

    protected function encodeNull(): string
    {
        return '!n';
    }

    protected function encodeInteger(int $integer): int
    {
        return $integer;
    }

    protected function encodeDouble(float $double): string
    {
        $s = strtolower(str_replace('+', '', (string) $double));

        return str_replace('.0e', 'e', $s);
    }

    protected function encodeResource(string $resource): never
    {
        throw new \InvalidArgumentException('Cannot encode resource '.$resource);
    }

    protected function encodeString(string $string): string
    {
        if ('' === $string) {
            return "''";
        }

        if (preg_match($this->idOkRegex, (string) $string)) {
            return $string;
        }

        $string = preg_replace("/['!]/", '!$0', (string) $string);

        return \sprintf("'%s'", $string);
    }

    /**
     * @param array<mixed> $array
     */
    protected function encodeArray(array $array): string
    {
        $keys = array_keys($array);
        $isArray = range(0, \count($keys) - 1) === $keys;
        if (! $isArray) {
            return $this->encodeObject($array);
        }

        return '!('.implode(',', array_map($this->encodeValue(...), $array)).')';
    }

    /**
     * @param object|array<mixed> $object
     */
    protected function encodeObject(object|array $object): string
    {
        $object = (array) $object;
        ksort($object);
        $encoded = [];

        foreach ($object as $key => $value) {
            $encoded[] = $this->encodeValue($key).':'.$this->encodeValue($value);
        }

        return '('.implode(',', $encoded).')';
    }
}
