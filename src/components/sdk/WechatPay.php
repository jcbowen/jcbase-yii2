<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Exception;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Util;
use WeChatPay\Builder;
use WeChatPay\BuilderChainable;
use WeChatPay\Crypto\Rsa;
use WeChatPay\Formatter;
use WeChatPay\Util\PemUtil;
use Yii;
use yii\base\Component;

class WechatPay extends Component
{
    /**
     * @var string v3 API密钥
     */
    public $apiKey;
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
     * @var string 异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。 公网域名必须为https，如果是走专线接入，使用专线NAT IP或者私有回调域名可使用http。 示例值：https://www.weixin.qq.com/wxpay/pay.php
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
        'openid' => '', // 用户在直连商户appid下的唯一标识。 下单前需获取到用户的Openid。 示例值：oUpF8uMuAJO_M2pxb1Q9zNjWeS6o
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
     * @var array 错误信息
     */
    public $errors = [];

    /**
     * 构建APIv3客户端实例
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $type 微信支付类型 小程序:WeChatMiniProgram，公众号:WeChatOfficialAccount，企业微信:WeChatWork，APP:App
     * @return $this
     * @lasttime 2022/11/10 01:12
     */
    public function build(string $type = 'WeChatMiniProgram'): WechatPay
    {
        $this->merchantId                = Yii::$app->params[$type . "Config"]['mchId'];
        $this->appId                     = Yii::$app->params[$type . "Config"]['app_id'];
        $this->merchantCertificateSerial = Yii::$app->params[$type . "Config"]['merchantCertificateSerial'];
        $this->apiKey                    = Yii::$app->params[$type . "Config"]['apiKey'];
        $this->notifyUrl                 = Yii::$app->params[$type . "Config"]['notifyUrl'];

        // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
        $merchantPrivateKeyFilePath = Yii::getAlias('@common/pay/wechat/') . $this->merchantId . '/apiclient_key.pem';
        if (!file_exists($merchantPrivateKeyFilePath)) {
            $this->errors[] = '商户API私钥文件不存在';
            return $this;
        }
        $merchantPrivateKeyFilePath = 'file://' . $merchantPrivateKeyFilePath;
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);

        // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
        $platformCertificateFilePath = 'file://' . Yii::getAlias('@common/pay/wechat/') . $this->merchantId . '/cert.pem';
        $platformPublicKeyInstance   = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

