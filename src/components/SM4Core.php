<?php

namespace Jcbowen\JcbaseYii2\components;

use Exception;

/**
 * Class SM4Core
 *
 * 国密 SM4 分组算法的纯 PHP 实现（GB/T 32907-2016 / GM/T 0002-2012）。
 *
 * 算法参数：分组长度 128 bit（16 字节），密钥长度 128 bit（16 字节），迭代 32 轮。
 *
 * 存在的意义：
 * `openssl_encrypt()` 要到 OpenSSL 1.1.1 才提供 `SM4-CBC`，而 CentOS 7 默认带的是 OpenSSL 1.0.2，
 * 该环境下 openssl 扩展的算法列表里没有 SM4，调用会直接失败。
 * 本类不依赖任何扩展（只需要 PHP 本身的位运算），用于在缺少 OpenSSL 支持时兜底，
 * 其加解密结果与 OpenSSL 的 `SM4-CBC` / `SM4-ECB` **逐字节一致**，可无缝互解。
 *
 * 注意：本类只负责「分组运算」，不做填充，输入必须是 16 字节的整数倍。
 * 填充请交给上层（SM4 类的 PKCS7）。
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @package Jcbowen\JcbaseYii2\components
 */
class SM4Core
{
    /**
     * @var int 分组长度（字节）
     */
    const BLOCK_SIZE = 16;

    /**
     * @var int 密钥长度（字节）
     */
    const KEY_SIZE = 16;

    /**
     * @var int 迭代轮数
     */
    const ROUNDS = 32;

    /**
     * @var int 轮密钥缓存上限，避免长期运行的进程（如队列 worker）内存无限增长
     */
    const RK_CACHE_LIMIT = 32;

    /**
     * @var array S 盒（非线性变换 τ 的查表），256 字节，取自 GB/T 32907-2016 附录
     */
    private static $sbox = [
        0xd6, 0x90, 0xe9, 0xfe, 0xcc, 0xe1, 0x3d, 0xb7, 0x16, 0xb6, 0x14, 0xc2, 0x28, 0xfb, 0x2c, 0x05,
        0x2b, 0x67, 0x9a, 0x76, 0x2a, 0xbe, 0x04, 0xc3, 0xaa, 0x44, 0x13, 0x26, 0x49, 0x86, 0x06, 0x99,
        0x9c, 0x42, 0x50, 0xf4, 0x91, 0xef, 0x98, 0x7a, 0x33, 0x54, 0x0b, 0x43, 0xed, 0xcf, 0xac, 0x62,
        0xe4, 0xb3, 0x1c, 0xa9, 0xc9, 0x08, 0xe8, 0x95, 0x80, 0xdf, 0x94, 0xfa, 0x75, 0x8f, 0x3f, 0xa6,
        0x47, 0x07, 0xa7, 0xfc, 0xf3, 0x73, 0x17, 0xba, 0x83, 0x59, 0x3c, 0x19, 0xe6, 0x85, 0x4f, 0xa8,
        0x68, 0x6b, 0x81, 0xb2, 0x71, 0x64, 0xda, 0x8b, 0xf8, 0xeb, 0x0f, 0x4b, 0x70, 0x56, 0x9d, 0x35,
        0x1e, 0x24, 0x0e, 0x5e, 0x63, 0x58, 0xd1, 0xa2, 0x25, 0x22, 0x7c, 0x3b, 0x01, 0x21, 0x78, 0x87,
        0xd4, 0x00, 0x46, 0x57, 0x9f, 0xd3, 0x27, 0x52, 0x4c, 0x36, 0x02, 0xe7, 0xa0, 0xc4, 0xc8, 0x9e,
        0xea, 0xbf, 0x8a, 0xd2, 0x40, 0xc7, 0x38, 0xb5, 0xa3, 0xf7, 0xf2, 0xce, 0xf9, 0x61, 0x15, 0xa1,
        0xe0, 0xae, 0x5d, 0xa4, 0x9b, 0x34, 0x1a, 0x55, 0xad, 0x93, 0x32, 0x30, 0xf5, 0x8c, 0xb1, 0xe3,
        0x1d, 0xf6, 0xe2, 0x2e, 0x82, 0x66, 0xca, 0x60, 0xc0, 0x29, 0x23, 0xab, 0x0d, 0x53, 0x4e, 0x6f,
        0xd5, 0xdb, 0x37, 0x45, 0xde, 0xfd, 0x8e, 0x2f, 0x03, 0xff, 0x6a, 0x72, 0x6d, 0x6c, 0x5b, 0x51,
        0x8d, 0x1b, 0xaf, 0x92, 0xbb, 0xdd, 0xbc, 0x7f, 0x11, 0xd9, 0x5c, 0x41, 0x1f, 0x10, 0x5a, 0xd8,
        0x0a, 0xc1, 0x31, 0x88, 0xa5, 0xcd, 0x7b, 0xbd, 0x2d, 0x74, 0xd0, 0x12, 0xb8, 0xe5, 0xb4, 0xb0,
        0x89, 0x69, 0x97, 0x4a, 0x0c, 0x96, 0x77, 0x7e, 0x65, 0xb9, 0xf1, 0x09, 0xc5, 0x6e, 0xc6, 0x84,
        0x18, 0xf0, 0x7d, 0xec, 0x3a, 0xdc, 0x4d, 0x20, 0x79, 0xee, 0x5f, 0x3e, 0xd7, 0xcb, 0x39, 0x48,
    ];

