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

        if (empty($showDeleted)) $row = $row->andWhere(['deleted_at' => NO_TIME]);

        if (!empty($where)) $row = $row->andWhere($where);
        $row = $row->andFilterWhere($filterWhere);

        $row = $row->limit($pageSize)->offset(($page - 1) * $pageSize);
        if (!empty($order)) $row = $row->orderBy($order);

        $list  = $row->asArray()->all();
        $total = $row->count();

        if ($this->runListEach() && !empty($list)) {
            foreach ($list as &$item) {
                $item = $this->listEach($item);
            }
        }

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
        return '*';
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
        return (new Util)->result(0, 'ok', $list, ['count' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }

    //---------- 无分页列表查询(适用于选择器) ----------/

    /**
     * 无分页列表查询(最大输出100条数据)
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @lasttime: 2022/3/19 10:39 上午
     */
    public function actionSelector()
    {
        global $_GPC;

        $fields      = $this->getSelectorFields();
        $where       = $this->getSelectorWhere();
        $filterWhere = $this->getSelectorFilterWhere();
        $order       = $this->getSelectorOrder();

        $limit = intval($_GPC['limit']);
        if (empty($limit)) $limit = 5;
        $limit       = min(100, $limit);
        $showDeleted = intval($_GPC['show_deleted']);

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row = $row->select($fields);

        $row = $this->getSelectorRow($row);

        if (empty($showDeleted)) $row = $row->andWhere(['deleted_at' => NO_TIME]);

        if (!empty($where)) $row = $row->andWhere($where);
        $row = $row->andFilterWhere($filterWhere);

        $row = $row->limit($limit);
        if (!empty($order)) $row = $row->orderBy($order);

        $list = $row->asArray()->all();

        if ($this->runSelectorEach() && !empty($list)) {
            foreach ($list as &$item) {
                $item = $this->selectorEach($item);
            }
        }

        return (new Util)->result(0, 'ok', $list);
    }

    /**
     * 无分页列表查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function getSelectorWhere()
    {
        return [];
    }

    /**
     * 无分页列表查询过滤条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime: 2022/3/13 10:52 上午
     */
    public function getSelectorFilterWhere(): array
    {
        return [];
    }

    /**
     * 无分页列表查询的链式查询
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param ActiveQuery $row
     * @return ActiveQuery
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getSelectorRow(ActiveQuery $row): ActiveQuery
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
    public function getSelectorFields()
    {
        return '*';
    }

    /**
     * 获取无分页列表查询排序
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getSelectorOrder()
    {
        return '';
    }

    /**
     * 是否遍历无分页列表查询结果数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return boolean
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function runSelectorEach(): bool
    {
        return false;
    }

    /**
     * 遍历无分页列表查询数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param $item
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function selectorEach($item)
    {
        return $item;
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
        $fields = $this->getDetailFields();
        $where  = $this->getDetailWhere();

        /** @var ActiveQuery $row */
        $row    = call_user_func($this->modelClass . '::find');
        $row    = $row->select($fields);
        $row    = $this->getDetailRow($row);
        $detail = $row->andWhere($where)->one();
        if (!empty($detail)) {
            $detail = $detail->toArray();
            $detail = $this->detail($detail);
            return (new Util())->result(0, 'ok', $detail);
        }
        return (new Util())->result(ErrCode::NOT_EXIST, '查询数据不存在或已被删除');
    }

    /**
     * 获取数据详情回的字段
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/21 8:50 下午
     */
    public function getDetailFields()
    {
        return '*';
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
     * @return string|array|Response
     * @lasttime: 2022/3/13 3:25 下午
     */
    public function getDetailWhere()
    {
        global $_GPC;
        $pkId = intval($_GPC[$this->pkId]);
        if (empty($pkId)) {
            return (new Util)->result(1, "{$this->pkId}不能为空");
        }
        $where   = ['and'];
        $where[] = [$this->pkId => $pkId];
        return $where;
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
        // 获取新增的表单数据
        $data = $this->getCreateFormData();
        if (empty($data)) {
            return (new Util())->result(1, '数据不能为空');
        }
        // 新增前
        if ($this->createBefore() === false) {
            return (new Util())->result(1, '添加失败，请稍后再试');
        }
        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave(new $this->modelClass(), $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util())->result($result['errcode'], $result['errmsg'], $result['data']);
        }

        $id = Yii::$app->db->getLastInsertID();

        // 新增后
        $result = $this->createAfter($id);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util())->result($result['errcode'], $result['errmsg'], $result['data']);
        }

        // 提交
        $tr->commit();

        // 如果是通过result函数输出的成功信息，则应该根据成功信息进行输出
        if (!empty($result) && is_array($result) && $result['errcode'] == 0) {
            return (new Util())->result(0, $result['errmsg'], $result['data']);
        }

        return (new Util())->result(0, '添加成功', [$this->pkId => $id]);
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
     * @return bool
     * @lasttime: 2022/3/13 10:01 下午
     */
    public function createBefore(): bool
    {
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
        return $id;
    }

    //---------- 更新数据 ----------/

    /**
     * 更新数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @throws Exception
     * @lasttime: 2022/3/13 10:20 下午
     */
    public function actionUpdate()
    {
        global $_GPC;
        $pkId = intval($_GPC[$this->pkId]);
        if (empty($pkId)) {
            return (new Util)->result(1, "{$this->pkId}不能为空");
        }
        // 获取更新表单数据
        $data = $this->getUpdateFormData();
        if (is_string($data)) {
            return (new Util())->result(1, '非法访问', $data);
        }
        if ($data instanceof Response) {
            return $data;
        }
        // 查询数据是否存在
        $where = $this->getUpdateWhere();
        if (!$model = call_user_func($this->modelClass . '::findOne', $where)) {
            return (new Util())->result(ErrCode::NOT_EXIST, '更新数据不存在');
        }
        // 更新前
        $result_before = $this->updateBefore($model);
        if (Util::isError($result_before)) {
            return (new Util())->result(1, $result_before['errmsg'] ?: '更新前发现错误，请稍后再试');
        }
        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave($model, $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return (new Util())->result($result['errcode'], $result['errmsg'], $result['data']);
        }

        // 更新后
        $result_after = $this->updateAfter();
        if (Util::isError($result_after)) {
            $tr->rollBack();
            return (new Util())->result(1, $result_before['errmsg'] ?: '更新失败，请稍后再试');
        }

        $tr->commit();

        return (new Util())->result(0, '更新成功');
    }

    /**
     * 更新数据的查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return string|array|Response
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function getUpdateWhere()
    {
        global $_GPC;
        $pkId = intval($_GPC[$this->pkId]);
        if (empty($pkId)) {
            return (new Util)->result(1, "{$this->pkId}不能为空");
        }
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
     * @return bool
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function updateBefore($model): bool
    {
        return true;
    }

    /**
     * 更新数据后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return bool
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function updateAfter(): bool
    {
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
        global $_GPC;
        $pkId = intval($_GPC[$this->pkId]);
        if (empty($pkId)) return (new Util())->result(1, "{$this->pkId}不能为空");

        $field = Safe::gpcString(trim($_GPC['field']));
        $type  = trim($_GPC['type']);
        $value = $_GPC['value']; // 字段值的类型

        if ($type === 'number') {
            $value = intval($value);
        } elseif (is_array($value)) {
            if ($type === 'serialize') {
                $value = serialize($value);
            } else {
                $value = json_encode($value);
            }
        } else {
            $value = Safe::gpcString(trim($value));
        }

        $res = call_user_func($this->modelClass . '::updateAll', [$field => $value], [$this->pkId => $pkId]);
        if (!empty($res)) {
            return (new Util)->result(0, '设置成功', ['value' => $value]);
        }
        return (new Util)->result(1, '设置失败，请刷新后再试');
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
        if (!empty($_GPC[$this->pkId])) {
            return $this->actionUpdate();
        }
        // 新增操作
        return $this->actionCreate();
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
        foreach ($ids as $i => $id) {
            if (empty($id)) unset($ids[$i]);
        }
        if (empty($ids)) return (new Util)->result(9001002, '参数缺失，请重试');

        // 获取删除条件
        $where = $this->getDeleteWhere();
        if (empty($where)) $where = ['in', $this->pkId, $ids];

        $dels   = call_user_func($this->modelClass . '::find')
            ->select([$this->pkId])
            ->where([
                'deleted_at' => NO_TIME
            ])
            ->andWhere($where)
            ->asArray()
            ->all();
        $delIds = ArrayHelper::getColumn($dels, $this->pkId);

        if (empty($delIds)) return (new Util)->result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');
        // 删除前
        if ($this->deleteBefore($dels, $delIds) === false) {
            return (new Util)->result(1, '删除数据失败，请稍后再试');
        }

        $transaction = Yii::$app->db->beginTransaction();

        if (!call_user_func("$this->modelClass::updateAll", ['deleted_at' => TIME], $where)) {
            $transaction->rollBack();
            return (new Util)->result(1, '删除失败，未知错误');
        }

        // 删除后
        if ($this->deleteAfter($delIds) === false) {
            $transaction->rollBack();
            return (new Util)->result(1, '删除数据失败，请稍后再试');
        }

        $transaction->commit();
        return (new Util)->result(0, '删除成功', ['delIds' => $delIds]);
    }

    /**
     * 删除查询条件
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2021/5/9 3:00 下午
     */
    public function getDeleteWhere()
    {
        return [];
    }

    /**
     * 删除前数据调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $dels 所有要被删除的数据
     * @param array $delIds 所有要被删除的数据的ids
     * @return bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteBefore(array $dels, array $delIds): bool
    {
        return true;
    }

    /**
     * 删除后调用
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     * @param array $ids
     * @return bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteAfter(array $ids = []): bool
    {
        return true;
    }
}

