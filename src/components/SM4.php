<?php

namespace Jcbowen\JcbaseYii2\components;

use Exception;

/**
 * SM4 对称加密组件（国密 SM4 / GB/T 32907-2016）
 *
 * 关于兼容性：
 * OpenSSL 直到 1.1.1 才提供 SM4 算法，CentOS 7 默认带的是 OpenSSL 1.0.2，
 * 该环境下 openssl_get_cipher_methods() 里没有 SM4，openssl_encrypt() 会直接失败。
 * 因此本类会先探测当前环境是否支持 SM4：
 *   - 支持：走 OpenSSL，速度快；
 *   - 不支持：自动降级到纯 PHP 实现 SM4Core，无需改动任何调用方代码。
 * 两条路径的加解密结果逐字节一致，同一份密文在不同环境间可互解。
 *
 * 关于填充：
 * 采用标准 PKCS7 单次填充，与 OpenSSL、Java、Go 等的标准 SM4-CBC 实现互通。
 * 注意：2024 年修复前的旧版本存在「手动 PKCS7 填充 + OpenSSL 默认再填一次」的双重填充问题，
 * 其产生的历史密文与本版本不兼容，需要解密后重新加密迁移。
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @package Jcbowen\JcbaseYii2\components
 */
class SM4
{
    /**
     * @var string 待加密/解密的文本内容
     */
    public $text = '';

    /**
     * @var string 加密密钥，SM4算法要求密钥长度必须为16字节
     */
    public $key = 'jcbase.sm4_key__';

    /**
     * @var string 初始化向量，SM4算法要求IV长度必须为16字节（ECB模式不需要）
     */
    public $iv = 'jcbase.sm4_iv___';

    /**
     * @var string 加密模式，可选值：CBC、ECB
     */
    public $mode = 'CBC';

    /**
     * @var string 输出/输入编码格式，可选值：Std、Raw、RawURL、Hex
     */
    public $encoding = 'Std';

    /**
     * @var bool 是否强制使用纯 PHP 实现（跳过 OpenSSL）
     *
     * 正常情况下不需要开启，仅用于自测或排查环境差异时对比两条路径的结果。
     */
    public static $forcePurePhp = false;

    /**
     * @var int SM4块大小（16字节）
     */
    const BLOCK_SIZE = 16;

    /**
     * @var bool|null 当前环境 OpenSSL 是否支持 SM4，null 表示尚未探测
     */
    private static $opensslSupport = null;

    /**
     * 构造函数
     *
     * @param array $config 配置参数
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    // ---------------------------------------------------------------------
    // 通用入口
    // ---------------------------------------------------------------------

    /**
     * 通用加密方法，根据Mode字段选择加密模式
     *
     * @return string 加密后的密文
     * @throws Exception 加密失败时的异常
     */
    public function encrypt(): string
    {
        $this->validateParams();

        $paddedText = $this->pkcs7Pad($this->text, self::BLOCK_SIZE);

        return $this->encodeBytes($this->cryptRaw($paddedText, true));
    }

    /**
     * 通用解密方法，根据Mode字段选择解密模式
     *
     * @return string 解密后的明文
     * @throws Exception 解密失败时的异常
     */
    public function decrypt(): string
    {
        $this->validateParams();

        $cipherBytes = $this->decodeString($this->text);

        if (strlen($cipherBytes) === 0 || strlen($cipherBytes) % self::BLOCK_SIZE !== 0) {
            throw new Exception('密文长度非法：' . strlen($cipherBytes) . ' 字节，应为 ' . self::BLOCK_SIZE . ' 的整数倍');
        }

        $paddedText = $this->cryptRaw($cipherBytes, false);

        return $this->pkcs7Unpad($paddedText, self::BLOCK_SIZE);
    }

    // ---------------------------------------------------------------------
    // 分模式入口（保持向后兼容）
    // ---------------------------------------------------------------------

    /**
     * 使用CBC模式加密数据
     *
     * @return string 加密后的密文
     * @throws Exception 加密失败时的异常
     */
    public function encryptCBC(): string
    {
        $this->mode = 'CBC';

        return $this->encrypt();
    }

