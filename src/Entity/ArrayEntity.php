<?php
namespace Eyf\Modelify\Entity;

abstract class ArrayEntity extends Entity
{
    protected $attributes = array();

    public function setFromArray(array $attrs)
    {
        foreach ($attrs as $attr => $value) {
            $this->attributes[$attr] = $value;
        }
    }

    public function getId()
    {
        $pKey = $this->getMetadata()['primary_key'];

        if (!isset($this->attributes[$pKey])) {
            return null;
        }

        return $this->attributes[$pKey];
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public function __get($property)
    {
        if (isset($this->attributes[$property])) {
          return $this->attributes[$property];
        }
    }

    public function __set($property, $value)
    {
        $this->attributes[$property] = $value;
    }

    public function __isset($property)
    {
        return array_key_exists($property, $this->attributes);
    }
}