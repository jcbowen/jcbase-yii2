<?php

namespace Jcbowen\JcbaseYii2\components;

use CURLFile;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Utils;
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
    private static $client;

    /**
     * 初始化 Guzzle 客户端
     */
    private static function initClient()
    {
        if (!self::$client) {
            self::$client = new Client([
                'timeout' => 60,
                'verify'  => false,
            ]);
        }
    }

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
        self::initClient();
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
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
        self::initClient();
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
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }

        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if (!empty($header)) {
            $headers = ArrayHelper::merge($headers, $header);
        }

        return self::request($url, $body, $headers);
    }

    /**
     * 发起http/https请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     * @param string|array $post 数组格式，要POST请求的数据，上传文件时，传入 ‘@’ 符号 + 文件路径，比如 ‘file’ ⇒ ‘@/root/1.jpg’
     * @param array $extra 请求附加值
     * @param int $timeout 超时时间
     *
     * @param $url
     * @param array $post
     * @param array $extra
     * @param int $timeout
     * @return array|false|int|resource|string|null
     */
    public static function request($url, $post = [], array $extra = [], int $timeout = 60)
    {
        self::initClient();

        $options = [
            'headers' => is_array($extra) ? $extra : [],
        ];

        // 处理上传文件和 JSON 数据
        if (!empty($post)) {
            if (is_string($post)) {
                if (Util::is_json($post)) {
                    $options['body']                    = $post;
                    $options['headers']['Content-Type'] = 'application/json';
                } else {
                    $options['form_params'] = $post;
                }
            } elseif (is_array($post)) {
                $isFile = false;
                foreach ($post as $key => $value) {
                    if (is_string($value) && strpos($value, '@') === 0) {
                        $post[$key] = Utils::tryFopen(ltrim($value, '@'), 'r');
                        $isFile     = true;
                    }
                }
                if ($isFile) {
                    $options['multipart'] = [];
                    foreach ($post as $name => $content) {
                        $options['multipart'][] = [
                            'name'     => $name,
                            'contents' => $content
                        ];
                    }
                } else {
                    $contentType = $options['headers']['Content-Type'] ?? 'application/x-www-form-urlencoded';
                    if (strpos($contentType, 'application/json') !== false) {
                        $options['json'] = $post;
                    } else {
                        $options['form_params'] = $post;
                    }
                }
            }
        }

        try {
            $response   = self::$client->request(empty($post) ? 'GET' : 'POST', $url, $options);
            $statusCode = $response->getStatusCode();
            $body       = $response->getBody()->getContents();

            return [
                'code'         => $statusCode,
                'status'       => $response->getReasonPhrase(),
                'responseline' => $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
                'headers'      => $response->getHeaders(),
                'content'      => $body,
                'meta'         => $response,
            ];
        } catch (\Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 发起批量请求
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @lastTime 2022/1/14 11:21 下午
     *
     * @param array $urls
     * @param array $posts
     * @param array $extra
     * @param int $timeout
     * @return array
     */
    public static function multiRequest(array $urls, array $posts = [], array $extra = [], int $timeout = 60): array
    {
        self::initClient();

        $promises = [];
        foreach ($urls as $i => $url) {
            $options = [
                'headers' => is_array($extra) ? $extra : [],
                'timeout' => $timeout,
            ];
            if (isset($posts[$i])) {
                if (is_string($posts[$i])) {
                    if (Util::is_json($posts[$i])) {
                        $options['body']                    = $posts[$i];
                        $options['headers']['Content-Type'] = 'application/json';
                    } else {
                        $options['form_params'] = $posts[$i];
                    }
                } elseif (is_array($posts[$i])) {
                    $isFile = false;
                    foreach ($posts[$i] as $key => $value) {
                        if (is_string($value) && strpos($value, '@') === 0) {
                            $posts[$i][$key] = Utils::tryFopen(ltrim($value, '@'), 'r');
                            $isFile          = true;
                        }
                    }
                    if ($isFile) {
                        $options['multipart'] = [];
                        foreach ($posts[$i] as $name => $content) {
                            $options['multipart'][] = [
                                'name'     => $name,
                                'contents' => $content
                            ];
                        }
                    } else {
                        $contentType = $options['headers']['Content-Type'] ?? 'application/x-www-form-urlencoded';
                        if (strpos($contentType, 'application/json') !== false) {
                            $options['json'] = $posts[$i];
                        } else {
                            $options['form_params'] = $posts[$i];
                        }
                    }
                }
            }
            $promises[] = self::$client->requestAsync(isset($posts[$i]) ? 'POST' : 'GET', $url, $options);
        }

        $results   = Promise\Utils::settle($promises)->wait();
        $responses = [];

        foreach ($results as $result) {
            if ($result['state'] === 'fulfilled') {
                $response   = $result['value'];
                $statusCode = $response->getStatusCode();
                $body       = $response->getBody()->getContents();

                $responses[] = [
                    'code'         => $statusCode,
                    'status'       => $response->getReasonPhrase(),
                    'responseline' => $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
                    'headers'      => $response->getHeaders(),
                    'content'      => $body,
                    'meta'         => $response,
                ];
            } else {
                $responses[] = Util::error($result['reason']->getCode(), $result['reason']->getMessage());
            }
        }

        return $responses;
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $url
     * @param string|array $message
     * @param int $timeout
     * @return array|string
     * @lasttime: 2022/11/30 11:57 AM
     */
    public static function tcpRequest($url, $message = '', int $timeout = 60)
    {
        $urlSet = self::parseUrl($url, true);
        if (!empty($urlSet['ip']))
            $urlSet['host'] = $urlSet['ip'];

        $fp = self::socketOpen($urlSet['host'], $urlSet['port'], $errno, $error);
        stream_set_blocking($fp, $timeout > 0);
        stream_set_timeout($fp, ini_get('default_socket_timeout'));
        if (!$fp) {
            return Util::error($errno, $error);
        } else {
            $message = is_string($message) ? $message : json_encode($message);
            fwrite($fp, $message);
            $content = '';
            if ($timeout > 0) {
                while (!feof($fp)) {
                    $content .= fgets($fp, 128);
                }
            }
            fclose($fp);

            return $content;
        }
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
            return Util::error(ErrCode::PARAMETER_ERROR, 'url为空');
        }
        $urlSet = parse_url($url);
        if (!empty($urlSet['scheme']) && !in_array($urlSet['scheme'], ['http', 'https', 'tcp'])) {
            return Util::error(ErrCode::PARAMETER_ERROR, '只能使用 http / https / tcp 协议');
        }
        if (empty($urlSet['path'])) {
            $urlSet['path'] = '/';
        }
        if (!empty($urlSet['query'])) {
            $urlSet['query'] = "?{$urlSet['query']}";
        }
        if (Util::strExists($url, 'https://') && !extension_loaded('openssl')) {
            return Util::error(ErrCode::NO_CONFIG, '请开启您PHP环境的openssl', '');
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
            return Util::error(ErrCode::NO_CONFIG, 'curl扩展未开启');
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