    /**
     * @var array 系统参数 FK，用于密钥扩展的初始异或
     */
    private static $fk = [0xa3b1bac6, 0x56aa3350, 0x677d9197, 0xb27022dc];

    /**
     * @var array|null 固定参数 CK，32 个字，首次使用时生成
     */
    private static $ck = null;

    /**
     * @var array|null 合成表 T0[i] = L(S(i) << 24)，用于加速轮函数，首次使用时生成
     */
    private static $t0 = null;

    /**
     * @var array 轮密钥缓存，[key => rk[]]
     */
    private static $rkCache = [];

    // ---------------------------------------------------------------------
    // 对外接口
    // ---------------------------------------------------------------------

    /**
     * CBC 模式加密
     *
     * @param string $data 明文，长度必须为 16 的整数倍（已填充）
     * @param string $key  16 字节密钥
     * @param string $iv   16 字节初始化向量
     *
     * @return string 密文
     * @throws Exception
     */
    public static function encryptCBC(string $data, string $key, string $iv): string
    {
        self::assertLength($data, $key, $iv);

        $rk       = self::keyExpansion($key);
        $blockNum = strlen($data) >> 4;
        $out      = '';
        $prev     = $iv;

        for ($i = 0; $i < $blockNum; $i++) {
            $block = substr($data, $i << 4, self::BLOCK_SIZE);
            $prev  = self::cryptBlock(self::xorBytes($block, $prev), $rk);
            $out   .= $prev;
        }

        return $out;
    }

    /**
     * CBC 模式解密
     *
     * @param string $data 密文，长度必须为 16 的整数倍
     * @param string $key  16 字节密钥
     * @param string $iv   16 字节初始化向量
     *
     * @return string 明文（仍带填充，需自行去除）
     * @throws Exception
     */
    public static function decryptCBC(string $data, string $key, string $iv): string
    {
        self::assertLength($data, $key, $iv);

        $rk       = array_reverse(self::keyExpansion($key));
        $blockNum = strlen($data) >> 4;
        $out      = '';
        $prev     = $iv;

        for ($i = 0; $i < $blockNum; $i++) {
            $block = substr($data, $i << 4, self::BLOCK_SIZE);
            $out   .= self::xorBytes(self::cryptBlock($block, $rk), $prev);
            $prev  = $block;
        }

        return $out;
    }

    /**
     * ECB 模式加密
     *
     * @param string $data 明文，长度必须为 16 的整数倍（已填充）
     * @param string $key  16 字节密钥
     *
     * @return string 密文
     * @throws Exception
     */
    public static function encryptECB(string $data, string $key): string
    {
        self::assertLength($data, $key);

        $rk       = self::keyExpansion($key);
        $blockNum = strlen($data) >> 4;
        $out      = '';

        for ($i = 0; $i < $blockNum; $i++) {
            $out .= self::cryptBlock(substr($data, $i << 4, self::BLOCK_SIZE), $rk);
        }

        return $out;
    }

    /**
     * ECB 模式解密
     *
     * @param string $data 密文，长度必须为 16 的整数倍
     * @param string $key  16 字节密钥
     *
     * @return string 明文（仍带填充，需自行去除）
     * @throws Exception
     */
    public static function decryptECB(string $data, string $key): string
    {
        self::assertLength($data, $key);

        $rk       = array_reverse(self::keyExpansion($key));
        $blockNum = strlen($data) >> 4;
        $out      = '';

        for ($i = 0; $i < $blockNum; $i++) {
            $out .= self::cryptBlock(substr($data, $i << 4, self::BLOCK_SIZE), $rk);
        }

        return $out;
    }

