<?php
namespace Engine;

use Phalcon\DI;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\Query\Builder as QueryBuilder;
use Phalcon\Mvc\Model\Query as ModelQuery;


class CurdBuilder extends QueryBuilder
{
    public $_Phql = NULL;
    public $_type = NULL;
/*
    # DELETE FROM Cars WHERE id = :id:
    $result = $this->modelsManager->createBuilder()
    ->delete('Cars')
    ->where('id = :id:', array('id' => 100))
    ->getQuery()
    ->execute();

    ## Or alternate concept

    # INSERT INTO Cars (price, type) VALUES (:price:, :type:)
    $result = $this->modelsManager->createBuilder()
    ->insert('Cars', array('price', 'type'), array(15000.00, 'Sedan'))
    ->getQuery()
    ->execute();

    # UDPATE Cars SET price = :price, type = :type: WHERE id = :id:
    $result = $this->modelsManager->createBuilder()
    ->update(array('price', 'type'), array(15000.00, 'Sedan'))
    ->where('id = :id:', array('id' => 100))
    ->getQuery()
    ->execute();*/

    public function insert(array $keys = array(), array $values = array())
    {
        if (empty($keys) || empty($values)
        ) {
            throw new \Exception('The frist params or second params must is array');
        }

        if (count($keys) !== count($values)) {
            throw new \Exception('The keys neq values params number');
        }

        $args = func_get_args();
        if (func_num_args() < 2) {
            throw new \Exception('Sorry, please input your params');
        }

        $fields = array_shift($args);
        $values = array_slice($args, 0);

        $format_keys = NULL;
        foreach ($fields AS $val)
            $format_keys .= '' . $val . ',';

        $valueList = [];
        foreach ($values AS $val) {
            if (empty($val) ||
                !is_array($val) ||
                count($val) !== count($keys)
            ) {
                continue;
            }
            $str = '(\'' . implode('\',\'', array_values($val)) . '\')';
            $valueList[] = $str;
            unset($str);
        }

        $sql = "INSERT INTO [%s] (%s) VALUES " . join(',', $valueList);

        $this->_Phql = sprintf($sql, $this->_models, rtrim($format_keys, ','));
        $this->_type = 306;
        unset($fields, $values, $format_keys, $valueList);

        return $this;
    }

    public function update(array $keys = array(), array $values = array())
    {
        if (empty($keys) || empty($values)) {
            throw new \Exception('The frist params or second params must is array');
        }

        if (count($keys) !== count($values)) {
            throw new \Exception('The keys neq values params number');
        }

        $setVal = NULL;
        foreach ($keys AS $key => $val) {
            if (preg_match('/^(' . $val . ')/', $values[$key])) {// 递加
                $setVal .= "$val =  $values[$key],";
            } else
                $setVal .= "$val =  '$values[$key]',";

        }

        $sql = "UPDATE [%s] SET %s ";
        $this->_Phql = sprintf($sql, $this->_models, rtrim($setVal, ','));
        $this->_type = 300;

        return $this;
    }


    public function delete()
    {
        // set query type method for executeDelete
        $this->_type = 303;
        $this->_Phql = "DELETE FROM [%s] ";
        $this->_Phql = sprintf($this->_Phql, $this->_models);

        return $this;
    }


    public function getQuery()
    {
        if (!$this->_Phql) {
            throw new \Exception('please format sql after call this method');
        }

        $where = $this->getWhere();
        if (!empty($where)) {
            $this->_Phql .= (' WHERE ' . $where);
        }

        $limit = $this->getLimit();
        if (!empty($limit)) {
            $this->_Phql .= (' LIMIT ' . $limit);
        }

        // or new ModelQuery($this->_Phql) call all
        $query = new \Phalcon\Mvc\Model\Query($this->_Phql, \Phalcon\Di::getDefault());
        if (!empty($this->_bindParams)) {
            $query->setBindParams($this->_bindParams);
        }
        if (!empty($this->_bindTypes)) {
            $query->setBindTypes($this->_bindTypes);
        }
        $query->setType($this->_type);
        $query->setDI(\Phalcon\Di::getDefault());

        return $query;
    }
}