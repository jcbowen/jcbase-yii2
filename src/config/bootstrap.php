<?php

const REGULAR_EMAIL    = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/i';
const REGULAR_MOBILE   = '/^\d{6,15}$/';
const REGULAR_USERNAME = '/^[\x{4e00}-\x{9fa5}a-z\d_\.]{3,30}$/iu';

const PASSWORD_STRONG_STATE   = '密码至少8-16个字符，至少1个大写字母，1个小写字母和1个数字';
const PASSWORD_STRONG_REGULAR = '/(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,30}/';

const TEMPLATE_DISPLAY     = 0;
const TEMPLATE_FETCH       = 1;
const TEMPLATE_INCLUDEPATH = 2;

// 附件存储方式
const ATTACH_FTP   = 1;
const ATTACH_OSS   = 2;
const ATTACH_QINIU = 3;
const ATTACH_COS   = 4;

// 附件类型
const ATTACH_TYPE_IMAGE  = 1;
const ATTACH_TYPE_VOICE  = 2;
const ATTACH_TYPE_VIDEO  = 3;
const ATTACH_TYPE_NEWS   = 4;
const ATTACH_TYPE_OFFICE = 5;
const ATTACH_TYPE_ZIP    = 6;

// IN_CLIENT 默认为 false
defined("IN_CLIENT") or define("IN_CLIENT", false);

// 客户端类型
const CLIENT_UNKNOWN               = 0;
const CLIENT_ANDROID               = 1;
const CLIENT_IOS                   = 2;
const CLIENT_PC                    = 3;
const CLIENT_H5                    = 4;
const CLIENT_WECHAT                = 5;
const CLIENT_WECHAT_MINI_PROGRAM   = 6;
const CLIENT_WECHAT_WORK           = 7;
const CLIENT_ALIPAY                = 8;
const CLIENT_ALIPAY_MINI_PROGRAM   = 9;
const CLIENT_BAIDU_SMART_PROGRAM   = 10;
const CLIENT_BYTEDANCE_MICRO_APP   = 11;
const CLIENT_QUICK_APP             = 12;
const CLIENT_QQ                    = 13;
const CLIENT_QQ_MINI_PROGRAM       = 14;
const CLIENT_360_MINI_PROGRAM      = 15;
const CLIENT_KUAISHOU_MINI_PROGRAM = 16;
const CLIENT_FEISHU                = 17;
const CLIENT_FEISHU_MICRO_APP      = 18;
const CLIENT_DINGTALK              = 19;
const CLIENT_DINGTALK_MINI_PROGRAM = 20;
const CLIENT_JINGDONG_MINI_PROGRAM = 21;
const CLIENT_LIST                  = [
    CLIENT_UNKNOWN               => '未知',
    CLIENT_ANDROID               => '安卓',
    CLIENT_IOS                   => 'IOS',
    CLIENT_PC                    => 'PC',
    CLIENT_H5                    => 'H5',
    CLIENT_WECHAT                => '微信公众号',
    CLIENT_WECHAT_MINI_PROGRAM   => '微信小程序',
    CLIENT_WECHAT_WORK           => '企业微信',
    CLIENT_ALIPAY                => '支付宝',
    CLIENT_ALIPAY_MINI_PROGRAM   => '支付宝小程序',
    CLIENT_BAIDU_SMART_PROGRAM   => '百度小程序',
    CLIENT_BYTEDANCE_MICRO_APP   => '字节跳动小程序',
    CLIENT_QUICK_APP             => '快应用',
    CLIENT_QQ                    => 'QQ',
    CLIENT_QQ_MINI_PROGRAM       => 'QQ小程序',
    CLIENT_360_MINI_PROGRAM      => '360小程序',
    CLIENT_KUAISHOU_MINI_PROGRAM => '快手小程序',
    CLIENT_FEISHU                => '飞书',
    CLIENT_FEISHU_MICRO_APP      => '飞书小程序',
    CLIENT_DINGTALK              => '钉钉',
    CLIENT_DINGTALK_MINI_PROGRAM => '钉钉小程序',
    CLIENT_JINGDONG_MINI_PROGRAM => '京东小程序',
];

// 空时间字符串（一般用于时间字段表示为空，如：deleted_at为NO_TIME，就代表没有删除）
const NO_TIME = '0000-00-00 00:00:00';

define('MAGIC_QUOTES_GPC', (bool)ini_set("magic_quotes_runtime", 0));

global $_B;
$_B['params'] = [];
