<?php

namespace Jcbowen\JcbaseYii2\components;

use Jcbowen\JcbaseYii2\base\ComponentArrayAccess;
use ReflectionException;
use ReflectionFunction;
use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;

/**
 * Class PaymentNotifyData
 * 支付平台异步通知数据解释器
 *
 * @author  Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2023/4/19 9:06 AM
 * @package frontend\components
 */
class PaymentNotifyData extends ComponentArrayAccess
{
    /** @var string 支付平台，目前仅支持alipay wechatPay */
    public $platform;

    /** @var string 回调类型。支付/退款 */
    public $eventType;

    /**
     * @var array|mixed 支付平台回调数据
     */
    public $rawData;

    /**
     * @var array 解密后的数据
     */
    public $decryptData;

    // ----- 需要使用的数据 ----- /

    /**
     * @var string 支付平台通知ID
     * - 必填
     */
    public $notifyId;

    /**
     * @var string 通知类型
     * - 必填
     */
    public $notifyType;

    /**
     * @var string 通知时间
     * - 必填
     */
    public $notifyTime;

    /**
     * @var string 支付平台应用ID
     * - 支付宝必填
     * - 微信退款回调中没有此字段
     */
    public $appId;

    /**
     * @var string 支付平台商户号
     * - 支付宝-appid
     * - 微信必填
     */
    public $mchId;

    /**
     * @var string 支付平台子商户号
     * - 支付宝-auth_app_id
     * - 微信必填
     */
    public $subMchId;

    /**
     * @var string 发起支付的交易号(我方)
     * - 必填
     */
    public $outTradeNo;

    /**
     * @var string 支付平台的交易号
     * - 必填
     */
    public $tradeNo;

    /**
     * @var string 退款的交易号
     * - 支付宝仅在我方发起退款时传入了out_request_no时才必填
     * - 微信退款必填
     */
    public $outRefundNo;

    /**
     * @var string 支付平台退款交易号
     * - 支付宝无
     * - 微信退款必填
     */
    public $refundNo;

    /**
     * @var string 货币类型
     * - 支付宝无
     * - 微信支付，支付必填，退款无
     * - CNY：人民币，境内商户号仅支持人民币。
     */
    public $amountCurrency = 'CNY';

    /**
     * @var int 订单总金额(总，单位分)
     * - 必填
     */
    public $amountTotal = 0;

    /**
     * @var int 退款金额(本次退款，单位分)
     * - 退款必填
     */
    public $amountRefund = 0;

    /**
     * @var int 实际退款金额（退给用户的金额，单位分)，不包含所有优惠券金额
     * - 支付宝退款选填，在支付宝的回调通知中如果金额过小，这里会显示为0
     * - 微信退款必填
     */
    public $amountRefundReal = 0;

    /**
     * @var string 退款入账账户
     * - 支付宝无
     * - 微信退款必填
     */
    public $userReceivedAccount;

    // ----- 商家批量转账回调 ----- /
    /**
     * @var string 【商家批次单号】 商户系统内部的商家批次单号，在商户系统内部唯一
     *             - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $outBatchNo;

    /**
     * @var string 【微信批次单号】 微信批次单号，微信商家转账系统返回的唯一标识
     *             - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $batchId;

    /**
     * @var string 【批次状态】
     *             - WAIT_PAY: 待付款确认。需要付款出资商户在商家助手小程序或服务商助手小程序进行付款确认
     *             -
     *             ACCEPTED:已受理。批次已受理成功，若发起批量转账的30分钟后，转账批次单仍处于该状态，可能原因是商户账户余额不足等。商户可查询账户资金流水，若该笔转账批次单的扣款已经发生，则表示批次已经进入转账中，请再次查单确认
     *             - PROCESSING:转账中。已开始处理批次内的转账明细单
     *             - FINISHED:已完成。批次内的所有转账明细单都已处理完成
     *             - CLOSED:已关闭。可查询具体的批次关闭原因确认
     *             - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $batchStatus;

    /**
     * @var integer 【批次总笔数】转账总笔数。
     *              - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $totalNum;

    /**
     * @var integer 【批次总金额】转账总金额，单位为“分”。
     *              - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $totalAmount;

    /**
     * @var string 【批次关闭原因】如果批次单状态为“CLOSED”（已关闭），则有关闭原因
     *             可选取值：
     *             OVERDUE_CLOSE：系统超时关闭，可能原因账户余额不足或其他错误
     *             TRANSFER_SCENE_INVALID：付款确认时，转账场景已不可用，系统做关单处理
     *             - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $closeReason;

    /**
     * @var integer 【转账成功金额】
     *                     转账成功的金额，单位为“分”。当批次状态为“PROCESSING”（转账中）时，转账成功金额随时可能变化
     *                     - 微信必填，来自于商家转账到零钱的结果回调，目前只接入了微信
     */
    public $successAmount;

