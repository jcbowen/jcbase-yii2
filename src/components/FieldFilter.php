<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\helpers\ArrayHelper;

class FieldFilter
{
    public $model; // 模型实例
    public $modelClass; // 模型类名(带命名空间)

    // ---------- 运行时属性 ----------/
    public $attributes = []; // 模型属性
    private $integerFiles = []; // 整型字段

    static $config; // model_filter_field配置

    public function __construct($model = null)
    {
        if (!empty($model)) {
            $modelClass = '';
            if (is_object($model)) {
                $modelClass = get_class($model);
                if (empty($this->model) && method_exists($model, 'rules'))
                    $this->model = $model;
            } elseif (is_string($model)) {
                $modelClass = $model;
                if (empty($this->model)) $this->model = new $modelClass();
            }
            if (!empty($modelClass))
                $this->set([
                    'model'      => $model,
                    'modelClass' => $modelClass,
                ]);
        }

    }

    public function set($params = []): FieldFilter
    {
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * 数据结构转化处理
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     *
     * @return false|float|int|string
     * @lasttime 2022/10/17 14:42
     */
    public function en($field, $value)
    {
        // 如果未初始化integerFiles则初始化
        if (empty($this->integerFiles) && !empty($this->model) && method_exists($this->model, 'rules')) {
            foreach ($this->model->rules() as $rules) {
                if (in_array(array_pop($rules), ['integer', 'number'])) {
                    $this->integerFiles = array_merge($rules[0], $this->integerFiles);
                }
            }
        }

        if (!empty($this->modelClass) && !empty($this->getConfig()[$field])) {
            return $this->enRules($field, $value);
        } else {
            return $this->enNoRules($field, $value);
        }
    }

    /**
     * 定义有规则的情况下，数据结构转化处理
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     *
     * @return string
     * @lasttime 2022/10/17 14:40
     */
    private function enRules($field, $value): string
    {
        $config = $this->getConfig();
        switch ($config[$field]) {
            case 'json':
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                break;
            case 'json&base64':
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $value = !empty($value) ? base64_encode($value) : '';
                break;
            case 'serialize':
                $value = serialize($value);
                break;
            case 'round_money':
                $value = Util::round_money($value);
                break;
            case 'rich_text':
            case 'rich_text2':
                $value = Content::toSave($value);
                break;
            case 'datetime':
                $value = !empty($value) ? $value : NO_TIME;
                break;
        }
        return (string)$value;
    }

    /**
     * 未定义规则的情况下，数据结构处理
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     *
     * @return false|float|int|string
     * @lasttime 2022/10/17 14:40
     */
    private function enNoRules($field, $value)
    {
        // 如果值是数组类型时json_encode处理
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        // 如果字段布尔类型强制转换成数字
        if (is_bool($value)) {
            return intval($value);
        }
        // 如果字段是整数或小数
        if (in_array($field, $this->integerFiles, true)) {
            return floatval($value);
        }

        // 处理时间类型的字段
        if (in_array($field, ['updated_at', 'created_at', 'deleted_at']) && ($value === '' || $value === null)) {
            return NO_TIME;
        }

        // 其它类型全部转换成字符串
        return (string)$value;
    }

    public function de(&$fields)
    {
        if (empty($fields) || !is_array($fields))
            goto returnVar;

        $config = $this->getConfig();
        if (!empty($config)) {
            $config['_extend'] = 'json';
            foreach ($fields as $key => &$field) {
                if (empty($config[$key])) {
                    // 重置空时间'0000-00-00 00:00:00'为空字符串
                    if ($field === NO_TIME) $field = '';
                    continue;
                }
                switch ($config[$key]) {
                    case 'json':
                        if (is_array($field)) break;
                        $field = $field ? (array)@json_decode($field, true) : $field;
                        break;
                    case 'json&base64':
                        if (is_array($field)) break;
                        $field = $field ? (array)@json_decode(base64_decode($field), true) : [];
                        break;
                    case 'serialize':
                        if (is_array($field)) break;
                        $field = $field ? (array)@Util::unserializer($field) : [];
                        break;
                    case 'rich_text':
                        $field = !empty($field) ? Content::toShow($field) : '';
                        break;
                    case 'rich_text2':
                        $field = !empty($field) ? Content::toShow($field, false) : '';
                        break;
                    case 'datetime':
                        $field = $field != NO_TIME ? $field : '';
                        break;
                }
            }
            // 将_extend字段合并，并删除_extend字段
            if (!empty($fields['_extend']) && is_array($fields['_extend'])) {
                $fields = ArrayHelper::merge($fields, $fields['_extend']);
                unset($fields['_extend']);
            }
        }

        returnVar:
        return $fields;
    }

    /**
     * 判断字段是否存在
     *
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $field
     *
     * @return bool
     * @lasttime 2022/10/17 15:59
     */
    public function filedExist(string $field): bool
    {
        return in_array($field, $this->getAttributes(), true);
    }

    /**
     * 获取模型属性
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime 2022/10/17 15:58
     */
    public function getAttributes(): array
    {
        if (!$this->attributes && !empty($this->model) && method_exists($this->model, 'attributes'))
            $this->attributes = array_keys($this->model->attributeLabels());
        return $this->attributes;
    }

    /**
     * 获取字段特殊结构配置
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|mixed
     * @lasttime: 2023/1/20 11:15
     */
    private function getConfig()
    {
        if (empty($this->modelClass)) return [];

        if (empty(self::$config[$this->modelClass])) {
            self::$config[$this->modelClass] = Yii::$app->params['model_filter_field'][$this->modelClass] ?? [];
        }

        return self::$config[$this->modelClass];
    }
}

