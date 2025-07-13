<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * Class Cache
 * 目前仅支持redis缓存
 *
 * @author  Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:29 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Cache extends \yii\base\Component
{
    /**
     * @var string 缓存key的前缀，避免项目冲突，推荐使用项目名
     */
    public static $prefix = 'jcbase';

    /**
     * @var int|string 默认缓存时间，单位秒
     */
    public static $expire = 0;

    /**
     * @var string \yii\redis\Connection 配置中在components里的键名
     */
    public static $componentName = 'redis';

    /**
     * @var string redis存储的值的类型： serialize | json
     */
    public static $valueType = 'serialize';

    /**
     * @var array
     * - prefix: string, 缓存key的前缀，避免项目冲突，推荐使用项目名
     * - expire: int, 默认缓存时间，单位秒
     */
    public $config = [];

    /**
     * 获取redis实例
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return Redis
     * @lasttime: 2023/10/9 2:49 PM
     */
    public static function getRedis(): Redis
    {
        static $redis = null;
        if ($redis === null) {
            $redis = new Redis(static::$componentName, [
                'valueType' => static::$valueType,
            ]);
        }
        return $redis;
    }

    /**
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     *
     * @return mixed
     * @lasttime: 2023/10/9 2:52 PM
     */
    public static function get(string $key = '')
    {
        $redis = static::getRedis();
        if (Util::isError($redis)) return $redis;
        return $redis->get(static::keygen($key));
    }

    /**
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string          $key
     * @param array|string    $value
     * @param int|string|null $expire
     *
     * @return mixed
     * @lasttime: 2023/10/9 2:53 PM
     */
    public static function set(string $key = '', $value = null, $expire = null)
    {
        $expire = $expire ?? static::$expire;
        $redis  = static::getRedis();
        if (Util::isError($redis)) return $redis;
        return $redis->set(static::keygen($key), $value, $expire);
    }

    /**
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     *
     * @return mixed
     * @lasttime: 2022/10/5 21:26
     */
    public static function del(string $key)
    {
        $redis = static::getRedis();
        if (Util::isError($redis)) return $redis;
        return $redis->del(static::keygen($key));
    }

    /**
     * 判断缓存是否存在
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     *
     * @return mixed
     * @lasttime: 2022/10/6 20:51
     */
    public static function exists(string $key)
    {
        $redis = static::getRedis();
        if (Util::isError($redis)) return $redis;
        return $redis->exists(static::keygen($key));
    }

    /**
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     *
     * @return string
     * @lasttime: 2022/10/5 21:27
     */
    public static function keygen(string $key = ''): string
    {
        return 'jcbase_' . static::$prefix . '_' . md5($key);
    }

    // ----- 动态调用 ----- /
    public function init()
    {
        parent::init();
        static::$prefix = $this->config['prefix'] ?? static::$prefix;
        static::$expire = $this->config['expire'] ?? static::$expire;
    }

    public function setPrefix(string $prefix)
    {
        static::$prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return static::$prefix;
    }

    public function setExpire(int $expire)
    {
        static::$expire = $expire;
    }

    public function getExpire()
    {
        return static::$expire;
    }

    /**
     * @deprecated use \jcbowen\JcbaseYii2\components\Cache::get($key)
     */
    public function getValue($key)
    {
        return static::get($key);
    }

    /**
     * @deprecated use \jcbowen\JcbaseYii2\components\Cache::set($key, $value, $expire)
     */
    public function setValue($key, $value, $expire = null)
    {
        return static::set($key, $value, $expire);
    }

    /**
     * @deprecated use \jcbowen\JcbaseYii2\components\Cache::del($key)
     */
    public function delValue($key)
    {
        return static::del($key);
    }

    /**
     * @deprecated use \jcbowen\JcbaseYii2\components\Cache::exists($key)
     */
    public function existsValue($key)
    {
        return static::exists($key);
    }

    /**
     * @deprecated use \jcbowen\JcbaseYii2\components\Cache::keygen($key)
     */
    public function getKey($key): string
    {
        return static::keygen($key);
    }
}
