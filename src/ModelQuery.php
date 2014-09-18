<?php
namespace Modelify;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Query\Expression\CompositeExpression;


class ModelQuery extends QueryBuilder
{
    protected $model;

    /**
     * Initializes a new <tt>QueryBuilder</tt>.
     *
     * @param \Doctrine\DBAL\Connection $connection The DBAL Connection.
     * @param \Modelify\ActiveRecord $model The model associated with the query.
     */
    public function __construct(Connection $connection, ActiveRecord $model, $includeFrom = true)
    {
        parent::__construct($connection);

        $this->model = $model;

        // Select model
        $this->select($this->model->getAlias().'.*');

        if ($includeFrom)
          $this->from($this->model->getTableName(), $this->model->getAlias());
    }

    public function where($predicates)
    {
        $args = $this->normalizeWhereArgs(func_get_args());

        if (empty($args)) {
          return $this;
        }

        return call_user_func_array(array('parent', 'where'), $args);
    }

    public function andWhere($where)
    {
        $args = $this->normalizeWhereArgs(func_get_args());
        
        if (empty($args)) {
          return $this;
        }

        return call_user_func_array(array('parent', 'andWhere'), $args);
    }

    public function orWhere($where)
    {
        $args = $this->normalizeWhereArgs(func_get_args());

        if (empty($args)) {
          return $this;
        }
        
        return call_user_func_array(array('parent', 'orWhere'), $args);
    }

    protected function normalizeWhereArgs(array $args)
    {
      if (count($args) === 3 && in_array($args[1], $this->getOperators())) {
        $args = array($args); // where('id', 'eq', 2) --> where(['id', 'eq', 2])
      }

      $newArgs = array();

      foreach ($args as $arg)
      {
        if (!is_array($arg))
        {
          // This is a normal (DBAL) argument: either a string 'u.id = 2' or an expr()
          $newArgs[] = $arg;
          continue;
        }
        
        if (count($arg) === 3 && in_array($arg[1], $this->getOperators())) {
          $arg = array($arg); // ['id', 'eq', 2] --> [['id', 'eq', 2]]
        }

        foreach($arg as $i=>$criterion)
        {
          if (is_string($i)) // ['id' => 2]
          {
            $criterion = array($i, 'eq', $criterion);
          }
          list($column, $operator, $value) = $this->normalizeCriterion($criterion);

          if ($column)
          {
            $expr = $this->getExpr($column, $operator, $value);
            if ($expr)
            {
              $newArgs[] = $expr;
            }
          }
        }
      }
      return $newArgs;
    }

    /**
     * Specifies an ordering for the query results.
     * Replaces any previously specified orderings, if any.
     *
     * @param string $sort  The ordering expression.
     * @param string $order The ordering direction.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder This QueryBuilder instance.
     */
    public function orderBy($sort, $order = null)
    {
      if (!$sort)
        return $this;

      list($sort, $dir) = $this->parseOrderBy($sort);
      if ($order)
        $dir = $order;

      if (strpos($sort, '.') === false)
        $sort = $this->model->getAlias().'.'.$sort;

      return parent::orderBy($sort, $dir);
    }

    public function parseOrderBy($orderBy)
    {
      if (is_array($orderBy))
      {
        $sort = array_keys($orderBy)[0];
        $dir = array_values($orderBy)[0];
      }
      else { // string
        $sort = $orderBy;
        $dir = 'ASC';
      }

      return array($sort, $dir);
    }

    /**
      * Fetch all records of a given query and returns a collection of models
      *
      * @return array The models, an array of ActiveRecord
      */
    public function fetchAll()
    {
      $rows = $this->execute()->fetchAll();
      $models = array();

      foreach ($rows as $row)
        $models[] = $this->model->newInstance((array) $row);

      return $models;
    }

    /**
      * Fetch the first record of a given query and returns a model
      *
      * @return ActiveRecord The model
      */
    public function fetchOne()
    {
      $row = $this->execute()->fetch();

      return ($row === false) ? null : $this->model->newInstance((array) $row);
    }

    /**
      * Normalize a criterion to an array.
      *
      * @param string $key
      * @param array|string|int $val
      * @return array An array describing the criterion: [column, operator, value]
      */
    protected function normalizeCriterion($criterion, $prefixColumn = true)
    {
        if (count($criterion) === 2)
            list($column, $operator) = $criterion;

        else if (count($criterion) === 3)
            list($column, $operator, $value) = $criterion;

        else
            throw new \Exception('Invalid Criterion');

        if (!in_array($operator, $this->getOperators()))
            throw new \Exception('Invalid Operator');

        if (strpos($column, '.') === false)
            $column = $this->model->getAlias().'.'.$column;

        if (!isset($value))
            $value = null;

        return array($column, $operator, $value);
    }

    public function getExpr($column, $operator, $value)
    {
        if (!is_string($column) || !is_string($operator))
            return null;

        if (!in_array($operator, $this->getOperators()))
            return null;

        $expr = $this->expr();

        $args = array($column);

        if ($value)
        {
            if (is_array($value)) // IN
            {
                $in = array();

                foreach($value as $val)
                    $in[] = $this->createPositionalParameter($val);

                $args[] = $in;

            }
            else
                $args[] = $this->createPositionalParameter($value);
        }
        return call_user_func_array(array($expr, $operator), $args);
    }

    public static function getOperators()
    {
        return array('eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'isNull', 'isNotNull', 'like', 'notLike', 'in', 'notIn');
    }
}