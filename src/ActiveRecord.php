<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;

use Eyf\Modelify\Entity\ArrayEntity;


abstract class ActiveRecord extends ArrayEntity
{
    protected static $db;
    protected static $repositories = array();

    protected $hasMany              = array();
    protected $hasAndBelongsToMany  = array();

    /**
      * Set the DBAL connection
      *
      * @param Connection $db
      */
    public static function setConnection(Connection $db)
    {
        self::$db = $db;
    }

    /**
      * Get self-related Repository
      */
    protected static function getRepository()
    {
      return self::getEntityRepository(get_called_class());
    }

    /**
      * Get entity-related Repository
      */
    protected static function getEntityRepository($className)
    {
      if (!isset(self::$repositories[$className])) {
        self::$repositories[$className] = new Repository(self::$db, $className);
      }

      return self::$repositories[$className];
    }


    /**
      * Save an entity in DB
      *
      * @return ActiveRecord The record.
      */
    public function save()
    {
      static::getRepository()->save($this);

      return $this;
    }

    /**
      * Delete an entity in DB
      */
    public function delete()
    {
      static::getRepository()->delete($this);
    }

    /**
      * Finds a record by its primary key / identifier.
      *
      * @param int $id The identifier.
      *
      * @return ActiveRecord The record.
      */
    public static function find($id)
    {
        return static::getRepository()->find($id);
    }

    /**
      * Finds all records in the table.
      *
      * @return array The records.
      */
    public static function findAll($orderBy = null, $limit = null, $offset = null)
    {
        return static::getRepository()->findAll($orderBy, $limit, $offset);
    }

    /**
      * Finds records by a set of criteria.
      *
      * @param mixed $criteria
      * @param array|null $orderBy
      * @param int|null $limit
      * @param int|null $offset
      *
      * @return array The records
      */
    public static function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        return static::getRepository()->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
      * Finds a single record by a set of criteria.
      *
      * @param mixed $criteria
      * @param array|null $orderBy
      *
      * @return ActiveRecord The record.
      */
    public static function findOneBy(array $criteria, $orderBy = null)
    {
        return static::getRepository()->findOneBy($criteria, $orderBy);
    }

    /**
      * Has-many relationship
      *
      * @param string $className The class name of the owner
      * @param string $orderBy order entities by specified column
      *
      * @return array
      */
    protected function hasMany($className, $orderBy = null)
    {
      if (!isset($this->hasMany[$className])) {
        $this->hasMany[$className] = $this->getEntityRepository($className)->findBy(array($this->getForeignKey() => $this->getId()), $orderBy);
      }

      return $this->hasMany[$className];
    }

    /**
      * Many-to-many relationship through a joining table
      *
      * @param string $className The class name of the owner
      * @return array
      */
    protected function hasAndBelongsToMany($className, $throughOrderBy = null)
    {
      if (!isset($this->hasAndBelongsToMany[$className])) {
        $this->hasAndBelongsToMany[$className] = $this->getEntityRepository($className)->manyToMany($this->getRepository(), $this->getId(), $throughOrderBy);
      }

      return $this->hasAndBelongsToMany[$className];
    }
    
    /**
     * @TODO
     *
     * http://guides.rubyonrails.org/association_basics.html:
     *
     *  - has_many :through
     *  - has_one :through
     */

    protected function belongsTo($className)
    {
      if (!isset($this->relationships[$className]))
      {
        $owner = new $className;
        $ownerId = $this[$owner->getForeignKey()];

        $this->relationships[$className] = call_user_func(array($className, 'find'), $ownerId);
      }

      return $this->relationships[$className];
    }


    protected function hasOne($className)
    {
      if (!isset($this->relationships[$className]))
      {
        $this->relationships[$className] = call_user_func(array($className, 'findOneBy'), array($this->getForeignKey() => $this->getId()));
      }

      return $this->relationships[$className];
    }

    /**
      * Returns the primary key of the table
      *
      * @return string
      */
    public function getPrimaryKey()
    {
      return static::getRepository()->getPrimaryKey();
    }

    /**
      * Returns the foreign key used in relationships
      *
      * @return string
      */
    public function getForeignKey()
    {
      return static::getRepository()->getForeignKey();
    }
}