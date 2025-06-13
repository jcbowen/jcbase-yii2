<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Util;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Sms\V20210111\SmsClient;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;
use Yii;
use yii\helpers\FileHelper;

/**
 * 腾讯云短信SDK封装
 * Class SmsTencent
 *
 * @author  Bowen
 * @email bowen@jiuchet.com
 * @package Jcbowen\JcbaseYii2\components\sdk
 */
class SmsTencent
{
    public $SecretId;
    public $SecretKey;
    public $SmsSdkAppId;
    public $SignName;
    public $Region = 'ap-guangzhou';
    public $enableLog = true;
    public $logPath = "@runtime/logs/sms-tencent/{date|Y/m-d}.txt";

    private $Handle;

    public function __construct()
    {
        $timeStamp = time();
        $regex     = '/\{date\|([^}]+)}/';
        if (preg_match($regex, $this->logPath, $matches)) {
            $dateFormat    = $matches[1];
            $this->logPath = preg_replace($regex, date($dateFormat, $timeStamp), $this->logPath);
        }
        $fileName = Yii::getAlias($this->logPath);
        if (!file_exists($fileName)) {
            FileHelper::createDirectory(dirname($fileName));
        }
        $this->Handle = fopen($fileName, 'a');
    }

    /**
     * 打印日志
     *
     * @param string $log 日志内容
     */
    private function showLog(string $log)
    {
        if ($this->enableLog) {
            fwrite($this->Handle, date('Y-m-d H:i:s') . " " . $log . "\n");
        }
    }

    /**
     * 发送短信
     *
     * @param string $mobile     手机号码
     * @param array  $content    短信内容数组
     * @param string $templateId 模板ID
     *
     * @return array
     */
    public function send(string $mobile, array $content, string $templateId)
    {
        try {
            // 参数校验
            $auth = $this->accAuth();
            if ($auth !== "") {
                return $auth;
            }

            // 实例化一个认证对象
            $cred = new Credential($this->SecretId, $this->SecretKey);

            // 实例化一个http选项
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("sms.tencentcloudapi.com");

            // 实例化一个client选项
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);

            // 实例化SMS的client对象
            $client = new SmsClient($cred, $this->Region, $clientProfile);

            // 实例化一个请求对象
            $req = new SendSmsRequest();

            $params = [
                "PhoneNumberSet"   => ["+86" . $mobile],
                "SmsSdkAppId"      => $this->SmsSdkAppId,
                "SignName"         => $this->SignName,
                "TemplateId"       => $templateId,
                "TemplateParamSet" => $content
            ];

            $req->fromJsonString(json_encode($params));

            $this->showLog("Request params: " . json_encode($params, JSON_UNESCAPED_UNICODE));

            // 发起请求
            $resp = $client->SendSms($req);

            $this->showLog("Response: " . $resp->toJsonString());

            // 处理响应
            $sendStatus = $resp->getSendStatusSet()[0];
            if ($sendStatus->Code === "Ok") {
                return Util::error(0, '发送成功', [
                    'messageId' => $sendStatus->SerialNo,
                    'mobile'    => $mobile,
                    'response'  => $resp->toJsonString()
                ]);
            } else {
                return Util::error(ErrCode::UNKNOWN, $sendStatus->Message, [
                    'code'     => $sendStatus->Code,
                    'message'  => $sendStatus->Message,
                    'response' => $resp->toJsonString()
                ]);
            }

        } catch (TencentCloudSDKException $e) {
            $this->showLog("Error: " . $e->getMessage());
            return Util::error(ErrCode::UNKNOWN, $e->getMessage(), [
                'code'      => $e->getErrorCode(),
                'message'   => $e->getMessage(),
                'requestId' => $e->getRequestId()
            ]);
        }
    }

    /**
     * 参数验证
     *
     * @return array|string
     */
    private function accAuth()
    {
        if (empty($this->SecretId)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'SecretId为空');
        }
        if (empty($this->SecretKey)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'SecretKey为空');
        }
        if (empty($this->SmsSdkAppId)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'SmsSdkAppId为空');
        }
        if (empty($this->SignName)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'SignName为空');
        }
        return "";
    }
} 