    // ---------------------------------------------------------------------
    // 算法核心
    // ---------------------------------------------------------------------

    /**
     * 单分组加/解密（16 字节进，16 字节出）
     *
     * 加密传正序轮密钥，解密传逆序轮密钥，其余流程完全一致。
     *
     * @param string $input 16 字节输入
     * @param array  $rk    32 个轮密钥字
     *
     * @return string 16 字节输出
     */
    private static function cryptBlock(string $input, array $rk): string
    {
        self::initTables();

        $x = [
            self::bytesToWord($input, 0),
            self::bytesToWord($input, 4),
            self::bytesToWord($input, 8),
            self::bytesToWord($input, 12),
        ];

        for ($i = 0; $i < self::ROUNDS; $i++) {
            $x[$i + 4] = $x[$i] ^ self::roundT($x[$i + 1] ^ $x[$i + 2] ^ $x[$i + 3] ^ $rk[$i]);
        }

        // 反序变换 R：输出 (X35, X34, X33, X32)
        return self::wordToBytes($x[35])
            . self::wordToBytes($x[34])
            . self::wordToBytes($x[33])
            . self::wordToBytes($x[32]);
    }

    /**
     * 密钥扩展，由 16 字节主密钥生成 32 个轮密钥字
     *
     * @param string $key 16 字节密钥
     *
     * @return array 32 个轮密钥字
     * @throws Exception
     */
    private static function keyExpansion(string $key): array
    {
        if (isset(self::$rkCache[$key])) {
            return self::$rkCache[$key];
        }

        self::initTables();

        $k = [
            self::bytesToWord($key, 0) ^ self::$fk[0],
            self::bytesToWord($key, 4) ^ self::$fk[1],
            self::bytesToWord($key, 8) ^ self::$fk[2],
            self::bytesToWord($key, 12) ^ self::$fk[3],
        ];

        $rk = [];
        for ($i = 0; $i < self::ROUNDS; $i++) {
            $k[$i + 4] = $k[$i] ^ self::linearLKey(
                self::tau($k[$i + 1] ^ $k[$i + 2] ^ $k[$i + 3] ^ self::$ck[$i])
            );
            $rk[$i] = $k[$i + 4];
        }

        if (count(self::$rkCache) >= self::RK_CACHE_LIMIT) {
            self::$rkCache = [];
        }
        self::$rkCache[$key] = $rk;

        return $rk;
    }

    /**
     * 合成变换 T，等价于 L(τ(x))，用 T0 表实现以避免重复拆字节
     *
     * T(x) = T0[a0] ^ rotl(T0[a1], 24) ^ rotl(T0[a2], 16) ^ rotl(T0[a3], 8)
     *
     * 推导：记 B_j = S(a_j) 放在第 j 个字节位（j=0 为最高字节），则
     * B_j = rotr(B_0, 8j) = rotl(B_0, 32 - 8j)，而 L 与循环移位可交换，
     * 故 L(B_j) = rotl(L(B_0), 32 - 8j) = rotl(T0[a_j], 32 - 8j)。
     *
     * @param int $x 32 位字
     *
     * @return int
     */
    private static function roundT(int $x): int
    {
        $t0 = self::$t0;

        return $t0[($x >> 24) & 0xFF]
            ^ self::rotl($t0[($x >> 16) & 0xFF], 24)
            ^ self::rotl($t0[($x >> 8) & 0xFF], 16)
            ^ self::rotl($t0[$x & 0xFF], 8);
    }

    /**
     * 非线性变换 τ，4 个字节各自过 S 盒
     *
     * @param int $x 32 位字
     *
     * @return int
     */
    private static function tau(int $x): int
    {
        $s = self::$sbox;

        return ($s[($x >> 24) & 0xFF] << 24)
            | ($s[($x >> 16) & 0xFF] << 16)
            | ($s[($x >> 8) & 0xFF] << 8)
            | $s[$x & 0xFF];
    }

    /**
     * 线性变换 L，用于轮函数
     *
     * L(B) = B ^ (B <<< 2) ^ (B <<< 10) ^ (B <<< 18) ^ (B <<< 24)
     *
     * @param int $b
     *
     * @return int
     */
    private static function linearL(int $b): int
    {
        $b &= 0xFFFFFFFF;

        return $b
            ^ self::rotl($b, 2)
            ^ self::rotl($b, 10)
            ^ self::rotl($b, 18)
            ^ self::rotl($b, 24);
    }