    /**
     * 使用CBC模式解密数据
     *
     * @return string 解密后的明文
     * @throws Exception 解密失败时的异常
     */
    public function decryptCBC(): string
    {
        $this->mode = 'CBC';

        return $this->decrypt();
    }

    /**
     * 使用ECB模式加密数据
     *
     * @return string 加密后的密文
     * @throws Exception 加密失败时的异常
     */
    public function encryptECB(): string
    {
        $this->mode = 'ECB';

        return $this->encrypt();
    }

    /**
     * 使用ECB模式解密数据
     *
     * @return string 解密后的明文
     * @throws Exception 解密失败时的异常
     */
    public function decryptECB(): string
    {
        $this->mode = 'ECB';

        return $this->decrypt();
    }

    // ---------------------------------------------------------------------
    // 加解密核心：OpenSSL 优先，不可用时降级纯 PHP
    // ---------------------------------------------------------------------

    /**
     * 执行分组运算（输入输出均为原始字节，不含填充处理）
     *
     * @param string $data    已填充的明文 / 密文
     * @param bool   $encrypt true 加密，false 解密
     *
     * @return string
     * @throws Exception
     */
    private function cryptRaw(string $data, bool $encrypt): string
    {
        $opensslError = '';

        if (!self::$forcePurePhp && self::isOpensslSupported()) {
            $method = $this->getOpensslMethod();
            // 填充已由 pkcs7Pad() 完成，这里必须关闭 OpenSSL 的默认填充，否则会被再填一次
            $options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;

            while (openssl_error_string() !== false) {
                // 清空错误队列，避免拿到上一次调用的残留错误
            }

            $result = $encrypt
                ? @openssl_encrypt($data, $method, $this->key, $options, $this->iv)
                : @openssl_decrypt($data, $method, $this->key, $options, $this->iv);

            if ($result !== false) {
                return $result;
            }

            $opensslError = openssl_error_string() ?: '未知错误';
        }

        try {
            return $this->cryptPurePhp($data, $encrypt);
        } catch (Exception $e) {
            $message = 'SM4 ' . strtoupper($this->mode) . ($encrypt ? '加密' : '解密') . '失败：' . $e->getMessage();
            if ($opensslError !== '') {
                $message .= '（OpenSSL 亦失败：' . $opensslError . '）';
            }
            throw new Exception($message, 0, $e);
        }
    }

    /**
     * 纯 PHP 实现的分组运算
     *
     * @param string $data
     * @param bool   $encrypt
     *
     * @return string
     * @throws Exception
     */
    private function cryptPurePhp(string $data, bool $encrypt): string
    {
        if (strtoupper($this->mode) === 'ECB') {
            return $encrypt
                ? SM4Core::encryptECB($data, $this->key)
                : SM4Core::decryptECB($data, $this->key);
        }

        return $encrypt
            ? SM4Core::encryptCBC($data, $this->key, $this->iv)
            : SM4Core::decryptCBC($data, $this->key, $this->iv);
    }

    /**
     * 当前环境探测：OpenSSL 是否支持 SM4
     *
     * 结果会被静态缓存，仅首次调用时探测。
     *
     * @return bool
     */
    public static function isOpensslSupported(): bool
    {
        if (self::$opensslSupport === null) {
            self::$opensslSupport = false;

            if (function_exists('openssl_get_cipher_methods')) {
                $methods = array_map('strtolower', openssl_get_cipher_methods());
                // 只要有一种模式可用即可，这里以最常用的 CBC 作为判据
                self::$opensslSupport = in_array('sm4-cbc', $methods, true);
            }
        }

        return self::$opensslSupport;
    }

    /**
     * 当前实际使用的驱动名称，便于排查环境差异
     *
     * @return string openssl 或 pure-php
     */
    public static function getDriver(): string
    {
        return (!self::$forcePurePhp && self::isOpensslSupported()) ? 'openssl' : 'pure-php';
    }

