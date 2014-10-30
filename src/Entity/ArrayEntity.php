<?php
namespace Eyf\Modelify\Entity;

abstract class ArrayEntity extends Entity
{
    protected $attributes = array();

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getId()
    {
        $pKey = $this->getMetadata()['primary_key'];

        if (!isset($this->attributes[$pKey])) {
            return null;
        }

        return $this->attributes[$pKey];
    }

    public function getAttributes()
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