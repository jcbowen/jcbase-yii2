<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Safe;
use Jcbowen\JcbaseYii2\components\sdk\helper\WechatPay\TransferDetailInput;
use Jcbowen\JcbaseYii2\components\Util;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\AesGcm;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;
use Yii;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\InvalidArgumentException;

class WechatPay extends Component
{
    /**
     * 平台密钥模式：自动探测。
     * 配置了「微信支付公钥」就用公钥，配置了「平台证书」就用平台证书，两者都配置则同时注册，
     * 由应答/回调头中的 Wechatpay-Serial 决定实际使用哪一个验签（兼容官方切换灰度期）。
     */
    const PLATFORM_KEY_MODE_AUTO = 'auto';
    /**
     * 平台密钥模式：仅使用「微信支付平台证书」
     */
    const PLATFORM_KEY_MODE_CERTIFICATE = 'certificate';
    /**
     * 平台密钥模式：仅使用「微信支付公钥」
     */
    const PLATFORM_KEY_MODE_PUBLIC_KEY = 'publicKey';

    /**
     * @var string v3 API密钥
     */
    public $apiV3Key;
    /**
     * @var string 商户证书序列号
     */
    public $merchantCertificateSerial;

    /**
     * @var string 直连商户的商户号，由微信支付生成并下发。示例值：1230000109
     */
    public $merchantId;
    /**
     * @var string 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一。示例值：1217752501201407033233368018
     */
    public $outTradeNo;
    /**
     * @var string 由微信生成的应用ID，全局唯一。请求基础下单接口时请注意APPID的应用属性，例如公众号场景下，需使用应用属性为公众号的服务号APPID。示例值：wxd678efh567hg6787
     */
    public $appId;
    /**
     * @var string 商品描述 示例值：Image形象店-深圳腾大-QQ公仔
     */
    public $description;
    /**
     * @var string 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用，实际情况下只有支付完成状态才会返回该字段。
     */
    public $attach;
    /**
     * @var string 异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。 公网域名必须为https，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用http。
     *     示例值：https://www.weixin.qq.com/wxpay/pay.php
     */
    public $notifyUrl;
    /**
     * @var array 订单金额信息
     */
    public $amount = [
        'total'    => 0, // 订单总金额，单位为分。示例值：100
        'currency' => 'CNY', // CNY：人民币，境内商户号仅支持人民币。 示例值：CNY
    ];
    /**
     * @var array 支付者信息
     */
    public $payer = [
        'openid'    => '', // 用户在直连商户appid下的唯一标识。 下单前需获取到用户的Openid。 示例值：oUpF8uMuAJO_M2pxb1Q9zNjWeS6o
        'auth_code' => '', // 【授权码】付款码支付授权码，即用户打开微信钱包显示的码。
    ];

    /**
     * @var array 场景信息
     */
    public $sceneInfo = [
        'device_id'  => '', // 选填，商户端设备号】 商户端设备号（门店号或收银设备ID）
        'device_ip'  => '', // 选填，【商户端设备 IP】 商户端设备 IP。
        // 必填，【商户门店信息】 商户门店信息。
        'store_info' => [
            // 【门店编号】 此参数与商家自定义编码(out_id)二选一必填。微信支付线下场所ID，格式为纯数字。基于合规要求与风险管理目的，线下条码支付时需传入用户实际付款的场景信息。
            'id'     => '',
            // 【商家自定义编码】 此参数与门店(id)二选一必填。商户系统的门店编码，支持大小写英文字母、数字，仅支持utf-8格式。
            'out_id' => '',
        ]
    ];

    /**
     * @var string 预支付交易会话标识。用于后续接口调用中使用，该值有效期为2小时。 示例值：wx201410272009395522657a690389285100
     */
    public $prepayId;

    /**
     * @var BuilderChainable APIv3 客户端实例
     */
    public $instance;

    /**
     * @var string 支付类型 JSAPI、APP、H5、Native
     */
    public $payType = 'JSAPI';

    /**
     * @var array 错误信息
     */
    public $errors = [];

    /**
     * @var string 证书路径
     */
    public $certPath = '@common/pay/wechat/';

    /**
     * @var bool 是否在请求中开启调试模式
     */
    public $debug = false;

    /**
     * @var string 平台密钥模式，可选值见 PLATFORM_KEY_MODE_* 常量，默认 auto
     *      - auto：自动探测，配置了什么就用什么（推荐，可兼容平台证书与微信支付公钥并存的切换期）
     *      - certificate：仅使用「微信支付平台证书」
     *      - publicKey：仅使用「微信支付公钥」
     *      也可通过 params 中的 platformKeyMode 配置。
     */
    public $platformKeyMode = self::PLATFORM_KEY_MODE_AUTO;

    /**
     * @var string 微信支付公钥ID，形如 PUB_KEY_ID_01142321349124100000000000********
     *      在 商户平台 -> 账户中心 -> API安全 查看。
     *      与「平台证书序列号」不同，公钥ID无法从公钥文件中解析，必须显式配置。
     *      也可通过 params 中的 platformPublicKeyId 配置。
     */
    public $platformPublicKeyId;

