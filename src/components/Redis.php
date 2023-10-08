<?php

namespace Jcbowen\JcbaseYii2\components;


use Yii;
use yii\base\BaseObject;
use yii\redis\Connection;

/**
 * Class Redis
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime 2022/9/26 12:22
 * @package Jcbowen\JcbaseYii2\components
 */
class Redis extends BaseObject
{
    /** @var string */
    public $componentName = 'redis';

    /** @var string 存储的值的类型： serialize | json */
    public $valueType = 'serialize';

    /** @var Connection|null */
    public $connection = null;

    /** @var array */
    public $errors = [];

    /**
     * @param string $name \yii\redis\Connection 配置中在components里的键名
     * @param array $config
     * {@inheritdoc}
     */
    public function __construct(string $name = '', array $config = [])
    {
        $this->componentName = $name ?: $this->componentName;

        $componentName    = $this->componentName;
        $this->connection = Yii::$app->$componentName;

        parent::__construct($config);
    }

    /**
     * 获取组件名称
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string
     * @lasttime 2022/9/26 12:23
     */
    public function getComponentName(): string
    {
        return $this->componentName;
    }

    /**
     * 获取错误
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/9/26 12:23
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取redisConnection
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return false|Connection
     * @lasttime 2022/9/26 09:06
     */
    public function getConnection()
    {
        if (!$this->connection instanceof Connection) {
            $this->errors[] = "redis配置有误，未查找到{$this->componentName}的component配置";
            return false;
        }
        return $this->connection;
    }

    /**
     * 将值转换为字符串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param mixed $value
     * @return void
     * @lasttime: 2023/10/8 11:07 PM
     */
    public function stringify(&$value)
    {
        if (empty($value) || !is_array($value)) return;
        $value = $this->valueType === 'serialize' ? serialize($value) : stripslashes(json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 解析字符串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $string
     * @return void
     * @lasttime: 2023/10/8 11:07 PM
     */
    public function parse(&$string)
    {
        if (empty($string) || !is_string($string)) return;
        $string = $this->valueType === 'serialize' ? Util::unserializer($string) : @json_decode($string, true);
    }

    /**
     * redis->get
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @param mixed $default
     * @return mixed
     * @lasttime 2022/9/26 12:23
     */
    public function get($key, $default = null)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $value = $redis->get($key);
        $this->parse($value);

        return $value ?: $default;
    }

    /**
     * redis->set
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @param $value
     * @param int $expire
     * @param ...$options
     * @return false|mixed|Connection|null
     * @lasttime 2022/9/26 12:24
     */
    public function set($key, $value, int $expire = 0, ...$options)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $this->stringify($value);
        $result = $redis->set($key, $value, ...$options);
        if (!empty($expire)) $redis->expire($key, $expire);
        return $result;
    }

    /**
     * redis->setex
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @param $seconds
     * @param $value
     * @return false|mixed|Connection|null
     * @lasttime: 2022/10/14 15:21
     */
    public function setex($key, $seconds, $value)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;
        $this->stringify($value);
        return $redis->setex($key, $seconds, $value);
    }

    /**
     * 批量获取
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ...$key
     * @return array|false|Connection|null
     * @lasttime 2022/9/26 12:24
     */
    public function mget(...$key)
    {
        if (empty($key)) return [];

        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $list = (array)$redis->mget(...$key);

        foreach ($list as &$item)
            $this->parse($item);

        return $list;
    }

    /**
     * redis->del
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ...$keys
     * @return mixed
     * @lasttime 2022/9/26 12:24
     */
    public function del(...$keys)
    {
        $redis = $this->getConnection();
        return $redis->del(...$keys);
    }

    /**
     * redis->exists
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param ...$keys
     * @return mixed
     * @lasttime: 2023/4/21 4:39 PM
     */
    public function exists(...$keys)
    {
        $redis = $this->getConnection();
        return $redis->exists(...$keys);
    }

    /**
     * redis->expire
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param string|int $expire 到期时间
     * @return mixed
     * @lasttime 2022/9/26 12:24
     */
    public function expire(string $key, $expire)
    {
        $expire = intval($expire);

        $redis = $this->getConnection();
        return $redis->expire($key, $expire);
    }

    /**
     * redis->lpush
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @param ...$values
     * @return false|mixed|Connection|null
     * @lasttime: 2023/1/17 4:39 PM
     */
    public function lpush($key, ...$values)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        foreach ($values as &$value)
            $this->stringify($value);

        return $redis->lpush($key, ...$values);
    }

    /**
     * redis->rpop
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @return false|mixed|Connection|null
     * @lasttime: 2023/1/17 4:40 PM
     */
    public function rpop($key)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;
        $value = $redis->rpop($key);
        $this->parse($value);
        return $value;
    }

    /**
     * redis->setnx
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @param $value
     * @return false|mixed|Connection|null
     * @lasttime: 2023/1/17 4:40 PM
     */
    public function setnx($key, $value)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $this->stringify($value);

        return $redis->setnx($key, $value);
    }

    /**
     * redis->keys
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $pattern
     * @return mixed
     * @lasttime: 2023/3/18 12:12 PM
     */
    public function keys($pattern)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;
        return $redis->keys($pattern);
    }

    /**
     * redis->ttl
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $key
     * @return mixed
     * @lasttime: 2023/4/21 4:32 PM
     */
    public function ttl($key)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;
        return $redis->ttl($key);
    }
}
