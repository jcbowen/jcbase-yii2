<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\web\Response;
use yii\helpers\ArrayHelper;
use RuntimeException;

/**
 * 增删改查
 */
trait CurdActionTrait
{
    use ModelHelper;

    /**
     * @var string 数据表主键ID
     */
    public $pkId = 'id';

    /**
     * @var string 数据模型
     */
    public $modelClass;

    //---------- 检查 ----------/

    /** @var string 数据表名称 */
    public $modelTableName;

    /** @var array 该表拥有的字段 */
    public $modelAttributes;

    /** @var string 操作时间 */
    public $operateTime;

    /** @var string 空时间字符 */
    public $noTime = '0000-00-00 00:00:00';

    /**
     * 调用前需进行的初始化检查
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @lasttime: 2021/5/9 2:43 下午
     */
    private function checkInit()
    {
        if (!$this->modelClass) {
            throw new RuntimeException('未定义调用的数据模型');
        } else {
            $this->operateTime    = date('Y-m-d H:i:s');
            $this->modelTableName = call_user_func($this->modelClass . '::tableName');

            $model = new $this->modelClass();
            if (method_exists($model, 'getAttributes'))
                $this->modelAttributes = $model->getAttributes();
        }
    }

    //---------- 列表查询 ----------/

    /**
     * 获取列表数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return Response|string
     * @lasttime: 2022/3/13 9:55 上午
     */
    public function actionList()
    {
        global $_GPC;

        $this->checkInit();

        $fields      = $this->getListFields();
        $where       = $this->getListWhere();
        $filterWhere = $this->getListFilterWhere();
        $order       = $this->getListOrder();

        $page        = max(1, intval($_GPC['page']));
        $pageSize    = intval($_GPC['page_size']);
        $pageSize    = $pageSize <= 0 ? 10 : $pageSize;
        $showDeleted = intval($_GPC['show_deleted']);

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row = $row->select($fields);

        $row = $this->getListRow($row);

        if (empty($showDeleted) && array_key_exists('deleted_at', $this->modelAttributes))
            $row = $row->andWhere([$this->modelTableName . '.deleted_at' => $this->noTime]);

        if (!empty($where)) $row = $row->andWhere($where);
        $row = $row->andFilterWhere($filterWhere);

        $row = $row->limit($pageSize)->offset(($page - 1) * $pageSize);
        if (!empty($order)) $row = $row->orderBy($order);

        $list  = $row->asArray()->all();
        $total = $row->count();

        if ($this->runListEach() && !empty($list))
            foreach ($list as &$item) $item = $this->listEach($item);

        return $this->listReturn($list, $total, $page, $pageSize);
    }

    /**
     * 查询列表条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function getListWhere()
    {
        return [];
    }

    /**
     * 获取列表查询过滤条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime: 2022/3/13 10:52 上午
     */
    public function getListFilterWhere(): array
    {
        return [];
    }

    /**
     * 获取列表查询的链式查询
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ActiveQuery $row
     * @return ActiveQuery
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getListRow(ActiveQuery $row): ActiveQuery
    {
        return $row;
    }

    /**
     * 设置查询列表返回的字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getListFields()
    {
        return $this->modelTableName . '.*';
    }

    /**
     * 获取列表排序
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getListOrder()
    {
        return '';
    }

    /**
     * 是否遍历数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return boolean
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function runListEach(): bool
    {
        return false;
    }

    /**
     * 遍历列表数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $item
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function listEach($item)
    {
        return $item;
    }

    /**
     * 查询列表返回数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $total
     * @param $page
     * @param $pageSize
     * @param $list
     * @return Response|string
     * @lasttime: 2022/3/13 10:34 上午
     */
    public function listReturn($list, $total, $page, $pageSize)
    {
        return (new Util)->result(ErrCode::SUCCESS, 'ok', $list, [
            'count'     => $total, 'page' => $page,
            'page_size' => $pageSize
        ]);
    }

    //---------- 无分页列表查询(适用于选择器) ----------/

