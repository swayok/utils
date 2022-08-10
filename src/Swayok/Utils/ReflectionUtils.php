<?php

namespace Swayok\Utils;

use ReflectionClass;
use ReflectionMethod;

abstract class ReflectionUtils
{
    
    public static function getObjectPropertyValue($object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
    
    public static function callObjectMethod($object, string $methodName, ...$args)
    {
        return static::getMethodReflection($object, $methodName)
            ->invokeArgs($object, $args);
    }
    
    public static function getMethodReflection($object, string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}