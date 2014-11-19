<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;

use Eyf\Modelify\Entity\Entity;
use Eyf\Modelify\Behavior\Timestampable;

class Repository
{
    protected $db;

    protected $entityClassName;
    protected $metadata;

    protected $alias;
    protected static $aliasCounter = 'a';

    public function __construct(Connection $db, $entityClassName)
    {
        $this->db              = $db;
        $this->metadata        = call_user_func(array($entityClassName, 'getMetadata'));
        $this->entityClassName = $entityClassName;

        $this->finder = new Finder($this);
    }

    public function getEntityClassName()
    {
        return $this->entityClassName;
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

    public function findIn(array $ids)
    {
        return $this->getEntities($this->finder->findIn($ids));
    }

    public function findAll($orderBy = null, $limit = null, $offset = null)
    {
        return $this->getEntities($this->finder->findBy(array(), $orderBy, $limit, $offset));
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
        
        return new $this->entityClassName($row);
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
        return $this->metadata->getPrimaryKey();
    }

    public function getForeignKey()
    {
        return $this->metadata->getForeignKey();
    }

    public function getTableName($escape = true)
    {
        $table = $this->metadata->getTableName();

        return $escape ? '`'.$table.'`' : $table;
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