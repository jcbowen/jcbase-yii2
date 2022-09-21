<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use InvalidArgumentException;

/**
 *
 */
trait ModelHelper
{
    //---------- 其他方法 ----------/
    public $_attributes = [];
    public $model;

    /**
     * 通用save方法，方便输出报错
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $model
     * @param array $data
     * @param string $formName
     * @return array|bool|mixed
     * @lasttime: 2022/8/28 22:58
     */
    protected function toSave($model, array $data = [], string $formName = '')
    {
        $this->model = $model;
        if (!empty($data)) {
            $data = $this->filterData($data, $model);
            if ($model->load($data, $formName) && $model->save()) {
                return true;
            }
        } else {
            if ($model->save()) {
                return $model;
            }
        }

        $errors = $model->errors;
        if (!empty($errors)) {
            $errmsg = '';
            foreach ($errors as $item) {
                $errmsg .= "【" . implode(',', $item) . "】";
            }
            return Util::error(ErrCode::UNKNOWN, $errmsg, $model->errors);
        }

        return Util::error(ErrCode::UNKNOWN, '未知错误');
    }

    /**
     * 新增/更新过滤数据(强制数据转换为数组格式)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $data
     * @param mixed $model
     * @return array
     * @lasttime: 2022/8/28 23:03
     */
    protected function filterData($data, $model = ''): array
    {
        // 如果数据为空返回空数组
        if (empty($data)) return [];

        // 数据为对象时
        if (is_object($data)) {
            // 如果data为对象时且实现toArray方法把对象转换为数组
            if (method_exists($data, 'toArray')) $data = $data->toArray();

            // 如果数据为空返回空数组
            if (empty($data)) return [];
        }

        if (!is_array($data)) {
            throw new InvalidArgumentException('更新参数必须是数组');
        }

        // 把不存在的字段放入扩展字段extend中
        if ($this->filedExist('_extend')) {
            $data['_extend'] = [];
            foreach ($data as $field => $value) {
                // 如果字段不存在放入extend中
                if (!$this->filedExist($field)) {
                    $data['_extend'][$field] = $value;
                    unset($data[$field]);
                }
            }
        }

        // 获取数据模型的命名空间
        $modelClass = '';
        if (!empty($model)) {
            if (is_object($model)) {
                $modelClass = get_class($model);
                if (empty($this->model) && method_exists($model, 'rules')) $this->model = $model;
            } elseif (is_string($model)) {
                $modelClass = $model;
                if (empty($this->model)) $this->model = new $modelClass();
            }
        }

        // 从模型验证规则中获取数字类型的字段
        $integerFiles = [];
        if (!empty($this->model)) {
            foreach ($this->model->rules() as $rules) {
                if (in_array(array_pop($rules), ['integer', 'number'])) {
                    $integerFiles = array_merge($rules[0], $integerFiles);
                }
            }
        }

        // 转换字段值类型
        foreach ($data as $field => $value) {
            $data[$field] = $this->translateData($field, $value, $integerFiles, $modelClass);
        }

        return $data;
    }

    /**
     * 数据结构转化处理
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     * @param $integerFiles
     * @param $modelClass
     * @return false|float|int|string
     * @lasttime: 2022/8/28 23:03
     */
    private function translateData($field, $value, $integerFiles, $modelClass)
    {
        if (!empty($modelClass) && !empty(Yii::$app->params['model_filter_field'][$modelClass][$field])) {
            return $this->translateData_rules($field, $value, $modelClass);
        } else {
            return $this->translateData_noRules($field, $value, $integerFiles);
        }
    }

    /**
     * 定义有规则的情况下，数据结构转化处理
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     * @param $modelClass
     * @return string
     * @lasttime: 2022/8/28 23:03
     */
    private function translateData_rules($field, $value, $modelClass): string
    {
        switch (Yii::$app->params['model_filter_field'][$modelClass][$field]) {
            case 'json':
                $value = json_encode($value);
                break;
            case 'json&base64':
                $value = json_encode($value);
                $value = !empty($value) ? base64_encode($value) : '';
                break;
            case 'serialize':
                $value = serialize($value);
                break;
            case 'round_money':
                $value = Util::round_money($value);
                break;
            case 'rich_text':
                $value = Content::toSave($value);
                break;
        }
        return (string)$value;
    }

    /**
     * 未定义规则的情况下，数据结构处理
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $field
     * @param $value
     * @param $integerFiles
     * @return false|float|int|string
     * @lasttime: 2022/8/28 23:03
     */
    private function translateData_noRules($field, $value, $integerFiles)
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
        if (in_array($field, $integerFiles, true)) {
            return floatval($value);
        }
        // 其它类型全部转换成字符串
        return (string)$value;
    }

    /**
     * 判断字段是否存在
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $field
     * @return bool
     * @lasttime: 2022/8/28 23:03
     */
    public function filedExist(string $field): bool
    {
        return in_array($field, $this->getAttributes(), true);
    }

    /**
     * 获取模型字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array
     * @lasttime: 2022/8/28 23:02
     */
    public function getAttributes(): array
    {
        if (!$this->_attributes) {
            $this->_attributes = array_keys($this->model->attributeLabels());
        }
        return $this->_attributes;
    }
}