    /**
     * 线性变换 L'，用于密钥扩展
     *
     * L'(B) = B ^ (B <<< 13) ^ (B <<< 23)
     *
     * @param int $b
     *
     * @return int
     */
    private static function linearLKey(int $b): int
    {
        $b &= 0xFFFFFFFF;

        return $b ^ self::rotl($b, 13) ^ self::rotl($b, 23);
    }

    /**
     * 32 位循环左移
     *
     * @param int $x
     * @param int $n 1 - 31
     *
     * @return int
     */
    private static function rotl(int $x, int $n): int
    {
        return (($x << $n) | ($x >> (32 - $n))) & 0xFFFFFFFF;
    }

    // ---------------------------------------------------------------------
    // 初始化与工具
    // ---------------------------------------------------------------------

    /**
     * 惰性生成 CK 常量与 T0 合成表
     *
     * CK 按标准公式 ck[i][j] = (4i + j) * 7 mod 256 生成，避免手写常量出错。
     */
    private static function initTables()
    {
        if (self::$t0 !== null) {
            return;
        }

        $t0 = [];
        for ($i = 0; $i < 256; $i++) {
            $t0[$i] = self::linearL(self::$sbox[$i] << 24);
        }
        self::$t0 = $t0;

        $ck = [];
        for ($i = 0; $i < 32; $i++) {
            $w = 0;
            for ($j = 0; $j < 4; $j++) {
                $w = ($w << 8) | (((4 * $i + $j) * 7) & 0xFF);
            }
            $ck[$i] = $w;
        }
        self::$ck = $ck;
    }

    /**
     * 4 字节（大端）转 32 位字
     *
     * @param string $bytes
     * @param int    $offset
     *
     * @return int
     */
    private static function bytesToWord(string $bytes, int $offset): int
    {
        return (ord($bytes[$offset]) << 24)
            | (ord($bytes[$offset + 1]) << 16)
            | (ord($bytes[$offset + 2]) << 8)
            | ord($bytes[$offset + 3]);
    }

    /**
     * 32 位字转 4 字节（大端）
     *
     * @param int $w
     *
     * @return string
     */
    private static function wordToBytes(int $w): string
    {
        return chr(($w >> 24) & 0xFF)
            . chr(($w >> 16) & 0xFF)
            . chr(($w >> 8) & 0xFF)
            . chr($w & 0xFF);
    }

    /**
     * 两个等长字符串逐字节异或
     *
     * 关于 PHP 的 ^ 运算符：当两个操作数都是 string 时，它按字符的 ASCII 值逐字节异或并返回字符串，
     * 这是 PHP 手册「位运算符」明确定义的行为，不会把字符串转成数字——
     * 例如 "12" ^ "34" 的结果是二进制 "\x02\x06"（逐字节异或），而不是整数 12 ^ 34 = 46。
     * 多字节字符同样按字节处理，不存在编码问题。
     *
     * 唯一需要留意的是：两串长度不等时，结果会**静默**按较短的那个截断。
     * 本方法加上长度校验，避免这种静默截断演变成难以排查的加解密错误。
     *
     * @param string $a
     * @param string $b
     *
     * @return string
     * @throws Exception 两串长度不一致时抛出
     */
    private static function xorBytes(string $a, string $b): string
    {
        if (strlen($a) !== strlen($b)) {
            throw new Exception('参与异或的两个字符串长度不一致：' . strlen($a) . ' 与 ' . strlen($b));
        }

        return $a ^ $b;
    }

    /**
     * 校验输入长度
     *
     * @param string      $data
     * @param string      $key
     * @param string|null $iv
     *
     * @throws Exception
     */
    private static function assertLength(string $data, string $key, $iv = null)
    {
        if (strlen($key) !== self::KEY_SIZE) {
            throw new Exception('SM4 密钥长度必须为 ' . self::KEY_SIZE . ' 字节，当前：' . strlen($key));
        }

        if (strlen($data) === 0 || strlen($data) % self::BLOCK_SIZE !== 0) {
            throw new Exception('SM4 待处理数据长度必须为 ' . self::BLOCK_SIZE . ' 的整数倍，当前：' . strlen($data));
        }

        if ($iv !== null && strlen($iv) !== self::BLOCK_SIZE) {
            throw new Exception('SM4 初始化向量长度必须为 ' . self::BLOCK_SIZE . ' 字节，当前：' . strlen($iv));
        }
    }
}
