<?php

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

define('MAGIC_QUOTES_GPC', (bool)ini_set("magic_quotes_runtime", 0));

//----- 初始化附件域名配置 -----/
if (empty(Yii::$app->params['domain']['attachment_local'])) {
    Yii::$app->params['domain']['attachment_local'] = Util::getSiteRoot();
}
if (empty(Yii::$app->params['domain']['attachment'])) {
    Yii::$app->params['domain']['attachment'] = Yii::$app->params['domain']['attachment_local'];
}