    /**
     * 滚动分页数据加载(最大输出100条数据)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @lasttime: 2022/3/19 10:39 上午
     */
    public function actionLoader()
    {
        global $_GPC;

        $this->checkInit();

        $fields      = $this->getLoaderFields();
        $where       = $this->getLoaderWhere();
        $filterWhere = $this->getLoaderFilterWhere();
        $order       = $this->getLoaderOrder();

        $pageSize    = intval($_GPC['page_size']);
        $pageSize    = max(1, $pageSize);
        $pageSize    = min(1000, $pageSize);
        $showDeleted = intval($_GPC['show_deleted']);

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row->select($fields);

        // 传给子类便于扩展
        $this->getLoaderRow($row);

        if (empty($showDeleted) && array_key_exists('deleted_at', $this->modelAttributes))
            $row->andWhere([$this->modelTableName . '.deleted_at' => $this->noTime]);

        if (!empty($where)) $row->andWhere($where);
        $row->andFilterWhere($filterWhere);

        $row->limit($pageSize);
        if (!empty($order)) $row->orderBy($order);

        $list = $row->asArray()->all();

        $minTime = '';
        $maxTime = '';
        if ($this->runLoaderEach() && !empty($list))
            foreach ($list as &$item) $item = $this->loaderEach($item, $minTime, $maxTime);

        return $this->loaderReturn($list, $pageSize, $minTime, $maxTime);
    }

    /**
     * 无分页列表查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function getLoaderWhere()
    {
        return [];
    }

    /**
     * 无分页列表查询过滤条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:52 上午
     */
    public function getLoaderFilterWhere()
    {
        global $_GPC;

        $maxTime = Safe::gpcString(trim($_GPC['maxTime'])); // 上次查询数据里的最大时间
        $minTime = Safe::gpcString(trim($_GPC['minTime'])); // 上次查询数据里的最小时间

        // minTime和maxTime都不为空时，查询时间区间内的数据
        if (!empty($maxTime) && !empty($minTime))
            return ['between', $this->modelTableName . '.created_at', $minTime, $maxTime];
        // 只传minTime意味着时间为倒叙，所以只查询小于minTime的数据
        if (!empty($minTime)) return ['<', $this->modelTableName . '.created_at', $maxTime];
        // 只传maxTime意味着时间为正序，所以只查询大于maxTime的数据
        if (!empty($maxTime)) return ['>', $this->modelTableName . '.created_at', $minTime];

        return [];
    }

    /**
     * 无分页列表查询的链式查询
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ActiveQuery $row
     * @return ActiveQuery|void
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getLoaderRow(ActiveQuery &$row): ActiveQuery
    {
        return $row;
    }

    /**
     * 设置无分页列表查询返回的字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getLoaderFields()
    {
        return $this->modelTableName . '.*';
    }

    /**
     * 获取无分页列表查询排序
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getLoaderOrder()
    {
        global $_GPC;

        $minTime = Safe::gpcString(trim($_GPC['minTime']));
        $maxTime = Safe::gpcString(trim($_GPC['maxTime']));

        // 都不为空的时候，不进行排序
        if (!empty($minTime) && !empty($maxTime)) return [];
        // 只传maxTime时，正序
        if (!empty($maxTime) && empty($minTime)) return [$this->modelTableName . '.created_at' => SORT_ASC];

        return [$this->modelTableName . '.created_at' => SORT_DESC];
    }

    /**
     * 是否遍历无分页列表查询结果数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return boolean
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function runLoaderEach(): bool
    {
        return true;
    }

    /**
     * 遍历无分页列表查询数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $item
     * @param $minTime string 最小的创建时间
     * @param $maxTime string 最大的创建时间
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function loaderEach($item, string &$minTime, string &$maxTime)
    {
        if (empty($maxTime) || $item['created_at'] > $maxTime) $maxTime = $item['created_at'];
        if (empty($minTime) || $item['created_at'] < $minTime) $minTime = $item['created_at'];

        return $item;
    }

    /**
     * 滚动分页数据加载返回数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param $list
     * @param $pageSize
     * @param $minTime
     * @param $maxTime
     * @return string|Response
     * @lasttime: 2022/10/13 17:58
     */
    public function loaderReturn($list, $pageSize, $minTime, $maxTime)
    {
        return (new Util)->result(ErrCode::SUCCESS, 'ok', [
            'list'      => $list,
            'max_time'  => $maxTime,
            'min_time'  => $minTime,
            'page_size' => $pageSize
        ]);
    }

