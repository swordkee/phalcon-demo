<?php
namespace Engine;

use Phalcon\Db;
use Phalcon\DI;
use Phalcon\Mvc\Model as PhalconModel;
use Phalcon\Mvc\Model\Query\Builder;

/**
 * Abstract Model.
 *
 * @method static findFirstById($id)
 * @method static findFirstByLanguage($name)
 *
 * @method DIBehaviour|\Phalcon\DI getDI()
 */
class BaseModel extends PhalconModel
{
    protected $_class;
    protected $cache;
    public static $_tbprefix = "";
    public static $fieldMap = array();
    public static $cacheflag = 'redisflag';
    public static $cache_index_expire = 0;
    protected $table = "";
    protected $idKey = "";
    protected $default_fields = "";

    public function initialize()
    {
        self::$_tbprefix = config('config')['dbMaster']['prefix'];
        $this->cache = Di::getDefault()->get('cacheData');
        $this->setReadConnectionService('db');//读
        $this->setWriteConnectionService('dbMaster');//写
        $this->useDynamicUpdate(true);//关闭更新全字段
        $this->setup(array('notNullValidations' => false));//关闭ORM自动验证非空列的映射表

    }

    public function getProfile()
    {
        if (config('config')['profiler']) {
            $profiles = $this->getDI()->getProfiler()->getProfiles();
            foreach ($profiles as $profile) {
                if (preg_match('/^(DESCRIBE)/', $profile->getSQLStatement())
                    || preg_match('/^(SELECT IF)/', $profile->getSQLStatement())
                ) continue;
                echo "SQL: ", $profile->getSQLStatement(), "; ->", sprintf("%.4f", $profile->getTotalElapsedSeconds()), "ms <br>";
            }
        }
    }

    public function getSource()
    {
        $table = explode("\\", get_class($this));
        return self::$_tbprefix . strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', end($table)));
    }

    /**
     * Get builder associated with table of this model.
     *
     * @param $table
     * @param string|null $tableAlias Table alias to use in query.
     * @return Builder
     */
    public function getBuilder($table, $tableAlias = null)
    {
        $temp = explode("\\", get_class($this));
        $table = $table != end($temp) ? $table : get_called_class();
        $builder = $this->getModelsManager()->createBuilder();
        if (!$tableAlias) {
            $builder->from($table);
        } else {
            $builder->addFrom($table, $tableAlias);
        }

        return $builder;
    }


    /**
     * load curdbuilder set write operation
     * @param $table
     * @param null $tableAlias
     * @return CurdBuilder
     */
    public function sqlBuilder($table, $tableAlias = null)
    {
        $temp = explode("\\", get_class($this));
        $table = $table != end($temp) ? $table : get_called_class();
        $builder = new CurdBuilder();
        if (!$tableAlias) {
            $builder->from($table);
        } else {
            $builder->addFrom($table, $tableAlias);
        }

        return $builder;
    }

    public function fetchOneBySQL($sql)
    {
        try {
            return $this->getReadConnection()->fetchOne($sql, Db::FETCH_ASSOC);
        } catch (\PDOException $Ex) {
            die($this->sqlError('SQL Query Error', $Ex->getMessage(), $sql));
        }
    }

    public function fetchAllBySQL($sql)
    {
        try {
            return $this->getReadConnection()->fetchAll($sql);
        } catch (\PDOException $Ex) {
            die($this->sqlError('SQL Query Error', $Ex->getMessage(), $sql));
        }

    }

    private function sqlError($message = 'SQL Query Error', $info, $sql = '')
    {
        header('Content-type:text/html;charset=utf-8;');
        $html = '<!DOCTYPE><html><head><title>mySQL Message</title><style type="text/css">body {margin:0px;color:#555555;font-size:12px;background-color:#efefef;font-family:Verdana} ol {margin:0px;padding:0px;} .w {width:800px;margin:100px auto;padding:0px;border:1px solid #cccccc;background-color:#ffffff;} .h {padding:8px;background-color:#ffffcc;} li {height:auto;padding:5px;line-height:22px;border-top:1px solid #efefef;list-style:none;overflow:hidden;}</style></head>';
        $html .= '<body><div class="w"><ol>';
        if ($message) {
            $html .= '<div class="h">' . $message . '</div>';
        }
        $html .= '<li>Date: ' . date('Y-n-j H:i:s', time()) . '</li>';
        if ($info) {
            $html .= '<li>SQLID: ' . $info . '</li>';
        }
        if ($sql) {
            $html .= '<li>Error: ' . $sql . '</li>';
        }
        $html .= '</ol></div></body></html>';
        return $html;
    }

