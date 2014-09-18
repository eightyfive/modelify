<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;

use Eyf\Modelify\Entity\LegacyEntity;


abstract class ActiveRecord extends LegacyEntity
{
    protected static $db;

    protected static $tableName;
    protected static $alias;
    protected static $primaryKey = 'id';
    protected static $foreignKey;
    protected static $aliasCounter = 'a';

    protected $relationships = array();
    protected $defaultTableName;
    protected $defaultAlias;
    protected $defaultForeignKey;

    
    public function getId()
    {
      return $this[$this->getPrimaryKey()];
    }

    
    public function isNew()
    {
      $id = $this->getId();
      return empty($id);
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
      * Many-to-many relationship through a joining table
      *
      * @param string $className The class name of the owner
      * @return array
      */
    protected function hasMany($className, $orderBy = null, $key = null)
    {
      if (!isset($this->relationships[$className]))
      {
        $this->relationships[$className] = call_user_func(array($className, 'findBy'), array($this->getForeignKey() => $this->getId()), $orderBy);
      }

      return $this->relationships[$className];
    }

    /**
      * Many-to-many relationship through a joining table
      *
      * @param string $className The class name of the owner
      * @return array
      */
    protected function hasAndBelongsToMany($className, $throughOrderBy = null)
    {
      if (!isset($this->relationships[$className]))
      {
        $this->relationships[$className] = call_user_func(array($className, 'manyToMany'), $this, $throughOrderBy);
      }

      return $this->relationships[$className];
    }

    /**
      * Returns the table name associated with the model
      *
      * @return string
      */
    public function getTableName()
    {
      if (isset(static::$tableName))
      {
          return static::$tableName;
      }
      else if (!isset($this->defaultTableName))
      {
        $this->defaultTableName = $this->classToTableName(get_called_class());
      }

      return $this->defaultTableName;
    }


    /**
      * Returns the table alias used by DBAL
      *
      * @return string
      */
    public function getAlias()
    {
      if (isset(static::$alias))
      {
          return static::$alias;
      }
      else if (!isset($this->defaultAlias))
      {
        $this->defaultAlias = $this->getUniqueAlias();
      }

      return $this->defaultAlias;
    }

    /**
      * Returns the primary key of the table
      *
      * @return string
      */
    public function getPrimaryKey()
    {
      return static::$primaryKey;
    }

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
      * Set the DBAL connection
      *
      * @param Connection $db
      */
    public static function setConnection(Connection $db)
    {
        self::$db = $db;
    }

    /**
      * @return Connection $db
      */
    public function getDb()
    {
        return self::$db;
    }

    /**
      * Generate a unique alias
      *
      * @return string
      */
    public static function getUniqueAlias()
    {
      return '`'.self::$aliasCounter++.'`';
    }

    /**
      * Finds a record by its primary key / identifier.
      *
      * @param int $id The identifier.
      * @return ActiveRecord The record.
      */
    public static function find($id)
    {
      $instance = new static;
      $pKey = $instance->getPrimaryKey();

      if (is_array($id))
        return $instance->newQuery()->where($pKey, 'in', $id)->fetchAll();

      return $instance->newQuery()->where($pKey, 'eq', $id)->fetchOne();
    }

    /**
      * Finds a single record by a set of criteria.
      *
      * @param mixed $criteria
      * @param array|null $orderBy
      * @return ActiveRecord The record.
      */
    public static function findOneBy($criteria, array $orderBy = null)
    {
      $instance = new static;

      return $instance->newQuery()->where($criteria)->orderBy($orderBy)->fetchOne();
    }

    /**
      * Finds all records in the table.
      *
      * @return array The records.
      */
    public static function findAll()
    {
        return static::findBy(array());
    }

    /**
      * Finds records by a set of criteria.
      *
      * @param mixed $criteria
      * @param array|null $orderBy
      * @param int|null $limit
      * @param int|null $offset
      * @return array The records
      */
    public static function findBy($criteria, $orderBy = null, $limit = null, $offset = null)
    {
      $instance = new static;

      return $instance->newQuery()->where($criteria)->orderBy($orderBy)->fetchAll();
    }

    /**
      * Finds records through a joining table with $owner.
      *
      * @param ActiveRecord $owner
      * @return array The records
      */
    protected static function manyToMany(ActiveRecord $owner, $throughOrderBy = null)
    {
      $instance = new static;

      $table  = $instance->getTableName();
      $alias  = $instance->getAlias();
      $pKey   = $instance->getPrimaryKey();
      $fKey   = $instance->getForeignKey();

      
      $joinTable = $instance->getJoiningTableName($instance->getTableName(), $owner->getTableName());
      $joinAlias = $instance->getUniqueAlias();

      $joinCondition = sprintf('%s.%s = %s.%s',
        $joinAlias,
        $fKey,
        $alias,
        $pKey
      );

      $query = $instance->newQuery(false)
        ->select($alias.'.*')
        ->from($joinTable, $joinAlias)
        ->innerJoin($joinAlias, $table, $alias, $joinCondition)
        ->where(sprintf('%s.%s', $joinAlias, $owner->getForeignKey()), 'eq', $owner->getId());

      // ORDER BY
      if ($throughOrderBy)
      {
        list($sort, $dir) = $query->parseOrderBy($throughOrderBy);
        $sort = $joinAlias.'.'.$sort;
        
        $query->orderBy($sort, $dir);
      }

      return $query->fetchAll();
    }

    /**
      * Builds a new query for this ActiveRecord according to criteria
      *
      * @param array $criteria
      * @param array $orderBy
      * @param int $limit
      * @param int $offset
      * @return QueryBuilder The query
      */
    public function newQuery($includeFrom = true)
    {
      $query = new ModelQuery(self::$db, $this, $includeFrom);

      return $query;
    }

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

    public function save()
    {
      $types = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
      ];

      $data = $this->getAttributes();

      $now = new \DateTime();

      $qb = $this->getDb()->createQueryBuilder();

      $isUpdate = !$this->isNew();

      if ($isUpdate) {

        // UPDATE
        $qb->update($this->getTableName());

        foreach ($data as $key=>$val) {

          if ($key === 'created_at') {
            continue;
          }
          
          $qb->set($key,  ":{$key}");
        }

        if (isset($data['updated_at'])) {
          $data['updated_at'] = $now;
        }

        $qb->where($this->getPrimaryKey().' = :'.$this->getPrimaryKey());

      } else {

        // INSERT
        unset($data[$this->getPrimaryKey()]);

        $qb->insert($this->getTableName());

        foreach ($data as $key=>$val) {
            $qb->setValue($key, ":{$key}");
        }

        if (isset($data['created_at'])) {
          $data['created_at'] = $now;
        }

        if (isset($data['updated_at'])) {
          $data['updated_at'] = $now;
        }
      }

      $qb->setParameters($data, $types);

      // ll($qb->getSQL());
      // dd($qb->getParameters());

      $qb->execute();

      if (!$isUpdate) { // INSERT
          $this->attributes[$this->getPrimaryKey()] = $this->getDb()->lastInsertId();
      }

      return $this;
    }
}