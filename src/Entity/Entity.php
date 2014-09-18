<?php
namespace Eyf\Modelify\Entity;

abstract class Entity implements EntityInterface
{
    public function __construct(array $attrs = array())
    {
        $this->setFromArray($attrs);
    }

    abstract public function setFromArray(array $attrs);

    public static function getMetadata()
    {
        return array(
            'id'                    => 'id',
            'table_name'            => null,
            'table_name_strategy'   => 'camelToSnake'
        );
    }
}