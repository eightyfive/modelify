<?php
namespace Eyf\Modelify\Entity;

class EntityMetadata
{
    protected $entityClassName;
    
    protected $tableName;
    protected $primaryKey = 'id';
    protected $foreignKey;

    public function __construct($entityClassName)
    {
        $this->entityClassName = $entityClassName;
    }

    public function getEntityClassName()
    {
        return $this->entityClassName;
    }

    public function setTableName($table)
    {
        $this->tableName = $table;
    }

    public function getTableName()
    {
        if (!isset($this->tableName)) {

            $names = explode('\\', $this->entityClassName);
            $this->tableName = $this->camelCaseTo_snake_case(end($names));
        }

        return $this->tableName;
    }

    public function setPrimaryKey($key)
    {
        $this->primaryKey = $key;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function setForeignKey($key)
    {
        $this->foreignKey = $key;
    }

    public function getForeignKey()
    {
        if (!isset($this->foreignKey)) {
            $this->foreignKey = sprintf('%s_id', $this->getTableName());
        }

        return $this->foreignKey;
    }

    protected function camelCaseTo_snake_case($camel)
    {
        return ctype_lower($camel) ? $camel : strtolower(preg_replace('/(.)([A-Z])/', '$1_$2', $camel));
    }
}