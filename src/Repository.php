<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;


use Eyf\Modelify\Entity\Entity;
use Eyf\Modelify\Behavior\Timestampable;

class Repository
{
    protected $db;

    protected $entityName;
    protected $entityMetadata;
    protected $entitySchema;

    protected $tableName;
    protected $tableDetails;
    protected $tableColumns;

    public function __construct(Connection $db, $entityName)
    {
        $this->db             = $db;
        $this->entityName     = $entityName;
        $this->entityMetadata = call_user_func(array($entityName, 'getMetadata'));

        $this->finder = new Finder($this->db, $this->getTableName(), $this->getPrimaryKey());
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
        return $this->getEntities($this->finder->findAll());
    }

    public function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        return $this->getEntities($this->finder->findBy($criteria, $orderBy, $limit, $offset));
    }

    public function rowCount(array $criteria = array())
    {
        return $this->finder->rowCount($criteria);
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

    public function save(Entity &$entity)
    {
      $isUpdate = $entity->getId() !== null;

      if ($entity instanceof Timestampable) {
        $now = new \DateTime();
        if ($isUpdate) {
            $entity->setUpdatedAt($now);
        } else {
            $entity->setCreatedAt($now);
            $entity->setUpdatedAt($now);
        }
      }

      $data = $entity->toArray();
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

      if (!$isUpdate) { // INSERT
        $entity->{$this->getPrimaryKey()} = $this->db->lastInsertId();
      }
    }

    protected function getPrimaryKey()
    {
        return $this->entityMetadata['id'];
    }

    protected function getTableName()
    {
        if (!isset($this->tableName)) {

            if (isset($this->entityMetadata['table_name']) && !empty($this->entityMetadata['table_name'])) {
                $this->tableName = $this->entityMetadata['table_name'];
            } else {
                $names = explode('\\', $this->entityName);
                $this->tableName = '`'.$this->camelToSnake(end($names)).'`';
            }
        }

        return $this->tableName;
    }

    protected function camelToSnake($camel)
    {
        $replace = '$1_$2';
 
        return ctype_lower($camel) ? $camel : strtolower(preg_replace('/(.)([A-Z])/', $replace, $camel));
    }

    protected function getTableDetails()
    {
        if (!isset($this->tableDetails)) {
            $this->tableDetails = $this->db->getSchemaManager()->listTableDetails($this->getTablename());
        }

        return $this->tableDetails;
    }

    protected function getTableColumns()
    {
        if (!isset($this->tableColumns)) {
            $columns = $this->getTableDetails()->getColumns();
            $this->tableColumns = array();

            foreach ($columns as $column) {
                $this->tableColumns[$column->getName()] = $column;
            }
        }

        return $this->tableColumns;
    }

    public function getEntitySchema()
    {
        if (!isset($this->entitySchema)) {

            $columns = $this->getTableColumns();
            $this->entitySchema = array();

            foreach ($columns as $column) {
                $this->entitySchema[$column->getName()] = $column->getType();
            }
        }

        return $this->entitySchema;
    }
}