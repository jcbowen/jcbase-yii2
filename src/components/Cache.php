<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * Class Cache
 * 目前仅支持redis缓存
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:29 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Cache
{
    // 缓存key的前缀，避免项目冲突，推荐使用项目名
    public static $prefix = 'jcbase';

    // 默认缓存时间，单位秒
    public static $expire = 0;

    /**
     * @var array
     * - prefix: string, 缓存key的前缀，避免项目冲突，推荐使用项目名
     * - expire: int, 默认缓存时间，单位秒
     */
    public $config = [];

    public function __construct()
    {
        if (!empty($this->config)) {
            if (!empty($this->config['prefix'])) self::$prefix = $this->config['prefix'];
            if (!empty($this->config['expire'])) self::$expire = $this->config['expire'];
        }
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     * @return array|mixed|string
     * @lasttime: 2022/10/5 21:24
     */
    public static function get(string $key = '')
    {
        return Util::redisGet(self::getKey($key));
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     * @param array|string $value
     * @param int|string $expire
     * @return mixed
     * @lasttime: 2022/10/5 21:25
     */
    public static function set(string $key = '', $value = null, $expire = null)
    {
        $expire = $expire ?? self::$expire;
        return Util::redisSet(self::getKey($key), $value, $expire);
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     * @return mixed
     * @lasttime: 2022/10/5 21:26
     */
    public static function del(string $key)
    {
        return Util::redisDel(self::getKey($key));
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $key
     * @return string
     * @lasttime: 2022/10/5 21:27
     */
    private static function getKey(string $key = ''): string
    {
        return 'jcsoft_' . md5(self::$prefix . '_' . $key);
    }

    // ----- 动态调用 ----- /
    public function setPrefix(string $prefix)
    {
        self::$prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return self::$prefix;
    }

    public function getValue($key)
    {
        return self::get($key);
    }

    public function setValue($key, $value, $expire = 0)
    {
        return self::set($key, $value, $expire);
    }

    public function delValue($key)
    {
        return self::del($key);
    }
}

