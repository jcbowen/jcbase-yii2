<?php

use Jcbowen\JcbaseYii2\components\Agent;
use Jcbowen\JcbaseYii2\components\Util;

global $_B, $_GPC;

// 设备类型
$_B['os'] = Agent::deviceType();
if (Agent::DEVICE_MOBILE == $_B['os']) {
    $_B['os'] = 'mobile';
} elseif (Agent::DEVICE_DESKTOP == $_B['os']) {
    $_B['os'] = 'windows';
} else {
    $_B['os'] = 'unknown';
}

// 连接方式
$_B['siteScheme'] = 'http://';
$_B['isHttps']    = false;
if (Util::isSecureConnection()) {
    $_SERVER['HTTPS'] = 1;
    $_B['isHttps']    = true;
    $_B['siteScheme'] = 'https://';
}

// 初始化$_GPC
$res  = new yii\web\Request();
$_GPC = $res->get();
$_GPC = array_merge($_GPC, $res->post());

if (!empty($res->getRawBody())) {
    if (strpos($res->getContentType(), 'application/json') !== false) {
        $_GPC = array_merge($_GPC, (array)json_decode($res->getRawBody(), true));
    } elseif (strpos($res->getContentType(), 'text/xml') !== false) {
        $_GPC = array_merge($_GPC, (array)Util::xmlToArray($res->getRawBody()));
    }
}

unset($res);

// 默认header
header('Content-Type: text/html; charset=UTF-8');

if (!function_exists('allowCrossDomain')) {
    /**
     * 设置允许跨域的域名
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $domain 域名
     * @param array       $params 自定义跨域参数
     *                            - methods
     *                            - headers
     *
     * @return bool
     * @lasttime: 2021/12/26 10:30 下午
     */
    function allowCrossDomain(?string $domain = '', array $params = []): bool
    {
        global $_B;

        if (empty($domain)) return false;

        // 定义默认参数
        $defaultParams = [
            'methods' => '*',
            'headers' => implode(', ', [
                'Authorization',
                'Accept',
                'Client-Security-Token',
                'Accept-Encoding',
                'Content-Type',
                'Depth',
                'User-Agent',
                'X-File-Size',
                'X-Requested-With',
                'X-Requested-By',
                'If-Modified-Since',
                'X-File-Name',
                'X-File-Type',
                'Cache-Control',
                'Origin',
                'online_token',
                'Referer',
                'User-Agent',
                'JcClient',
                'EnvVersion', // 后期需移除
                'X-Environment',
                'release',
                'releaseCode',
                'X-ResourceVersion',
            ]),
        ];

        // 只保留有效参数
        $params = array_filter($params, function ($v, $k) {
            return in_array($k, [
                'methods',
                'headers',
            ]);
        }, ARRAY_FILTER_USE_BOTH);

        // 如果是传递的数组，则拼接为字符串
        if (!empty($params)) {
            foreach ($params as &$value) {
                if (is_array($value))
                    $value = implode(', ', $value);
            }
        }

        // 合并新的参数到默认值中
        $params = Util::merge($defaultParams, $params);

        header("Access-Control-Allow-Origin: $domain");
        header("Access-Control-Allow-Methods: {$params['methods']}");
        header("Access-Control-Allow-Headers: {$params['headers']}");
        header('Access-Control-Allow-Credentials: true'); // 允许跨域携带cookie
        header('Access-Control-Request-Method: OPTIONS,GET,POST');
        header('Access-Control-Expose-Headers: Authorization');
        if (strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(json_encode(['code' => 200, 'msg' => 'ok']));
        }

        $_B['allowCrossDomain'] = true;
        return true;
    }
}
