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
}
