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
    public $apiKey;
    public $apiSecret;

    protected $authFile = '@runtime/baiduAipAuth/';

    protected $isCloudUser = false;
    protected $scope = 'brain_all_scope';

    /**
     * @throws Exception
     */
    public function init()
    {
        parent::init();

        $this->apiKey    = Yii::$app->params['baiduAip']['ApiKey'];
        $this->apiSecret = Yii::$app->params['baiduAip']['ApiSecret'];

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new Exception('apiKey or apiSecret is empty');
        }

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

        $this->isCloudUser = !$this->isPermission($obj);

        return $obj;
    }

    protected function isPermission($authObj): bool
    {
        if (empty($authObj) || !isset($authObj['scope']))
            return false;

        $scopes = explode(' ', $authObj['scope']);

        return in_array($this->scope, $scopes);
    }

    /**
     * 写入本地缓存文件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $obj
     * @return false|int
     * @lasttime: 2022/11/3 14:30
     */
    private function writeAuthObj(array $obj)
    {
        if ((isset($obj['is_read']) && $obj['is_read'] === true))
            return false;
        if (!is_dir(dirname($this->authFile))) {
            try {
                FileHelper::createDirectory(dirname($this->authFile));
            } catch (Exception $e) {
            }
        }
        $obj['time']          = time();
        $obj['is_cloud_user'] = $this->isCloudUser;
        return @file_put_contents($this->authFile, json_encode($obj));
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
                $this->isCloudUser = $obj['is_cloud_user'];
                $obj['is_read']    = true;
                if ($this->isCloudUser || $obj['time'] + $obj['expires_in'] - 30 > time()) {
                    return $obj;
                }
            }
        }
        return null;
    }

    public function request($url, $data, $headers = [])
    {
        $params                 = [];
        $authObj                = $this->auth();
        $params['access_token'] = $authObj['access_token'];

        $response = $this->client->post($url, $data, $params, $headers);
        $response = Communication::post($url, $data, $params, $headers);

        if (!$this->isCloudUser && isset($obj['error_code']) && $obj['error_code'] == 110) {
            $authObj                = $this->auth(true);
            $params['access_token'] = $authObj['access_token'];
            $response               = $this->client->post($url, $data, $params, $headers);
            $obj                    = $this->proccessResult($response['content']);
        }

        if (empty($obj) || !isset($obj['error_code'])) {
            $this->writeAuthObj($authObj);
        }
    }
}