    /**
     * @var string|array 「微信支付公钥」文件名（相对 certPath/商户号/ 目录），支持传入数组按顺序探测
     *      也可通过 params 中的 platformPublicKeyFile 配置（可传绝对路径或Yii别名）。
     */
    public $platformPublicKeyFile = ['pub_key.pem', 'wxp_pub.pem', 'publickey.pem', 'public_key.pem'];

    /**
     * @var string|array 「微信支付平台证书」文件名（相对 certPath/商户号/ 目录），支持传入数组按顺序探测
     *      也可通过 params 中的 platformCertificateFile 配置（可传绝对路径或Yii别名）。
     */
    public $platformCertificateFile = 'cert.pem';

    /** @var string 「微信支付平台证书」文件路径，在build中解析 */
    public $platformCertificateFilePath;

    /** @var string 「微信支付公钥」文件路径，在build中解析 */
    public $platformPublicKeyFilePath;

    /**
     * @var array 已加载的平台验签公钥集合，格式为 [平台证书序列号|微信支付公钥ID => 公钥实例]
     *      该集合会直接作为 SDK 的 certs 传入，同时用于按 Wechatpay-Serial 匹配回调验签的公钥。
     */
    public $platformPublicKeys = [];

    /**
     * @var string 当前主用的平台标识(Wechatpay-Serial)，在build中生成
     *      可能是「平台证书序列号」，也可能是「微信支付公钥ID」，取决于当前生效的模式。
     *      （保持原有属性名以确保向后兼容）
     */
    public $platformCertificateSerial;

    /** @var string 当前主用的平台公钥实例，在build中生成 */
    public $platformPublicKeyInstance;

    /**
     * @var array 平台密钥加载过程中的错误信息，用于在最终无可用密钥时给出排障详情
     */
    private $platformKeyLoadErrors = [];

    public $merchantPrivateKeyInstance;

    /**
     * 构建APIv3客户端实例
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $type 微信支付类型 小程序:WeChatMiniProgram，公众号:WeChatOfficialAccount，企业微信:WeChatWork，APP:App
     *
     * @return $this
     * @lasttime 2022/11/10 01:12
     */
    public function build(string $type = 'WeChatMiniProgram'): WechatPay
    {
        $config = Yii::$app->params[$type . "Config"] ?? [];

        $this->merchantId                = $config['mchId'] ?? $this->merchantId;
        $this->appId                     = $config['app_id'] ?? $this->appId;
        $this->merchantCertificateSerial = $config['merchantCertificateSerial'] ?? $this->merchantCertificateSerial;
        $this->apiV3Key                  = $config['apiKey'] ?? $this->apiV3Key;
        $this->notifyUrl                 = $config['notifyUrl'] ?? $this->notifyUrl;

        // 平台密钥模式、微信支付公钥ID、自定义证书文件名（均为可选配置，未配置时使用属性默认值）
        if (!empty($config['platformKeyMode'])) {
            $this->platformKeyMode = $config['platformKeyMode'];
        }
        if (!empty($config['platformPublicKeyId'])) {
            $this->platformPublicKeyId = $config['platformPublicKeyId'];
        }
        if (!empty($config['platformPublicKeyFile'])) {
            $this->platformPublicKeyFile = $config['platformPublicKeyFile'];
        }
        if (!empty($config['platformCertificateFile'])) {
            $this->platformCertificateFile = $config['platformCertificateFile'];
        }

        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = Yii::getAlias($this->certPath) . $this->merchantId . '/apiclient_key.pem';
        if (!file_exists($merchantPrivateKeyFilePath)) {
            throw new InvalidArgumentException("商户API私钥文件不存在：$merchantPrivateKeyFilePath");
        }

        $merchantPrivateKeyFilePath       = 'file://' . $merchantPrivateKeyFilePath;
        $this->merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);

        // 加载「微信支付平台证书」与「微信支付公钥」，两者可并存
        $this->platformPublicKeys     = [];
        $this->platformKeyLoadErrors  = [];
        if ($this->platformKeyMode !== self::PLATFORM_KEY_MODE_PUBLIC_KEY) {
            $this->loadPlatformCertificate();
        }
        if ($this->platformKeyMode !== self::PLATFORM_KEY_MODE_CERTIFICATE) {
            $this->loadPlatformPublicKey();
        }

        if (empty($this->platformPublicKeys)) {
            $message = '未找到可用的「微信支付平台证书」或「微信支付公钥」，请检查证书目录：'
                . Yii::getAlias($this->certPath) . $this->merchantId . '/'
                . (empty($this->platformPublicKeyId) && $this->platformKeyMode !== self::PLATFORM_KEY_MODE_CERTIFICATE
                    ? '（若使用「微信支付公钥」，还需配置 platformPublicKeyId）' : '');
            if (!empty($this->platformKeyLoadErrors)) {
                $message .= PHP_EOL . '加载失败详情：' . PHP_EOL . implode(PHP_EOL, $this->platformKeyLoadErrors);
            }
            throw new InvalidArgumentException($message);
        }