    /**
     * 环境自检
     *
     * 部署到新服务器（尤其是 CentOS 7 这类 OpenSSL 版本较老的系统）后建议先跑一次，
     * 确认 SM4 在当前环境下确实可用、且降级路径也正常。
     *
     * 用法：
     *     print_r(SM4::selfTest());
     *
     * @return array 诊断信息
     *               - driver: 当前默认驱动（openssl / pure-php）
     *               - opensslSm4: 当前环境 OpenSSL 是否支持 SM4
     *               - vector: 国标测试向量是否通过
     *               - currentDriver: 当前驱动加解密往返是否通过
     *               - purePhp: 纯 PHP 实现加解密往返是否通过
     *               - elapsed: 耗时（毫秒）
     * @throws Exception 自检未通过时抛出
     */
    public static function selfTest(): array
    {
        $start = microtime(true);

        $report = [
            'driver'        => self::getDriver(),
            'opensslSm4'    => self::isOpensslSupported(),
            'vector'        => false,
            'currentDriver' => false,
            'purePhp'       => false,
        ];

        // 1. 国标测试向量（GM/T 0002-2012 附录 A.1）
        $testKey   = hex2bin('0123456789abcdeffedcba9876543210');
        $testPlain = $testKey;
        $report['vector'] = bin2hex(SM4Core::encryptECB($testPlain, $testKey))
            === '681edf34d206965e86b3e94f536e4246';

        $text = 'SM4 self test：中文 mixed 123 !@#';
        $key  = 'jcbase.sm4_key__';
        $iv   = 'jcbase.sm4_iv___';

        // 2. 当前默认驱动往返（CBC + ECB）
        try {
            $cbc = (new SM4(['text' => $text, 'key' => $key, 'iv' => $iv]))->encrypt();
            $ecb = (new SM4(['text' => $text, 'key' => $key, 'mode' => 'ECB']))->encrypt();
            $report['currentDriver'] =
                (new SM4(['text' => $cbc, 'key' => $key, 'iv' => $iv]))->decrypt() === $text
                && (new SM4(['text' => $ecb, 'key' => $key, 'mode' => 'ECB']))->decrypt() === $text;
        } catch (Exception $e) {
            $report['currentDriver'] = 'error: ' . $e->getMessage();
        }

        // 3. 强制纯 PHP 路径往返
        $force = self::$forcePurePhp;
        self::$forcePurePhp = true;
        try {
            $cbc = (new SM4(['text' => $text, 'key' => $key, 'iv' => $iv]))->encrypt();
            $ecb = (new SM4(['text' => $text, 'key' => $key, 'mode' => 'ECB']))->encrypt();
            $report['purePhp'] =
                (new SM4(['text' => $cbc, 'key' => $key, 'iv' => $iv]))->decrypt() === $text
                && (new SM4(['text' => $ecb, 'key' => $key, 'mode' => 'ECB']))->decrypt() === $text;
        } catch (Exception $e) {
            $report['purePhp'] = 'error: ' . $e->getMessage();
        } finally {
            self::$forcePurePhp = $force;
        }

        $report['elapsed'] = round((microtime(true) - $start) * 1000, 2);

        if ($report['vector'] !== true || $report['currentDriver'] !== true || $report['purePhp'] !== true) {
            throw new Exception('SM4 环境自检未通过：' . json_encode($report, JSON_UNESCAPED_UNICODE));
        }

        return $report;
    }

    /**
     * 当前模式对应的 OpenSSL 算法名
     *
     * @return string
     * @throws Exception
     */
    private function getOpensslMethod(): string
    {
        switch (strtoupper($this->mode)) {
            case 'CBC':
                return 'SM4-CBC';
            case 'ECB':
                return 'SM4-ECB';
            default:
                throw new Exception('不支持的加密模式: ' . $this->mode);
        }
    }

    // ---------------------------------------------------------------------
    // 校验
    // ---------------------------------------------------------------------

    /**
     * 校验模式、密钥与 IV
     *
     * @throws Exception
     */
    private function validateParams()
    {
        if (!in_array(strtoupper($this->mode), ['CBC', 'ECB'], true)) {
            throw new Exception('不支持的加密模式: ' . $this->mode);
        }

        $this->validateSM4Key($this->key);

        // ECB 模式不使用 IV，无需校验
        if (strtoupper($this->mode) === 'CBC') {
            $this->validateSM4Iv($this->iv);
        }
    }

