<?php
namespace Models;

use Engine\BaseModel;

class Demo extends BaseModel
{
    public function initialize()
    {
        parent::initialize();
        $this->table = "demo";
        $this->idKey = "id";
    }

    public function _id($one)
    {
        return $one['id'];

    }

    public function _field_demo($one, $isarray = false)
    {
        if ($isarray) {
            $rs = array();
            foreach ($one as $row) {
                $rs[] = empty($row['demo']) ? '' : $row['demo'];
            }
            return $rs;
        } else {
            return empty($one['demo']) ? '' : $one['demo'];
        }
    }

    public function saveInfo($content, $where)
    {
        $data = array('content' => $content, 'inputtime' => time());

        $result = $this->getBuilder('Demo')->columns("count(*) as total")
            ->where(K($where), V($where))->getQuery()->getSingleResult();
        $res = 0;
        try {
            $transaction = $this->getDI()->getTransactions()->get();
            $this->setTransaction($transaction);
            if ($result->total == 0) {
                $status = $this->sqlBuilder('Demo')->insert(array_keys($data), array_values($data))->getQuery()->execute();
            } else {
                $status = $this->sqlBuilder('Demo')->update(array_keys($data), array_values($data))->where(K($where), V($where))->getQuery()->execute();
            }
            $transaction->commit();
            if ($res = $status->success()) {
                if (isset($status->getModel()->id))
                    return $status->getModel()->id;
            }
        } catch (TxFailed $e) {
            echo "Failed, reason: ", $e->getMessage();
        }

        return $res;

    }

    /**
     *
     * @return mixed
     */
    public function flushToDB()
    {
        return 'flush';
    }
}
