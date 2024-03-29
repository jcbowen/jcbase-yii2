<?php

namespace Jcbowen\JcbaseYii2\components;

use ArrayObject;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;

/**
 * Class ActiveRecord
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:19 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class ActiveRecord extends \yii\db\ActiveRecord
{
    /** @var int|bool 缓存时间(单位秒)，不开启缓存为false */
    public static $cacheTime = false;

    /**
     * 注册事件: 新增,更新,删除时清理清理缓存
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @lasttime: 2022/1/5 3:20 下午
     */
    public function init()
    {
        if (static::$cacheTime !== false) {
            $this->on('*', static function ($e) {
                if (in_array($e->name, ['afterInsert', 'afterUpdate', 'afterDelete'])) {
                    self::clearCache();
                }
            });
        }
        parent::init();
    }

    /**
     * 对find方法进行缓存支持
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return ActiveQuery
     * @lasttime: 2022/1/5 3:20 下午
     */
    public static function find(): ActiveQuery
    {
        $query = parent::find();

        if (static::$cacheTime !== false) {
            $dependency = ModelCacheDependency::create(static::class);
            $query->cache(static::$cacheTime, $dependency);
        }

        return $query;
    }

    /**
     * 清理缓存
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return false|int
     * @lasttime: 2023/4/18 9:53 PM
     */
    public static function clearCache()
    {
        return ModelCacheDependency::clear(static::class);
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        // 是否需要自动更新时间
        $labels       = $this->attributeLabels();
        $value        = false;
        $behaviorsDef = [];
        if (isset($labels['updated_at'])) {
            $behaviorsDef['attributes'][self::EVENT_BEFORE_INSERT][] = 'updated_at';
            $behaviorsDef['attributes'][self::EVENT_BEFORE_UPDATE][] = 'updated_at';
            $value                                                   = true;
        }
        if (isset($labels['created_at'])) {
            $behaviorsDef['attributes'][self::EVENT_BEFORE_INSERT][] = 'created_at';
            $value                                                   = true;
        }
        if ($value) {
            $behaviorsDef['class'] = TimestampBehavior::class;
            $behaviorsDef['value'] = date('Y-m-d H:i:s');
        }

        if (!empty($behaviorsDef)) $behaviors[] = $behaviorsDef;

        return $behaviors;
    }

    /**
     * 过滤返回字段
     *
     * @lasttime: 2022/3/13 10:37 下午
     * @author Bowen
     * @email bowen@jiuchet.com
     */
    public function fields()
    {
        $fields = parent::fields();
        // 删除无用字段
        unset($fields['deleted_at']);
        // 过滤返回的字段
        return $this->filterFields($fields);
    }

    /**
     * 根据返回字段类型处理数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $fields
     * @return mixed
     * @lasttime: 2022/3/13 10:38 下午
     */
    private function filterFields($fields)
    {
        $config = Yii::$app->params['model_filter_field'] ?? [];
        if (!empty($config)) {
            $className = static::class;

            $config[$className]['_extend'] = 'json';
            if (empty($config[$className])) return $fields;
            foreach ($config[$className] as $name => $type) {
                if (empty($fields[$name])) continue;
                switch ($type) {
                    case 'json':
                        $fields[$name] = function () use ($name) {
                            return $this->$name ? (array)@json_decode($this->$name, true) : new ArrayObject();
                        };
                        break;
                    case 'json&base64':
                        $fields[$name] = function () use ($name) {
                            return $this->$name ? (array)@json_decode(base64_decode($this->$name), true) : new ArrayObject();
                        };
                        break;
                    case 'serialize':
                        $fields[$name] = function () use ($name) {
                            return $this->$name ? (array)@Util::unserializer($this->$name) : new ArrayObject();
                        };
                        break;
                    case 'rich_text':
                        $fields[$name] = function () use ($name) {
                            return !empty($this->$name) ? Content::toShow($this->$name) : '';
                        };
                        break;
                    case 'rich_text2':
                        $fields[$name] = function () use ($name) {
                            return !empty($this->$name) ? Content::toShow($this->$name, false) : '';
                        };
                        break;
                }
            }
        }
        return $fields;
    }
}
