<?php

use Jcbowen\JcbaseYii2\components\Agent;
use Jcbowen\JcbaseYii2\components\Util;

const REGULAR_EMAIL    = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/i';
const REGULAR_MOBILE   = '/^\d{6,15}$/';
const REGULAR_USERNAME = '/^[\x{4e00}-\x{9fa5}a-z\d_\.]{3,30}$/iu';

const PASSWORD_STRONG_STATE   = '密码至少8-16个字符，至少1个大写字母，1个小写字母和1个数字';
const PASSWORD_STRONG_REGULAR = '/(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,30}/';

const TEMPLATE_DISPLAY     = 0;
const TEMPLATE_FETCH       = 1;
const TEMPLATE_INCLUDEPATH = 2;

const ATTACH_FTP   = 1;
const ATTACH_OSS   = 2;
const ATTACH_QINIU = 3;
const ATTACH_COS   = 4;

const ATTACH_TYPE_IMAGE  = 1;
const ATTACH_TYPE_VOICE  = 2;
const ATTACH_TYPE_VEDIO  = 3;
const ATTACH_TYPE_NEWS   = 4;
const ATTACH_TYPE_OFFICE = 5;
const ATTACH_TYPE_ZIP    = 6;

const NO_TIME = '0000-00-00 00:00:00';

define('STARTTIME', microtime());
define('MAGIC_QUOTES_GPC', ini_set("magic_quotes_runtime", 0) ? true : false);

$_B = $_GPC = [];

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

$res  = new yii\web\Request();
$_GPC = $res->get();
$_GPC = array_merge($_GPC, $res->post());
if (strpos($res->getContentType(), 'application/json') !== false) {
    $_GPC = array_merge($_GPC, (array)json_decode($res->getRawBody(), true));
}
unset($apps, $app, $res);

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
        header("Access-Control-Allow-Origin: {$domain}");
        header('Access-Control-Allow-Methods: *');
//        header('Access-Control-Allow-Methods: GET, HEAD, POST, PUT, DELETE, TRACE, OPTIONS, PATCH');
//        header('Access-Control-Allow-Headers:*');
        header("Access-Control-Allow-Headers:Authorization, Accept, Client-Security-Token, Accept-Encoding, Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, X-Requested-By, If-Modified-Since, X-File-Name, X-File-Type, Cache-Control, Origin, online_token, Referer, User-Agent, JcsiteClient");
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

