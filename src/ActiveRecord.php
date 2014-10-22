<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;

use Eyf\Modelify\Entity\ArrayEntity;


abstract class ActiveRecord extends ArrayEntity
{
    protected static $db;
    protected static $repository;

    protected $repositories = array();

    protected static $tableName;
    protected static $alias;
    protected static $primaryKey = 'id';
    protected static $foreignKey;
    protected static $aliasCounter = 'a';


    protected $hasMany              = array();
    protected $hasAndBelongsToMany  = array();

    protected $defaultTableName;
    protected $defaultAlias;
    protected $defaultForeignKey;

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
      if (!isset(static::$repository)) {
        static::$repository = new Repository(self::$db, get_called_class());
      }

      return static::$repository;
    }

    /**
      * Get entity-related Repository
      */
    protected function getEntityRepository($className)
    {
      if (!isset($this->repositories[$className])) {
        $this->repositories[$className] = new Repository(self::$db, $className);
      }

      return $this->repositories[$className];
    }


    /**
      * Save an entity in DB
      *
      * @return ActiveRecord The record.
      */
    public function save()
    {
      self::getRepository()->save($this);

      return $this;
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
        return self::getRepository()->find($id);
    }

    /**
      * Finds all records in the table.
      *
      * @return array The records.
      */
    public static function findAll()
    {
        return self::getRepository()->findAll();
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
        return self::getRepository()->findBy($criteria, $orderBy, $limit, $offset);
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
        return self::getRepository()->findOneBy($criteria, $orderBy);
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
        $this->hasMany[$className] = $this->getEntityRepository($className)->findBy(array(array($this->getForeignKey(), 'eq', $this->getId())), $orderBy);
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
      * Returns the table name associated with the model
      *
      * @return string
      */
    // public function getTableName()
    // {
    //   if (isset(static::$tableName))
    //   {
    //       return static::$tableName;
    //   }
    //   else if (!isset($this->defaultTableName))
    //   {
    //     $this->defaultTableName = $this->classToTableName(get_called_class());
    //   }

    //   return $this->defaultTableName;
    // }


    /**
      * Returns the table alias used by DBAL
      *
      * @return string
      */
    // public function getAlias()
    // {
    //   if (isset(static::$alias))
    //   {
    //       return static::$alias;
    //   }
    //   else if (!isset($this->defaultAlias))
    //   {
    //     $this->defaultAlias = $this->getUniqueAlias();
    //   }

    //   return $this->defaultAlias;
    // }

    /**
      * Returns the primary key of the table
      *
      * @return string
      */
    // public function getPrimaryKey()
    // {
    //   return static::$primaryKey;
    // }

    /**
      * Returns the foreign key used in relationships
      *
      * @return string
      */
    public function getForeignKey()
    {
      if (isset(static::$foreignKey))
      {
          return static::$foreignKey;
      }
      else if (!isset($this->defaultForeignKey))
      {
        $this->defaultForeignKey = $this->getTableName().'_id';
      }

      return $this->defaultForeignKey;
    }


    /**
      * Generate a unique alias
      *
      * @return string
      */
    // public static function getUniqueAlias()
    // {
    //   return '`'.self::$aliasCounter++.'`';
    // }

    /**
      * Builds a new query for this ActiveRecord according to criteria
      *
      * @param array $criteria
      * @param array $orderBy
      * @param int $limit
      * @param int $offset
      * @return QueryBuilder The query
      */
    // public function newQuery($includeFrom = true)
    // {
    //   $query = new ModelQuery(self::$db, $this, $includeFrom);

    //   return $query;
    // }

    /**
      * Builds the table name of the joining table in a "many-to-many :through" relationship
      *
      * @param string $tableName1
      * @param string $tableName2
      * @return string
      */
    public function getJoiningTableName($tableName1, $tableName2)
    {
      $tables = array($tableName1, $tableName2);
      sort($tables);

      // @todo
      // HUGE caveat, have to figure out how to generate this table name
      // WITHOUT being tight to `underscore` strategy
      return implode('_', $tables);
    }

    /**
      * Converts a class name to a table name
      *
      * @param string $className
      * @return string
      */
    public function classToTableName($className)
    {
      $classPath = explode('\\', $className);
      $className = end($classPath);

      return $this->underscore($className);
    }

    /**
      * Transforms a camelCase string into an underscored string
      *
      * @param string $string
      * @return string
      */
    private function underscore($string)
    {
      $string = preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $string);

      return strtolower($string);
    }
}