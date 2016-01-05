<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

use Eyf\Modelify\Entity\Entity;
use Eyf\Modelify\Behavior\Timestampable;
use Eyf\Modelify\Behavior\Translatable;

class RepositoryTranslatable extends Repository
{
    public static $defaultLocale = 'en';

    protected $locale;
    protected $translatables;

    protected $transSelect;
    protected $transAlias;
    protected $transTable;
    protected $transJoin;


    public function __construct(Connection $db, $entityClassName, $pattern = '%s_t')
    {
        parent::__construct($db, $entityClassName);

        $alias = $this->getNewAlias();

        $this->translatables = call_user_func(array($entityClassName, 'getTranslatables'));

        $this->transSelect = array_merge(array($this->getAlias().'.*'), array_map(function($attr) use ($alias) {
            return $alias.'.'.$attr;
        }, $this->translatables));

        $this->transTable = sprintf($pattern, $this->getTableName(false));
        $this->transAlias = $alias;
        $this->transJoin  = sprintf('%s.%s = %s.%s',
            $this->transAlias,
            $this->getForeignKey(),
            $this->getAlias(),
            $this->getPrimaryKey()
        );
    }

    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    public function getLocale()
    {
        return isset($this->locale) ? $this->locale : static::$defaultLocale;
    }

    public function createQueryBuilder()
    {
        return $this->db->createQueryBuilder()
            ->select($this->transSelect)
            ->from($this->getTableName(), $this->getAlias())
            ->join($this->getAlias(), $this->transTable, $this->transAlias, $this->transJoin)
            ->where($this->transAlias.'.locale = ?')
            ->setParameter(0, $this->getLocale());
    }

    public function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        return parent::findBy($this->addTransAlias($criteria), $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria, $orderBy = null)
    {
        return parent::findOneBy($this->addTransAlias($criteria), $orderBy);
    }

    public function save(Entity &$entity)
    {
        $isUpdate = $entity->getId() !== null;
        $isCreate = !$isUpdate;

        // Save Translations params
        $tParams = array();
        foreach ($this->translatables as $attr) {
            $tParams[$attr] = $entity->{$attr};
            unset($entity->{$attr});
        }

        parent::save($entity);

        // Update Translations table
        $qb = $this->db->createQueryBuilder();

        if ($isCreate) {
            $qb->insert($this->transTable);
            foreach (array_keys($tParams) as $attr) {
                $qb->setValue($attr,  ":{$attr}");
            }
            $qb->setValue($this->getForeignKey(),  ":{$this->getForeignKey()}");
            $qb->setValue('locale', ":locale");
        } else {
            $qb->update($this->transTable);
            foreach (array_keys($tParams) as $attr) {
                $qb->set($attr,  ":{$attr}");
            }
            $qb->where($this->getForeignKey().' = :'.$this->getForeignKey());
            $qb->andWhere('locale = :locale');
        }

        $tParams[$this->getForeignKey()] = $entity->getId();
        $tParams['locale'] = $this->getLocale();

        $qb->setParameters($tParams);
        $qb->execute();
    }

    public function delete(Entity $entity)
    {
        $id = $entity->getId();
        parent::delete($entity);

        $this->db->delete($this->transTable, array($this->getForeignKey() => $id));
    }

    protected function addTransAlias($criteria)
    {
        $criteriaAliased = array();

        foreach ($criteria as $attr=>$criterion) {

            if (strpos($attr, '.') !== false) {
                continue;
            }

            if (!in_array($attr, $this->translatables)) {
                continue;
            }

            // This criterion is part of the Translations table
            unset($criteria[$attr]);

            $aliased = $this->transAlias.'.'.$attr;
            $criteriaAliased[$aliased] = $criterion;
        }

        return array_merge($criteria, $criteriaAliased);
    }
}