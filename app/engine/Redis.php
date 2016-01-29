<?php
namespace Engine;

use Phalcon\Cache\Exception;

class Redis
{
    protected $_options = null;
    private static $_db = 0;
    private static $_master = 0;
    private static $_multi = 0;
    private static $_prefix = "wj_";
    private static $_redis_w;
    private static $_redis_r;

    /**
     * Phalcon\Cache\Backend\Redis constructor
     *
     * @param  \Phalcon\Cache\FrontendInterface $frontend
     * @param  array $options
     * @throws \Phalcon\Cache\Exception
     */
    public function __construct($frontend, $options = null)
    {
        $this->_options = $options;
        if (!extension_loaded('redis')) {
            throw new Exception('redis failed to load');
        }
    }

    public function _getRedis($options)
    {
        if (!isset($options["host"]) || !isset($options["port"]) || !isset($options["persistent"])) {
            throw new Exception("Unexpected inconsistency in options");
        }

        if (isset($options['prefix'])) {
            self::$_prefix = $options['prefix'];
        }

        $redis = new \Redis();

        if ($options["persistent"]) {
            $success = $redis->pconnect($options['host'], intval($options['port']));
        } else {
            $success = $redis->connect($options['host'], intval($options['port']));
        }

        if (!$success) {
            throw new Exception("Could not connect to the Redisd server " . $options["host"] . ":" . $options["port"]);
        }

        if (isset($options["auth"])) {
            $success = $redis->auth($options["auth"]);
            if (!$success) {
                throw new Exception("Failed to authenticate with the Redisd server");
            }
        }

        if (isset($options["index"])) {
            $redis->select(intval($options["index"]));
        } else {
            $redis->select(self::$_db);
        }

        return $redis;
    }

    public function getWriteRedis()
    {
        if (!is_object(self::$_redis_w)) {
            self::$_redis_w = $this->_getRedis($this->_options['cache']);
        }

        return self::$_redis_w;
    }

    public function getReadRedis()
    {
        if (self::$_multi == 1) {
            return $this->getWriteRedis();
        }

        if (self::$_master == 1) {
            return $this->getWriteRedis();
        }

        if (!is_object(self::$_redis_r)) {
            if (array_key_exists('cacheSlave', $this->_options)) {
                $options = $this->_options['cacheSlave'];
            }
            $options[] = $this->_options['cache'];

            $arr = array();
            foreach ($options as $i => $option) {
                $arr[] = isset($option['weight']) ? intval($option['weight']) : 1;
            }

            self::$_redis_r = $this->_getRedis($options[get_rand($arr)]);
        }

        return self::$_redis_r;
    }

    public function get($key)
    {
        return $this->getReadRedis()->get($this->_key($key));
    }

    public function set($key, $value, $period = 0)
    {
        $isTrue = $this->getWriteRedis()->set($this->_key($key), $value);
        if (intval($period)) $this->getWriteRedis()->expire($this->_key($key), intval($period));
        return $isTrue;
    }

    public function keys($key)
    {
        return $this->getWriteRedis()->keys($this->_key($key));
    }

    public function deleteLike($key)
    {
        if (self::$_multi == 1) {
            return;
        }
        $keys = $this->getWriteRedis()->keys($this->_key($key));
        if (!empty($keys)) {
            $this->getWriteRedis()->delete($keys);
        }
    }

    public function delete($key, $id = '')
    {
        if (is_array($id)) {
            $keys = array_map(array($this, '_keys'), $key, $id);
        } else {
            $keys = $this->_key($key, $id);
        }
        $this->getWriteRedis()->delete($keys);
    }

    public function hmset($key, $id, $data, $period = 0)
    {
        $isTrue = $this->getWriteRedis()->hMset($this->_key($key, $id), $data);
        if (intval($period)) $this->getWriteRedis()->expire($this->_key($key, $id), intval($period));
        return $isTrue;
    }

    public function hmget($key, $id, $fields)
    {
        $fields = is_array($fields) ? $fields : explode(',', $fields);
        $fields[] = 'redisflag';
        $rs = $this->getReadRedis()->hmGet($this->_key($key, $id), $fields);
        return is_array($rs) && $rs['redisflag'] === false ? array() : $rs;
    }

    public function hdel($key, $id, $fields)
    {
        return $this->getWriteRedis()->hdel($this->_key($key, $id), $fields);
    }

    public function hIncrBy($key, $id, $field, $num = 1)
    {
        return $this->getWriteRedis()->hIncrBy($this->_key($key, $id), $field, intval($num));
    }

    public function hIncrByFloat($key, $id, $field, $num = 1)
    {
        return $this->getWriteRedis()->hIncrByFloat($this->_key($key, $id), $field, floatval($num));
    }

    public function incrBy($key, $num = 1)
    {
        return $this->getWriteRedis()->incrBy($this->_key($key), intval($num));
    }

    public function decrBy($key, $num = 1)
    {
        return $this->getWriteRedis()->decrBy($this->_key($key), $num);
    }

    public function llen($key, $id)
    {
        return $this->getReadRedis()->llen($this->_key($key, $id));
    }

    public function lrange($key, $id, $start, $stop)
    {
        return $this->getReadRedis()->lrange($this->_key($key, $id), intval($start), intval($stop));
    }

    public function rpush($key, $id, $arr = array(), $period = 0)
    {
        $redis = $this->getWriteRedis();
        foreach ($arr as $v) {
            $redis->rpush($this->_key($key, $id), $v);
        }
        if (intval($period)) $redis->expire($this->_key($key, $id), intval($period));
        return true;
    }

    public function sadd($key, $id, $arr = array(), $period = 0)
    {
        $redis = $this->getWriteRedis();
        foreach ($arr as $v) {
            $redis->sadd($this->_key($key, $id), $v);
        }
        if (intval($period)) $redis->expire($this->_key($key, $id), intval($period));
        return true;
    }

    public function lpush($key, $id, $arr = array(), $period = 0)
    {
        $redis = $this->getWriteRedis();
        foreach ($arr as $v) {
            $redis->lpush($this->_key($key, $id), $v);
        }
        if (intval($period)) $redis->expire($this->_key($key, $id), intval($period));
        return true;
    }

    public function smembers($key, $id)
    {
        return $this->getReadRedis()->sMembers($this->_key($key, $id));
    }

    public function clear()
    {
        return $this->getWriteRedis()->flushDB();
    }

    public function pipeline($rw = 'r')
    {
        if ($rw == 'w') {
            self::$_multi = 1;
            return $this->getWriteRedis()->multi(\Redis::PIPELINE);
        } else {
            self::$_multi = 0;
            return $this->getReadRedis()->multi(\Redis::PIPELINE);
        }
    }

    public function multi()
    {
        self::$_multi = 1;
        return $this->getWriteRedis()->multi(\Redis::MULTI);
    }

    public function exec()
    {
        if (self::$_multi == 1) {
            $rs = $this->getWriteRedis()->exec();
        } else {
            $rs = $this->getReadRedis()->exec();
        }
        self::$_multi = 0;
        return $rs;
    }

    public function master()
    {
        self::$_master = 1;
    }

    private function _key($key, $id = '')
    {
        return self::$_prefix . $key . (($id !== '') ? (':' . $id) : '');
    }
}