    //---------- 详情查询 ----------/

    /**
     * 查询数据详情
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return Response|string
     * @lasttime: 2022/3/13 3:26 下午
     */
    public function actionDetail()
    {
        global $_GPC;

        $this->checkInit();

        $fields  = $this->getDetailFields();
        $where   = $this->getDetailWhere($_GPC);
        $asArray = $this->detailAsArray();

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row = $row->select($fields);
        $row = $this->getDetailRow($row);
        $row = $row->andWhere($where);

        if (!$asArray && !empty($row->joinWith)) {
            $row2    = clone $row;
            $detail2 = $row2->one();
        }

        $detail = $row->asArray()->one();

        if (!empty($detail)) {
            if (!$asArray && !empty($detail2) && !empty($row->joinWith))
                $detail = ArrayHelper::merge($detail2->toArray(), $detail);

            $detail = $this->detail($detail);
            return (new Util)->result(ErrCode::SUCCESS, 'ok', $detail);
        }
        return (new Util)->result(ErrCode::NOT_EXIST, '查询数据不存在或已被删除');
    }

    /**
     * 获取数据详情的返回字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/21 8:50 下午
     */
    public function getDetailFields()
    {
        return $this->modelTableName . '.*';
    }

    /**
     * 查询数据详情的链式查询
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ActiveQuery $row
     * @return ActiveQuery
     * @lasttime: 2022/3/21 11:11 下午
     */
    public function getDetailRow(ActiveQuery $row): ActiveQuery
    {
        return $row;
    }

    /**
     * 查询数据详情的条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $data 通过$_GPC接收的数据
     * @return string|array|Response
     * @lasttime: 2022/3/13 3:25 下午
     */
    public function getDetailWhere(array &$data)
    {
        $pkId = intval($data[$this->pkId]);
        if (empty($pkId))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, "{$this->pkId}不能为空");