        // 确定当前主用的平台密钥：优先使用「微信支付公钥」（官方推荐，无有效期），否则回退到「平台证书」
        if (!empty($this->platformPublicKeyId) && isset($this->platformPublicKeys[$this->platformPublicKeyId])) {
            $this->platformCertificateSerial = $this->platformPublicKeyId;
            $this->platformPublicKeyInstance = $this->platformPublicKeys[$this->platformPublicKeyId];
        } else {
            $serials                         = array_keys($this->platformPublicKeys);
            $this->platformCertificateSerial = (string)reset($serials);
            $this->platformPublicKeyInstance = reset($this->platformPublicKeys);
        }

        // 构造一个 APIv3 客户端实例
        $this->instance = Builder::factory([
            'mchid'      => $this->merchantId, // 商户号
            'serial'     => $this->merchantCertificateSerial, // 「商户API证书」的「证书序列号」
            'privateKey' => $this->merchantPrivateKeyInstance,
            // 「平台证书序列号」或「微信支付公钥ID」 => 平台公钥实例，支持多个并存
            'certs'      => $this->platformPublicKeys,
        ]);

        switch ($type) {
            case 'App':
                $this->payType = 'APP';
                break;
            default:
                $this->payType = 'JSAPI';
                break;
        }

        return $this;
    }

    /**
     * 加载「微信支付平台证书」
     *
     * 平台证书为X.509证书，其「证书序列号」可直接从文件中解析，无需额外配置。
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return bool 是否加载成功
     * @lasttime: 2026/9/10 22:10
     */
    private function loadPlatformCertificate(): bool
    {
        $filePath = $this->locateKeyFile($this->platformCertificateFile);
        if (empty($filePath)) {
            return false;
        }

        try {
            $certPath                  = 'file://' . $filePath;
            $serial                    = PemUtil::parseCertificateSerialNo($certPath);
            $this->platformPublicKeys[$serial] = Rsa::from($certPath, Rsa::KEY_TYPE_PUBLIC);
            $this->platformCertificateFilePath = $filePath;
            return true;
        } catch (\Exception $e) {
            $this->platformKeyLoadErrors[] = '加载「微信支付平台证书」失败：' . $e->getMessage();
            Yii::warning(end($this->platformKeyLoadErrors), __METHOD__);
            return false;
        }
    }

    /**
     * 加载「微信支付公钥」
     *
     * 公钥ID无法从公钥文件中解析，必须由商户在 params 中配置 platformPublicKeyId。
     * 若检测到公钥文件但未配置公钥ID，则忽略公钥（保持与旧版一致的行为，避免静默出错）。
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return bool 是否加载成功
     * @lasttime: 2026/9/10 22:10
     */
    private function loadPlatformPublicKey(): bool
    {
        $filePath = $this->locateKeyFile($this->platformPublicKeyFile);
        if (empty($filePath)) {
            return false;
        }

        if (empty($this->platformPublicKeyId)) {
            $this->platformKeyLoadErrors[] = '检测到「微信支付公钥」文件但缺少 platformPublicKeyId 配置，已忽略公钥模式：' . $filePath;
            Yii::warning(end($this->platformKeyLoadErrors), __METHOD__);
            return false;
        }

        try {
            $this->platformPublicKeys[$this->platformPublicKeyId] = Rsa::from('file://' . $filePath, Rsa::KEY_TYPE_PUBLIC);
            $this->platformPublicKeyFilePath                      = $filePath;
            return true;
        } catch (\Exception $e) {
            $this->platformKeyLoadErrors[] = '加载「微信支付公钥」失败：' . $e->getMessage();
            Yii::warning(end($this->platformKeyLoadErrors), __METHOD__);
            return false;
        }
    }

    /**
     * 定位密钥文件
     *
     * 传入值可以是绝对路径、Yii别名，也可以是相对「certPath/商户号/」目录的文件名（或文件名数组，按顺序探测）。
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|array $file
     *
     * @return string 命中文件的绝对路径，未找到时返回空字符串
     * @lasttime: 2026/9/10 22:10
     */
    private function locateKeyFile($file): string
    {
        if (empty($file)) {
            return '';
        }

        // 绝对路径或Yii别名
        if (is_string($file)) {
            $resolved = Yii::getAlias($file);
            if (file_exists($resolved)) {
                return $resolved;
            }
        }

        // 相对证书目录（certPath/商户号/）的文件名
        $basePath = Yii::getAlias($this->certPath) . $this->merchantId . '/';
        foreach ((array)$file as $name) {
            $path = $basePath . $name;
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    /**
     * 按平台标识(Wechatpay-Serial)获取对应的验签公钥
     *
     * 「平台证书」与「微信支付公钥」并存时，微信会在应答/回调头中通过 Wechatpay-Serial
     * 告知本次签名使用的到底是哪一个，必须据此选取对应的公钥，否则切换期会验签失败。
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $serial 平台证书序列号或微信支付公钥ID
     *
     * @return mixed 公钥实例，未匹配时返回当前主用公钥
     * @lasttime: 2026/9/10 22:10
     */
    public function getPlatformPublicKey(string $serial = '')
    {
        if (!empty($serial) && isset($this->platformPublicKeys[$serial])) {
            return $this->platformPublicKeys[$serial];
        }

        return $this->platformPublicKeyInstance;
    }

    /**
     * 设置订单总金额
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param float|int $total 订单总金额(如果传入的是整数，则单位为分；如果传入的是浮点数，则单位为元；推荐使用整数)
     *
     * @return $this
     * @lasttime 2022/11/10 01:15
     */
    public function amount($total = 0): WechatPay
    {
        // 如果传入的单位是元，则转换为分
        if (Util::strExists($total, '.') !== false) {
            $total = bcmul($total, 100);
        }

        $this->amount['total'] = intval($total);
        return $this;
    }

    /**
     * 商品描述
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $description 商品描述
     *
     * @return $this
     * @lasttime 2022/11/10 01:18
     */
    public function description(string $description = null): WechatPay
    {
        $this->description = $description;
        return $this;
    }

    /**
     * 设置商户系统内部订单号
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $outTradeNo
     *
     * @return $this
     * @lasttime 2022/11/10 01:25
     */
    public function outTradeNo(string $outTradeNo = null): WechatPay
    {
        $this->outTradeNo = $outTradeNo;
        return $this;
    }

    /**
     * 设置支付成功回调地址
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $notifyUrl
     *
     * @return $this
     * @lasttime 2022/11/10 01:25
     */
    public function notifyUrl(string $notifyUrl = ''): WechatPay
    {
        $this->notifyUrl = $notifyUrl;
        return $this;
    }

    /**
     * 设置支付者信息
     * 为空的信息将会被移除
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|string $data
     *    - openid: 支付者openid
     *    - auth_code: 授权码（付款码）
     *
     * @return $this
     * @lasttime: 2024/5/25 11:15
     */
    public function payer($data = []): WechatPay
    {
        // 如果为字符串，就代表传的是openid（兼容旧版用法）
        if (is_string($data)) {
            $data = ['openid' => $data];
        }

        $this->payer = [
            'openid'    => $data['openid'] ?? '',
            'auth_code' => $data['auth_code'] ?? '',
        ];

        // 为空的直接移除
        foreach ($this->payer as $key => $value) {
            if (empty($value)) {
                unset($this->payer[$key]);
            }
        }

        return $this;
    }

    /**
     * 设置授权码(付款码)
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $authCode
     *
     * @return $this
     * @lasttime 2024/5/25 11:18:1
     */
    public function payerAuthCode(string $authCode = ''): WechatPay
    {
        $this->payer['auth_code'] = $authCode;
        return $this;
    }

    /**
     * 设置支付者openid
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $openid
     *
     * @return $this
     * @lasttime 2024/5/25 11:18:6
     */
    public function payerOpenid(string $openid = ''): WechatPay
    {
        $this->payer['openid'] = $openid;
        return $this;
    }

    /**
     * 设置场景信息
     *
     * @author  Bowen
     * @email   3308725087@qq.com
     *
     * @param array $data
     *
     * @return $this
     * @lasttime: 2024/5/26 21:34
     */
    public function sceneInfo(array $data = []): WechatPay
    {
        $this->sceneInfo = [
            'device_id' => $data['device_id'] ?? '',
            'device_ip' => $data['device_ip'] ?? '',
        ];
        $this->storeInfo($data['store_info'] ?? []);

        // 为空的直接移除
        foreach ($this->sceneInfo as $key => $value) {
            if (empty($value)) {
                unset($this->sceneInfo[$key]);
            }
        }

        return $this;
    }

    /**
     * 设置商户门店信息
     *
     * @author  Bowen
     * @email   3308725087@qq.com
     *
     * @param array $data
     *    - id: 【门店编号】 此参数与商家自定义编码(out_id)二选一必填。微信支付线下场所ID，格式为纯数字。基于合规要求与风险管理目的，线下条码支付时需传入用户实际付款的场景信息。
     *    - out_id: 【商家自定义编码】 此参数与门店(id)二选一必填。商户系统的门店编码，支持大小写英文字母、数字，仅支持utf-8格式。
     *
     * @return $this
     * @lasttime: 2024/5/26 21:28
     */
    public function storeInfo(array $data = []): WechatPay
    {
        $this->sceneInfo['store_info'] = [
            'id'     => $data['id'] ?? '',
            'out_id' => $data['out_id'] ?? '',
        ];

        // 为空的直接移除
        foreach ($this->sceneInfo['store_info'] as $key => $value) {
            if (empty($value)) {
                unset($this->sceneInfo['store_info'][$key]);
            }
        }

        // 如果没有传门店信息，这里默认设置一个
        if (empty($this->sceneInfo['store_info'])) {
            $this->sceneInfo['store_info']['out_id'] = 'jc001';
        }

        return $this;
    }

    /**
     * 设置附加数据
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $attach
     *
     * @return $this
     * @lasttime 2022/11/10 10:41
     */
    public function attach(string $attach = ''): WechatPay
    {
        $this->attach = $attach;
        return $this;
    }

    /**
     * 批量设置支付信息（通过上面的方法）
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     *
     * @return $this
     * @lasttime 2022/11/10 01:27
     */
    public function set(array $data = []): WechatPay
    {
        foreach ($data as $key => $value) {
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }
        return $this;
    }

    /**
     * 批量设置支付信息（通过property）
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     *
     * @return $this
     * @lasttime 2022/11/10 01:27
     */
    public function setProperty(array $data): WechatPay
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        return $this;
    }

    /**
     * 检查交易参数是否有误
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2023/3/10 12:21
     */
    public function checkTransactionsError()
    {
        if ($this->amount['total'] <= 0) {
            $this->errors[] = '金额必须大于0';
        }

        if (Util::strExists($this->amount['total'], '.')) {
            $this->errors[] = '订单金额(单位分)必须为整数';
        }

        if (empty($this->notifyUrl)) {
            $this->errors[] = '回调地址不能为空';
        }

        if (!empty($this->errors)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'errors', $this->errors);
        }

        return true;
    }

    /**
     * 检查jsApi交易参数是否有误
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2023/3/10 12:21
     */
    public function checkJsApiError()
    {
        if (empty($this->payer['openid'])) {
            $this->errors[] = '支付者openid不能为空';
        }

        if (empty($this->payer['auth_code'])) {
            unset($this->payer['auth_code']);
        }

        return $this->checkTransactionsError();
    }

    /**
     * 检查收款码支付交易参数是否有误
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2024/5/25 11:01
     */
    public function checkAuthCodeError()
    {
        if (empty($this->payer['auth_code'])) {
            $this->errors[] = '授权码不能为空';
        }

        if (empty($this->payer['openid'])) {
            unset($this->payer['openid']);
        }

        $this->sceneInfo($this->sceneInfo);

        return $this->checkTransactionsError();
    }

    /**
     * jsApi下单
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array|bool
     * @lasttime 2022/11/10 01:29
     */
    public function JSAPI()
    {
        $check = $this->checkJsApiError();
        if (Util::isError($check)) {
            return $check;
        }

        $jsonData = [
            'mchid'        => $this->merchantId,
            'out_trade_no' => $this->outTradeNo ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true),
            'appid'        => $this->appId,
            'description'  => $this->description ?? '商品' . date('YmdHis'),
            'notify_url'   => $this->notifyUrl,
            'amount'       => $this->amount,
            'payer'        => $this->payer,
        ];

        if (!empty($this->attach)) {
            $jsonData['attach'] = $this->attach;
        }

        try {
            $resp = $this->instance->chain('v3/pay/transactions/jsapi')->post([
                'debug' => $this->debug,
                'json'  => $jsonData,
            ]);

        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 生成前端调用支付的参数
     * 执行前务必保证当前实例已经执行过 jsApi/app下单 方法
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/11/10 01:31
     */
    public function getSignParams(): array
    {
        if (empty($this->prepayId)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'prepayId不能为空');
        }

        // 优先复用build时已加载的商户私钥实例，避免重复读取文件
        $merchantPrivateKeyInstance = $this->merchantPrivateKeyInstance;
        if (empty($merchantPrivateKeyInstance)) {
            $merchantPrivateKeyFilePath = 'file://' . Yii::getAlias($this->certPath) . $this->merchantId . '/apiclient_key.pem';
            $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);
        }

        if ($this->payType == 'APP') {
            // 适用于常规app
            $params = [
                'appId'     => $this->appId,
                'timeStamp' => (string)Formatter::timestamp(),
                'nonceStr'  => Formatter::nonce(),
                'prepayId'  => $this->prepayId,

            ];
            $params += [
                'sign'            => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance
                ), 'packageValue' => 'Sign=WXPay', 'partnerId' => $this->merchantId,
            ];
            // 如果用于uniapp的app支付，需要在接收到返回数据后，进行如下转换
            /*$params = [
                'appid'     => $appParams['appId'],
                'noncestr'  => $appParams['nonceStr'],
                'package'   => $appParams['packageValue'],
                'partnerid' => $appParams['partnerId'],
                'prepayid'  => $appParams['prepayId'],
                'timestamp' => $appParams['timeStamp'],
                'sign'      => $appParams['sign'],
            ];*/
        } else {
            // 适用于JSAPI
            $params = [
                'appId'     => $this->appId,
                'timeStamp' => (string)Formatter::timestamp(),
                'nonceStr'  => Formatter::nonce(),
                'package'   => 'prepay_id=' . $this->prepayId,
            ];
            $params += [
                'paySign'     => Rsa::sign(
                    Formatter::joinedByLineFeed(...array_values($params)),
                    $merchantPrivateKeyInstance
                ), 'signType' => 'RSA'
            ];
        }

        return $params;
    }

    /**
     * app下单
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2023/3/10 12:15
     */
    public function APP()
    {
        $check = $this->checkTransactionsError();
        if (Util::isError($check)) {
            return $check;
        }

        $jsonData = [
            'mchid'        => $this->merchantId,
            'out_trade_no' => $this->outTradeNo ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true),
            'appid'        => $this->appId,
            'description'  => $this->description ?? '商品' . date('YmdHis'),
            'notify_url'   => $this->notifyUrl,
            'amount'       => $this->amount,
        ];

        if (!empty($this->attach)) {
            $jsonData['attach'] = $this->attach;
        }

        try {
            $resp = $this->instance->chain('v3/pay/transactions/app')->post([
                'debug' => $this->debug,
                'json'  => $jsonData,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * CODE付款码
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array|bool
     * @lasttime 2024/5/25 10:33:23
     */
    public function CODE()
    {
        $check = $this->checkAuthCodeError();
        if (Util::isError($check)) {
            return $check;
        }

        $jsonData = [
            'appid'        => $this->appId,
            'mchid'        => $this->merchantId,
            'description'  => $this->description ?? '商品' . date('YmdHis'),
            'out_trade_no' => $this->getOutTradeNo(true),
            'payer'        => $this->payer,
            'amount'       => $this->amount,
            'scene_info'   => $this->sceneInfo,
        ];

        if (!empty($this->attach)) {
            $jsonData['attach'] = $this->attach;
        }

        try {
            $resp = $this->instance->chain('v3/pay/transactions/codepay')->post([
                'debug' => $this->debug,
                'json'  => $jsonData,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 商家转账到零钱
     *
     * @param array  $list              批量转账列表
     * @param string $transfer_scene_id 【转账场景ID】 该批次转账使用的转账场景，如不填写则使用商家的默认场景，如无默认场景可为空，可前往“商家转账到零钱-前往功能”中申请。
     *                                  如：1001-现金营销
     *
     * @return array
     * @throws Exception
     */
    public function TransferBatches(array $list, string $transfer_scene_id = ''): array
    {
        $total_amount = 0;
        $listArr      = [];
        $batchNo      = $this->getOutTradeNo(true, 'tb');
        $total_num    = 0;
        foreach ($list as $item) {
            if (!$item instanceof TransferDetailInput) {
                throw new InvalidArgumentException('转账明细必须是WechatPayTransferItem实例');
            }
            $total_num++;
            $itemData = $item->toArray();
            if ($itemData['transfer_amount'] >= 2000 * 100 && empty($itemData['user_name'])) {
                throw new InvalidArgumentException("转账金额大于2000元时，必须传入收款用户姓名");
            }
            if (!empty($itemData['user_name'])) {
                $itemData['user_name'] = $this->encryptText($itemData['user_name']);
            }
            $itemData['out_detail_no'] = $batchNo . $total_num;
            $total_amount              += $itemData['transfer_amount'];
            $listArr[]                 = $itemData;
        }

        $jsonData = [
            'appid'                => $this->appId,
            'out_batch_no'         => $batchNo, // 【商家批次单号】 商户系统内部的商家批次单号，要求此参数只能由数字、大小写字母组成，在商户系统内部唯一
            'batch_name'           => '转账' . date('YmdHis'), // 【批次名称】 该笔批量转账的名称
            'batch_remark'         => $this->description ?? '转账' . date('YmdHis'), // 【批次备注】 转账说明，UTF8编码，最多允许32个字符
            'total_amount'         => $total_amount, // 【转账总金额】 转账金额单位为“分”。转账总金额必须与批次内所有明细转账金额之和保持一致，否则无法发起转账操作
            'total_num'            => $total_num,
            'transfer_detail_list' => $listArr,
            'notify_url'           => $this->notifyUrl,
        ];
        if (!empty($transfer_scene_id)) {
            $jsonData['transfer_scene_id'] = $transfer_scene_id;
        }

        if (!empty($this->attach)) {
            $jsonData['attach'] = $this->attach;
        }

        try {
            $resp = $this->instance->chain('v3/transfer/batches')->post([
                'debug'   => $this->debug,
                'json'    => $jsonData,
                'headers' => [
                    'Wechatpay-Serial' => $this->platformCertificateSerial,
                ]
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp, ['transfer_detail_list' => $listArr]);
    }

    /**
     * 通过商家批次单号查询批次单
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param bool   $need_query_detail 【是否查询转账明细单】
     *                                  true-是；false-否，默认否。商户可选择是否查询指定状态的转账明细单，当转账批次单状态为“FINISHED”（已完成）时，才会返回满足条件的转账明细单
     * @param int    $offset            【请求资源起始位置】 该次请求资源（转账明细单）的起始位置，从0开始，默认值为0
     * @param int    $limit             【最大资源条数】 该次请求可返回的最大资源（转账明细单）条数，最小20条，最大100条，不传则默认20条。不足20条按实际条数返回
     * @param string $detail_status     【明细状态】 WAIT_PAY: 待确认。待商户确认, 符合免密条件时, 系统会自动扭转为转账中
     *                                  - ALL:全部。需要同时查询转账成功、失败和待确认的明细单
     *                                  - SUCCESS:转账成功
     *                                  - FAIL:转账失败。需要确认失败原因后，再决定是否重新发起对该笔明细单的转账（并非整个转账批次单）
     *                                  当need_query_detail为true时该字段必填
     *
     * @return array
     */
    public function TransferBatchesQuery(bool $need_query_detail = true, int $offset = 0, int $limit = 20, string $detail_status = 'ALL'): array
    {
        $detail_status = Safe::gpcBelong($detail_status, ['ALL', 'SUCCESS', 'FAIL'], 'ALL');
        $jsonData      = [

            'need_query_detail' => $need_query_detail,
            'offset'            => max($offset, 0),
            'limit'             => max(min($limit, 100), 20),
            'detail_status'     => $detail_status,
        ];

        try {
            $resp = $this->instance->chain('/v3/transfer/batches/out-batch-no/' . $this->getOutTradeNo())->get([
                'debug' => $this->debug,
                'query' => $jsonData,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }

            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 通过商家明细单号查询明细单
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $out_detail_no 【商家批次单号】 商户系统内部的商家批次单号，在商户系统内部唯一
     *
     * @return array
     */
    public function TransferBatchesQueryDetails(string $out_detail_no): array
    {
        try {
            $resp = $this->instance->chain('/v3/transfer/batches/out-batch-no/' . $this->getOutTradeNo() . "/details/out-detail-no/$out_detail_no")->get([
                'debug' => $this->debug,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }

            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 订单查询
     * 默认通过商户订单号查询，如果传入了微信支付订单号，则以微信支付订单号查询
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $transactionId 微信支付系统生成的订单号 示例值：1217752501201407033233368018
     *
     * @return array
     * @lasttime 2022/11/10 01:47
     */
    public function query(string $transactionId = null): array
    {
        if (empty($transactionId) && empty($this->outTradeNo)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'transactionId和outTradeNo不能同时为空');
        }

        if (!empty($transactionId)) {
            $path = 'v3/pay/transactions/id/' . $transactionId . '?mchid=' . $this->merchantId;
        } else {
            $path = 'v3/pay/transactions/out-trade-no/' . $this->outTradeNo . '?mchid=' . $this->merchantId;
        }

        try {
            $resp = $this->instance->chain($path)->get([
                'debug' => $this->debug,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 查询单笔退款
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $outRefundNo
     *
     * @return array
     * @lasttime 2022/11/11 17:11
     */
    public function queryRefund($outRefundNo): array
    {
        if (empty($outRefundNo)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'outRefundNo不能为空');
        }

        try {
            $resp = $this->instance->chain('v3/refund/domestic/refunds/' . $outRefundNo)
                ->get([
                    'debug' => $this->debug,
                ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 关闭订单
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/11/10 01:59
     */
    public function close(): array
    {
        if (empty($this->outTradeNo)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'outTradeNo不能为空');
        }

        try {
            $resp = $this->instance->chain('v3/pay/transactions/out-trade-no/' . $this->outTradeNo . '/close')->post([
                'debug' => $this->debug,
                'json'  => [
                    'mchid' => $this->merchantId,
                ],
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 申请退款
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param int         $refundAmount  退款金额，单位为分
     * @param int         $totalAmount   订单总金额，单位为分
     * @param string|null $outRefundNo   商户退款单号
     * @param string|null $refundReason  退款原因
     * @param string|null $transactionId 微信支付系统生成的订单号 示例值：1217752501201407033233368018
     *
     * @return array
     * @lasttime 2022/11/10 02:12
     */
    public function refund(int $refundAmount, int $totalAmount, ?string $outRefundNo = null, ?string $refundReason = '', string $transactionId = null): array
    {
        if (empty($transactionId) && empty($this->outTradeNo)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'transactionId和outTradeNo不能同时为空');
        }
        if (empty($refundAmount)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'refundAmount不能为空');
        }
        $outRefundNo = $outRefundNo ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true);
        if (empty($totalAmount)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'totalAmount不能为空');
        }
        if (empty($this->notifyUrl)) {
            return Util::error(ErrCode::PARAMETER_ERROR, 'notifyUrl不能为空');
        }

        $jsonData = [
            'out_refund_no' => $outRefundNo,

            'notify_url' => $this->notifyUrl,
            'amount'     => [
                'refund'   => $refundAmount,
                'total'    => $totalAmount,
                'currency' => 'CNY',
            ],
        ];

        if (!empty($transactionId)) {
            $jsonData['transaction_id'] = $transactionId;
        } else {
            $jsonData['out_trade_no'] = $this->outTradeNo;
        }

        if (!empty($refundReason)) {
            $jsonData['reason'] = $refundReason;
        }

        try {
            $resp = $this->instance->chain('v3/refund/domestic/refunds')->post([
                'debug' => $this->debug,
                'json'  => $jsonData,
            ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }

        return $this->returnResp($resp);
    }

    /**
     * 撤销
     * 支付交易返回失败或支付系统超时，调用该接口撤销交易。
     * 如果此订单用户支付失败，微信支付系统会将此订单关闭；
     * 如果用户支付成功，微信支付系统会将此订单资金退还给用户。
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $outRefundNo
     *
     * @return array
     * @lasttime: 2024/5/25 11:27
     */
    public function reverse($outRefundNo = null): array
    {
        $outRefundNo = $outRefundNo ?? $this->outTradeNo;
        $jsonData    = [
            'appid' => $this->appId,
            'mchid' => $this->merchantId,
        ];

        try {
            $resp = $this->instance
                ->chain("v3/pay/transactions/out-trade-no/$outRefundNo/reverse")
                ->post([
                    'json' => $jsonData,
                ]);
        } catch (RequestException $e) {
            // 检查异常是否有响应
            if ($e->hasResponse()) {
                return $this->returnResp($e->getResponse());
            }
            // 如果没有响应，则返回异常的代码和消息
            return Util::error($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            return Util::error($e->getCode(), $e->getMessage());
        }
        return $this->returnResp($resp);
    }

    /**
     * 解密回调消息
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/11/10 11:06
     * @throws ErrorException
     */
    public function dealNotify(): array
    {
        $inWechatpaySerial    = $_SERVER['HTTP_WECHATPAY_SERIAL'] ?? '';
        $inWechatpaySignature = $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ?? '';
        $inWechatpayTimestamp = $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ?? '';
        $inWechatpayNonce     = $_SERVER['HTTP_WECHATPAY_NONCE'] ?? '';
        $inBody               = file_get_contents('php://input');

        // 检查通知时间偏移量，允许5分钟之内的偏移
        $timeOffsetStatus = 300 >= abs(Formatter::timestamp() - (int)$inWechatpayTimestamp);
        $verifiedStatus   = Rsa::verify(
        // 构造验签名串
            Formatter::joinedByLineFeed($inWechatpayTimestamp, $inWechatpayNonce, $inBody),
            $inWechatpaySignature,
            // 按本次回调声明的 Wechatpay-Serial 选取「平台证书」或「微信支付公钥」
            $this->getPlatformPublicKey($inWechatpaySerial)
        );
        if (!$timeOffsetStatus || !$verifiedStatus) {
            throw new ErrorException('签名验证失败');
        }

        // 转换通知的JSON文本消息为PHP Array数组
        $inBodyArray = (array)json_decode($inBody, true);
        // 使用PHP7的数据解构语法，从Array中解构并赋值变量
        [
            'resource' => [
                'ciphertext'      => $ciphertext,
                'nonce'           => $nonce,
                'associated_data' => $aad
            ]
        ] = $inBodyArray;
        // 加密文本消息解密
        $inBodyResource = AesGcm::decrypt($ciphertext, $this->apiV3Key, $nonce, $aad);
        // 把解密后的文本转换为PHP Array数组
        $arrResource = (array)json_decode($inBodyResource, true);

        Yii::debug($arrResource, __METHOD__);

        return $arrResource;
    }

    /**
     * 加密文本
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $text
     *
     * @return string
     */
    public function encryptText(string $text): string
    {
        return Rsa::encrypt($text, $this->platformPublicKeyInstance);
    }

    /**
     * 解密文本
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $encryptedText
     *
     * @return string
     */
    public function decryptText(string $encryptedText): string
    {
        return Rsa::decrypt($encryptedText, $this->merchantPrivateKeyInstance);
    }

    /**
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param       $resp
     * @param array $params 需要传递附加参数
     *
     * @return array
     * @lasttime: 2024/5/29 下午1:35
     */
    private function returnResp($resp, array $params = []): array
    {
        $statusCode = $resp->getStatusCode();
        $body       = $resp->getBody();
        $body2      = @json_decode($body, true);
        $body       = $body2 ?? $body;
        if (isset($body['prepay_id'])) {
            $this->prepayId = $body['prepay_id'];
        }

        $message = $statusCode == '200' ? 'success' : 'error';
        $message = $body['message'] ?? $message;

        // 其他情况
        return Util::error($statusCode, $message, $body, $params);
    }

    /**
     * 获取订单号
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param bool   $emptyNew 为空时是否生成一个新的
     * @param string $prefix   前缀
     *
     * @return string
     * @lasttime: 2024/5/30 下午5:29
     */
    private function getOutTradeNo(bool $emptyNew = false, string $prefix = 'jc'): string
    {
        $outTradeNo = $this->outTradeNo;
        if ($emptyNew && empty($outTradeNo)) {
            $outTradeNo = $this->outTradeNo = $prefix . date('YmdHis') . '000' . Util::random(4, true);
        }
        return $outTradeNo;
    }
}
