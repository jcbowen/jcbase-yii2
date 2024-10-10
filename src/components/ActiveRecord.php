<?php

namespace Jcbowen\JcbaseYii2\components;

use ArrayObject;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\Exception;

/**
 * Class ActiveRecord
 *
 * @author  Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:19 AM
 * @package Jcbowen\JcbaseYii2\components
 */
class ActiveRecord extends \yii\db\ActiveRecord
{
    /** @var int|bool 缓存时间(单位秒)，不开启缓存为false */
    public static $cacheTime = false;

    /** @var string|null|false 操作时间 */
    protected $operationTime = null;

    /**
     * 注册事件: 新增,更新,删除时清理清理缓存
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @lasttime: 2022/1/5 3:20 下午
     */
    public function init()
    {
        $this->operationTime = defined('TIME') ? TIME : date('Y-m-d H:i:s');
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
     * @author  Bowen
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
     * 分页查询
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array         $options
     *                                - page 页码，默认1
     *                                - page_size 分页大小，默认10
     *                                - asArray 是否传递asArray给query
     * @param callable|null $getQuery query回调
     * @param callable|null $listEach 列表循环回调
     *
     * @return array
     * @lasttime: 2024/4/17 11:17 AM
     */
    public static function findForPage(array $options = [], callable $getQuery = null, callable $listEach = null): array
    {
        $defaultOptions = [
            'page'      => 1,
            'page_size' => 10,
            'asArray'   => true
        ];
        $options        = array_merge($defaultOptions, $options);

        $page     = max(1, intval($options['page']));
        $pageSize = intval($options['page_size']);
        $pageSize = $pageSize <= 0 ? $defaultOptions['page_size'] : $pageSize;

        $query = parent::find();

        if (static::$cacheTime !== false) {
            $dependency = ModelCacheDependency::create(static::class);
            $query->cache(static::$cacheTime, $dependency);
        }

        $query = $query->limit($pageSize)->offset(($page - 1) * $pageSize);

        if (is_callable($getQuery))
            $query = call_user_func($getQuery, $query);

        if ($options['asArray'])
            $query = $query->asArray();

        $list  = $query->all();
        $total = $query->count();

        if (!empty($list)) {
            // 自动处理字段结构
            foreach ($list as &$item) {
                if (is_callable($listEach))
                    $item = call_user_func($listEach, $item);
                else {
                    if (!$options['asArray']) {
                        if (method_exists($item, 'toArray'))
                            $item = $item->toArray();
                    } else {
                        (new FieldFilter)->set([
                            'modelClass' => static::class,
                        ])->de($item);
                    }
                }
            }
        }

        return [
            'list'  => $list,
            'total' => $total
        ];
    }

    /**
     * 批量插入数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data 需要插入的数据
     *                    - 每个元素都应当是key=>value结构
     *                    - 需要保证第一个元素的字段完整
     *                    - 非第一个元素无论是乱序还是缺字段都可以完成插入
     *                    - 如：[['username' => '张三', 'age' => '28'], ['username' => '李四']]
     *
     * @return int
     * @throws Exception
     * @lasttime: 2024/4/10 11:21 AM
     */
    public function batchInsert(array $data = []): int
    {
        if (empty($data)) return 0;

        // 实例化字段过滤类
        $fieldFilter = new FieldFilter($this);

        // 取数组的第一个元素作为插入字段
        $columns = array_keys($data[0]);
        $columns = array_values(array_filter($columns, function ($column) {
            return $this->hasAttribute($column);
        }));

        // 是否补充created_at字段
        $columnsAddCreatedAt = false;
        // 是否补充updated_at字段
        $columnsAddUpdatedAt = false;

        // 整理出每行的数据
        $rows = [];
        foreach ($data as $item) {
            // 根据字段名整理数据
            $fields = [];
            foreach ($columns as $itemKey) {
                // 自动处理字段结构
                $fields[$itemKey] = $fieldFilter->en($itemKey, $item[$itemKey]);
            }

            // 非有效数据直接跳过
            if (empty($fields)) continue;

            // 自动补充创建时间以及更新时间
            if ($this->hasAttribute('created_at') && empty($fields['created_at'])) {
                $columnsAddCreatedAt  = true;
                $fields['created_at'] = $this->operationTime;
            }
            if ($this->hasAttribute('updated_at') && empty($fields['updated_at'])) {
                $columnsAddUpdatedAt  = true;
                $fields['updated_at'] = $this->operationTime;
            }

            // 只保留值到待插入变量中
            $rows[] = array_values($fields);
        }

        // 如果没有待插入数据，直接输出0
        if (empty($rows)) return 0;

        // 判断是否需要给字段加上创建时间和更新时间字段
        if ($columnsAddCreatedAt && !in_array('created_at', $columns))
            $columns[] = 'created_at';
        if ($columnsAddUpdatedAt && !in_array('updated_at', $columns))
            $columns[] = 'updated_at';

        // 批量插入
        return static::getDb()->createCommand()->batchInsert(static::tableName(), $columns, $rows)->execute();
    }

    /**
     * 清理缓存
     *
     * @author  Bowen
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
            $behaviorsDef['value'] = $this->operationTime;
        }

        if (!empty($behaviorsDef)) $behaviors[] = $behaviorsDef;

        return $behaviors;
    }

    /**
     * 过滤返回字段
     *
     * @lasttime: 2022/3/13 10:37 下午
     * @author  Bowen
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $fields
     *
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
