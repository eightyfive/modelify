<?php
namespace Modelify\Entity;

abstract class LegacyEntity implements \ArrayAccess
{   
    protected static $defaults = array();

    protected $attributes = array();

    public function __construct(array $attrs = array())
    {
        $attrs = array_merge(static::$defaults, $attrs);
        $this->setFromArray($attrs);
    }

    public function setFromArray(array $attrs)
    {
        foreach ($attrs as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function newInstance($attrs = array())
    {
        return new static((array) $attrs);
    }


    /** protected */
    protected function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /** ArrayAccess */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->attributes[$offset] : NULL;
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->attributes[$offset]);
        }
    }

    /** static */
    public static function getMetadata()
    {
        return array(
            'id'                    => 'id',
            'table_name'            => null,
            'table_name_strategy'   => 'camelToSnake'
        );
    }
}