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
    public $type = '';

    public function __construct()
    {
        $smsConfig = Yii::$app->params['SmsConfig'];
        if (empty($this->type) && !empty($smsConfig['type'])) {
            $this->type = $smsConfig['type'];
        } elseif (empty($this->type)) {
            $this->type = 'YunTongXun';
        }
    }

    /**
     * 发送短信
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $mobile
     * @param array $content
     * @param string $templateId
     * @return array|\stdClass|string
     * @lasttime: 2022/10/15 11:31
     */
    public function send(string $mobile,array $content, string $templateId)
    {
        switch ($this->type) {
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

