<?php
use Phalcon\DI;

/**
 * 加载配置文件数据
 *
 *     config('database')
 *     config('database.default.adapter')
 *
 * @param  string $name
 * @return mixed
 */
function config($name)
{
    static $cached = array();

    // 移除多余的分隔符
    $name = trim($name, '.');

    if (!isset($cached[$name])) {
        $filename = $name;

        // 获取配置名及路径
        if (strpos($name, '.') !== false) {
            $paths = explode('.', $name);
            $filename = array_shift($paths);
        }

        // 查找配置文件
        $file = APP_PATH . '/config/' . $filename . '.php';
        if (!is_file($file)) {
            return null;
        }

        // 从文件中加载配置数据
        $data = require_once $file;

        if (is_array($data)) {
            $data = new Phalcon\Config($data);
        }
        // 缓存文件数据
        $cached[$filename] = $data;

        // 支持路径方式获取配置，例如：config('file.key.subkey')
        if (isset($paths)) {
            foreach ($paths as $key) {
                if (is_array($data) && isset($data[$key])) {
                    $data = $data[$key];
                } elseif (is_object($data) && isset($data->{$key})) {
                    $data = $data->{$key};
                } else {
                    $data = null;
                }
            }
        }

        // 缓存数据
        $cached[$name] = $data;
    }

    return $cached[$name];
}


/**
 * 简化日志写入方法
 *
 * @see    http://docs.phalconphp.com/en/latest/api/Phalcon_Logger.html
 * @see    http://docs.phalconphp.com/en/latest/api/Phalcon_Logger_Adapter_File.html
 * @param  string $name 日志名称
 * @param  string $message 日志内容
 * @param  string $type 日志类型
 * @param  boolean $addUrl 记录当前 url
 * @return Phalcon\Logger\Adapter\File
 */
function write_log($name, $message, $type = null, $addUrl = false)
{
    static $logger, $formatter;

    if (!isset($logger[$name])) {
        $logfile = DATA_PATH . '/logs/' . date('/Ym/') . $name . '_' . date('Ymd') . '.log';
        if (!is_dir(dirname($logfile))) {
            mkdir(dirname($logfile), 0755, true);
        }

        $logger[$name] = new Phalcon\Logger\Adapter\File($logfile);

        // Set the logger format
        if ($formatter === null) {
            $formatter = new Phalcon\Logger\Formatter\Line();
            $formatter->setDateFormat('Y-m-d H:i:s O');
        }

        $logger[$name]->setFormatter($formatter);
    }

    if ($type === null) {
        $type = Phalcon\Logger::INFO;
    }

    if ($addUrl) {
        $logger[$name]->log('URL: ' . HTTP_URL, Phalcon\Logger::INFO);
    }

    $logger[$name]->log($message, $type);

    return $logger[$name];
}


function K($params)
{
    $comparison = array('eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE');

    if (is_array($params)) {
        $condition = '';
        $t = '=';
        foreach ($params as $key => $var) {
            if (is_string($var[0])) {
                if (preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT|NOTLIKE|LIKE)$/i', $var[0])) { // 比较运算
                    $t = $comparison[strtolower($var[0])];
                }

            }
            $condition .= $key . ' ' . $t . ' :' . $key . ': AND ';
        }
        return substr($condition, 0, strlen($condition) - 4);
    } else {
        return $params;
    }
}

function V($params)
{
    $comparison = array('eq' => '=', 'neq' => '<>', 'gt' => '>', 'egt' => '>=', 'lt' => '<', 'elt' => '<=', 'notlike' => 'NOT LIKE', 'like' => 'LIKE');

    if (is_array($params)) {
        $arr = array();
        foreach ($params as $key => $var) {
            if (is_string($var[0])) {
                if (preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT|NOTLIKE|LIKE)$/i', $var[0])) { // 比较运算
                    $arr[$key] = $var[1];
                } else $arr[$key] = $var;
            } else  $arr[$key] = $var;
        }
        return $arr;
    } else {
        return $params;
    }
}

/**
 *  打印
 * @param null $var
 * @param $params
 */
function pr($var = null, ...$params)
{
    $count = count($params);
    echo '<pre>';
    echo '0 => ';
    print_r($var);
    echo '<p>';
    for ($i = 0; $i < $count; $i++) {
        echo ($i + 1) . ' => ';
        print_r($params[$i]);
        echo '<p>';
    }
    echo '</pre>';
}

function M($class)
{
    static $cache = array();
    $class = ucfirst($class);
    if (!isset($cache[$class])) {
        $modelCls = '\\Models\\' . $class;
        $cache[$class] = new $modelCls;
    }
    return $cache[$class];
}
function get_rand($proArr)
{
    $result = '';
    //概率数组的总概率精度
    $proSum = array_sum($proArr);

    //概率数组循环
    foreach ($proArr as $key => $proCur) {
        $randNum = mt_rand(1, $proSum);
        if ($randNum <= $proCur) {
            $result = $key;
            break;
        } else {
            $proSum -= $proCur;
        }
    }
    unset ($proArr);
    return $result;
}
function CACHE()
{
    static $cache = null;
    if (!is_object($cache)) {
        $cache = Di::getDefault()->get('cacheData');
    }
    return $cache;
}

/**
 * 规范数据返回函数
 * @param bool|unknown $state
 * @param string $code
 * @param string|unknown $msg
 * @param array|unknown $data
 * @return multitype :unknown
 */
function callback($state = true, $msg = '', $code = '', $data = array())
{
    die(json_encode(['state' => $state, 'msg' => $msg, 'code' => $code, 'data' => $data], JSON_UNESCAPED_UNICODE));
}

function replaceKey(&$array, $fieldMap)
{
    if (!is_array($array)) return $array;
    foreach (array_keys($array) as $k) {
        $v = &$array[$k];
        if (is_array($v)) {
            replaceKey($v, $fieldMap);
        }
        if (is_numeric($k)) {
            continue;
        }
        if (array_key_exists($k, $fieldMap)) {
            $keyto = $fieldMap[$k];
            if ($k != $keyto) {
                if ($keyto !== 'NULL') {
                    $array[$keyto] = $v;
                }
                unset($array[$k]);
            }
        }
    }
    return $array;
}