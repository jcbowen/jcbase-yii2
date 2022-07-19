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
    // 前缀，推荐使用项目名避免冲突
    public static $prefix = 'jcbase';

    public static function get($key = '')
    {
        return Util::redisGet(self::getKey($key));
    }

    public static function set($key = '', $value = null)
    {
        return Util::redisSet(self::getKey($key), $value);
    }

    public static function del($key)
    {
        return Util::redisDel(self::getKey($key));
    }

    private static function getKey($key = ''): string
    {
        return 'jcsoft_' . md5(self::$prefix . '_' . $key);
    }
}

