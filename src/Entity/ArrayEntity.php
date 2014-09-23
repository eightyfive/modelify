<?php
namespace Eyf\Modelify\Entity;

abstract class ArrayEntity extends Entity
{
    public function setFromArray(array $attrs)
    {
        foreach ($attrs as $attr => $value) {
            $this->attributes[$attr] = $value;
        }
    }

    public function getId()
    {
        return $this->attributes[$this->getMetadata()['id']];
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public function __get($property)
    {
        if (isset($this->attributes[$property]) {
          return $this->attributes[$property];
        }
    }

    public function __set($property, $value)
    {
        if (isset($this->attributes[$property]) {
          $this->attributes[$property] = $value;
        }
    }

    public function __isset($property)
    {
        return array_key_exists($property, $this->attributes);
    }
}