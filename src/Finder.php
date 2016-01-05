<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Query\QueryBuilder;

class Finder
{
    protected $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
    }

    public function find($id)
    {
        return $this->findOneBy(array($this->repo->getPrimaryKey() => array('eq', intval($id))));
    }

    public function findIn($ids)
    {
        return $this->findBy(array($this->repo->getPrimaryKey() => array('in', $ids)));
    }

    public function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->repo->createQueryBuilder();

        if (count($criteria)) {
            $this->addWhere($qb, $criteria);
        }

        if ($orderBy) {
            $this->addOrderBy($qb, $orderBy);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        if ($offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->execute()->fetchAll();
    }

    public function findOneBy(array $criteria)
    {
        $qb = $this->repo->createQueryBuilder();

        if (count($criteria)) {
            $this->addWhere($qb, $criteria);
        }

        return $qb->execute()->fetch();
    }

    /**
      * Finds records through a joining table with $owner.
      *
      * @param string $ownerTableName The owner table name
      * @param int $ownerId The owner ID
      *
      * @return array The rows
      */
    public function manyToMany(Repository $owners, $ownerId, $throughOrderBy = null)
    {
        $qb = $this->repo->createQueryBuilder();

        // Compute join table name
        $tables     = array($owners->getTableName(false), $this->repo->getTableName(false));
        sort($tables);

        $joinTable = implode('_', $tables);
        $joinAlias = $this->repo->getNewAlias();
        $joinCondition = sprintf('%s.%s = %s.%s',
            $joinAlias,
            $this->repo->getForeignKey(),
            $this->repo->getAlias(),
            $this->repo->getPrimaryKey()
        );

        $qb
            ->leftJoin($this->repo->getAlias(), $joinTable, $joinAlias, $joinCondition)
            ->where($joinAlias.'.'.$owners->getForeignKey().' = ?')
            ->setParameter(0, $ownerId);

        if ($throughOrderBy) {
            $this->addOrderBy($qb, $throughOrderBy, $joinAlias);
        }

        // dd($qb->execute()->fetchAll());
        // dd($qb. '');

        return $qb->execute()->fetchAll();
    }

    protected function addWhere(QueryBuilder &$qb, array $criteria)
    {
        $criteria = $this->normalizeCriteria($criteria);

        $andX = $qb->expr()->andX();

        foreach ($criteria as $property=>$criterion) {

            if (strpos($property, '.') === false) {
                $property = $this->repo->getAlias().'.'.$property;
            }

            list($operator, $value) = $this->normalizeCriterion($criterion);

            if ($value !== null) {

                $args = array($property);

                if (is_array($value)) { // IN
                    $in = array();

                    foreach($value as $val) {
                        $in[] = $qb->createPositionalParameter($val);
                    }

                    // ['?','?','?','?', ... ,'?']
                    $args[] = $in;
                }
                else {
                    // '?'
                    $args[] = $qb->createPositionalParameter($value);
                }

                $expr = call_user_func_array(array($qb->expr(), $operator), $args);
            } else {
                $expr = call_user_func(array($qb->expr(), $operator), $property);
            }

            $andX->add($expr);
        }

        // Finally, add `where` clause
        $qb->andWhere($andX);
    }

    protected function addOrderBy(QueryBuilder &$qb, $orderBy, $alias = null)
    {
        $orders = array();

        if (is_string($orderBy)) {
            array_push($orders, array($orderBy, null));

        } else if (is_array($orderBy)) {

            if ($this->isArrayAssoc($orderBy)) {
                array_push($orders, array(current(array_keys($orderBy)), current(array_values($orderBy))));
            } else {

                foreach ($orderBy as $order) {

                    if (is_string($order)) {
                        array_push($orders, array($order, null));

                    } else if (is_array($order)){
                        array_push($orders, array(current(array_keys($order)), current(array_values($order))));
                    }
                }
            }
        }

        if (!$alias) {
            $alias = $this->repo->getAlias();
        }

        foreach ($orders as $order) {
            $qb->addOrderBy($alias.'.'.$order[0], $order[1]);
        }
    }

    /**
      * Normalize some criteria.
      *
      * From: [
      *     'foo' => 'bar',
      *     'oof' => ['lte', 4] ...
      * ]
      *
      * To: [
      *     'foo' => ['eq', 'bar'],
      *     'oof' => ['lte', 4] ...
      * ]
      *
      * @param array $criteria
      * @return array The normalized criteria
      */
    protected function normalizeCriteria(array $criteria)
    {
        return array_map(function($val) {

            if (!is_array($val)) {
                $val = array((strpos($val, '%') === false) ? 'eq' : 'like', $val);
            }

            return $val;

        }, $criteria);
    }

    /**
      * Normalize a criterion.
      *
      * From:
      *     - ['isNull']
      *     - ['>', 9]
      *
      * To:
      *     - ['isNull', null]
      *     - ['gt', 9]
      *
      * @param array $criterion
      * @return array An array describing the criterion: [operator, value]
      */
    protected function normalizeCriterion(array $criterion)
    {
        // Case: ('isNull')
        if (count($criterion) === 1) {
            list($operator) = $criterion;
        }
        // Case: ('lte', 35)
        else if (count($criterion) === 2) {
            list($operator, $value) = $criterion;
        }
        else {
            throw new \Exception('Invalid Criterion');
        }

        $operator = $this->normalizeOperator($operator);

        if (!isset($value)) {
            $value = null;
        }

        return array($operator, $value);
    }

    protected function normalizeOperator($operator)
    {
        if (!in_array($operator, $this->getOperators())) {
            throw new \Exception('Unknown operator: '.$operator);
        }

        if ($operator === '=') {
            $operator = 'eq';
        }
        else if ($operator === '<>') {
            $operator = 'neq';
        }
        else if ($operator === '<') {
            $operator = 'lt';
        }
        else if ($operator === '<=') {
            $operator = 'lte';
        }
        else if ($operator === '>') {
            $operator = 'gt';
        }
        else if ($operator === '>=') {
            $operator = 'gte';
        }

        return $operator;
    }

    /** static */
    public static function getOperators()
    {
        return array('=', '<>', '<', '<=', '>', '>=', 'eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'isNull', 'isNotNull', 'like', 'notLike', 'in', 'notIn');
    }

    private function isArrayAssoc($arr)
    {
        return is_array($arr) && (bool)count(array_filter(array_keys($arr), 'is_string'));
    }
}