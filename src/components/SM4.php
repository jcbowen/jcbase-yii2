<?php

namespace Jcbowen\JcbaseYii2\components;

use Exception;

/**
 * SM4 PHP版本
 * 提供SM4对称加密算法的加解密功能
 *
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
     * @var string 初始化向量，SM4算法要求IV长度必须为16字节
     */
    public $iv = 'jcbase.sm4_iv___';

    /**
     * @var string 加密模式，可选值：CBC
     */
    public $mode = 'CBC';

    /**
     * @var string 输出/输入编码格式，可选值：Std、Raw、RawURL、Hex
     */
    public $encoding = 'Std';

    /**
     * @var int SM4块大小（16字节）
     */
    const BLOCK_SIZE = 16;

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

    /**
     * 使用CBC模式加密数据
     *
     * @return string 加密后的密文
     * @throws Exception 加密失败时的异常
     */
    public function encryptCBC(): string
    {

        $this->validateSM4Key($this->key);
        $this->validateSM4Iv($this->iv);

        // 使用PKCS7填充
        $plainText = $this->pkcs7Pad($this->text, self::BLOCK_SIZE);

        // 使用openssl进行SM4-CBC加密
        $cipherText = openssl_encrypt(
            $plainText,
            'SM4-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($cipherText === false) {
            throw new Exception('SM4 CBC加密失败: ' . openssl_error_string());
        }

        return $this->encodeBytes($cipherText);
    }

    /**
     * 使用CBC模式解密数据
     *
     * @return string 解密后的明文
     * @throws Exception 解密失败时的异常
     */
    public function decryptCBC(): string
    {
        $this->validateSM4Key($this->key);
        $this->validateSM4Iv($this->iv);

        $cipherBytes = $this->decodeString($this->text);

        if (strlen($cipherBytes) < self::BLOCK_SIZE) {
            throw new Exception('密文长度过短');
        }

        // 使用openssl进行SM4-CBC解密
        $plainText = openssl_decrypt(
            $cipherBytes,
            'SM4-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $this->iv
        );

        if ($plainText === false) {
            throw new Exception('SM4 CBC解密失败: ' . openssl_error_string());
        }

        // 去除PKCS7填充
        return $this->pkcs7Unpad($plainText, self::BLOCK_SIZE);
    }

    /**
     * 通用加密方法，根据Mode字段选择加密模式
     *
     * @return string 加密后的密文
     * @throws Exception 加密失败时的异常
     */
    public function encrypt(): string
    {
        switch (strtoupper($this->mode)) {
            case 'CBC':
                return $this->encryptCBC();
            default:
                throw new Exception('不支持的加密模式: ' . $this->mode);
        }
    }

    /**
     * 通用解密方法，根据Mode字段选择解密模式
     *
     * @return string 解密后的明文
     * @throws Exception 解密失败时的异常
     */
    public function decrypt(): string
    {
        switch (strtoupper($this->mode)) {
            case 'CBC':
                return $this->decryptCBC();
            default:
                throw new Exception('不支持的解密模式: ' . $this->mode);
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
        if (strlen($data) === 0) {
            throw new Exception('输入数据为空');
        }

        if (strlen($data) % $blockSize !== 0) {
            throw new Exception('输入数据不是块大小的倍数');
        }

        $padding = ord($data[strlen($data) - 1]);
        $padLen  = $padding;

        if ($padLen > $blockSize || $padLen === 0) {
            throw new Exception('无效的填充长度');
        }

        for ($i = strlen($data) - $padLen; $i < strlen($data); $i++) {
            if (ord($data[$i]) !== $padding) {
                throw new Exception('无效的填充');
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