    /**
     * @var integer 【转账成功笔数】
     * 转账成功的笔数。当批次状态为“PROCESSING”（转账中）时，转账成功笔数随时可能变化
     */
    public $successNum;

    /**
     * @var integer 【转账失败金额】转账失败的金额，单位为“分”
     */
    public $failAmount;

    /**
     * @var integer 【转账失败笔数】转账失败的笔数
     */
    public $failNum;

    /**
     * {@inheritDoc}
     */
    public function __construct($rawData, $config = [])
    {
        $this->rawData = is_array($rawData) ? $rawData : json_decode($rawData, true);
        parent::__construct($config);
    }

    /**
     * 解析支付平台回调数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|null $decryptData 解密后的数据，如果为空则使用$this->decryptData
     *
     * @return PaymentNotifyData
     * @throws InvalidArgumentException
     * @lasttime: 2023/4/19 3:24 PM
     */
    public function parse(?array $decryptData = null): PaymentNotifyData
    {
        $this->decryptData = $decryptData ?? $this->decryptData;

        if (Util::isError($this->decryptData))
            throw new InvalidArgumentException($this->decryptData['errmsg'] ?? '无法解析支付平台回调数据');

        // 如果platform没有值则根据$this->decryptData判断是支付宝的还是微信的
        if ($this->platform === 'alipay' || (empty($this->platform) && !empty($this->decryptData['notify_type']) && $this->decryptData['notify_type'] === 'trade_status_sync')) {
            $this->platform = 'alipay';
        } elseif ($this->platform === 'wechatPay' || (empty($this->platform) && !empty($this->rawData['event_type']))) {
            $this->platform = 'wechatPay';
        } else {
            Yii::error(ArrayHelper::toArray($this), 'invalidPaymentPlatform');
            throw new InvalidArgumentException('无法识别的支付平台');
            return $this;
        }

        // 解析数据
        $parseMethod = 'parse' . ucfirst($this->platform);
        $this->$parseMethod($this->decryptData);

        // 提示模式下输出解析数据
        Yii::debug(ArrayHelper::toArray($this), 'PaymentNotifyDataParse');

        return $this;
    }

    /**
     * 解密支付平台回调数据
     * 暂不支持自动解密，所以需要通过传递回调函数的方式进行处理
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $decryptCallback
     *
     * @return $this
     * @lasttime: 2023/4/19 3:31 PM
     */
    public function decrypt($decryptCallback): PaymentNotifyData
    {
        if (!is_callable($decryptCallback)) {
            throw new InvalidArgumentException('参数错误，传递的不是一个回调函数');
        }

        try {
            $reflection = new ReflectionFunction($decryptCallback);
        } catch (ReflectionException $e) {
            throw new InvalidArgumentException('请传递正确的解密回调函数');
        }

        $params = $reflection->getParameters();

        if (count($params) !== 1) {
            throw new InvalidArgumentException('回调函数必须有且仅有一个参数');
        }

        if ($params[0]->getClass()->getName() !== self::class) {
            throw new InvalidArgumentException('回调函数的参数必须是' . self::class);
        }

        $this->decryptData = $decryptCallback($this) ?? $this->decryptData;

        return $this;
    }