        // 从「微信支付平台证书」中获取「证书序列号」
        $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);

        // 构造一个 APIv3 客户端实例
        $this->instance = Builder::factory([
            'mchid'      => $this->merchantId, // 商户号
            'serial'     => $this->merchantCertificateSerial, // 「商户API证书」的「证书序列号」
            'privateKey' => $merchantPrivateKeyInstance,
            'certs'      => [
                $platformCertificateSerial => $platformPublicKeyInstance,
            ],
        ]);

        return $this;
    }

    /**
     * 设置订单总金额
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param float|int $total 订单总金额(如果传入的是整数，则单位为分；如果传入的是浮点数，则单位为元；推荐使用整数)
     * @return $this
     * @lasttime 2022/11/10 01:15
     */
    public function amount($total = 0): WechatPay
    {
        // 如果传入的单位是元，则转换为分
        if (Util::strExists($total, '.') !== false) {
            $total = bcmul($total, 100);
        }

        $this->amount['total'] = $total;
        return $this;
    }

    /**
     * 商品描述
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|null $description 商品描述
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
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|null $outTradeNo
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
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $notifyUrl
     * @return $this
     * @lasttime 2022/11/10 01:25
     */
    public function notifyUrl(string $notifyUrl = ''): WechatPay
    {
        $this->notifyUrl = $notifyUrl;
        return $this;
    }

    /**
     * 设置支付者openid
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $openid
     * @return $this
     * @lasttime 2022/11/10 01:26
     */
    public function payer(string $openid = ''): WechatPay
    {
        $this->payer['openid'] = $openid;
        return $this;
    }

    /**
     * 设置附加数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $attach
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
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $data
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
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $data
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
     * 检查jsApi参数是否有误
     * @return array|true
     */
    public function checkJsApiError()
    {
        if ($this->amount['total'] <= 0)
            $this->errors[] = '金额必须大于0';

        if (Util::strExists($this->amount['total'], '.'))
            $this->errors[] = '订单金额(单位分)必须为整数';

        if (empty($this->payer['openid']))
            $this->errors[] = '支付者openid不能为空';

        if (empty($this->notifyUrl))
            $this->errors[] = '回调地址不能为空';

        if (!empty($this->errors))
            return Util::error(ErrCode::PARAMETER_ERROR, 'errors', $this->errors);

        return true;
    }

    /**
     * jsApi下单
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|bool|mixed
     * @lasttime 2022/11/10 01:29
     */
    public function jsApi()
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

        if(!empty($this->attach)){
            $jsonData['attach'] = $this->attach;
        }

        try {
            $resp = $this->instance->chain('v3/pay/transactions/jsapi')->post([
                'json' => $jsonData,
            ]);
            if ($resp->getStatusCode() == 200) {
                $body = $resp->getBody();
                $body = json_decode($body, true);
                if (isset($body['prepay_id'])) {
                    $this->prepayId = $body['prepay_id'];
                }
                return $body;
            }
            return Util::error($resp->getStatusCode(), '请求失败', $resp->getBody());
        } catch (Exception $e) {
            return Util::error($e->getResponse()->getStatusCode(), $e->getMessage());
        }
    }

    /**
     * 生成前端调用jsApi支付的参数
     * 执行前务必保证当前实例已经执行过jsApi方法
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/11/10 01:31
     */
    public function getSignParams(): array
    {
        if (empty($this->prepayId))
            return Util::error(ErrCode::PARAMETER_ERROR, 'prepayId不能为空');

        $merchantPrivateKeyFilePath = 'file://' . Yii::getAlias('@common/pay/wechat/') . $this->merchantId . '/apiclient_key.pem';
        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath);

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

        return $params;
    }

    /**
     * 订单查询
     * 默认通过商户订单号查询，如果传入了微信支付订单号，则以微信支付订单号查询
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|null $transactionId 微信支付系统生成的订单号 示例值：1217752501201407033233368018
     * @return array|mixed
     * @lasttime 2022/11/10 01:47
     */
    public function query(string $transactionId = null)
    {
        if (empty($transactionId) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'transactionId和outTradeNo不能同时为空');

        if (!empty($transactionId)) {
            $path = 'v3/pay/transactions/id/' . $transactionId . '?mchid=' . $this->merchantId;
        } else {
            $path = 'v3/pay/transactions/out-trade-no/' . $this->outTradeNo . '?mchid=' . $this->merchantId;
        }

        try {
            $resp = $this->instance->chain($path)->get();
            if ($resp->getStatusCode() == 200) {
                $body = $resp->getBody();
                return json_decode($body, true);
            }
            return Util::error($resp->getStatusCode(), '请求失败', $resp->getBody());
        } catch (Exception $e) {
            return Util::error($e->getResponse()->getStatusCode(), $e->getMessage());
        }
    }

    /**
     * 关闭订单
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|mixed
     * @lasttime 2022/11/10 01:59
     */
    public function close()
    {
        if (empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'outTradeNo不能为空');

        try {
            $path = 'v3/pay/transactions/out-trade-no/' . $this->outTradeNo . '/close';
            $resp = $this->instance->chain($path)->post([
                'json' => [
                    'mchid' => $this->merchantId,
                ],
            ]);
            if ($resp->getStatusCode() == 200) {
                $body = $resp->getBody();
                return json_decode($body, true);
            }
            return Util::error($resp->getStatusCode(), '请求失败', $resp->getBody());
        } catch (Exception $e) {
            return Util::error($e->getResponse()->getStatusCode(), $e->getMessage());
        }
    }

    /**
     * 申请退款
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param int $refundAmount 退款金额，单位为分
     * @param int $totalAmount 订单总金额，单位为分
     * @param string|null $outRefundNo 商户退款单号
     * @param string|null $refundReason 退款原因
     * @param string|null $transactionId 微信支付系统生成的订单号 示例值：1217752501201407033233368018
     * @return array|mixed
     * @lasttime 2022/11/10 02:12
     */
    public function refund(int $refundAmount, int $totalAmount, ?string $outRefundNo = null, ?string $refundReason = '', string $transactionId = null)
    {
        if (empty($transactionId) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'transactionId和outTradeNo不能同时为空');
        if (empty($refundAmount))
            return Util::error(ErrCode::PARAMETER_ERROR, 'refundAmount不能为空');
        $outRefundNo = $outRefundNo ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true);
        if (empty($totalAmount))
            return Util::error(ErrCode::PARAMETER_ERROR, 'totalAmount不能为空');
        if (empty($this->notifyUrl))
            return Util::error(ErrCode::PARAMETER_ERROR, 'notifyUrl不能为空');

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

        if (!empty($refundReason))
            $jsonData['reason'] = $refundReason;

        try {
            $resp = $this->instance->chain('v3/refund/domestic/refunds')->post([
                'json' => $jsonData,
            ]);
            if ($resp->getStatusCode() == 200) {
                $body = $resp->getBody();
                return json_decode($body, true);
            }
            return Util::error($resp->getStatusCode(), '请求失败', $resp->getBody());
        } catch (Exception $e) {
            return Util::error($e->getResponse()->getStatusCode(), $e->getMessage());
        }
    }
}
