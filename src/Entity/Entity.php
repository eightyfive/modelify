<?php
namespace Eyf\Modelify\Entity;

abstract class Entity implements EntityInterface, \JsonSerializable
{
    protected static $metadata;

    public function __construct(array $attrs = array())
    {
        $this->setAttributes($attrs);
    }

    abstract public function getId();
    abstract public function getAttributes();
    abstract public function setAttribute($key, $value);
    
    public function setAttributes(array $attrs)
    {
        foreach ($attrs as $key => $value) {

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
        if (!isset(static::$metadata)) {
            static::$metadata = static::getMetadataInstance();
        }

        return static::$metadata;
    }

    protected static function getMetadataInstance()
    {
        return new EntityMetadata(get_called_class());
    }
}