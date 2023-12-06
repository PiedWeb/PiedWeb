<?php

namespace PiedWeb\MethodDocBlockGenerator;

/**
 * @see \PiedWeb\MethodDocBlockGenerator\Test\MethodDocBlockGeneratorTest
 */
class MethodDocBlockGenerator
{
    public function __construct(private readonly bool $addLink = true)
    {
    }

    public function run(string $extensionClassName): string
    {
        if (! class_exists($extensionClassName)) {
            throw new \Exception();
        }

        $reflectionClass = new \ReflectionClass($extensionClassName);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC);
        $phpDoc = '';

        foreach ($methods as $method) {
            if (\in_array($method->getName(), ['__construct', 'register'])) {
                continue;
            }

            $paramStrings = [];

            foreach ($method->getParameters() as $parameter) {
                $paramString = $this->formatType($parameter->getType()).' $'.$parameter->getName();

                if ($parameter->isDefaultValueAvailable()) {
                    $defaultValue = $parameter->getDefaultValue();
                    $paramString .= ' = '.str_replace("\n", '', var_export($defaultValue, true));
                }

                $paramStrings[] = $paramString;
            }

            $returnType = $this->formatType($method->getReturnType());

            $phpDoc .= ' * @method '.('' === $returnType ? '' : $returnType.' ').$method->getName().'('.implode(', ', $paramStrings).')';
            if ($this->addLink) {
                $phpDoc .= "\n".' * '
                    .ltrim(str_replace($this->getDir(), '', $reflectionClass->getFileName() ?: ''), '/')
                    .':'.$method->getStartLine()
                    ."\n".' * ';
            }

            $phpDoc .= "\n";
        }

        return $phpDoc;
    }

    private function getDir(): string
    {
        /** @var string */
        $dir = \Safe\preg_replace('#/vendor/.+$#', '', __DIR__);

        return $dir;
    }

    private function formatType(\ReflectionType|null $returnType): string
    {
        if (null === $returnType) {
            return '';
        }

        if ($returnType instanceof \ReflectionNamedType) {
            return false === $returnType->isBuiltin() ?
                (($returnType->allowsNull() ? '?' : '').'\\'.$returnType->getName())
                : $returnType->getName();
        }

        if ($returnType instanceof \ReflectionUnionType) {
            $toReturn = [];
            foreach ($returnType->getTypes() as $type) {
                $toReturn[] = $this->formatType($type);
            }

            return implode('|', $toReturn);
        }

        throw new \Exception();
    }
}
