<?php

namespace Jcbowen\JcbaseYii2\components;

use CURLFile;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Class Communication
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:27 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Communication
{
    /**
     * 发起get请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $url
     * @param array $params
     * @return array|false|int|resource|string|null
     * @lasttime: 2022/11/3 17:02
     */
    public static function get($url, array $params = [])
    {
        if (!empty($params)) {
            // 判断url字符串是否已经携带了参数
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url = $url . http_build_query($params);
        }

        return self::request($url);
    }

    /**
     * 发起post请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param string $url 请求地址
     * @param array $data 占位参数以实际情况为准
     *  - 只有一个参数，那么就是data，如果有更多的参数，那么第一个参数是params，第二个参数是data，第三个参数是header
     * 如： post($url, $data); post($url, $params, $data); post($url, $params, $data, $header);
     *
     * @return array|false|int|resource|string|null
     */
    public static function post(string $url, array $data = [])
    {
        $args = func_get_args();
        $url  = array_shift($args);

        if (empty($url)) {
            return false;
        }

        if (empty($args)) {
            return self::request($url, [], ['Content-Type' => 'application/x-www-form-urlencoded']);
        }

        $params = [];
        $body   = [];
        $header = [];

        if (count($args) == 1) {
            $body = (array)$args[0];
        } else {
            foreach ($args as $arg) {
                if (is_array($arg)) {
                    if (empty($params)) {
                        $params = $arg;
                    } elseif (empty($body)) {
                        $body = $arg;
                    } elseif (empty($header)) {
                        $header = $arg;
                        break;
                    }
                } else {
                    return Util::error(ErrCode::PARAMETER_ERROR, '参数格式错误');
                }
            }
        }

        if (!empty($params)) {
            // 判断url字符串是否已经携带了参数
            if (strpos($url, '?') === false) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url = $url . http_build_query($params);
        }

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if (!empty($header)) {
            $headers = ArrayHelper::merge($headers, $header);
        }

        return self::request($url, $body, $headers);
    }

    /**
     * 发起请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param string|array $post 数组格式，要POST请求的数据，上传文件时，传入 ‘@’ 符号 + 文件路径，比如 ‘file’ ⇒ ‘@/root/1.jpg’
     * @param array $extra 请求附加值
     * @param int $timeout 超时时间
     *
     * @param $url
     * @return array|false|int|resource|string|null
     */
    public static function request($url, $post = [], array $extra = [], int $timeout = 60)
    {
        if (function_exists('curl_init') && function_exists('curl_exec') && $timeout > 0) {
            $ch = self::buildCurl($url, $post, $extra, $timeout);
            if (Util::isError($ch)) {
                return $ch;
            }
            $data   = curl_exec($ch);
            $status = curl_getinfo($ch);
            $errno  = curl_errno($ch);
            $error  = curl_error($ch);
            curl_close($ch);
            if ($errno || empty($data)) {
                return Util::error($errno, $error, $status);
            } else {
                return self::responseParse($data);
            }
        }
        $urlSet = self::parseUrl($url, true);
        if (!empty($urlSet['ip'])) {
            $urlSet['host'] = $urlSet['ip'];
        }

        $body = self::buildHttpBody($url, $post, $extra);

        if ('https' == $urlSet['scheme']) {
            $fp = self::socketOpen('ssl://' . $urlSet['host'], $urlSet['port'], $errno, $error);
        } else {
            $fp = self::socketOpen($urlSet['host'], $urlSet['port'], $errno, $error);
        }
        stream_set_blocking($fp, $timeout > 0);
        stream_set_timeout($fp, ini_get('default_socket_timeout'));
        if (!$fp) {
            return Util::error(1, $error);
        } else {
            fwrite($fp, $body);
            $content = '';
            if ($timeout > 0) {
                while (!feof($fp)) {
                    $content .= fgets($fp, 512);
                }
            }
            fclose($fp);

            return self::responseParse($content, true);
        }
    }

    /**
     * 发起批量请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param array $urls
     * @param array $posts
     * @param array $extra
     * @param int $timeout
     * @return array
     */
    public static function multiRequest(array $urls, array $posts = [], array $extra = [], int $timeout = 60): array
    {
        $curl_multi  = curl_multi_init();
        $curl_client = $response = [];

        foreach ($urls as $i => $url) {
            $post = $posts;
            if (isset($posts[$i]) && is_array($posts[$i])) $post = $posts[$i];
            if (!empty($url)) {
                $curl = self::buildCurl($url, $post, $extra, $timeout);
                if (Util::isError($curl)) {
                    continue;
                }
                if (CURLM_OK === curl_multi_add_handle($curl_multi, $curl)) {
                    $curl_client[] = $curl;
                }
            }
        }
        if (!empty($curl_client)) {
            $active = null;
            do {
                $mrc = curl_multi_exec($curl_multi, $active);
            } while (CURLM_CALL_MULTI_PERFORM == $mrc);

            while ($active && CURLM_OK == $mrc) {
                do {
                    $mrc = curl_multi_exec($curl_multi, $active);
                } while (CURLM_CALL_MULTI_PERFORM == $mrc);
            }
        }

        foreach ($curl_client as $i => $curl) {
            $response[$i] = curl_multi_getcontent($curl);
            curl_multi_remove_handle($curl_multi, $curl);
        }
        curl_multi_close($curl_multi);

        return $response;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param int $port
     * @param null $error_code
     * @param null $error_message
     * @param int $timeout
     *
     * @param $hostname
     * @return false|resource|string
     */
    public static function socketOpen($hostname, int $port = 80, &$error_code = null, &$error_message = null, int $timeout = 15)
    {
        $fp = '';
        if (function_exists('fsockopen')) {
            $fp = @fsockopen($hostname, $port, $error_code, $error_message, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = @pfsockopen($hostname, $port, $error_code, $error_message, $timeout);
        } elseif (function_exists('stream_socket_client')) {
            $fp = @stream_socket_client($hostname . ':' . $port, $error_code, $error_message, $timeout);
        }

        return $fp;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param $data
     * @param bool $chunked
     * @return array
     */
    public static function responseParse($data, bool $chunked = false): array
    {
        $rlt = [];

        $pos       = strpos($data, "\r\n\r\n");
        $split1[0] = substr($data, 0, $pos);
        $split1[1] = substr($data, $pos + 4, strlen($data));

        $split2 = explode("\r\n", $split1[0], 2);
        preg_match('/^(\S+) (\S+) (.*)$/', $split2[0], $matches);
        $rlt['code']         = !empty($matches[2]) ? $matches[2] : 200;
        $rlt['status']       = !empty($matches[3]) ? $matches[3] : 'OK';
        $rlt['responseline'] = !empty($split2[0]) ? $split2[0] : '';
        $header              = explode("\r\n", $split2[1]);
        $isgzip              = false;
        $ischunk             = false;
        foreach ($header as $v) {
            $pos   = strpos($v, ':');
            $key   = substr($v, 0, $pos);
            $value = trim(substr($v, $pos + 1));
            if (isset($rlt['headers'][$key]) && is_array($rlt['headers'][$key])) {
                $rlt['headers'][$key][] = $value;
            } elseif (!empty($rlt['headers'][$key])) {
                $temp = $rlt['headers'][$key];
                unset($rlt['headers'][$key]);
                $rlt['headers'][$key][] = $temp;
                $rlt['headers'][$key][] = $value;
            } else {
                $rlt['headers'][$key] = $value;
            }
            if (!$isgzip && 'content-encoding' == strtolower($key) && 'gzip' == strtolower($value)) {
                $isgzip = true;
            }
            if (!$ischunk && 'transfer-encoding' == strtolower($key) && 'chunked' == strtolower($value)) {
                $ischunk = true;
            }
        }
        if ($chunked && $ischunk) {
            $rlt['content'] = self::responseParseUnChunk($split1[1]);
        } else {
            $rlt['content'] = $split1[1];
        }
        if ($isgzip && function_exists('gzdecode')) {
            $rlt['content'] = gzdecode($rlt['content']);
        }

        $rlt['meta'] = $data;
        if ('100' == $rlt['code']) {
            return self::responseParse($rlt['content']);
        }

        return $rlt;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:22 下午
     * @param null $str
     *
     * @return false|string
     */
    public static function responseParseUnChunk($str = null)
    {
        if (!is_string($str) or strlen($str) < 1) {
            return false;
        }
        $eol = "\r\n";
        $add = strlen($eol);
        $tmp = $str;
        $str = '';
        do {
            $tmp = ltrim($tmp);
            $pos = strpos($tmp, $eol);
            if (false === $pos) {
                return false;
            }
            $len = hexdec(substr($tmp, 0, $pos));
            if (!is_numeric($len) or $len < 0) {
                return false;
            }
            $str   .= substr($tmp, ($pos + $add), $len);
            $tmp   = substr($tmp, ($len + $pos + $add));
            $check = trim($tmp);
        } while (!empty($check));
        unset($tmp);

        return $str;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:22 下午
     * @param bool $set_default_port
     * @param $url
     * @return array|int|string
     */
    public static function parseUrl($url, bool $set_default_port = false)
    {
        if (empty($url)) {
            return Util::error(1, 'url为空');
        }
        $urlSet = parse_url($url);
        if (!empty($urlSet['scheme']) && !in_array($urlSet['scheme'], array('http', 'https'))) {
            return Util::error(1, '只能使用 http 及 https 协议');
        }
        if (empty($urlSet['path'])) {
            $urlSet['path'] = '/';
        }
        if (!empty($urlSet['query'])) {
            $urlSet['query'] = "?{$urlSet['query']}";
        }
        if (Util::strExists($url, 'https://') && !extension_loaded('openssl')) {
            return Util::error(1, '请开启您PHP环境的openssl', '');
        }
        if (empty($urlSet['host'])) {
            $current_url      = parse_url($GLOBALS['_B']['siteRoot']);
            $urlSet['host']   = $current_url['host'];
            $urlSet['scheme'] = $current_url['scheme'];
            $urlSet['path']   = $current_url['path'] . '/' . str_replace('./', '', $urlSet['path']);
            $urlSet['ip']     = '127.0.0.1';
        }

        if ($set_default_port && empty($urlSet['port'])) {
            $urlSet['port'] = 'https' == $urlSet['scheme'] ? '443' : '80';
        }

        return $urlSet;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:22 下午
     * @param string|array $post
     * @param $extra
     * @param $timeout
     *
     * @param $url
     * @return array|false|int|resource|string|null
     */
    public static function buildCurl($url, $post, $extra, $timeout)
    {
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            return Util::error(1, 'curl扩展未开启');
        }

        $urlSet = self::parseUrl($url);
        if (Util::isError($urlSet)) {
            return $urlSet;
        }

        if (!empty($urlSet['ip'])) {
            $extra['ip'] = $urlSet['ip'];
        }

        $ch = curl_init();
        if (!empty($extra['ip'])) {
            $extra['Host']  = $urlSet['host'];
            $urlSet['host'] = $extra['ip'];
            unset($extra['ip']);
        }
        curl_setopt($ch, CURLOPT_URL, $urlSet['scheme'] . '://' . $urlSet['host'] . (empty($urlSet['port']) || '80' == $urlSet['port'] ? '' : ':' . $urlSet['port']) . $urlSet['path'] . (!empty($urlSet['query']) ? $urlSet['query'] : ''));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        @curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        if ($post) {
            if (is_array($post)) {
                $filePost = false;
                foreach ($post as $name => &$value) {
                    if (version_compare(phpversion(), '5.5') >= 0 && is_string($value) && '@' == substr($value, 0, 1)) {
                        $post[$name] = new CURLFile(ltrim($value, '@'));
                    }
                    if ((is_string($value) && '@' == substr($value, 0, 1)) || (class_exists('CURLFile') && $value instanceof CURLFile)) {
                        $filePost = true;
                    }
                }
                if (!$filePost) {
                    $post = http_build_query($post);
                }
            }
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        // 代理
        /*if (!empty($GLOBALS['_B']['config']['setting']['proxy'])) {
            $urls = parse_url($GLOBALS['_B']['config']['setting']['proxy']['host']);
            if (!empty($urls['host'])) {
                curl_setopt($ch, CURLOPT_PROXY, "{$urls['host']}:{$urls['port']}");
                $proxytype = 'CURLPROXY_' . strtoupper($urls['scheme']);
                if (!empty($urls['scheme']) && defined($proxytype)) {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, constant($proxytype));
                } else {
                    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
                }
                if (!empty($GLOBALS['_B']['config']['setting']['proxy']['auth'])) {
                    curl_setopt($ch, CURLOPT_PROXYUSERPWD, $GLOBALS['_B']['config']['setting']['proxy']['auth']);
                }
            }
        }*/
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        if (defined('CURL_SSLVERSION_TLSv1')) {
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        if (!empty($extra) && is_array($extra)) {
            $headers = [];
            unset($value);
            foreach ($extra as $opt => $value) {
                if (Util::strExists($opt, 'CURLOPT_')) {
                    curl_setopt($ch, constant($opt), $value);
                } elseif (is_numeric($opt)) {
                    curl_setopt($ch, $opt, $value);
                } else {
                    $headers[] = "$opt: $value";
                }
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        }

        return $ch;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:22 下午
     * @param $post
     * @param $extra
     *
     * @param $url
     * @return array|int|string
     */
    public static function buildHttpBody($url, $post, $extra)
    {
        $urlset = self::parseUrl($url, true);
        if (Util::isError($urlset)) {
            return $urlset;
        }

        if (!empty($urlset['ip'])) {
            $extra['ip'] = $urlset['ip'];
        }

        $body     = '';
        $boundary = Util::random(40);
        if (!empty($post) && is_array($post)) {
            $filePost = false;
            foreach ($post as $name => $value) {
                if ((is_string($value) && '@' == substr($value, 0, 1)) && file_exists(Yii::getAlias($value))) {
                    $filePost = true;
                    $file     = ltrim($value, '@');

                    $body .= "--$boundary\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . basename($file) . '"; Content-Type: application/octet-stream' . "\r\n\r\n";
                    $body .= file_get_contents($file) . "\r\n";
                } else {
                    $body .= "--$boundary\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
                    $body .= $value . "\r\n";
                }
            }
            if (!$filePost) {
                $body = http_build_query($post);
            } else {
                $body .= "--$boundary\r\n";
            }
        }

        $method = empty($post) ? 'GET' : 'POST';
        $fData  = "$method {$urlset['path']}{$urlset['query']} HTTP/1.1\r\n";
        $fData  .= "Accept: */*\r\n";
        $fData  .= "Accept-Language: zh-cn\r\n";
        if ('POST' == $method) {
            $fData .= empty($filePost) ? "Content-Type: application/x-www-form-urlencoded\r\n" : "Content-Type: multipart/form-data; boundary=$boundary\r\n";
        }
        $fData .= "Host: {$urlset['host']}\r\n";
        $fData .= "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1\r\n";
        if (function_exists('gzdecode')) {
            $fData .= "Accept-Encoding: gzip, deflate\r\n";
        }
        $fData .= "Connection: close\r\n";
        if (!empty($extra) && is_array($extra)) {
            unset($value);
            foreach ($extra as $opt => $value) {
                if (!Util::strExists($opt, 'CURLOPT_')) {
                    $fData .= "$opt: $value\r\n";
                }
            }
        }
        if ($body) {
            $fData .= 'Content-Length: ' . strlen($body) . "\r\n\r\n$body";
        } else {
            $fData .= "\r\n";
        }

        return $fData;
    }
}


