<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\ExitException;
use Yii\redis\Connection;
use yii\web\Response;

/**
 * Class Util
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/9/6 11:38 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Util
{
    public static function getHostName(): ?string
    {
        return Yii::$app->request->getHostName();
    }

    public static function getHostInfo(): ?string
    {
        return Yii::$app->request->getHostInfo();
    }

    public static function getSiteRoot(): ?string
    {
        global $_B;
        $_B['siteRoot'] = $_B['siteRoot'] ?: self::getHostInfo();
        if (substr($_B['siteRoot'], -1) != '/') $_B['siteRoot'] .= '/';
        return $_B['siteRoot'];
    }

    public static function getHeaders($url, $format = 0)
    {
        $result = @get_headers($url, $format);
        if (empty($result)) {
            stream_context_set_default(array(
                'ssl' => array(
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ),
            ));
            $result = get_headers($url, $format);
        }
        return $result;
    }

    /**
     * 将附件路径转换为附件链接
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param bool $local_path
     * @param bool $is_cache
     * @param $src
     * @return string
     * @lasttime: 2022/3/19 5:45 下午
     */
    public static function toMedia($src, bool $local_path = false, bool $is_cache = true): string
    {
        $src = trim($src);

        if (empty($src)) return '';
        if (!$is_cache) $src .= "?v=" . time();

        if (substr($src, 0, 2) == '//') {
            return 'http:' . $src;
        }
        if ((substr($src, 0, 7) == 'http://') || (substr($src, 0, 8) == 'https://')) {
            return $src;
        }

        // 如果存在资源目录，转换为本地资源目录
        if (self::strExists($src, 'static/')) {
            return Yii::$app->params['domain']['attachment_local'] . substr($src, strpos($src, 'static/'));
        }

        // 移除资源链接中的本地附件域名
        if (self::startsWith($src, Yii::$app->params['domain']['attachment_local']) && !self::strExists($src, '/static/')) {
            $urls = parse_url($src);
            $src  = $t = substr($urls['path'], strpos($urls['path'], 'images'));
        }

        // 输出本地附件链接
        if ($local_path || empty(Yii::$app->params['attachment']['isRemote'])) {
            $src = Yii::$app->params['domain']['attachment_local'] . Yii::$app->params['attachment']['dir'] . '/' . $src;
        } else {
            $src = Yii::$app->params['domain']['attachment'] . $src;
        }
        return $src;
    }

    /**
     * 路径安全解析
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $path
     * @return false|string
     * @lasttime: 2022/3/19 5:46 下午
     */
    public static function parsePath(string $path)
    {
        $danger_char = ['../', '{php', '<?php', '<%', '<?', '..\\', '\\\\', '\\', '..\\\\', '%00', '\0', '\r'];
        foreach ($danger_char as $char) if (self::strExists($path, $char)) return false;
        return $path;
    }

    /**
     * 取出数组中指定部分
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $arr
     * @param mixed $default
     * @param string|array $keys
     * @return array
     * @lasttime: 2022/3/19 6:12 下午
     */
    public static function arrayElements($keys, array $arr, $default = FALSE): array
    {
        $return = [];
        $keys   = (array)$keys;
        foreach ($keys as $key) {
            $return[$key] = isset($arr[$key]) ? $arr[$key] : $default;
        }
        return $return;
    }

    /**
     * 获取当前app信息
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/17 11:33 下午
     * @return array
     */
    public static function getCurrentAppInfo(): array
    {
        static $appInfo = [];
        if (!empty($appInfo)) return $appInfo;

        if (defined('APP_NAME')) {
            $appInfo['name'] = APP_NAME;
        } else {
            throw new ExitException('APP_NAME is not defined');
        }

        if (!empty($appInfo['name'])) $appInfo['path'] = Yii::getAlias('@' . $appInfo['name']);

        return $appInfo;
    }

    /**
     * 是否为https通讯
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return bool
     * @lasttime: 2021/12/19 11:42 上午
     */
    public static function isSecureConnection(): bool
    {
        if (isset($_SERVER['HTTPS']) && (('1' == $_SERVER['HTTPS']) || ('on' == strtolower($_SERVER['HTTPS'])))) {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }
        // 反向代理的情况下判断，需要在nginx反向代理中配置【proxy_set_header   X-Forwarded-Proto $scheme;】
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ('https' == $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return true;
        }
        return false;
    }

    /**
     * 获取一个随机数
     * @param int $length 要获取随机数的长度
     * @param bool $numeric 返回纯数字随机数
     * @return string
     */
    public static function random($length, $numeric = FALSE): string
    {
        $seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));
        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            --$length;
        }
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 获取数组中指定key的
     *
     *
     * For example:
     *
     * ```php
     * $array = [
     *     'A' => [1, 2],
     *     'B' => [
     *         'C' => 1,
     *         'D' => 2,
     *     ],
     *     'E' => 1,
     * ];
     *
     * $result = \common\components\Util::getArrValueByKey($array, 'B.C');
     * // $result will be:
     * // 1
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param array $arr
     * @return mixed
     * @lasttime: 2022/3/26 10:05 下午
     */
    public static function getArrValueByKey($arr = [], $key = '')
    {
        $key = trim($key, '.');
        if (empty($arr)) return null;
        if (empty($key)) return $arr;

        $nodeArr = $arr;
        $keyArr  = explode('.', $key);
        foreach ($keyArr as $item) {
            if (!array_key_exists($item, $nodeArr)) {
                $nodeArr = null;
                break;
            }
            $nodeArr = $nodeArr[$item];
        }

        return $nodeArr;
    }

    /**
     * 判断字符串是否包含字串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:26 上午
     * @param string $find 需要查找的字串
     *
     * @param string $string 在该字符串中查找
     * @return bool
     */
    public static function strExists(string $string, string $find): bool
    {
        return !(strpos($string, $find) === FALSE);
    }

    /**
     * 反序列化
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 10:51 下午
     * @param $value
     *
     * @return mixed|array
     */
    public static function unserializer($value)
    {
        if (empty($value)) return [];
        if (!self::is_serialized($value)) {
            return $value;
        }
        if (version_compare(PHP_VERSION, '7.0.0', '>=')) {
            $result = unserialize($value, array('allowed_classes' => false));
        } else {
            if (preg_match('/[oc]:[^:]*\d+:/i', $value)) {
                return [];
            }
            $result = unserialize($value);
        }
        if (false === $result) {
            $temp = preg_replace_callback('!s:(\d+):"(.*?)";!s', function ($matchs) {
                return 's:' . strlen($matchs[2]) . ':"' . $matchs[2] . '";';
            }, $value);

            return unserialize($temp);
        } else {
            return $result;
        }
    }

    /**
     * 是否为序列化字符串
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 10:52 下午
     * @param bool $strict
     *
     * @param $data
     * @return bool
     */
    public static function is_serialized($data, $strict = true): bool
    {
        if (!is_string($data)) return false;
        $data = trim($data);
        if ('N;' == $data) return true;
        if (strlen($data) < 4) return false;
        if (':' !== $data[1]) return false;
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) return false;
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');
            if (false === $semicolon && false === $brace) return false;
            if (false !== $semicolon && $semicolon < 3) return false;
            if (false !== $brace && $brace < 4) return false;
        }
        $token = $data[0];
        switch ($token) {
            case 's' :
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) return false;
                } elseif (false === strpos($data, '"')) return false;
            case 'a' :
                return (bool)preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'O' :
                return false;
            case 'b' :
            case 'i' :
            case 'd' :
                $end = $strict ? '$' : '';
                return (bool)preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
        }
        return false;
    }

    /**
     * 是否以指定字符开始
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 11:19 下午
     * @param string $needle
     *
     * @param string $haystack
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }

    /**
     * 是否以指定字符结束
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/19 11:19 下午
     * @param string $needle
     *
     * @param string $haystack
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    public static function error($errno, $message = '', $data = []): array
    {
        return [
            'errcode' => $errno,
            'errmsg'  => $message,
            'data'    => $data
        ];
    }

    public static function isError($data): bool
    {
        if (empty($data) || (is_array($data) && array_key_exists('errcode', $data) && $data['errcode'] != 0)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取指定目录下的文件夹
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $path
     * @return array
     * @lasttime: 2022/1/14 12:31 上午
     */
    public static function getDirByPath(string $path): array
    {
        $path     .= substr($path, -1) == '/' ? '' : '/';
        $template = [];
        if (is_dir($path)) {
            if ($handle = opendir($path)) {
                while (false !== ($templatepath = readdir($handle))) {
                    if ($templatepath != '.' && $templatepath != '..') {
                        if (is_dir($path . $templatepath)) {
                            $template[] = $templatepath;
                        }
                    }
                }
            }
        }
        return $template;
    }

    /**
     * 获取指定目录下所有文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $dir 目录路径
     * @return array
     * @lasttime: 2022/1/14 12:26 上午
     */
    public static function getFilesByDir(string $dir): array
    {
        $dir     .= substr($dir, -1) == '/' ? '' : '/';
        $dirInfo = [];
        foreach (glob($dir . '*') as $v) {
            $dirInfo[] = $v;
            if (is_dir($v)) {
                $dirInfo = array_merge($dirInfo, self::getFilesByDir($v));
            }
        }
        return $dirInfo;
    }

    /**
     * 对数据进行AES加密
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param string $iv
     * @param string $data
     * @return string
     * @lasttime: 2021/12/28 1:24 下午
     */
    public static function myEncrypt(string $data, string $key = 'jcsoft.aes_key__', string $iv = 'jcsoft.aes_iv___'): string
    {
        return base64_encode(AES::encrypt($data, $key, $iv));
    }

    /**
     * 对数据进行AES解密
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $key
     * @param string $iv
     * @param string $encode
     * @return string
     * @lasttime: 2021/12/28 1:24 下午
     */
    public static function myDecrypt(string $encode, string $key = 'jcsoft.aes_key__', string $iv = 'jcsoft.aes_iv___'): string
    {
        return AES::decrypt(base64_decode($encode), $key, $iv);
    }

    /**
     * 获取redis
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/5/24 8:53 下午
     * @return Connection
     */
    public static function getRedis(): Connection
    {
        return Yii::$app->redis;
    }

    /**
     * redis设置数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $value
     * @param int $expire
     * @param mixed ...$options
     * @param $key
     * @return mixed
     */
    public static function redisSet($key, $value, $expire = 0, ...$options)
    {
        $expire = intval($expire);

        $redis = self::getRedis();
        if (is_array($value)) $value = serialize($value);
        $result = $redis->set($key, $value, ...$options);
        if (!empty($expire)) $redis->expire($key, $expire);
        return $result;
    }

    /**
     * redis根据key获取数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $key
     * @return string|array|mixed
     */
    public static function redisGet($key)
    {
        $redis = self::getRedis();

        $value = $redis->get($key);
        return self::unserializer($value);
    }

    /**
     * redis根据key获取数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param mixed ...$key
     * @return array
     */
    public static function redisMget(...$key): array
    {
        if (empty($key)) return [];

        $redis = self::getRedis();

        $list = (array)$redis->mget(...$key);
        foreach ($list as &$item) $item = self::unserializer($item);
        return $list;
    }

    public static function redisDel(...$keys)
    {
        $redis = self::getRedis();
        return $redis->del(...$keys);
    }

    /**
     * 输出json结构数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:18 上午
     * @param string $errmsg 错误信息
     * @param mixed $data 返回内容
     * @param array $params 补充参数
     * @param string $returnType
     *
     * @param string|int $errCode 错误码，其中0为正确
     * @return string|Response
     */
    public function result($errCode = '0', string $errmsg = '', $data = [], array $params = [], string $returnType = 'exit')
    {
        global $_GPC;
        $req   = Yii::$app->request;
        $data  = (array)$data;
        $count = count($data);

        $errCode = (int)$this->getResponseCode($errCode);
        $errmsg  = $this->getResponseMsg($errmsg);
        $data    = $this->getResponseData($data);

        $result = [
            'errcode' => $errCode,
            'code'    => $errCode,
            'errmsg'  => $errmsg,
            'msg'     => $errmsg,
            'count'   => $count,
            'data'    => $data
        ];
        if (!empty($params) && is_array($params)) {
            $result = array_merge($result, $params);
        }
        $result['totalCount'] = $result['count'];
        if ($_GPC['print_result'] == 1) {
            print_r($result);
            $this->_end();
        }
        if (($req->isAjax && $returnType == 'exit') || $returnType == 'exit') {
//            if ($errcode != 0) die(stripslashes(json_encode($result, JSON_UNESCAPED_UNICODE)));
            //  返回封装后的json格式数据
            $response             = Yii::$app->getResponse();
            $response->format     = Response::FORMAT_JSON;
            $response->data       = $result;
            $response->statusCode = 200;

            if ($errCode != 0 && $errCode != 200) {
                $response->send();
                $this->_end(0, $response);
            }

            return $response;
        } else {
            return stripslashes(json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 获取接口返回的数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $data
     *
     * @return array|int|mixed|string
     */
    private function getResponseData($data)
    {
        if (is_array($data) || is_string($data) || is_numeric($data)) return $data;
        if (is_object($data)) {
            if ($data instanceof Response) {
                $this->_end(0, $data);
                return [];
            }
            if (method_exists($data, 'toArray')) return $data->toArray();
        }
        return [];
    }

    /**
     * 获取接口返回的状态码
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $code
     *
     * @return int|mixed|string
     */
    private function getResponseCode($code)
    {
        if (is_numeric($code)) return $code;
        if (is_object($code) && $code instanceof Response) {
            $this->_end(0, $code);
            return intval($code);
        }
        return 0;
    }

    /**
     * 获取接口返回的消息
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:21 上午
     * @param $msg
     *
     * @return string
     */
    private function getResponseMsg($msg): string
    {
        if (is_string($msg)) return $msg;

        if (is_object($msg) && $msg instanceof Response) {
            $this->_end(0, $msg);
            return '';
        }

        return 'ok';
    }

    /**
     * 结束程序
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2021/12/18 12:22 上午
     * @param null $response
     *
     * @param string|int $status
     */
    private function _end($status = 0, $response = null)
    {
        try {
            Yii::$app->end($status, $response);
        } catch (ExitException $e) {
        }
        exit;
    }

    /** 获取图片大小
     * @param string $filename
     * @param array $imageInfo
     * @return array|false
     */
    public function getImageSize(string $filename, array $imageInfo = [])
    {
        $result = @getimagesize($filename, $imageInfo);
        if (empty($result)) {
            $file_content = Communication::get($filename);
            $content      = $file_content['content'];
            $result       = getimagesize('data://image/jpeg;base64,' . base64_encode($content), $imageInfo);
        }
        return $result;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param yii\web\Controller $controller
     * @return array|string|Response
     * @throws \yii\base\InvalidConfigException
     * @lasttime: 2021/12/20 10:00 上午
     */
    public function showCaptcha(Yii\web\Controller $controller)
    {
        global $_GPC;
        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) {
            return $this->result(9001002, '验证码类型不能为空');
        }
        $c            = Yii::createObject('Jcbowen\JcbaseYii2\components\captcha\CaptchaAction', [
            '__' . $type,
            $controller
        ]);
        $c->maxLength = $_GPC['maxLength'] ? intval($_GPC['maxLength']) : 5;
        $c->minLength = $_GPC['minLength'] ? intval($_GPC['minLength']) : 5;;
        $c->height = $_GPC['height'] ? intval($_GPC['height']) : 40;
        $c->width  = $_GPC['width'] ? intval($_GPC['width']) : 120;
        $c->offset = $_GPC['offset'] ? intval($_GPC['offset']) : 9;
        //$c->backColor = 0x000000;
        $c->getVerifyCode(true);
        return $c->run();
    }

    /**
     * 验证传入的验证码是否正确
     * @param string $code 传入的验证码
     * @param yii\web\Controller $controller
     * @return bool
     * @throws \yii\base\InvalidConfigException 控制器中的使用示例 verifyCaptcha($code, new \backend\controllers\utility\CaptchaController('utility/captcha', $this->module));
     */
    public function verifyCaptcha(string $code, Yii\web\Controller $controller)
    {
        global $_GPC;

        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) return $this->result(9001002, '验证码类型不能为空');

        $code = trim($code);
        $code = Safe::gpcString($code);
        $code = strtolower($code);
        if (empty($code)) return $this->result(9001002, '验证码不能为空');
        $verifycode = $this->getCaptcha($controller);
        if ($verifycode == $code) {
            return true;
        }
        return false;
    }

    /**
     * 获取图形验证码
     * @param yii\web\Controller $controller
     * @return string
     * @throws \yii\base\InvalidConfigException 控制器中的使用示例 getCaptcha(new \backend\controllers\utility\CaptchaController('utility/captcha', $this->module));
     */
    public function getCaptcha(Yii\web\Controller $controller)
    {
        global $_GPC;
        $type = Safe::gpcString($_GPC['captchaType']);
        if (empty($type)) return $this->result(9001002, '验证码类型不能为空');

        $c = Yii::createObject('yii\captcha\CaptchaAction', ['__' . $type, $controller]);
        return $c->getVerifyCode();
    }

    /**
     * 测试大妈运行市场
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $tag
     * @return float
     * @lasttime: 2022/9/6 11:37 AM
     */
    public static function test_code_time(int $tag = 0): float
    {
        static $logs = [];
        $time = microtime(true);
        if (empty($logs[$tag])) {
            $logs[$tag] = $time;
            return $time;
        }
        return round($time - $logs[$tag], 3);
    }
}