    /**
     * 通过主键从数据库获取一条记录
     *
     * @param  array $pkey 主键，catid
     * @return array
     */
    public function _fetchOneByPkey($pkey)
    {
        $one = $this->fetchOneById($this->table, $pkey, $this->idKey);
        if (empty($one)) {
            // 不存在时缓存一个redisflag标志
            $one[self::$cacheflag] = 0;
        } else {
            $one[self::$cacheflag] = 1;
        }

        return $one;
    }

    public function fetchOneById($table, $id, $idkey = "id")
    {
        $phql = "select * from " . self::$_tbprefix . $table . " where " . $idkey . " = '" . $id . "' limit 1";
        return $this->fetchOneBySQL($phql);
    }

    /**
     * 通过主键从数据库获取一条记录并进行缓存，最后根据指定字段返回该条记录
     *
     * @param  array $pkey 主键
     * @param  string $fields 返回字段
     * @return array
     */
    public function cacheOne($pkey, $fields = '')
    {
        if (empty($pkey)) {
            return null;
        }

        $map = $this->cacheList([$pkey], $fields);
        if (empty($map)) {
            return null;
        }

        return array_values($map)[0];
    }

    public function getOne($pkey, $fields)
    {
        if (empty($fields) || $fields == '*') {
            $fields = $this->default_fields;
        }

        $fields = is_array($fields) ? $fields : explode(',', $fields);

        $table = $this->table;
        $one = $this->cache->hmget($table, $pkey, $fields);
        if (empty($one)) {
            $one = $this->cacheOne($pkey, $fields);
        }
        if ($one[self::$cacheflag] == 0) {
            return null;
        }
        foreach ($one as $k => $v) {
            if (method_exists($this, '_field_' . $k)) {
                $one[$k] = $this->{'_field_' . $k}($one);
            }
        }
        return $one;
    }

    /**
     * 通过主键列表从数据库获取多条记录并进行缓存，最后根据指定字段返回多条记录
     *
     * @param  array $pkeys 主键列表
     * @param  string /array $fields 返回字段
     * @return array
     */
    public function cacheList($pkeys, $fields = '')
    {
        if (empty($pkeys)) {
            return null;
        }

        $table = $this->table;
        $this->cache->pipeline('w');
        foreach ($pkeys as $pkey) {
            $one = $this->_fetchOneByPkey($pkey);
            $this->cache->delete($table, $pkey);
            $this->cache->hmset($table, $pkey, $one);
            $this->cache->hmget($table, $pkey, $fields);
        }
        $rs = $this->cache->exec();

        if (count($rs) != count($pkeys) * 3) {
            return null;
        }

        $map = array();
        $count = count($pkeys);
        for ($i = 0; $i < $count; $i++) {
            $map[$pkeys[$i]] = $rs[($i + 1) * 3 - 1];
        }
        unset($rs, $pkeys);
        return $map;
    }

    public function getListByPkeys($pkeys, $fields)
    {
        if (empty($fields) || $fields == '*') {
            $fields = $this->default_fields;
        }

        $fields = is_array($fields) ? $fields : explode(',', $fields);

        $table = $this->table;

        $this->cache->pipeline();
        foreach ($pkeys as $pkey) {
            $this->cache->hmget($table, $pkey, $fields);
        }
        $list = $this->cache->exec();

        $emptyPkeys = array();
        foreach ($pkeys as $i => $pkey) {
            if ($list[$i][self::$cacheflag] === false) {
                $emptyPkeys[] = $pkey;
            }
        }

        if (!empty($emptyPkeys)) {
            $rs = $this->cacheList($emptyPkeys, $fields);
            foreach ($list as $i => $row) {
                if ($row[self::$cacheflag] === false) {
                    $list[$i] = $rs[$pkeys[$i]];
                }
            }
        }
        unset($emptyPkeys);

        foreach ($fields as $field) {
            if (method_exists($this, '_field_' . $field)) {
                $rs = $this->{'_field_' . $field}($list, true);
                if (!empty($rs)) {
                    foreach ($list as $i => &$row) {
                        $row[$field] = $rs[$i];
                    }
                }
                unset($rs);
            }
        }

        return $list;
    }

}
