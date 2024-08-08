<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Util;
use stdClass;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;

/**
 * 云通讯sdk
 * Class SmsYunTongXun
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/9/13 4:09 PM
 */
class SmsYunTongXun
{
    public $AccountSid;
    public $AccountToken;
    public $AppId;
    public $ServerIP = 'app.cloopen.com';
    public $ServerPort = '8883';
    public $SoftVersion = '2013-12-26';
    public $BodyType = "json"; //包体格式，可填值：json 、xml
    public $enableLog = true; //日志开关。可填值：true、
    public $logPath = "@runtime/logs/sms-ytx/{date|Y/m-d}.txt"; //日志文件

    private $Batch;  // 时间sh
    private $Handle; // 句柄

    public function __construct()
    {
        $timeStamp = time();

        $this->Batch = date("YmdHis", $timeStamp);

        $regex = '/\{date\|([^}]+)}/';
        if (preg_match($regex, $this->logPath, $matches)) {
            // 获取日期格式
            $dateFormat = $matches[1];
            // 替换模板中的 {date|格式} 部分
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
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $log 日志内容
     * @lasttime: 2022/9/13 4:15 PM
     */
    private function showLog(string $log)
    {
        if ($this->enableLog) {
            fwrite($this->Handle, $log . "\n");
        }
    }

    /**
     * 发起HTTPS请求
     */
    private function curl_post($url, $data, $header)
    {//初始化curl
        $ch = curl_init();
        //参数设置
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        if (1) curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $result = curl_exec($ch);
        //连接失败
        if (!$result) {
            if ($this->BodyType == 'json') {
                $result = "{\"statusCode\":\"172001\",\"statusMsg\":\"网络错误\"}";
            } else {
                $result = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?><Response><statusCode>172001</statusCode><statusMsg>网络错误</statusMsg></Response>";
            }
        }

        curl_close($ch);
        return $result;
    }

    /**
     * 发送模板短信
     * @param string $to 短信接收彿手机号码集合,用英文逗号分开
     * @param array $datas 内容数据
     * @param string $tempId 模板Id
     */
    public function send(string $to, array $datas, string $tempId)
    {
        //主帐号鉴权信息验证，对必选参数进行判空。
        $auth = $this->accAuth();
        if ($auth != "") {
            return $auth;
        }
        // 拼接请求包体
        $data = "";
        if ($this->BodyType == "json") {
            for ($i = 0; $i < count($datas); $i++) {
                if ($i == 0) {
                    $data = $data . "'" . $datas[$i] . "'";
                } else {
                    $data = $data . ",'" . $datas[$i] . "'";
                }
            }
            $body = "{'to':'$to','templateId':'$tempId','appId':'$this->AppId','datas':[" . $data . "]}";
        } else {
            for ($i = 0; $i < count($datas); $i++) {
                $data = $data . "<data>" . $datas[$i] . "</data>";
            }
            $body = "<TemplateSMS>
                    <to>$to</to> 
                    <appId>$this->AppId</appId>
                    <templateId>$tempId</templateId>
                    <datas>" . $data . "</datas>
                  </TemplateSMS>";
        }
        $this->showLog("request body = " . $body);
        // 大写的sig参数
        $sig = strtoupper(md5($this->AccountSid . $this->AccountToken . $this->Batch));
        // 生成请求URL
        $url = "https://$this->ServerIP:$this->ServerPort/$this->SoftVersion/Accounts/$this->AccountSid/SMS/TemplateSMS?sig=$sig";
        $this->showLog("request url = " . $url);
        // 生成授权：主帐户Id + 英文冒号 + 时间戳。
        $authen = base64_encode($this->AccountSid . ":" . $this->Batch);
        // 生成包头
        $header = array(
            "Accept:application/$this->BodyType",
            "Content-Type:application/$this->BodyType;charset=utf-8",
            "Authorization:$authen"
        );
        // 发送请求
        $result = $this->curl_post($url, $body, $header);
        $this->showLog("response body = " . $result);
        if ($this->BodyType == "json") {//JSON格式
            $datas = json_decode($result);
        } else { //xml格式
            $datas = simplexml_load_string(trim($result, " \t\n\r"));
        }
        //重新装填数据
        if ($datas->statusCode == 0) {
            if ($this->BodyType == "json") {
                $datas->TemplateSMS = $datas->templateSMS;
                unset($datas->templateSMS);
            }
            return Util::error(0, '发送成功', ArrayHelper::toArray($datas));
        } else {
            return Util::error($datas->statusCode, $datas->statusMsg, ArrayHelper::toArray($datas));
        }
    }

    /**
     * 主帐号鉴权
     */
    private function accAuth()
    {
        if (empty($this->ServerIP)) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '服务地址(serverIP)为空';
            return $data;
        }
        if ($this->ServerPort <= 0) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '端口(ServerPort)错误（小于等于0）';
            return $data;
        }
        if (empty($this->SoftVersion)) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '版本号(SoftVersion)为空';
            return $data;
        }
        if (empty($this->AccountSid)) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '主帐号(AccountSid)为空';
            return $data;
        }
        if (empty($this->AccountToken)) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '主帐号令牌(AccountToken)为空';
            return $data;
        }
        if (empty($this->AppId)) {
            $data             = new stdClass();
            $data->statusCode = ErrCode::PARAMETER_ERROR;
            $data->statusMsg  = '应用ID(AppId)为空';
            return $data;
        }
        return "";
    }
}
