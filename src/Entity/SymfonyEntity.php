<?php
namespace Eyf\Modelify\Entity;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraint;

abstract class SymfonyEntity extends Entity implements SymfonyEntityInterface
{
    private $properties;

    public function setFromArray(array $attrs)
    {
        foreach ($attrs as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function toArray()
    {
        if (!isset($this->properties)) {
            $reflected = new \ReflectionClass($this);
            $this->properties = $reflected->getProperties($this->getPropertiesFilter());
        }

        $attributes = array();
        foreach ($this->properties as $property) {
            $property->setAccessible(true);
            $attributes[$property->getName()] = $property->getValue($this);
        }

        return $attributes;
    }

    protected function getPropertiesFilter()
    {
        return \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED;
    }
}