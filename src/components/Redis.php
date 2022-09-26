<?php

namespace Jcbowen\JcbaseYii2\components;


use Yii;

/**
 * Class Redis
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime 2022/9/26 12:22
 * @package Jcbowen\JcbaseYii2\components
 */
class Redis
{
    /** @var string */
    public $componentName = 'redis';

    /** @var yii\redis\Connection|null */
    public $connection = null;

    /** @var array */
    public $errors = [];

    /**
     * @param string $name 在配置中的components里的键名
     */
    public function __construct(string $name = '')
    {
        if (!empty($name)) $this->componentName = $name;

        $componentName    = $this->componentName;
        $this->connection = Yii::$app->$componentName;
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
     * @return false|Yii\redis\Connection
     * @lasttime 2022/9/26 09:06
     */
    public function getConnection()
    {
        if (!$this->connection instanceof yii\redis\Connection) {
            $this->errors[] = "redis配置有误，未查找到{$this->componentName}的component配置";
            return false;
        }
        return $this->connection;
    }

    /**
     * redis->get
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @return array|false|mixed|Yii\redis\Connection|null
     * @lasttime 2022/9/26 12:23
     */
    public function get($key)
    {
        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $value = $redis->get($key);
        return Util::unserializer($value);
    }

    /**
     * redis->set
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @param $value
     * @param $expire
     * @param ...$options
     * @return false|mixed|Yii\redis\Connection|null
     * @lasttime 2022/9/26 12:24
     */
    public function set($key, $value, $expire = 0, ...$options)
    {
        $expire = intval($expire);

        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        if (is_array($value)) $value = serialize($value);
        $result = $redis->set($key, $value, ...$options);
        if (!empty($expire)) $redis->expire($key, $expire);
        return $result;
    }

    /**
     * 批量获取
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ...$key
     * @return array|false|Yii\redis\Connection|null
     * @lasttime 2022/9/26 12:24
     */
    public function mget(...$key)
    {
        if (empty($key)) return [];

        $redis = $this->getConnection();
        if (Util::isError($redis)) return $redis;

        $list = (array)$redis->mget(...$key);
        foreach ($list as &$item) $item = Util::unserializer($item);
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
     * 延长到期时间
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
}