        $where   = ['and'];
        $where[] = [$this->pkId => $pkId];
        return $where;
    }

    /**
     * 查询数据详情时是否调用asArray()
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return bool
     * @lasttime 2022/9/21 09:58
     */
    public function detailAsArray(): bool
    {
        return false;
    }

    /**
     * 查询数据详情的数据过滤
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $detail
     * @return mixed
     * @lasttime: 2022/3/13 3:30 下午
     */
    public function detail($detail)
    {
        return $detail;
    }

    //---------- 新增数据 ----------/

    /**
     * 新增数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime: 2022/3/13 10:33 下午
     */
    public function actionCreate()
    {
        $this->checkInit();

        // 获取新增数据
        $data = $this->getCreateFormData();
        if (empty($data))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, '数据不能为空');

        if ($data instanceof Response) return $data;

        // 新增前
        $result_before = $this->createBefore($data);
        if (Util::isError($result_before))
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '添加失败，请稍后再试');

        // 如果在createBefore中补充了主键值，则转给更新方法来处理
        if (!empty($data[$this->pkId])) return $this->actionUpdate($data);

        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave(new $this->modelClass(), $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util)->result($result['errcode'], $result['errmsg'], $result['data']);
        }

        $id = Yii::$app->db->getLastInsertID();

        // 新增后
        $result = $this->createAfter($id);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, $result['errmsg'], $result['data']);
        }

        // 提交
        $tr->commit();

        // 如果是通过result函数输出的成功信息，则应该根据成功信息进行输出
        if (!empty($result) && is_array($result) && $result['errcode'] == ErrCode::SUCCESS)
            return (new Util)->result(ErrCode::SUCCESS, $result['errmsg'], $result['data']);

        return (new Util)->result(ErrCode::SUCCESS, '添加成功', [$this->pkId => $id]);
    }

    /**
     * 创建接口接收数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|Response
     * @lasttime: 2022/3/13 3:43 下午
     */
    public function getCreateFormData()
    {
        return $this->getFormData();
    }

    /**
     * 新增数据前调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $data
     * @return array|bool
     * @lasttime: 2022/3/13 10:01 下午
     */
    public function createBefore(array &$data)
    {
        $result = $this->beforeSave($data);
        if (Util::isError($result))
            return $result;
        return true;
    }

    /**
     * 新增数据后
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param string|int $id
     * @return string|int
     * @lasttime: 2022/3/13 10:02 下午
     */
    public function createAfter($id)
    {
        $result = $this->afterSave($id);
        if (Util::isError($result))
            return $result;
        return $id;
    }

    //---------- 更新数据 ----------/

    /**
     * 更新数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array|null $data
     * @return string|Response
     * @throws Exception
     * @lasttime: 2022/3/13 10:20 下午
     */
    public function actionUpdate(?array $data = null)
    {
        $this->checkInit();

        // 获取更新数据
        $data = $data ?? $this->getUpdateFormData();

        if ($data instanceof Response) return $data;

        $pkId = intval($data[$this->pkId]);
        if (empty($pkId))
            return (new Util)->result(ErrCode::UNKNOWN, "{$this->pkId}不能为空");

        // 查询数据是否存在
        $where = $this->getUpdateWhere($data);
        if (!$model = call_user_func($this->modelClass . '::findOne', $where))
            return (new Util)->result(ErrCode::NOT_EXIST, '更新数据不存在');

        // 更新前
        $result_before = $this->updateBefore($model, $data);
        if (Util::isError($result_before))
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '更新前发现错误，请稍后再试');

        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave($model, $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util)->result($result['errcode'], $result['errmsg'], $result['data']);
        }

        // 更新后
        $result_after = $this->updateAfter($pkId);
        if (Util::isError($result_after)) {
            $tr->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '更新失败，请稍后再试');
        }

        $tr->commit();

        return (new Util)->result(ErrCode::SUCCESS, '更新成功', [$this->pkId => $pkId]);
    }

    /**
     * 更新数据的查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $data 通过$_GPC接收的数据
     * @return string|array|Response
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function getUpdateWhere(array &$data)
    {
        $pkId = intval($data[$this->pkId]);
        if (empty($pkId))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, "{$this->pkId}不能为空");

        $where   = ['and'];
        $where[] = [$this->pkId => $pkId];
        return $where;
    }

    /**
     * 更新的数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|Response
     * @lasttime: 2022/3/13 10:06 下午
     */
    public function getUpdateFormData()
    {
        return $this->getFormData();
    }

    /**
     * 更新数据前调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $model
     * @param array $data
     * @return array|bool
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function updateBefore($model, array &$data)
    {
        $result = $this->beforeSave($data, $model);
        if (Util::isError($result))
            return $result;
        return true;
    }

    /**
     * 更新数据后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $id
     * @return array|bool
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function updateAfter($id)
    {
        $result = $this->afterSave($id);
        if (Util::isError($result))
            return $result;
        return true;
    }

    /**
     * 设置某个字段的值
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @lasttime: 2022/3/13 10:31 下午
     */
    public function actionSetValue()
    {
        $this->checkInit();

        $data = $this->getSetValueFormData();

        $pkId = intval($data[$this->pkId]);
        if (empty($pkId))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, "$this->pkId 不能为空");

        $field = Safe::gpcString(trim($data['field'])); // 字段名
        $type  = trim($data['type']); // 字段值的类型
        $value = $data['value']; // 字段值

        if ($type === 'number' || $type === 'int')
            $value = intval($value);
        elseif ($type === 'money')
            $value = Util::round_money($value);
        elseif (is_array($value)) {
            if ($type === 'serialize')
                $value = serialize($value);
            else
                $value = json_encode($value);
        } else
            $value = Safe::gpcString(trim($value));

        $model = call_user_func($this->modelClass . '::findOne', $this->getSetValueQueryWhere($pkId));
        if (!$model)
            return (new Util)->result(ErrCode::NOT_EXIST, '数据不存在或已被删除');

        if ($model->$field === $value)
            return (new Util)->result(ErrCode::SUCCESS, '值未发生改变，请确认修改内容');

        $result = $this->toSave($model, [$field => $value]);
        if (Util::isError($result))
            return (new Util)->resultError($result);

        return (new Util)->result(ErrCode::SUCCESS, '设置成功', ['value' => $value]);
    }

    /**
     * 获取设置某个字段的值的数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|Response
     * @lasttime: 2022/9/10 08:32
     */
    public function getSetValueFormData()
    {
        return $this->getFormData();
    }

    /**
     * setValue查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param int $pkId
     * @return array|string
     * @lasttime 2022/9/30 09:21
     */
    public function getSetValueQueryWhere(int $pkId = 0)
    {
        $where   = ['and'];
        $where[] = [$this->pkId => $pkId];
        if (array_key_exists('deleted_at', $this->modelAttributes))
            $where[] = ['deleted_at' => $this->noTime];

        return $where;
    }

    //---------- 变更通用 ----------/

    /**
     * 新增/更新数据(接口合并)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime: 2022/3/13 10:31 下午
     */
    public function actionSave()
    {
        global $_GPC;
        // 更新操作
        if (!empty($_GPC[$this->pkId]))
            return $this->actionUpdate();

        // 新增操作
        return $this->actionCreate();
    }

    /**
     * 新增/更新前调用(接口合并)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     * @param $model
     * @return bool|array
     * @lasttime: 2022/10/10 15:04
     */
    public function beforeSave(array &$data, $model = null)
    {
        return true;
    }

    /**
     * 新增/更新后调用(接口合并)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param int|null $id
     * @return bool|array
     * @lasttime: 2022/10/10 15:05
     */
    public function afterSave(?int $id = 0)
    {
        return true;
    }

    /**
     * 获取新增/更新数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|Response
     * @lasttime: 2022/3/13 10:05 下午
     */
    public function getFormData()
    {
        global $_GPC;
        return $_GPC;
    }

    //---------- 删除 ----------/

    /**
     * 删除数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function actionDelete()
    {
        global $_GPC;

        $this->checkInit();

        $ids = Safe::gpcArray($_GPC[$this->pkId . 's']);
        foreach ($ids as $i => $id)
            if (empty($id)) unset($ids[$i]);

        if (empty($ids))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, '参数缺失，请重试');

        $fields = $this->getDeleteFields();

        // 获取删除条件
        $where = $this->getDeleteWhere($ids);

        $dels = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->where([
                $this->modelTableName . '.deleted_at' => $this->noTime,
            ])
            ->andWhere($where)
            ->asArray()
            ->all();

        if (empty($dels))
            return (new Util)->result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        $delIds = ArrayHelper::getColumn($dels, $this->pkId);

        // 删除前
        $result_before = $this->deleteBefore($dels, $delIds);
        if (Util::isError($result_before))
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');

        $transaction = Yii::$app->db->beginTransaction();

        // 获取删除操作更新的属性值
        $condition = $this->getDeleteCondition($dels);

        if (!call_user_func("$this->modelClass::updateAll", $condition, $where)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::STORAGE_ERROR, '删除失败，未知错误');
        }

        // 删除后
        $result_after = $this->deleteAfter($delIds);
        if (Util::isError($result_after)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');
        }

        $transaction->commit();
        return (new Util)->result(ErrCode::SUCCESS, '删除成功', ['delIds' => $delIds]);
    }

    /**
     * 设置被删除数据的查询返回字段（重写方法时，务必查询主键！）
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|string
     * @lasttime: 2022/8/30 3:18 PM
     */
    public function getDeleteFields()
    {
        return [$this->pkId];
    }

    /**
     * 删除查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @return array|string
     * @lasttime: 2021/5/9 3:00 下午
     */
    public function getDeleteWhere($ids)
    {
        return ['in', $this->pkId, $ids];
    }

    /**
     * 删除前数据调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $dels 所有要被删除的数据
     * @param array $delIds 所有要被删除的数据的ids
     * @return array|bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteBefore(array $dels, array $delIds)
    {
        return true;
    }

    /**
     * 删除时需要更新的属性值
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $dels 要被删除的数据
     * @return array|string
     * @lasttime 2022/9/21 15:15
     */
    public function getDeleteCondition(array $dels)
    {
        return ['deleted_at' => $this->operateTime];
    }

    /**
     * 删除后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $ids
     * @return array|bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteAfter(array $ids = [])
    {
        return true;
    }

    //---------- 恢复删除的数据 ----------/

    /**
     * 恢复删除的数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime 2022/9/21 14:52
     */
    public function actionRestore()
    {
        global $_GPC;

        $this->checkInit();

        $ids = Safe::gpcArray($_GPC[$this->pkId . 's']);
        foreach ($ids as $i => $id)
            if (empty($id)) unset($ids[$i]);

        if (empty($ids))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, '参数缺失，请重试');

        $fields = $this->getRestoreFields();

        // 获取删除条件
        $where = $this->getRestoreWhere($ids);

        $items   = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->where(['<>', $this->modelTableName . '.deleted_at', $this->noTime,])
            ->andWhere($where)
            ->asArray()
            ->all();
        $itemIds = ArrayHelper::getColumn($items, $this->pkId);

        if (empty($itemIds))
            return (new Util)->result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        // 删除前
        $result_before = $this->restoreBefore($items, $itemIds);
        if (Util::isError($result_before))
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '恢复数据失败，请稍后再试');


        $transaction = Yii::$app->db->beginTransaction();

        $condition = $this->getRestoreCondition($items);

        if (!call_user_func("$this->modelClass::updateAll", $condition, $where)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, '恢复失败，未知错误');
        }

        // 删除后
        $result_after = $this->restoreAfter($itemIds);
        if (Util::isError($result_after)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '恢复数据失败，请稍后再试');
        }

        $transaction->commit();
        return (new Util)->result(ErrCode::SUCCESS, '恢复成功', ['itemIds' => $itemIds]);
    }

    /**
     * 设置被恢复数据的查询返回字段（重写方法时，务必查询主键！）
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime 2022/9/21 14:58
     */
    public function getRestoreFields()
    {
        return [$this->pkId];
    }

    /**
     * 恢复查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $ids
     * @return array|string
     * @lasttime 2022/9/21 15:00
     */
    public function getRestoreWhere($ids)
    {
        return ['in', $this->pkId, $ids];
    }

    /**
     * 恢复前数据调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $items
     * @param array $itemIds
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function restoreBefore(array $items, array $itemIds)
    {
        return true;
    }

    /**
     * 恢复时需要更新的属性值
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $items 需要恢复的数据
     * @return array|string
     * @lasttime 2022/9/21 15:19
     */
    public function getRestoreCondition(array $items)
    {
        return ['deleted_at' => $this->noTime];
    }

    /**
     * 恢复后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $ids
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function restoreAfter(array $ids = [])
    {
        return true;
    }

    //---------- 真实删除数据 ----------/

    /**
     * 真实删除数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime 2022/9/21 14:52
     */
    public function actionRemove()
    {
        global $_GPC;

        $this->checkInit();

        $ids = Safe::gpcArray($_GPC[$this->pkId . 's']);
        foreach ($ids as $i => $id)
            if (empty($id)) unset($ids[$i]);

        if (empty($ids))
            return (new Util)->result(ErrCode::PARAMETER_EMPTY, '参数缺失，请重试');

        $fields = $this->getRemoveFields();

        // 获取删除条件
        $where = $this->getRemoveWhere($ids);

        $items   = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->andWhere($where)
            ->asArray()
            ->all();
        $itemIds = ArrayHelper::getColumn($items, $this->pkId);

        if (empty($itemIds))
            return (new Util)->result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        // 删除前
        $result_before = $this->removeBefore($items, $itemIds);
        if (Util::isError($result_before))
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');

        $transaction = Yii::$app->db->beginTransaction();

        if (!call_user_func("$this->modelClass::deleteAll", $where)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, '删除失败，未知错误');
        }

        // 删除后
        $result_after = $this->removeAfter($itemIds);
        if (Util::isError($result_after)) {
            $transaction->rollBack();
            return (new Util)->result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');
        }

        $transaction->commit();
        return (new Util)->result(ErrCode::SUCCESS, '永久删除成功', ['itemIds' => $itemIds]);
    }

    /**
     * 设置被永久删除数据的查询返回字段（重写方法时，务必查询主键！）
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime 2022/9/21 14:58
     */
    public function getRemoveFields()
    {
        return [$this->pkId];
    }

    /**
     * 永久删除查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $ids
     * @return array|string
     * @lasttime 2022/9/21 15:00
     */
    public function getRemoveWhere($ids)
    {
        return ['in', $this->pkId, $ids];
    }

    /**
     * 永久删除前数据调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $items
     * @param array $itemIds
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function removeBefore(array $items, array $itemIds)
    {
        return true;
    }

    /**
     * 永久删除后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $ids
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function removeAfter(array $ids = [])
    {
        return true;
    }
}
