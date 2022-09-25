<?php

namespace Jcbowen\JcbaseYii2\components;


use Yii;

class Redis
{
    public $componentName = 'redis';

    /** @var yii\redis\Connection|null */
    public static $connection = null;

    public function __construct($name = '')
    {
        if (!empty($name)) $this->componentName = $name;

        $componentName    = $this->componentName;
        self::$connection = Yii::$app->$componentName;
    }

    public function getComponentName()
    {
        return $this->componentName;
    }

    public function get($key)
    {
        $redis = self::$connection;

        $value = $redis->get($key);
        return Util::unserializer($value);
    }

    public function set($key, $value, $expire = 0, ...$options)
    {
        $expire = intval($expire);

        $redis = self::$connection;
        if (is_array($value)) $value = serialize($value);
        $result = $redis->set($key, $value, ...$options);
        if (!empty($expire)) $redis->expire($key, $expire);
        return $result;
    }

    public function mget(...$key): array
    {
        if (empty($key)) return [];

        $redis = self::$connection;

        $list = (array)$redis->mget(...$key);
        foreach ($list as &$item) $item = Util::unserializer($item);
        return $list;
    }

    public function del(...$keys)
    {
        $redis = self::$connection;
        return $redis->del(...$keys);
    }

    public function expire($key, $expire)
    {
        $redis = self::$connection;
        return $redis->expire($key, $expire);
    }
}
