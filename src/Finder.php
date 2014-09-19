<?php
namespace Eyf\Modelify;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Connection;

class Finder
{
    protected $db;
    protected $tableName;
    protected $primaryKey;
    protected $alias;
    protected $aliasCounter = 'a';

    public function __construct(Connection $db, $tableName, $primaryKey)
    {
        $this->tableName = $tableName;
        $this->primaryKey = $primaryKey;
        $this->db = $db;
    }

    protected function createQueryBuilder()
    {
        unset($this->alias);

        return $this->db->createQueryBuilder()
            ->select($this->getAlias().'.*')
            ->from($this->tableName, $this->getAlias());
    }

    public function find($id)
    {
        return $this->findOneBy(array(array($this->primaryKey, 'eq', intval($id))));
    }

    public function findIn($ids)
    {
        return $this->findBy(array(array($this->primaryKey, 'in', $ids)));
    }

    public function findAll()
    {
        return $this->findBy(array());
    }

    public function findBy(array $criteria, $orderBy = null, $limit = null, $offset = null)
    {
        $qb = $this->createQueryBuilder();

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
        $qb = $this->createQueryBuilder();

        if (count($criteria)) {
            $this->addWhere($qb, $criteria);
        }

        return $qb->execute()->fetch();
    }

    public function rowCount(array $criteria = array())
    {
        $qb = $this->createQueryBuilder();

        if (count($criteria)) {
            $this->addWhere($qb, $criteria);
        }

        return $qb->execute()->rowCount();
    }

    public function addWhere(QueryBuilder &$qb, array $criteria)
    {
        $criteria = $this->normalizeCriteria($criteria);

        $andX = $qb->expr()->andX();

        foreach ($criteria as $criterion) {

            list($property, $operator, $value) = $criterion;

            // ll($property);
            // ll($operator);
            // ll($value);
            // die;

            if (isset($value)) {

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
        $qb->where($andX);
    }

    protected function addOrderBy(QueryBuilder &$qb, $orderBy)
    {
        if (is_string($orderBy)) {
            $qb->orderBy($orderBy);
        }
        else if (is_array($orderBy)) {

            if ($this->isArrayAssoc($orderBy)) {
                $qb->orderBy(current(array_keys($orderBy)), current(array_values($orderBy)));
            }
            else {

                foreach ($orderBy as $order) {

                    if (is_string($order)) {
                        $qb->addOrderBy($order);
                    }
                    else if (is_array($order)){
                        $qb->addOrderBy(current(array_keys($order)), current(array_values($order)));
                    }
                }
            }
        }
    }

    protected function getAlias()
    {
        if (!isset($this->alias)) {
            $this->alias = $this->getNewAlias();
        }

        return $this->alias;
    }

    protected function getNewAlias()
    {
        return $this->aliasCounter++;
    }

    protected function camelToSnake($value, $delimiter = '_')
    {
        $replace = '$1'.$delimiter.'$2';
 
        return ctype_lower($value) ? $value : strtolower(preg_replace('/(.)([A-Z])/', $replace, $value));
    }

    /**
      * Normalize a criteria.
      *
      * @param array $criteria
      * @return array The normalized criteria
      */
    protected function normalizeCriteria(array $criteria)
    {
        $normalized = array();

        foreach ($criteria as $i=>$criterion) {

            if (is_string($i)) { // ['name' => 'john'] or ['label' => '%foo%']..

                // '*' and '%' serve the same purpose
                $criterion = str_replace('*', '%', $criterion);

                if (strpos($criterion, '%') === false) {
                    $criterion = array($i, 'eq', $criterion);
                }
                else {
                    $criterion = array($i, 'like', $criterion);
                }
            }

            array_push($normalized, $this->normalizeCriterion($criterion));
        }

        return $normalized;
    }

    /**
      * Normalize a criterion to an array.
      *
      * @param array $criterion
      * @return array An array describing the criterion: [property, operator, value]
      */
    protected function normalizeCriterion(array $criterion)
    {
        // Case: ('property_name', 'isNull')
        if (count($criterion) === 2) {
            list($property, $operator) = $criterion;
        }
        // Case: ('property_name', 'lte', 35)
        else if (count($criterion) === 3) {
            list($property, $operator, $value) = $criterion;
        }
        else {
            throw new \Exception('Invalid Criterion');
        }

        $operator = $this->normalizeOperator($operator);

        if (strpos($property, '.') === false) {
            $property = $this->getAlias().'.'.$property;
        }

        if (!isset($value)) {
            $value = null;
        }

        return array($property, $operator, $value);
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