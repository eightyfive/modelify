<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;

use Eyf\Modelify\Entity\Entity;
use Eyf\Modelify\Behavior\Timestampable;

class Repository
{
    protected $db;

    protected $entityName;
    protected $entityMetadata;

    protected $tableName;
    protected $alias;
    protected static $aliasCounter = 'a';

    public function __construct(Connection $db, $entityName)
    {
        $this->db             = $db;
        $this->entityName     = $entityName;
        $this->entityMetadata = call_user_func(array($entityName, 'getMetadata'));

        $this->finder = new Finder($this);
    }

    public function getEntityClassName()
    {
        return $this->entityName;
    }

    public function createQueryBuilder()
    {
        return $this->db->createQueryBuilder()
            ->select($this->getAlias().'.*')
            ->from($this->getTableName(), $this->getAlias());
    }

    public function find($id)
    {
        if (is_array($id)) {
            return $this->findIn($id);
        }

        return $this->getEntity($this->finder->find($id));
    }

    public function findIn($ids)
    {
        return $this->getEntities($this->finder->findIn($ids));
    }

    public function findAll()
    {
        return $this->getEntities($this->finder->findBy(array()));
    }

    public function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        return $this->getEntities($this->finder->findBy($criteria, $orderBy, $limit, $offset));
    }

    public function paginate($page, $perPage, array $criteria = array(), $orderBy = null)
    {
        $offset = ($page-1) * $perPage;

        return $this->getEntities($this->finder->findBy($criteria, $orderBy, $perPage, $offset));
    }

    public function findOneBy(array $criteria, $orderBy = null)
    {
        return $this->getEntity($this->finder->findOneBy($criteria, $orderBy));
    }

    public function manyToMany(Repository $owners, $ownerId, $throughOrderBy = null)
    {
        return $this->getEntities($this->finder->manyToMany($owners, $ownerId, $throughOrderBy));
    }

    public function getEntity($row)
    {
        if (!$row) {
            return null;
        }
        
        return new $this->entityName($row);
    }

    public function getEntities(array $rows)
    {
        if (count($rows) === 0) {
            return array();
        }

        $entities = array();
        foreach ($rows as $row) {
            array_push($entities, $this->getEntity($row));
        }

        return $entities;
    }

    protected function beforeSave($isUpdate, Entity &$entity)
    {
        // ...
    }

    public function save(Entity &$entity)
    {
      $isUpdate = $entity->getId() !== null;

      $this->beforeSave($isUpdate, $entity);

      if ($entity instanceof Timestampable) {
        $now = new \DateTime();
        if ($isUpdate) {
            $entity->setUpdatedAt($now);
        } else {
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
        }
      }

      $data = $entity->getAttributes();
      $qb = $this->db->createQueryBuilder();

      if ($isUpdate) {

        // UPDATE
        $qb->update($this->getTableName());

        foreach ($data as $key => $value) {
          $qb->set($key,  ":{$key}");
        }

        $qb->where($this->getPrimaryKey().' = :'.$this->getPrimaryKey());

      } else {

        // INSERT
        $qb->insert($this->getTableName());

        foreach ($data as $key => $value) {
            $qb->setValue($key, ":{$key}");
        }
      }

      $qb->setParameters($data);

      // ll($qb->getSQL());
      // dd($qb->getParameters());

      $qb->execute();

      if (!$isUpdate) {
        $entity->{$this->getPrimaryKey()} = intval($this->db->lastInsertId());
      }
    }

    public function delete(Entity $entity)
    {
        $this->db->delete($this->getTableName(), array($this->getPrimaryKey() => $entity->getId()));
    }

    public function getPrimaryKey()
    {
        return $this->entityMetadata['primary_key'];
    }

    public function getForeignKey()
    {
        return sprintf($this->entityMetadata['foreign_key'], $this->getTableName(false));
    }

    public function getTableName($escape = true)
    {
        if (!isset($this->tableName)) {

            if (isset($this->entityMetadata['table_name']) && !empty($this->entityMetadata['table_name'])) {
                $this->tableName = trim($this->entityMetadata['table_name'], '`');
            } else {
                $names = explode('\\', $this->entityName);
                $this->tableName = $this->camelToSnake(end($names));
            }
        }

        return $escape ? '`'.$this->tableName.'`' : $this->tableName;
    }

    protected function camelToSnake($camel)
    {
        $replace = '$1_$2';
 
        return ctype_lower($camel) ? $camel : strtolower(preg_replace('/(.)([A-Z])/', $replace, $camel));
    }

    public function getAlias()
    {
        if (!isset($this->alias)) {
            $this->alias = $this->getNewAlias();
        }

        return $this->alias;
    }

    public function getNewAlias($escape = true)
    {
        $alias = self::$aliasCounter;
        self::$aliasCounter++;

        return $escape ? '`'.$alias.'`' : $alias;
    }
}