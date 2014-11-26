<?php
namespace Eyf\Modelify\Entity;

abstract class ArrayEntity extends Entity
{
    protected static $schema = array();

    protected $attributes = array();

    public function setAttribute($key, $value)
    {
        if (count(static::$schema) && !in_array($key, static::$schema)) {
            throw new RuntimeException('Unknown attribute: '.$key);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute($key)
    {
        if (count(static::$schema) && !in_array($key, static::$schema)) {
            throw new RuntimeException('Unknown attribute: '.$key);
        }

        if (!isset($this->attributes[$key])) {
            return null;
        }

        return $this->attributes[$key];
    }

    public function getId()
    {
        $pKey = $this->getMetadata()->getPrimaryKey();

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
        return $this->getAttribute($property);
    }

    public function __set($property, $value)
    {
        $this->setAttribute($property, $value);
    }

    public function __isset($property)
    {
        return array_key_exists($property, $this->attributes);
    }

    public function __unset($property)
    {
        unset($this->attributes[$property]);
    }
}