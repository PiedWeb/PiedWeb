<?php

namespace PiedWeb\MethodDocBlockGenerator;

class MethodDocBlockGenerator
{
    public function run($extensionClassName)
    {
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
                    $paramString .= ' = '.str_replace(\chr(10), '', var_export($defaultValue, true));
                }

                $paramStrings[] = $paramString;
            }

            $returnType = $this->formatType($method->getReturnType());

            $phpDoc .= ' * @method '.('' === $returnType ? '' : $returnType.' ').$method->getName().'('.implode(', ', $paramStrings).')';
            $phpDoc .= ' // LINK '.$reflectionClass->getFileName().':'.$method->getStartLine();
            $phpDoc .= "\n";
        }

        return $phpDoc;
    }

    private function formatType(\ReflectionType $returnType): string
    {
        if (null === $returnType) {
            return '';
        }

        if ($returnType instanceof \ReflectionNamedType) {
            return false === $returnType?->isBuiltin() ?
                (($returnType->allowsNull() ? '?' : '').'\\'.$returnType->getName())
                : $returnType;
        }

        if ($returnType instanceof \ReflectionUnionType) {
            $toReturn = [];
            foreach ($returnType->getTypes() as $type) {
                $toReturn[] = $this->formatType($type);
            }

            return implode('|', $toReturn);
        }

        dd($returnType);

        throw new \Exception();
    }
}
