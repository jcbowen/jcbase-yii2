<?php

namespace Jcbowen\JcbaseYii2\components\sdk\helper\WechatPay;

use Jcbowen\JcbaseYii2\base\ComponentArrayAccess;

/**
 * 批量转账列表
 */
class TransferDetailInput extends ComponentArrayAccess
{
    /**
     * @var string 【商家明细单号】 商户系统内部区分转账批次单下不同转账明细单的唯一标识，要求此参数只能由数字、大小写字母组成
     */
    public $out_detail_no;

    /**
     * @var integer 【转账金额】 转账金额单位为“分”
     */
    public $transfer_amount;

    /**
     * @var string 【转账备注】 单条转账备注（微信用户会收到该备注），UTF8编码，最多允许32个字符
     */
    public $transfer_remark;

    /**
     * @var string 【收款用户openid】 商户appid下，某用户的openid
     */
    public $openid;

    /**
     * @var string 【收款用户姓名】 收款方真实姓名。支持标准RSA算法和国密算法，公钥由微信侧提供
     * 明细转账金额<0.3元时，不允许填写收款用户姓名
     * 明细转账金额 >= 2,000元时，该笔明细必须填写收款用户姓名
     * 同一批次转账明细中的姓名字段传入规则需保持一致，也即全部填写、或全部不填写
     * 若商户传入收款用户姓名，微信支付会校验用户openID与姓名是否一致，并提供电子回单
     */
    public $user_name;
    
    /**
     * {@inheritdoc}
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        $arr = parent::toArray($fields, $expand, $recursive);

        // 金额只能是整数
        $arr['transfer_amount'] = (int)$arr['transfer_amount'];

        // 忽略不需要的字段
        if ($arr['transfer_amount'] < 30 || empty($arr['user_name']))
            unset($arr['user_name']);

        return $arr;
    }
}