    /**
     * 验证SM4密钥是否有效
     *
     * @param string $key 密钥
     *
     * @throws Exception 密钥无效时的异常
     */
    private function validateSM4Key(string $key)
    {
        if (strlen($key) !== 16) {
            throw new Exception('SM4密钥长度必须为16字节，当前长度: ' . strlen($key));
        }
    }

    /**
     * 验证SM4初始化向量是否有效
     *
     * @param string $iv 初始化向量
     *
     * @throws Exception IV无效时的异常
     */
    private function validateSM4Iv(string $iv)
    {
        if (strlen($iv) !== self::BLOCK_SIZE) {
            throw new Exception('SM4初始化向量长度必须为' . self::BLOCK_SIZE . '字节，当前长度: ' . strlen($iv));
        }
    }

    // ---------------------------------------------------------------------
    // 填充与编解码
    // ---------------------------------------------------------------------

    /**
     * PKCS7填充
     *
     * @param string $data      原始数据
     * @param int    $blockSize 块大小
     *
     * @return string 填充后的数据
     */
    private function pkcs7Pad(string $data, int $blockSize): string
    {
        $padding = $blockSize - (strlen($data) % $blockSize);
        return $data . str_repeat(chr($padding), $padding);
    }

    /**
     * PKCS7去填充
     *
     * @param string $data      填充后的数据
     * @param int    $blockSize 块大小
     *
     * @return string 去填充后的数据
     * @throws Exception 去填充失败时的异常
     */
    private function pkcs7Unpad(string $data, int $blockSize): string
    {
        $legacyTip = '若为历史数据，请注意旧版本存在双重填充的缺陷，其密文与标准实现不兼容，需先解密后重新加密迁移。';

        if (strlen($data) === 0) {
            throw new Exception('解密结果为空，密钥或密文可能有误。' . $legacyTip);
        }

        if (strlen($data) % $blockSize !== 0) {
            throw new Exception('解密结果长度不是块大小的倍数。' . $legacyTip);
        }

        $padding = ord($data[strlen($data) - 1]);
        $padLen  = $padding;

        if ($padLen > $blockSize || $padLen === 0) {
            throw new Exception('无效的填充长度，密钥或密文可能有误。' . $legacyTip);
        }

        for ($i = strlen($data) - $padLen; $i < strlen($data); $i++) {
            if (ord($data[$i]) !== $padding) {
                throw new Exception('无效的填充，密钥或密文可能有误。' . $legacyTip);
            }
        }

        return substr($data, 0, -$padLen);
    }

    /**
     * 字节数组编码
     *
     * @param string $bytes 字节数据
     *
     * @return string 编码后的字符串
     */
    private function encodeBytes(string $bytes): string
    {
        switch ($this->encoding) {
            case 'Raw':
                return rtrim(base64_encode($bytes), '=');
            case 'RawURL':
                // 使用base64 URL编码，去除填充
                return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($bytes));
            case 'Hex':
                return bin2hex($bytes);
            default: // Std
                return base64_encode($bytes);
        }
    }

    /**
     * 字符串解码为字节数组
     *
     * @param string $str 编码后的字符串
     *
     * @return string 解码后的字节数据
     * @throws Exception 解码失败时的异常
     */
    private function decodeString(string $str): string
    {
        switch ($this->encoding) {
            case 'Raw':
                // 为Raw编码添加必要的填充
                $padding = strlen($str) % 4;
                if ($padding !== 0) {
                    $str .= str_repeat('=', 4 - $padding);
                }
                return base64_decode($str);
            case 'RawURL':
                // 替换字符后直接解码，不需要添加填充
                $str = str_replace(['-', '_'], ['+', '/'], $str);
                return base64_decode($str);
            case 'Hex':
                return hex2bin($str);
            default: // Std
                return base64_decode($str);
        }
    }
}
