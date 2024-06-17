<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;

trait ModelHelper
{
    //---------- 其他方法 ----------/
    /** @var ActiveRecord */
    public $model;

    /** @var FieldFilter */
    private $_FieldFilter;

    /**
     * 通用save方法，方便输出报错
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveRecord $model
     * @param array $data
     * @param string $formName
     * @return array|bool
     * @lasttime: 2022/12/28 10:29 AM
     */
    public function toSave(ActiveRecord $model, array $data = [], string $formName = '')
    {
        $this->model        = $model;
        $this->_FieldFilter = new FieldFilter($model);
        if (!empty($data)) {
            $data = $this->filterData($data);
            if ($model->load($data, $formName) && $model->save()) return true;
        } else
            if ($model->save()) return true;

        $errors = $model->errors;
        if (!empty($errors)) {
            $errmsg = '';
            foreach ($errors as $item) $errmsg .= "【" . implode(',', $item) . "】";

            return Util::error(ErrCode::STORAGE_ERROR, $errmsg, $model->errors);
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
     * @return array
     * @throws InvalidArgumentException
     * @lasttime: 2022/8/28 23:03
     */
    protected function filterData($data): array
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

        if (!is_array($data))
            throw new InvalidArgumentException('更新参数必须是数组');

        // 把不存在的字段放入扩展字段extend中以json格式存储
        if ($this->filedExist('_extend')) {
            $data['_extend'] = [];

            // 读取原有的扩展数据
            if (!empty($this->model)) {
                if (!empty($this->model['_extend'])) {
                    $data['_extend'] = (array)@json_decode($this->model['_extend'], true);
                }
            }

            foreach ($data as $field => $value) {
                // 如果字段不存在放入extend中
                if (!$this->filedExist($field)) {
                    $data['_extend'][$field] = $value;
                    unset($data[$field]);
                }
            }
        }

        // 转换字段值类型
        foreach ($data as $field => $value)
            $data[$field] = $this->_FieldFilter->en($field, $value);

        return $data;
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
     * @throws InvalidArgumentException
     */
    protected function filedExist(string $field): bool
    {
        $this->check_FieldFilter();
        return $this->_FieldFilter->filedExist($field);
    }

    /**
     * 获取模型字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @throws InvalidArgumentException
     * @lasttime: 2022/8/28 23:02
     */
    protected function getAttributes(): array
    {
        $this->check_FieldFilter();
        return $this->_FieldFilter->getAttributes();
    }

    /**
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @throws InvalidArgumentException
     *
     * @lasttime: 2024/6/17 13:31
     */
    private function check_FieldFilter()
    {
        if (empty($this->_FieldFilter)) {
            if (empty($this->model)) {
                Yii::error('未初始化模型', __METHOD__);
                throw new InvalidArgumentException('模型不存在');
            }
            $this->_FieldFilter = new FieldFilter($this->model);
        }
    }
}