    /**
     * 解析支付宝回调数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $decryptData 解密后的数据
     *
     * @lasttime: 2023/4/19 3:52 PM
     */
    private function parseAlipay(array $decryptData = [])
    {
        $this->notifyId   = $decryptData['notify_id'];
        $this->notifyType = $decryptData['notify_type'];
        $this->notifyTime = $decryptData['notify_time'];

        $this->appId    = $decryptData['app_id'];
        $this->mchId    = $decryptData['app_id'];
        $this->subMchId = $decryptData['auth_app_id'] ?? '';

        $this->outTradeNo = $decryptData['out_trade_no'];
        $this->tradeNo    = $decryptData['trade_no'];

        $this->amountTotal = bcmul($decryptData['total_amount'], 100);

        // 如果退款金额大于0则为退款
        if (floatval($decryptData['refund_fee']) > 0)
            $this->eventType = 'refund';
        else
            $this->eventType = 'pay';

        // 如果是退款则需要解析退款数据
        if ($this->eventType === 'refund') {
            $this->outRefundNo      = $decryptData['out_biz_no'] ?? '';
            $this->amountRefund     = bcmul($decryptData['refund_fee'], 100);
            $this->amountRefundReal = bcmul($decryptData['send_back_fee'], 100);
        }
    }

    /**
     * 解析微信回调数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $decryptData 解密后的数据
     *
     * @lasttime: 2023/4/19 4:36 PM
     */
    private function parseWechatPay(array $decryptData = [])
    {
        $this->notifyId   = $this->rawData['id'] ?? null;
        $this->notifyType = $this->rawData['event_type'] ?? null;
        $this->notifyTime = $this->rawData['create_time'] ?? null;

        $this->appId    = $decryptData['appid'] ?? null;
        $this->mchId    = $decryptData['mchid'];
        $this->subMchId = $decryptData['sub_mchid'] ?? '';

        $this->outTradeNo = $decryptData['out_trade_no'];
        $this->tradeNo    = $decryptData['transaction_id'];

        $this->amountTotal = $decryptData['amount']['total'];

        // 如果存在原始回调数据，就通过原始回调数据判断是支付还是退款
        if (!empty($this->rawData)) {
            if ($this->rawData['resource']['original_type'] === 'transaction') {
                $this->eventType = 'pay';
            } elseif ($this->rawData['resource']['original_type'] === 'refund') {
                $this->eventType = 'refund';
            } elseif ($this->rawData['resource']['original_type'] === 'mch_payment') {
                // 商家转账到零钱
                $this->eventType = 'transfer_batches';
            } else {
                Yii::error(ArrayHelper::toArray($this), 'invalidWechatPaymentStatus');
                throw new InvalidArgumentException('暂不支持的微信支付状态');
            }
        }

        if (empty($this->eventType)) {
            if ($this->decryptData['trade_state'] === 'SUCCESS') {
                $this->eventType = 'pay';
            } elseif ($this->decryptData['refund_status'] === 'SUCCESS') {
                $this->eventType = 'refund';
            } else {
                Yii::error(ArrayHelper::toArray($this), 'invalidWechatPaymentStatus');
                throw new InvalidArgumentException('暂不支持的微信支付状态');
            }
        }

        // 如果是退款，就解析退款数据
        if ($this->eventType === 'refund') {
            $this->outRefundNo         = $decryptData['out_refund_no'];
            $this->refundNo            = $decryptData['refund_id'];
            $this->amountRefund        = $decryptData['amount']['refund'];
            $this->amountRefundReal    = $decryptData['amount']['payer_refund'];
            $this->userReceivedAccount = $decryptData['user_received_account'];
        } elseif ($this->eventType === 'pay') {
            $this->amountCurrency = $decryptData['amount']['currency'];
        } elseif ($this->eventType === 'transfer_batches') {
            $this->outBatchNo    = $decryptData['out_batch_no'];
            $this->batchId       = $decryptData['batch_id'];
            $this->batchStatus   = $decryptData['batch_status'];
            $this->totalNum      = $decryptData['total_num'];
            $this->totalAmount   = $decryptData['total_amount'];
            $this->closeReason   = $decryptData['close_reason'] ?? '';
            $this->successAmount = $decryptData['success_amount'] ?? 0;
            $this->successNum    = $decryptData['success_num'] ?? 0;
            $this->failNum       = $decryptData['fail_amount'] ?? 0;
        }
    }
}