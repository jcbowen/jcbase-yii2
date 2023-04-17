<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Jcbowen\JcbaseYii2\components\Content;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\sdk\Aop\AopClient;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeAppPayRequest;
use Jcbowen\JcbaseYii2\components\Util;
use stdClass;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class AliPay extends Component
{
    /**
     * @var string 支付宝网关(固定为：https://openapi.alipay.com/gateway.do)
     */
    public $gatewayUrl = 'https://openapi.alipay.com/gateway.do';

    /**
     * @var string 由支付宝分配给开发者的应用ID
     */
    public $appId;

    /**
     * @var string 应用私钥文件
     */
    public $rsaPrivateKeyFile;

    /**
     * @var string 支付宝公钥文件
     */
    public $alipayRsaPublicKeyFile;

    /**
     * @var string api版本
     */
    public $apiVersion = '1.0';

    /**
     * @var string 加签方式
     */
    public $signType = 'RSA2';

    /**
     * @var string 请求使用的编码格式，如utf-8,gbk,gb2312等
     */
    public $charset = 'utf-8';

    /**
     * @var string 返回数据格式
     */
    public $format = 'json';

    /**
     * @var string 支付宝服务器主动通知商户服务器里指定的页面http/https路径。
     */
    public $notify_url;

    /**
     * @var AopClient
     */
    public $instance;

    /**
     * @var string 商户网站唯一订单号。由商家自定义，64个字符以内，仅支持字母、数字、下划线且需保证在商户端不重复。
     */
    public $out_trade_no;

    /**
     * @var array 订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]，金额不能为0
     */
    public $total_amount = 0.00;

    /**
     * @var string 订单标题。注意：不可使用特殊字符，如 /，=，& 等。
     */
    public $subject;

    /**
     * @var string 下单成功后返回的支付宝订单号
     */
    public $trade_no;

    /**
     * @var array 错误信息
     */
    public $errors = [];

    /**
     * 构建支付宝支付实例
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string $type 支付宝支付客户端类型 APP:App
     * @return $this
     * @lasttime 2022/11/10 01:12
     */
    public function build(string $type = 'App'): AliPay
    {
        $this->appId                  = Yii::$app->params[$type . "Config"]['alipay']['app_id'];
        $this->rsaPrivateKeyFile      = Yii::getAlias(Yii::$app->params[$type . "Config"]['alipay']['rsaPrivateKeyFile'] ?? "@common/pay/alipay/$this->appId/rsa_private_key.txt");
        $this->alipayRsaPublicKeyFile = Yii::getAlias(Yii::$app->params[$type . "Config"]['alipay']['alipayRsaPublicKeyFile'] ?? "@common/pay/alipay/$this->appId/alipay_rsa_public_key.txt");
        if (!file_exists($this->rsaPrivateKeyFile)) {
            $this->errors[] = '应用私钥文件不存在';
            return $this;
        }
        $this->notify_url = Yii::$app->params[$type . "Config"]['alipay']['notifyUrl'];

        // 获取应用私钥和支付宝公钥
        $rsaPrivateKey      = file_get_contents($this->rsaPrivateKeyFile);
        $alipayRsaPublicKey = file_get_contents($this->alipayRsaPublicKeyFile);
        // 去除不需要的字符
        $rsaPrivateKey      = Content::removeRN(trim($rsaPrivateKey));
        $alipayRsaPublicKey = Content::removeRN(trim($alipayRsaPublicKey));

        $this->instance                     = new AopClient();
        $this->instance->gatewayUrl         = $this->gatewayUrl;
        $this->instance->appId              = $this->appId;
        $this->instance->rsaPrivateKey      = $rsaPrivateKey;
        $this->instance->alipayrsaPublicKey = $alipayRsaPublicKey;
        $this->instance->apiVersion         = $this->apiVersion;
        $this->instance->signType           = $this->signType;
        $this->instance->postCharset        = $this->charset;
        $this->instance->format             = $this->format;

        return $this;
    }

    /**
     * 设置订单总金额
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param float|int $total 订单总金额(如果传入的是整数，则单位为分；如果传入的是浮点数，则单位为元；推荐使用浮点数)
     * @return $this
     * @lasttime 2022/11/10 01:15
     */
    public function total_amount($total = 0): AliPay
    {
        // 如果不包含浮点数，意味着是整数，需要转换为元
        if (Util::strExists($total, '.') === false) {
            $this->total_amount = bcdiv($total, 100, 2);
        } else {
            $this->total_amount = Util::round_money($total);
        }

        return $this;
    }

    /**
     * 商品描述
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|null $subject 订单标题。注意：不可使用特殊字符，如 /，=，& 等。
     * @return $this
     * @lasttime 2022/11/10 01:18
     */
    public function subject(string $subject = null): AliPay
    {
        $this->subject = $subject;
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
    public function outTradeNo(string $outTradeNo = null): AliPay
    {
        $this->out_trade_no = $outTradeNo;
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
    public function notifyUrl(string $notifyUrl = ''): AliPay
    {
        $this->notify_url = $notifyUrl;
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
    public function set(array $data = []): AliPay
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
    public function setProperty(array $data): AliPay
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
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2023/3/10 12:21
     */
    public function checkTransactionsError()
    {
        if ($this->total_amount <= 0)
            $this->errors[] = '金额必须大于0';

        if (!Util::strExists($this->total_amount, '.'))
            $this->errors[] = '订单金额(单位元)必须为保留两位小数的浮点数';

        if (!empty($this->errors))
            return Util::error(ErrCode::PARAMETER_ERROR, 'errors', $this->errors);

        return true;
    }

    /**
     * app下单
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|mixed|true
     * @lasttime: 2023/3/10 12:15
     */
    public function APP()
    {
        $check = $this->checkTransactionsError();
        if (Util::isError($check))
            return $check;

        $object               = new stdClass();
        $object->out_trade_no = $this->out_trade_no ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true);
        $object->total_amount = $this->total_amount;
        $object->subject      = $this->subject ?? '商品' . date('YmdHis');
        $object->product_code = 'QUICK_MSECURITY_PAY'; // app支付需固定为此参数

        $json    = json_encode($object);
        $request = new AlipayTradeAppPayRequest();
        $request->setNotifyUrl($this->notify_url ?: '');
        $request->setBizContent($json);

        $result = $this->instance->sdkExecute($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {

            if (isset($result->$responseNode->trade_no))
                $this->trade_no = $result->$responseNode->trade_no;

            return ArrayHelper::toArray($result);
        } else {
            return Util::error($resultCode, $result->$responseNode->sub_msg, ArrayHelper::toArray($result));
        }
    }
}
