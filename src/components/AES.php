<?php

namespace Jcbowen\JcbaseYii2\components;

use InvalidArgumentException;

/**
 * Class AES
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:19 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class AES
{
    /**
     *
     * @param string $text
     * @param string $key
     * @param string $iv
     * @param int $option
     * @return string
     * @lasttime: 2021/12/28 1:06 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public static function encrypt(string $text, string $key, string $iv, int $option = OPENSSL_RAW_DATA): string
    {
        self::validateKey($key);
        self::validateIv($iv);

        return openssl_encrypt($text, self::getMode($key), $key, $option, $iv);
    }

    /**
     *
     * @param string $cipherText
     * @param string $key
     * @param string $iv
     * @param int $option
     * @param null $method
     * @return string
     * @lasttime: 2021/12/28 1:06 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public static function decrypt(string $cipherText, string $key, string $iv, int $option = OPENSSL_RAW_DATA, $method = null): string
    {
        self::validateKey($key);
        self::validateIv($iv);

        return openssl_decrypt($cipherText, $method ?: self::getMode($key), $key, $option, $iv);
    }

    /**
     *
     * @param $key
     * @return string
     * @lasttime: 2021/12/28 1:06 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public static function getMode($key): string
    {
        return 'aes-' . (8 * strlen($key)) . '-cbc';
    }

    /**
     *
     * @param string $key
     * @lasttime: 2021/12/28 1:06 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public static function validateKey(string $key)
    {
        if (!in_array(strlen($key), [16, 24, 32], true)) {
            throw new InvalidArgumentException(sprintf('Key length must be 16, 24, or 32 bytes; got key len (%s).', strlen($key)));
        }
    }

    /**
     *
     * @param string $iv
     * @lasttime: 2021/12/28 1:06 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public static function validateIv(string $iv)
    {
        if (!empty($iv) && 16 !== strlen($iv)) {
            throw new InvalidArgumentException('IV length must be 16 bytes.');
        }
    }
}
