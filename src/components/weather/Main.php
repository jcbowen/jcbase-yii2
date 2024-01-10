<?php

namespace Jcbowen\JcbaseYii2\components\weather;

use Jcbowen\JcbaseYii2\base\Component;
use Yii;

/**
 * Class Weather
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2024/1/4 11:38 PM
 * @package common\components
 */
class Main extends Component
{
    /**
     * @var array 城市数据，以省市区结构储存城市ID
     */
    public static $cityData = [];

    /**
     * @var array 城市搜索数据
     */
    public static $citySearchData = [];


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $cityDataJsonFile = file_get_contents(__DIR__ . '/json/city-data.json');
        static::$cityData = json_decode($cityDataJsonFile, true);

        $citySearchDataJsonFile = file_get_contents(__DIR__ . '/json/city-data.json');
        static::$citySearchData = json_decode($citySearchDataJsonFile, true);

        parent::init();
    }

    /**
     * 根据城市信息获取城市ID
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $city
     * @return int|mixed
     * @lasttime: 2024/1/8 5:17 PM
     */
    public function getCityId(array $city = [
        'country'  => '中国',
        'province' => '重庆市',
        'city'     => '重庆市',
        'district' => '九龙坡区'
    ])
    {
        $city['country'] = !empty($city['country']) ? $city['country'] : '中国';

        // 移除掉省、市、区、县字样
        $city['old_province'] = $city['province'];
        $city['province']     = str_replace(['省', '市', '区', '县'], '', $city['province']);
        $city['city']         = str_replace(['省', '市', '区', '县'], '', $city['city']);
        $city['district']     = str_replace(['省', '市', '区', '县'], '', $city['district']);

        $cityId = 0;

        // 国外地址或没有精确到城市就直接干掉
        if ($city['country'] !== '中国' || empty($city['city']))
            return $cityId;

        // 优先根据cityData进行常规拼装匹配
        if (isset(static::$cityData[$city['province']][$city['city']][$city['district']]['AREAID']))
            $cityId = static::$cityData[$city['province']][$city['city']][$city['district']]['AREAID'];

        // 常规拼装无法拼装出来时，再根据citySearchData进行搜索
        if (empty($cityId) && !empty($city['district'])) {
            $filter = array_filter(static::$citySearchData, function ($item) use ($city) {
                return
                    $item['n'] == $city['district'] &&
                    (
                        $item['pv'] == $city['province'] ||
                        $item['pv'] == $city['old_province'] . '景区' ||
                        $item['pv'] == $city['old_province'] . '景点'
                    );
            });
            if (!empty($filter))
                $cityId = $filter[0]['ac'];
        }

        // 根据城市进行拼装
        if (empty($cityId))
            if (isset(static::$cityData[$city['province']][$city['city']][$city['city']]['AREAID']))
                $cityId = static::$cityData[$city['province']][$city['city']][$city['city']]['AREAID'];

        return $cityId;
    }
}
