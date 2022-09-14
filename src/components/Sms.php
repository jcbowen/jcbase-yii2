<?php

namespace Jcbowen\JcbaseYii2\components;

use Jcbowen\JcbaseYii2\components\sdk\SmsYunTongXun;
use Yii;

/**
 * Class Sms
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/9/14 9:11 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class Sms
{
    public static $type = '';

    public function __construct()
    {
        $smsConfig = Yii::$app->params['SmsConfig'];
        if (empty(self::$type) && !empty($smsConfig['type'])) {
            self::$type = $smsConfig['type'];
        } elseif (empty(self::$type)) {
            self::$type = 'YunTongXun';
        }
    }

    public function send($mobile, $content, $templateId)
    {
        switch (self::$type) {
            case 'YunTongXun':
                $sms = new SmsYunTongXun();
                foreach (Yii::$app->params['SmsConfig']['YunTongXun'] as $key => $value) {
                    $sms->$key = $value;
                }
                return $sms->send($mobile, $content, $templateId);
            default:
                return Util::error(ErrCode::NOT_SUPPORTED, '暂不支持该类型的短信接口');
        }
    }
}

