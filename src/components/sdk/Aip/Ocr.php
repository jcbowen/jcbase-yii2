<?php

namespace Jcbowen\JcbaseYii2\components\sdk\Aip;

use Jcbowen\JcbaseYii2\components\Communication;
use Jcbowen\JcbaseYii2\components\ErrCode;
use Jcbowen\JcbaseYii2\components\Util;
use yii\helpers\ArrayHelper;

class Ocr extends Base
{
    /**
     * 身份证识别接口
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $image 图片url
     * @param array $options
     * - detect_risk 是否开启身份证风险类型(身份证复印件、临时身份证、身份证翻拍、修改过的身份证)功能，默认不开启，即：false。
     * - detect_quality 是否开启身份证质量类型(边框/四角不完整、头像或关键字段被遮挡/马赛克)检测功能，默认不开启，即：false。
     * - detect_photo 是否开启身份证头像照片检测功能，默认不开启，即：false。
     * - detect_card 是否检测身份证进行裁剪，默认不检测，默认不检测，即：false。
     * @return array|false|int|resource|string|null
     * @lasttime: 2022/11/3 13:57
     */
    public function idCard(string $image, array $options = [])
    {
        $obj    = $this->Auth();
        $token  = $obj['access_token'];
        $apiUrl = 'https://aip.baidubce.com/rest/2.0/ocr/v1/multi_idcard';
        $data   = [
            'url' => $image,
        ];
        if (!empty($options)) {
            foreach ($options as &$item) {
                $item = !empty($item) ? 'true' : 'false';
            }
            $data = ArrayHelper::merge($data, $options);
        }

        $response = Communication::post($apiUrl, [
            'access_token' => $token
        ], $data);

        if ($response['code'] != 200) {
            return Util::error(ErrCode::NETWORK_ERROR, '网络错误', $response);
        }

        if (empty($response['content'])) {
            return Util::error(ErrCode::NETWORK_ERROR, '返回信息为空', $response);
        }

        $content = @json_decode($response['content'], true);
        if (empty($content)) {
            return Util::error(ErrCode::NETWORK_ERROR, '返回信息解析失败', $response);
        }

        if (!empty($content['error_code'])) {
            return Util::error(ErrCode::NETWORK_ERROR, $content['error_msg'], $content);
        }

        return $content;
    }

    /**
     * 将身份证识别接口返回的数据进行解析
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     * @return array
     * @lasttime: 2022/11/4 14:57
     */
    public static function parseIdCard(array $data = []): array
    {
        $translate = [
            '姓名'         => 'name',
            '民族'         => 'nation',
            '住址'         => 'address',
            '公民身份号码' => 'idCard',
            '出生'         => 'birth',
            '性别'         => 'genderTitle',
            '签发机关'     => 'issue',
            '签发日期'     => 'issued',
            '失效日期'     => 'expired',
        ];

        if (empty($data['words_result'])) {
            return Util::error(ErrCode::PARAMETER_EMPTY, '身份证信息为空');
        }

        $result = [];
        foreach ($data['words_result'] as $item) {
            foreach ($item['card_result'] as $key => $value) {
                if (isset($translate[$key])) {
                    $result[$translate[$key]] = $value['words'];
                }
            }
        }

        $result['gender']     = $result['genderTitle'] == '男' ? 1 : 2;
        $birth                = date('Y-m-d', strtotime($result['birth']));
        $result['birth']      = $birth;
        $birthArr             = explode('-', $birth);
        $result['birthYear']  = $birthArr[0];
        $result['birthMonth'] = $birthArr[1];
        $result['birthDay']   = $birthArr[2];
        $result['issued']     = date('Y-m-d', strtotime($result['issued']));
        $result['expired']    = date('Y-m-d', strtotime($result['expired']));

        return $result;
    }
}
