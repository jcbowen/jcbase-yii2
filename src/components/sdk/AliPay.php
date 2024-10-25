<?php

namespace Jcbowen\JcbaseYii2\components\sdk;

use Exception;
use Jcbowen\JcbaseYii2\components\Content;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Safe;
use Jcbowen\JcbaseYii2\components\sdk\Aop\AopClient;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeAppPayRequest;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeCloseRequest;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeFastpayRefundQueryRequest;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeQueryRequest;
use Jcbowen\JcbaseYii2\components\sdk\Aop\request\AlipayTradeRefundRequest;
use Jcbowen\JcbaseYii2\components\Util;
use stdClass;
use Yii;
use yii\base\Component;
use yii\base\InvalidArgumentException;
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
    public $charset = 'UTF-8';

    /**
     * @var string 返回数据格式
     */
    public $format = 'json';

    /**
     * @var string 支付宝服务器主动通知商户服务器里指定的页面http/https路径。
     */
    public $notifyUrl = '';

    /**
     * @var string 商户网站唯一订单号。由商家自定义，64个字符以内，仅支持字母、数字、下划线且需保证在商户端不重复。
     */
    public $outTradeNo;

    /**
     * @var float 订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]，金额不能为0
     */
    public $totalAmount = 0.00;

    /**
     * @var string 订单标题。注意：不可使用特殊字符，如 /，=，& 等。
     */
    public $subject;

    /**
     * @var string 下单成功后返回的支付宝订单号
     */
    public $trade_no;

    // ------ 以下为SDK运行中的参数 ------ /

    /**
     * @var AopClient
     */
    public $instance;

    /**
     * @var array 错误信息
     */
    public $errors = [];

    /**
     * @var string 证书路径
     */
    public $certPath = '@common/pay/alipay/';

    /**
     * 构建支付宝支付实例
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return $this
     * @lasttime 2022/11/10 01:12
     */
    public function build(): AliPay
    {
        $this->appId                  = Yii::$app->params['alipay']['app_id'];
        $this->rsaPrivateKeyFile      = Yii::getAlias(Yii::$app->params['alipay']['rsaPrivateKeyFile'] ?? $this->certPath . "$this->appId/rsa_private_key.txt");
        $this->alipayRsaPublicKeyFile = Yii::getAlias(Yii::$app->params['alipay']['alipayRsaPublicKeyFile'] ?? $this->certPath . "$this->appId/alipay_rsa_public_key.txt");
        if (!file_exists($this->rsaPrivateKeyFile))
            throw new InvalidArgumentException("商户API私钥文件不存在:$this->rsaPrivateKeyFile");

        $this->notifyUrl = $this->notifyUrl ?: Yii::$app->params['alipay']['notifyUrl'];

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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param float|int $total 订单总金额(如果传入的是整数，则单位为分；如果传入的是浮点数，则单位为元；推荐使用浮点数)
     *
     * @return $this
     * @lasttime 2022/11/10 01:15
     */
    public function totalAmount($total = 0): AliPay
    {
        // 如果不包含浮点数，意味着是整数，需要转换为元
        if (Util::strExists($total, '.') === false)
            $this->totalAmount = bcdiv($total, 100, 2);

        $this->totalAmount = Util::round_money($total);

        return $this;
    }

    /**
     * 商品描述
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $subject 订单标题。注意：不可使用特殊字符，如 /，=，& 等。
     *
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|null $outTradeNo
     *
     * @return $this
     * @lasttime 2022/11/10 01:25
     */
    public function outTradeNo(string $outTradeNo = null): AliPay
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
    public function notifyUrl(string $notifyUrl = ''): AliPay
    {
        $this->notifyUrl = $notifyUrl;
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     *
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|true
     * @lasttime: 2023/3/10 12:21
     */
    public function checkTransactionsError()
    {
        if ($this->totalAmount <= 0)
            $this->errors[] = '金额必须大于0';

        if (!empty($this->errors))
            return Util::error(ErrCode::PARAMETER_ERROR, 'errors', $this->errors);

        return true;
    }

    /**
     * app下单
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return string|array
     * @lasttime: 2023/3/10 12:15
     */
    private function appPay()
    {
        $check = $this->checkTransactionsError();
        if (Util::isError($check))
            return $check;

        $object               = new stdClass();
        $object->out_trade_no = $this->outTradeNo ?? 'jc' . date('YmdHis') . '000' . Util::random(4, true);
        $object->total_amount = $this->totalAmount;
        $object->subject      = $this->subject ?? '商品' . date('YmdHis');
        $object->product_code = 'QUICK_MSECURITY_PAY'; // app支付需固定为此参数

        $json    = json_encode($object);
        $request = new AlipayTradeAppPayRequest();
        $request->setNotifyUrl($this->notifyUrl ?: '');
        $request->setBizContent($json);

        return $this->instance->sdkExecute($request);
    }

    /**
     * 进行支付下单
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $payProduct
     *
     * @return string|array
     * @lasttime: 2023/4/17 1:13 PM
     */
    public function pay(string $payProduct = 'wap')
    {
        $payProduct = Safe::gpcBelong($payProduct, ['app'], 'app') . 'Pay';
        return $this->$payProduct();
    }

    /**
     * 查询订单
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $trade_no 支付宝订单号，如果为空，则通过商户订单号查询
     *
     * @return array
     * @throws Exception
     * @lasttime: 2023/4/17 1:27 PM
     */
    public function query(string $trade_no = ''): array
    {
        $trade_no = $trade_no ?: $this->trade_no;
        if (empty($trade_no) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'trade_no和out_trade_no不能同时为空');

        $object = new stdClass();
        if (!empty($trade_no))
            $object->trade_no = $trade_no;
        if (empty($object->trade_no) && !empty($this->outTradeNo))
            $object->out_trade_no = $this->outTradeNo;

        $json    = json_encode($object);
        $request = new AlipayTradeQueryRequest();
        $request->setBizContent($json);
        $result = $this->instance->execute($request);

        $result       = ArrayHelper::toArray($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result[$responseNode]['code'];
        if (!empty($resultCode) && $resultCode == 10000) {
            return Util::error(ErrCode::SUCCESS, 'success', $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        } else {
            return Util::error($resultCode, $result[$responseNode]['sub_msg'], $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        }
    }

    /**
     * 关闭订单
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $trade_no 支付宝订单号，如果为空，则通过商户订单号查询
     *
     * @return array
     * @throws Exception
     * @lasttime: 2023/4/17 1:31 PM
     */
    public function close(string $trade_no = ''): array
    {
        $trade_no = $trade_no ?: $this->trade_no;
        if (empty($trade_no) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'trade_no和out_trade_no不能同时为空');

        $object = new stdClass();
        if (!empty($trade_no))
            $object->trade_no = $trade_no;
        if (empty($object->trade_no) && !empty($this->outTradeNo))
            $object->out_trade_no = $this->outTradeNo;

        $json    = json_encode($object);
        $request = new AlipayTradeCloseRequest();
        $request->setBizContent($json);
        $result = $this->instance->execute($request);

        $result       = ArrayHelper::toArray($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result[$responseNode]['code'];
        if (!empty($resultCode) && $resultCode == 10000) {
            return Util::error(ErrCode::SUCCESS, 'success', $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        } else {
            return Util::error($resultCode, $result[$responseNode]['sub_msg'], $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        }
    }

    /**
     * 申请退款
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param float       $refundAmount 退款金额（单位元）。需要退款的金额，该金额不能大于订单金额，单位为元，支持两位小数。注：如果正向交易使用了营销，该退款金额包含营销金额，支付宝会按业务规则分配营销和买家自有资金分别退多少，默认优先退买家的自有资金。如交易总金额100元，用户支付时使用了80元自有资金和20元无资金流的营销券，商家实际收款80元。如果首次请求退款60元，则60元全部从商家收款资金扣除退回给用户自有资产；如果再请求退款40元，则从商家收款资金扣除20元退回用户资产以及把20元的营销券退回给用户（券是否可再使用取决于券的规则配置）。
     * @param float       $totalAmount  订单总金额（单位元）
     * @param string|null $refundReason 退款原因说明。商家自定义，将在会在商户和用户的pc退款账单详情中展示
     * @param string|null $outRequestNo 退款请求号。标识一次退款请求，需要保证在交易号下唯一，如需部分退款，则此参数必传。注：针对同一次退款请求，如果调用接口失败或异常了，重试时需要保证退款请求号不能变更，防止该笔交易重复退款。支付宝会保证同样的退款请求号多次请求只会退一次。
     * @param string      $trade_no     支付宝交易号。和商户订单号 out_trade_no 不能同时为空。
     *
     * @return array
     * @throws Exception
     * @lasttime: 2023/4/17 1:58 PM
     */
    public function refund(float $refundAmount, float $totalAmount = 0.00, ?string $refundReason = '', ?string $outRequestNo = null, string $trade_no = ''): array
    {
        if ($refundAmount <= 0)
            return Util::error(ErrCode::PARAMETER_ERROR, '退款金额不能为空');

        if ($totalAmount > 0) {
            if ($refundAmount > $totalAmount)
                return Util::error(ErrCode::PARAMETER_ERROR, '退款金额不能大于订单金额');
            if ($refundAmount < $totalAmount && empty($outRequestNo))
                return Util::error(ErrCode::PARAMETER_ERROR, '部分退款时，退款请求号不能为空');
        }

        $trade_no = $trade_no ?: $this->trade_no;
        if (empty($trade_no) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'trade_no和out_trade_no不能同时为空');

        $object = new stdClass();
        if (!empty($trade_no))
            $object->trade_no = $trade_no;
        if (empty($object->trade_no) && !empty($this->outTradeNo))
            $object->out_trade_no = $this->outTradeNo;

        $object->refund_amount = $refundAmount;
        if (!empty($refundReason))
            $object->refund_reason = $refundReason;
        if (!empty($outRequestNo))
            $object->out_request_no = $outRequestNo;

        $json    = json_encode($object);
        $request = new AlipayTradeRefundRequest();
        $request->setBizContent($json);
        $result = $this->instance->execute($request);

        $result       = ArrayHelper::toArray($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result[$responseNode]['code'];
        if (!empty($resultCode) && $resultCode == 10000) {
            return Util::error(ErrCode::SUCCESS, 'success', $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        } else {
            return Util::error($resultCode, $result[$responseNode]['sub_msg'], $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        }
    }

    /**
     * 退款查询
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $outRequestNo 退款请求号。请求退款接口时，传入的退款请求号，如果在退款请求时未传入，则该值为创建交易时的商户订单号。
     * @param string $trade_no     支付宝交易号。和商户订单号 out_trade_no 不能同时为空。
     *
     * @return array
     * @throws Exception
     * @lasttime: 2023/4/17 4:12 PM
     */
    public function queryRefund(string $outRequestNo = '', string $trade_no = ''): array
    {
        $trade_no = $trade_no ?: $this->trade_no;
        if (empty($trade_no) && empty($this->outTradeNo))
            return Util::error(ErrCode::PARAMETER_ERROR, 'trade_no和out_trade_no不能同时为空');

        $object = new stdClass();
        if (!empty($trade_no))
            $object->trade_no = $trade_no;
        if (empty($object->trade_no) && !empty($this->outTradeNo))
            $object->out_trade_no = $this->outTradeNo;

        $object->out_request_no = $outRequestNo ?: $this->outTradeNo;

        $json    = json_encode($object);
        $request = new AlipayTradeFastpayRefundQueryRequest();
        $request->setBizContent($json);
        $result = $this->instance->execute($request);

        $result       = ArrayHelper::toArray($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result[$responseNode]['code'];
        if (!empty($resultCode) && $resultCode == 10000) {
            return Util::error(ErrCode::SUCCESS, 'success', $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        } else {
            return Util::error($resultCode, $result[$responseNode]['sub_msg'], $result[$responseNode], [
                'sign' => $result['sign'],
            ]);
        }
    }
}