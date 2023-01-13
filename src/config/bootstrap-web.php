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
$_B['sitescheme'] = 'http://';
$_B['isHttps']    = false;
if (Util::isSecureConnection()) {
    $_SERVER['HTTPS'] = 1;
    $_B['isHttps']    = true;
    $_B['sitescheme'] = 'https://';
}

header('Content-Type: text/html; charset=UTF-8');

/**
 * 设置允许跨域
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lastTime 2021/12/26 10:30 下午
 * @param string $domain
 *
 * @return bool
 */
if (!function_exists('allowCrossDomain')) {
    function allowCrossDomain(string $domain): bool
    {
        header("Access-Control-Allow-Origin: $domain");
        header('Access-Control-Allow-Methods: *');
//        header('Access-Control-Allow-Methods: GET, HEAD, POST, PUT, DELETE, TRACE, OPTIONS, PATCH');
//        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Headers:Authorization, Accept, Client-Security-Token, Accept-Encoding, Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, X-Requested-By, If-Modified-Since, X-File-Name, X-File-Type, Cache-Control, Origin, online_token, Referer, User-Agent, JcClient");
        header('Access-Control-Allow-Credentials:true');
        header('Access-Control-Request-Method:OPTIONS,GET,POST');
        header('Access-Control-Expose-Headers: Authorization');
        if (strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit(json_encode(['code' => 200, 'msg' => 'ok']));
        }

        return true;
    }
}

