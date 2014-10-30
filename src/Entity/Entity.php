<?php
namespace Eyf\Modelify\Entity;

abstract class Entity implements EntityInterface, \JsonSerializable
{
    public function __construct(array $data = array())
    {
        foreach ($data as $key => $value) {

            $attrType = $this->getAttributeType($key);
            if ($attrType) {

                if ($attrType === 'json') {
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }
                } else {
                    settype($value, $attrType);
                }
            }

            if (is_array($value)) {
                $this->setArrayAttribute($key, $value);
            } else {
                $this->setAttribute($key, $value);
            }
        }
    }

    abstract public function getId();
    abstract public function setAttribute($key, $value);
    abstract public function getAttributes();

    protected function setArrayAttribute($key, array $data)
    {
        // ..
    }

    public function toArray()
    {
        return $this->getAttributes();
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    protected static $attributesType = array();
    protected static function getAttributeType($key)
    {
        return isset(static::$attributesType[$key]) ? static::$attributesType[$key] : null;
    }

    public static function getMetadata()
    {
        return array(
            'primary_key'           => 'id',
            'foreign_key'           => '%s_id',
            'table_name'            => null,
            'table_name_strategy'   => 'camelToSnake'
        );
    }
}