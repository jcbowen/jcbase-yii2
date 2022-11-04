<?php

namespace Jcbowen\JcbaseYii2\components\sdk\Aip;

use Jcbowen\JcbaseYii2\components\Communication;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Util;
use Yii;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\FileHelper;

class Base extends Component
{
    protected $action;
    protected $args;
    protected $errors = [];

    protected $apiKey;
    protected $apiSecret;

    protected $authFile = '@runtime/baiduAipAuth/';

    public function init()
    {
        parent::init();

        $this->authFile = Yii::getAlias($this->authFile . md5($this->apiKey));
    }

    public function Auth($refresh = false)
    {
        if (!$refresh) {
            $obj = $this->readAuthObj();
            if (!empty($obj)) {
                return $obj;
            }
        }
        $apiUrl   = 'https://aip.baidubce.com/oauth/2.0/token';
        $response = Communication::post($apiUrl, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->apiKey,
            'client_secret' => $this->apiSecret,
        ]);

        if ($response['code'] != 200) {
            return Util::error(ErrCode::NETWORK_ERROR, '网络错误', $response);
        }

        $content = $response['content'];
        if (empty($content))
            return Util::error(ErrCode::UNKNOWN, '网络请求成功，但未获取到返回内容', $response);

        $obj = @json_decode($content, true);
        $this->writeAuthObj($obj);
        return $obj;
    }

    /**
     * 写入本地缓存文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $obj
     * @lasttime: 2022/11/3 14:30
     */
    private function writeAuthObj(array $obj)
    {
        if ((isset($obj['is_read']) && $obj['is_read'] === true)) {
            return;
        }
        if (!is_dir(dirname($this->authFile))) {
            try {
                FileHelper::createDirectory(dirname($this->authFile));
            } catch (Exception $e) {
            }
        }
        $obj['time'] = time();
        @file_put_contents($this->authFile, json_encode($obj));
    }

    /**
     * 读取本地缓存文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return mixed|null
     * @lasttime: 2022/11/3 14:33
     */
    public function readAuthObj()
    {
        if (file_exists($this->authFile)) {
            $content = @file_get_contents($this->authFile);
            if (!empty($content)) {
                $obj = @json_decode($content, true);
                if (empty($obj)) return null;
                $obj['is_read'] = true;
                if ($obj['time'] + $obj['expires_in'] - 30 > time()) {
                    return $obj;
                }
            }
        }
        return null;
    }
}
