<?php
namespace Models;

use Engine\BaseModel;

class Search extends BaseModel
{
    public function initialize()
    {
        parent::initialize();
    }

    public function getKeyword($key, array $param)
    {
        $keys = implode(' ', explode('|', $key));
        $search_time = 0;

        import('library.sphinx/search');
        $sphinx = new \search();
        $offset = $param['limit'] * ($param['page'] - 1);

        $res = $sphinx->search($keys, array($search_time, TIMESTAMP), $offset, $param['limit'], '@weight desc,adddate desc');
        $total = $res['total'];
        //如果结果不为空
        if (!empty($res['matches'])) {
            $result = $res['matches'];
        }
        if (!empty($result)) {
            $ids = array_column(array_column($result, 'attrs'), 'ids');
        } else {
            return array(0, 0, []);
        }
        return $ids;
    }

}

