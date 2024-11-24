<?php

namespace Jcbowen\JcbaseYii2\components;

use PDO;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Exception;
use yii\db\ActiveRecord;
use yii\web\Response;
use yii\helpers\ArrayHelper;
use RuntimeException;

defined('NO_TIME') || define('NO_TIME', '0000-00-00 00:00:00');

/**
 * Trait CurdActionTrait
 * 增删改查操作
 *
 * @method string|Response result($errCode = ErrCode::UNKNOWN, string $errmsg = '', $data = [], array $params = [], string $returnType = 'response') 输出json结构数据到Response中
 * @method string|Response result_r($errCode = '0', string $errmsg = '', $data = [], array $params = []) 输出json字符串
 * @method string|Response resultError($error = []) 将error数组转换Response输出
 *
 * @package Jcbowen\JcbaseYii2\components
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

    // ---------- 字段名定义 ----------/

    /** @var string 数据表删除时间字段 */
    public $field_deleted_at = 'deleted_at';

    //---------- 以下赋值于checkInit之后 ----------/

    /** @var string 数据表名称 */
    public $modelTableName;

    /** @var ActiveRecord 数据模型实例 */
    public $modelInstance;

    /** @var array 该表拥有的字段 */
    public $modelAttributes;

    /** @var string 操作时间 */
    public $operateTime;

    /** @var string 空时间字符 */
    public $noTime = NO_TIME;

    /** @var string 数据表查询别名 */
    public $modelQueryAlias;

    //---------- 以下赋值于actionCreate/actionUpdate之后 ----------/

    /** @var bool 是否新增数据 */
    public $isCreate = false;

    /**
     * 调用前需进行的初始化检查
     *
     * @author  Bowen
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

            $this->modelInstance = new $this->modelClass;
            if (method_exists($this->modelInstance, 'getAttributes'))
                $this->modelAttributes = $this->modelInstance->getAttributes();
        }
    }

    //---------- 列表查询 ----------/

    /**
     * 获取列表数据
     *
     * @author  Bowen
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
        if (!empty($this->modelQueryAlias))
            $row->alias($this->modelQueryAlias);

        // 用于补充查询
        $result = $this->getListRow($row);
        if (isset($result) && Util::isError($result))
            return $this->resultError($result);

        // 仅在存在deleted_at字段时才进行软删除过滤
        if (empty($showDeleted) && array_key_exists($this->field_deleted_at, $this->modelAttributes))
            $row = $row->andWhere([($this->modelQueryAlias ?: $this->modelTableName) . ".$this->field_deleted_at" => $this->noTime]);

        if (!empty($where)) $row = $row->andWhere($where);
        $row = $row->andFilterWhere($filterWhere);

        $row = $row->limit($pageSize)->offset(($page - 1) * $pageSize);
        if (!empty($order)) $row = $row->addOrderBy($order);

        if ($this->listAsArray())
            $row = $row->asArray();
        $list  = $row->all();
        $total = $row->count();

        if (!empty($list))
            foreach ($list as &$item) $item = $this->listEach($item);

        return $this->listReturn($list, $total, $page, $pageSize);
    }

    /**
     * 查询列表条件
     *
     * @author  Bowen
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
     * @author  Bowen
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveQuery $row
     *
     * @return ActiveQuery|array|Response|void
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getListRow(ActiveQuery &$row)
    {
        return $row;
    }

    /**
     * 设置查询列表返回的字段
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getListFields()
    {
        return ($this->modelQueryAlias ?: $this->modelTableName) . '.*';
    }

    /**
     * 获取列表排序
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getListOrder()
    {
        return '';
    }

    /**
     * 查询数据列表时是否调用asArray()
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return bool
     * @lasttime: 2022/12/20 1:45 PM
     */
    public function listAsArray(): bool
    {
        return true;
    }

    /**
     * 遍历列表数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $item
     *
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function listEach($item)
    {
        if (!$this->listAsArray()) {
            if (method_exists($item, 'toArray')) $item = $item->toArray();
        } else {
            (new FieldFilter)->set([
                'modelClass' => $this->modelClass,
            ])->de($item);
        }

        return $item;
    }

    /**
     * 查询列表返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $total
     * @param $page
     * @param $pageSize
     * @param $list
     *
     * @return Response|string
     * @lasttime: 2022/3/13 10:34 上午
     */
    public function listReturn($list, $total, $page, $pageSize)
    {
        return static::result(ErrCode::SUCCESS, 'ok', $list, [
            'count' => $total, 'page' => $page, 'page_size' => $pageSize
        ]);
    }

    //---------- 根据时间分页的列表查询(适用于下拉加载等有可能因数据插入导致分页数据重复问题的情况) ----------/

    /**
     * @var string loader接口使用的时间字段名
     */
    public $loaderTimeField = 'created_at';

    /**
     * 根据时间分页的列表查询(最大输出1000条数据)
     *
     * @author  Bowen
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
        if (!empty($this->modelQueryAlias))
            $row->alias($this->modelQueryAlias);

        // 用于补充查询
        $result = $this->getLoaderRow($row);
        if (isset($result) && Util::isError($result))
            return static::resultError($result);

        // 仅在存在deleted_at字段时才进行软删除过滤
        if (empty($showDeleted) && array_key_exists($this->field_deleted_at, $this->modelAttributes))
            $row->andWhere([($this->modelQueryAlias ?: $this->modelTableName) . ".$this->field_deleted_at" => $this->noTime]);

        if (!empty($where)) $row->andWhere($where);
        $row->andFilterWhere($filterWhere);

        $row->limit($pageSize);
        if (!empty($order)) $row->addOrderBy($order);

        if ($this->loaderAsArray())
            $row = $row->asArray();
        $list = $row->all();

        $minTime = '';
        $maxTime = '';
        if (!empty($list))
            foreach ($list as &$item) $item = $this->loaderEach($item, $minTime, $maxTime);

        return $this->loaderReturn($list, $pageSize, $minTime, $maxTime);
    }

    /**
     * 根据时间分页的列表查询条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function getLoaderWhere()
    {
        return [];
    }

    /**
     * 根据时间分页的列表查询过滤条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:52 上午
     */
    public function getLoaderFilterWhere()
    {
        global $_GPC;

        $maxTime = Safe::gpcString(trim($_GPC['maxTime'])); // 上次查询数据里的最大时间
        $minTime = Safe::gpcString(trim($_GPC['minTime'])); // 上次查询数据里的最小时间

        // 验证maxTime及minTime是否为Y-m-d H:i:s格式
        if (!empty($maxTime) && !preg_match('/^\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}$/', $maxTime))
            $maxTime = '';
        if (!empty($minTime) && !preg_match('/^\d{4}-\d{1,2}-\d{1,2} \d{1,2}:\d{1,2}:\d{1,2}$/', $minTime))
            $minTime = '';

        // minTime和maxTime都不为空时，查询时间区间内的数据
        if (!empty($maxTime) && !empty($minTime))
            return [
                'between',
                ($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->loaderTimeField,
                $minTime, $maxTime
            ];
        // 只传minTime意味着时间为倒叙，所以只查询小于minTime的数据
        if (!empty($minTime))
            return [
                '<',
                ($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->loaderTimeField,
                $minTime
            ];
        // 只传maxTime意味着时间为正序，所以只查询大于maxTime的数据
        if (!empty($maxTime))
            return ['>', ($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->loaderTimeField, $maxTime];

        return [];
    }

    /**
     * 根据时间分页的列表查询的链式查询
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveQuery $row
     *
     * @return ActiveQuery|array|Response|void
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getLoaderRow(ActiveQuery &$row)
    {
        return $row;
    }

    /**
     * 设置根据时间分页的列表查询返回的字段
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getLoaderFields()
    {
        return ($this->modelQueryAlias ?: $this->modelTableName) . '.*';
    }

    /**
     * 获取根据时间分页的列表查询排序
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getLoaderOrder()
    {
        global $_GPC;

        // 都没传的时候，不进行排序
        if (!isset($_GPC['minTime']) && !isset($_GPC['maxTime'])) return [];
        // 只传maxTime时，正序
        if (isset($_GPC['maxTime']) && !isset($_GPC['minTime']))
            return [($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->loaderTimeField => SORT_ASC];

        return [($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->loaderTimeField => SORT_DESC];
    }

    /**
     * 根据时间分页的列表查询时是否调用asArray()
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return bool
     * @lasttime: 2022/12/20 1:45 PM
     */
    public function loaderAsArray(): bool
    {
        return true;
    }

    /**
     * 遍历根据时间分页的列表查询数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $item
     * @param $minTime string 最小的创建时间
     * @param $maxTime string 最大的创建时间
     *
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function loaderEach($item, string &$minTime, string &$maxTime)
    {
        if (!$this->loaderAsArray()) {
            if (method_exists($item, 'toArray')) $item = $item->toArray();
        } else {
            (new FieldFilter)->set([
                'modelClass' => $this->modelClass,
            ])->de($item);
        }

        if (empty($maxTime) || $item[$this->loaderTimeField] > $maxTime) $maxTime = $item[$this->loaderTimeField];
        if (empty($minTime) || $item[$this->loaderTimeField] < $minTime) $minTime = $item[$this->loaderTimeField];

        return $item;
    }

    /**
     * 根据时间分页的列表查询返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $list
     * @param $pageSize
     * @param $minTime
     * @param $maxTime
     *
     * @return string|Response
     * @lasttime: 2022/10/13 17:58
     */
    public function loaderReturn($list, $pageSize, $minTime, $maxTime)
    {
        return static::result(ErrCode::SUCCESS, 'ok', $list, [
            'maxTime' => $maxTime, 'minTime' => $minTime, 'page_size' => $pageSize
        ]);
    }

    //---------- 批处理列表查询（一般用于需要一次性输出所有数据的情况） ----------/

    /**
     * 批处理列表查询数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return Response|string
     * @lasttime: 2022/3/13 9:55 上午
     */
    public function actionAll()
    {
        global $_GPC;

        $this->checkInit();

        $fields      = $this->getAllFields();
        $where       = $this->getAllWhere();
        $filterWhere = $this->getAllFilterWhere();
        $order       = $this->getAllOrder();

        $fetchSize   = intval($_GPC['fetch_size']);
        $fetchSize   = $fetchSize <= 0 ? 100 : $fetchSize;
        $showDeleted = intval($_GPC['show_deleted']);

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row = $row->select($fields);
        if (!empty($this->modelQueryAlias))
            $row->alias($this->modelQueryAlias);

        // 用于补充查询
        $result = $this->getAllRow($row);
        if (isset($result) && Util::isError($result))
            return static::resultError($result);

        // 仅在存在deleted_at字段时才进行软删除过滤
        if (empty($showDeleted) && array_key_exists($this->field_deleted_at, $this->modelAttributes))
            $row = $row->andWhere([($this->modelQueryAlias ?: $this->modelTableName) . ".$this->field_deleted_at" => $this->noTime]);

        if (!empty($where)) $row = $row->andWhere($where);
        $row = $row->andFilterWhere($filterWhere);

        if (!empty($order)) $row = $row->addOrderBy($order);

        if ($this->allAsArray())
            $row = $row->asArray();

        // 获取缓存配置
        $mysql_attr_use_buffered_query = Yii::$app->db->pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        // 批量查询时，如果开启了缓存，则关闭缓存
        if ($mysql_attr_use_buffered_query !== false)
            Yii::$app->db->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $list = [];
        foreach ($row->each($fetchSize) as $item) {
            $list[] = $this->allEach($item);
        }
        // 还原缓存配置
        if ($mysql_attr_use_buffered_query !== false)
            Yii::$app->db->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $mysql_attr_use_buffered_query);

        $total = $row->count();

        return $this->allReturn($list, $total);
    }

    /**
     * 批处理列表查询条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return array|string
     * @lasttime: 2022/3/13 10:53 上午
     */
    public function getAllWhere()
    {
        return [];
    }

    /**
     * 批处理列表查询过滤条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return array
     * @lasttime: 2022/3/13 10:52 上午
     */
    public function getAllFilterWhere(): array
    {
        return [];
    }

    /**
     * 批处理列表查询链式查询
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveQuery $row
     *
     * @return mixed
     * @lasttime: 2022/3/18 11:11 下午
     */
    public function getAllRow(ActiveQuery &$row)
    {
        return $row;
    }

    /**
     * 批处理列表查询返回的字段
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getAllFields()
    {
        return ($this->modelQueryAlias ?: $this->modelTableName) . '.*';
    }

    /**
     * 批处理列表查询排序
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/13 10:51 上午
     */
    public function getAllOrder()
    {
        return '';
    }

    /**
     * 批处理列表查询时是否调用asArray()
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @return bool
     * @lasttime: 2022/12/20 1:45 PM
     */
    public function allAsArray(): bool
    {
        return true;
    }

    /**
     * 批处理列表查询数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $item
     *
     * @return mixed
     * @lasttime: 2022/3/13 10:50 上午
     */
    public function allEach($item)
    {
        if (!$this->listAsArray()) {
            if (method_exists($item, 'toArray')) $item = $item->toArray();
        } else {
            (new FieldFilter)->set([
                'modelClass' => $this->modelClass,
            ])->de($item);
        }

        return $item;
    }

    /**
     * 批处理列表查询返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $total
     * @param $list
     *
     * @return Response|string
     * @lasttime: 2022/3/13 10:34 上午
     */
    public function allReturn($list, $total)
    {
        return static::result(ErrCode::SUCCESS, 'ok', $list, [
            'count' => $total
        ]);
    }

    //---------- 详情查询 ----------/

    /**
     * 查询数据详情
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return Response|string
     * @lasttime: 2022/3/13 3:26 下午
     */
    public function actionDetail()
    {
        global $_GPC;

        $this->checkInit();
        $showDeleted = intval($_GPC['show_deleted']);

        $fields = $this->getDetailFields();
        $where  = $this->getDetailWhere($_GPC);

        /** @var ActiveQuery $row */
        $row = call_user_func($this->modelClass . '::find');
        $row = $row->select($fields);
        if (!empty($this->modelQueryAlias))
            $row->alias($this->modelQueryAlias);
        $row = $row->where($where);

        $result = $this->getDetailRow($row);
        if (isset($result) && Util::isError($result))
            return static::resultError($result);

        // 仅在存在deleted_at字段时才进行软删除过滤
        if (empty($showDeleted) && array_key_exists($this->field_deleted_at, $this->modelAttributes))
            $row = $row->andWhere([($this->modelQueryAlias ?: $this->modelTableName) . ".$this->field_deleted_at" => $this->noTime]);

        if ($this->detailAsArray())
            $row = $row->asArray();
        $detail = $row->one();

        if (empty($detail)) return static::result(ErrCode::NOT_EXIST, '查询数据不存在或已被删除');

        $detail = $this->detail($detail);

        return $this->detailReturn($detail);
    }

    /**
     * 获取数据详情的返回字段
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|array
     * @lasttime: 2022/3/21 8:50 下午
     */
    public function getDetailFields()
    {
        return ($this->modelQueryAlias ?: $this->modelTableName) . '.*';
    }

    /**
     * 查询数据详情的链式查询
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveQuery $row
     *
     * @return ActiveQuery|array|Response|void
     * @lasttime: 2022/3/21 11:11 下午
     */
    public function getDetailRow(ActiveQuery &$row)
    {
        return $row;
    }

    /**
     * 查询数据详情的条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data 通过$_GPC接收的数据
     *
     * @return string|array|Response
     * @lasttime: 2022/3/13 3:25 下午
     */
    public function getDetailWhere(array &$data)
    {
        $id = intval($data[$this->pkId]);
        if (empty($id))
            return static::result(ErrCode::PARAMETER_ERROR, "{$this->pkId}不能为空");

        $where   = ['and'];
        $where[] = [($this->modelQueryAlias ?: $this->modelTableName) . '.' . $this->pkId => $id];
        return $where;
    }

    /**
     * 查询数据详情时是否调用asArray()
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return bool
     * @lasttime 2022/9/21 09:58
     */
    public function detailAsArray(): bool
    {
        return true;
    }

    /**
     * 查询数据详情的数据过滤
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|ActiveRecord $detail
     *
     * @return mixed
     * @lasttime: 2022/3/13 3:30 下午
     */
    public function detail($detail)
    {
        if (!$this->detailAsArray()) {
            if (method_exists($detail, 'toArray')) $detail = $detail->toArray();
        } else {
            (new FieldFilter)->set([
                'modelClass' => $this->modelClass,
            ])->de($detail);
        }

        return $detail;
    }

    /**
     * 查询数据详情的返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $detail
     *
     * @return string|Response
     * @lasttime: 2022/12/15 14:03
     */
    public function detailReturn($detail)
    {
        return static::result(ErrCode::SUCCESS, 'ok', $detail);
    }

    //---------- 新增数据 ----------/

    /**
     * 新增数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @lasttime: 2022/3/13 10:33 下午
     */
    public function actionCreate()
    {
        $this->checkInit();

        $this->isCreate = true;

        // 获取新增数据
        $data = $this->getCreateFormData();
        if (Util::isError($data)) {
            $data['errmsg'] = $data['errmsg'] ?: '数据不能为空';
            return static::resultError($data);
        }

        if ($data instanceof Response) return $data;

        // 新增前
        $result_before = $this->createBefore($data);
        if (Util::isError($result_before)) {
            $result_before['errmsg'] = $result_before['errmsg'] ?: '添加失败，请稍后再试';
            return static::resultError($result_before);
        }

        // 如果在createBefore中补充了主键值，则转给更新方法来处理
        if (!empty($data[$this->pkId])) return $this->actionUpdate($data);

        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave($this->modelInstance, $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return static::resultError($result);
        }

        $id = Yii::$app->db->getLastInsertID();

        // 新增后
        $result = $this->createAfter($id, $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return static::resultError($result);
        }

        try {
            $tr->commit();
        } catch (Exception $e) {
            return $this->result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，请稍后再试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        // 如果是通过Util::error输出的成功信息，则应该根据成功信息进行输出
        if (!empty($result) && is_array($result) && $result['errcode'] == ErrCode::SUCCESS)
            return static::result(ErrCode::SUCCESS, $result['errmsg'], $result['data']);

        return $this->createReturn($id);
    }

    /**
     * 创建接口接收数据
     *
     * @author  Bowen
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     *
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|int $id
     * @param array      $data
     *
     * @return string|int
     * @lasttime: 2022/3/13 10:02 下午
     */
    public function createAfter($id, array $data)
    {
        $result = $this->afterSave($id, $data);
        if (Util::isError($result))
            return $result;
        return $id;
    }

    /**
     * 新增数据成功后的返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $id
     *
     * @return string|Response
     * @lasttime: 2023/3/19 8:00 PM
     */
    public function createReturn($id)
    {
        return $this->saveReturn($id);
    }

    //---------- 更新数据 ----------/

    /**
     * 更新数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|null $data
     *
     * @return string|Response
     * @lasttime: 2022/3/13 10:20 下午
     */
    public function actionUpdate(?array $data = null)
    {
        $this->checkInit();

        $this->isCreate = false;

        // 获取更新数据
        $data = $data ?? $this->getUpdateFormData();
        if (Util::isError($data)) {
            $data['errmsg'] = $data['errmsg'] ?: '数据不能为空';
            return static::resultError($data);
        }

        if ($data instanceof Response) return $data;

        $id = intval($data[$this->pkId]);
        if (empty($id))
            return static::result(ErrCode::UNKNOWN, "{$this->pkId}不能为空");

        // 查询数据是否存在
        $where = $this->getUpdateWhere($data);
        if (!$model = call_user_func($this->modelClass . '::findOne', $where))
            return static::result(ErrCode::NOT_EXIST, '更新数据不存在');

        // 更新前
        $result_before = $this->updateBefore($model, $data);
        if (Util::isError($result_before)) {
            $result_before['errmsg'] = $result_before['errmsg'] ?: '更新前发现错误，请稍后再试';
            return static::resultError($result_before);
        }

        // 开启事务
        $tr = Yii::$app->db->beginTransaction();

        $result = $this->toSave($model, $data);
        if (Util::isError($result)) {
            $tr->rollBack();
            return static::resultError($result);
        }

        // 更新后
        $result_after = $this->updateAfter($id, $data);
        if (Util::isError($result_after)) {
            $tr->rollBack();
            return static::result(ErrCode::UNKNOWN, $result_after['errmsg'] ?: '更新失败，请稍后再试');
        }

        try {
            $tr->commit();
        } catch (Exception $e) {
            return $this->result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，请稍后再试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        return $this->updateReturn($id);
    }

    /**
     * 更新数据的查询条件
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data 通过$_GPC接收的数据
     *
     * @return string|array|Response
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function getUpdateWhere(array &$data)
    {
        $id = intval($data[$this->pkId]);
        if (empty($id))
            return static::result(ErrCode::PARAMETER_ERROR, "{$this->pkId}不能为空");

        $where   = ['and'];
        $where[] = [$this->pkId => $id];
        return $where;
    }

    /**
     * 更新的数据
     *
     * @author  Bowen
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param       $model
     * @param array $data
     *
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param int|string $id
     * @param array      $data
     *
     * @return array|bool
     * @lasttime: 2022/3/13 10:09 下午
     */
    public function updateAfter($id, array $data)
    {
        $result = $this->afterSave($id, $data);
        if (Util::isError($result))
            return $result;
        return true;
    }

    /**
     * 更新数据成功后的返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $id
     *
     * @return string|Response
     * @lasttime: 2023/3/19 8:05 PM
     */
    public function updateReturn($id)
    {
        return $this->saveReturn($id);
    }

    //---------- 设置字段值 ----------/

    /**
     * 设置某个字段的值
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
     * @lasttime: 2022/3/13 10:31 下午
     */
    public function actionSetValue()
    {
        $this->checkInit();

        $data = $this->getSetValueFormData();

        $id = intval($data[$this->pkId]);
        if (empty($id))
            return static::result(ErrCode::PARAMETER_ERROR, "$this->pkId 不能为空");

        $field = Safe::gpcString(trim($data['field'])); // 字段名
        $type  = trim($data['type']); // 字段值的类型
        $value = $data['value']; // 字段值

        // 检查字段名的有效性
        $result = $this->setValueCheckField($field);
        if (Util::isError($result))
            return static::resultError($result);

        // 根据字段值的类型，对字段值进行格式化
        if ($type === 'int')
            $value = intval($value);
        elseif ($type === 'float')
            $value = floatval($value);
        elseif ($type === 'money')
            $value = Util::round_money($value);
        elseif (is_array($value)) {
            if ($type === 'serialize')
                $value = serialize($value);
            else
                $value = json_encode($value);
        } else
            $value = Safe::gpcString(trim($value));

        $model = call_user_func($this->modelClass . '::findOne', $this->getSetValueQueryWhere($id));
        if (!$model)
            return static::result(ErrCode::NOT_EXIST, '数据不存在或已被删除');

        // 存在该属性的时候，才进行验证值是否发生变化
        if (array_key_exists($field, $this->modelAttributes) && $model->$field === $value)
            return static::result(ErrCode::SUCCESS, '值未发生改变，请确认修改内容');

        $res = $this->getSetValueRecord($model);
        if (Util::isError($res))
            return static::resultError($res);

        $tr = Yii::$app->db->beginTransaction();

        $changeData = $this->getSetValueChangeData($field, $value, $model);
        if (Util::isError($changeData))
            return static::resultError($changeData);

        $result = $this->toSave($model, $changeData);
        if (Util::isError($result)) {
            $tr->rollBack();
            return static::resultError($result);
        }

        $result_after = $this->setValueAfter($id, $field, $value);
        if (Util::isError($result_after)) {
            $tr->rollBack();
            return static::resultError($result_after);
        }

        try {
            $tr->commit();
        } catch (Exception $e) {
            $tr->rollBack();
            return static::result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，请稍后再试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        return $this->setValueReturn($value, $field, $id);
    }

    /**
     * 获取设置某个字段的值的数据
     *
     * @author  Bowen
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
     * 设置某个字段时，检查传入的字段是否有效
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string $field
     *
     * @return array|bool
     * @lasttime: 2022/9/10 08:32
     */
    public function setValueCheckField(string $field)
    {
        // 验证是否传入了字段名，且字段名是否有效
        if (empty($field) || !array_key_exists($field, $this->modelAttributes))
            return Util::error(ErrCode::PARAMETER_ERROR, '参数错误，请传入有效的字段名');

        return true;
    }

    /**
     * setValue查询条件
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param int $id
     *
     * @return array|string
     * @lasttime 2022/9/30 09:21
     */
    public function getSetValueQueryWhere(int $id = 0)
    {
        $where   = ['and'];
        $where[] = [$this->pkId => $id];
        if (array_key_exists($this->field_deleted_at, $this->modelAttributes))
            $where[] = [$this->field_deleted_at => $this->noTime];

        return $where;
    }

    /**
     * 获取setValue查询结果
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param ActiveRecord $record
     *
     * @return ActiveRecord|Response
     * @lasttime 2022/11/17 16:09
     */
    public function getSetValueRecord(ActiveRecord &$record)
    {
        return $record;
    }

    /**
     * 获取setValue修改的数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string       $field
     * @param mixed        $value
     * @param ActiveRecord $model
     *
     * @return array
     * @lasttime: 2022/12/28 4:48 PM
     */
    public function getSetValueChangeData(string $field, $value, ActiveRecord $model): array
    {
        return [$field => $value];
    }

    /**
     * 设置某个字段的值后执行
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param int         $id
     * @param string      $field
     * @param string|null $value
     *
     * @return bool|array
     * @lasttime: 2022/12/26 3:20 PM
     */
    public function setValueAfter(int $id, string $field = '', ?string $value = '')
    {
        return true;
    }

    /**
     * 设置某个字段的值返回数据
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $value
     * @param $field
     * @param $id
     *
     * @return string|Response
     * @lasttime: 2023/3/19 8:10 PM
     */
    public function setValueReturn($value, $field, $id)
    {
        return static::result(ErrCode::SUCCESS, '设置成功', ['value' => $value]);
    }

    //---------- 变更通用 ----------/

    /**
     * 新增/更新数据(接口合并)
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $data
     * @param       $model
     *
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param int|null   $id
     * @param array|null $data
     *
     * @return bool|array
     * @lasttime: 2022/10/10 15:05
     */
    public function afterSave(?int $id = 0, ?array $data = [])
    {
        return true;
    }

    /**
     * 新增/更新返回数据(接口合并)
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param $id
     *
     * @return string|Response
     * @lasttime: 2023/3/19 8:14 PM
     */
    public function saveReturn($id)
    {
        return static::result(ErrCode::SUCCESS, ($this->isCreate ? '添加' : '更新') . '成功', [$this->pkId => $id]);
    }

    /**
     * 获取新增/更新数据
     *
     * @author  Bowen
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


    public function actionDelete()
    {
        global $_GPC;

        $this->checkInit();

        $ids = Safe::gpcArray($_GPC[$this->pkId . 's']);
        foreach ($ids as $i => $id)
            if (empty($id)) unset($ids[$i]);

        if (empty($ids))
            return static::result(ErrCode::PARAMETER_ERROR, '参数缺失，请重试');

        // 设置删除数据的select字段
        $fields = $this->getDeleteFields();

        self::checkFieldExistInSelect($fields, $this->pkId, '设置删除数据的');

        // 获取删除条件
        $where = $this->getDeleteWhere($ids);

        $delArr = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->where([
                $this->modelTableName . ".$this->field_deleted_at" => $this->noTime,
            ])
            ->andWhere($where)
            ->asArray()
            ->all();

        if (empty($delArr))
            return static::result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        $delIds = ArrayHelper::getColumn($delArr, $this->pkId);

        // 删除前
        $result_before = $this->deleteBefore($delArr, $delIds);
        if (Util::isError($result_before))
            return static::result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');

        $tr = Yii::$app->db->beginTransaction();

        // 获取删除操作更新的属性值
        $condition = $this->getDeleteCondition($delArr);

        if (!call_user_func("$this->modelClass::updateAll", $condition, $where)) {
            $tr->rollBack();
            return static::result(ErrCode::STORAGE_ERROR, '删除失败，未知错误');
        }

        // 删除后
        $result_after = $this->deleteAfter($delIds, $delArr);
        if (Util::isError($result_after)) {
            $tr->rollBack();
            return static::result(ErrCode::UNKNOWN, $result_after['errmsg'] ?: '删除数据失败，请稍后再试');
        }

        try {
            $tr->commit();
            call_user_func($this->modelClass . '::clearCache');
        } catch (Exception $e) {
            $tr->rollBack();
            return static::result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '事务提交失败，请重试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        return $this->deleteReturn($delIds, $delArr);
    }

    /**
     * 设置删除数据的select字段（重写方法时，务必查询主键！）
     *
     * @author  Bowen
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
     * @author  Bowen
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
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $delArr 所有要被删除的数据
     * @param array $delIds 所有要被删除的数据的ids
     *
     * @return array|bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteBefore(array $delArr, array $delIds)
    {
        return true;
    }

    /**
     * 删除时需要更新的属性值
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $delArr 要被删除的数据
     *
     * @return array|string
     * @lasttime 2022/9/21 15:15
     */
    public function getDeleteCondition(array $delArr)
    {
        return [$this->field_deleted_at => $this->operateTime];
    }

    /**
     * 删除后调用
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $ids    被删除的数据id
     * @param array $delArr 被删除的数据
     *
     * @return array|bool
     * @lasttime: 2021/5/9 3:04 下午
     */
    public function deleteAfter(array $ids = [], array $delArr = [])
    {
        return true;
    }

    /**
     * 删除返回
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $delIds
     * @param array $delArr
     *
     * @return string|Response
     * @lasttime: 2023/3/19 10:33 PM
     */
    public function deleteReturn(array $delIds, array $delArr)
    {
        return static::result(ErrCode::SUCCESS, '删除成功', ['delIds' => $delIds]);
    }

    //---------- 恢复删除的数据 ----------/

    /**
     * 恢复删除的数据
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
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
            return static::result(ErrCode::PARAMETER_ERROR, '参数缺失，请重试');

        // 设置恢复数据的select字段
        $fields = $this->getRestoreFields();

        self::checkFieldExistInSelect($fields, $this->pkId, '设置恢复数据的');

        // 获取删除条件
        $where = $this->getRestoreWhere($ids);

        $items   = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->where(['<>', $this->modelTableName . ".$this->field_deleted_at", $this->noTime])
            ->andWhere($where)
            ->asArray()
            ->all();
        $itemIds = ArrayHelper::getColumn($items, $this->pkId);

        if (empty($itemIds))
            return static::result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        // 删除前
        $result_before = $this->restoreBefore($items, $itemIds);
        if (Util::isError($result_before))
            return static::result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '恢复数据失败，请稍后再试');


        $transaction = Yii::$app->db->beginTransaction();

        $condition = $this->getRestoreCondition($items);

        if (!call_user_func("$this->modelClass::updateAll", $condition, $where)) {
            $transaction->rollBack();
            return static::result(ErrCode::UNKNOWN, '恢复失败，未知错误');
        }

        // 删除后
        $result_after = $this->restoreAfter($itemIds, $items);
        if (Util::isError($result_after)) {
            $transaction->rollBack();
            return static::result(ErrCode::UNKNOWN, $result_after['errmsg'] ?: '恢复数据失败，请稍后再试');
        }

        try {
            $transaction->commit();
        } catch (Exception $e) {
            return $this->result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，请稍后再试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        return $this->restoreReturn($itemIds, $items);
    }

    /**
     * 设置恢复数据的select字段（重写方法时，务必查询主键！）
     *
     * @author   Bowen
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $ids
     *
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $items
     * @param array $itemIds
     *
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $items 需要恢复的数据
     *
     * @return array|string
     * @lasttime 2022/9/21 15:19
     */
    public function getRestoreCondition(array $items)
    {
        return [$this->field_deleted_at => $this->noTime];
    }

    /**
     * 恢复后调用
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $ids   被恢复的id
     * @param array $items 被恢复的数据
     *
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function restoreAfter(array $ids = [], array $items = [])
    {
        return true;
    }

    /**
     * 恢复成功返回
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $restoreIds
     * @param array $restoreArr
     *
     * @return string|Response
     * @lasttime: 2023/3/19 10:48 PM
     */
    public function restoreReturn(array $restoreIds, array $restoreArr)
    {
        return static::result(ErrCode::SUCCESS, '恢复成功', ['restoreIds' => $restoreIds]);
    }

    //---------- 真实删除数据 ----------/

    /**
     * 真实删除数据
     *
     * @author   Bowen
     * @email bowen@jiuchet.com
     * @return string|Response
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
            return static::result(ErrCode::PARAMETER_ERROR, '参数缺失，请重试');

        $fields = $this->getRemoveFields();

        self::checkFieldExistInSelect($fields, $this->pkId, '设置真实删除数据的');

        // 获取删除条件
        $where = $this->getRemoveWhere($ids);

        $items   = call_user_func($this->modelClass . '::find')
            ->select($fields)
            ->andWhere($where)
            ->asArray()
            ->all();
        $itemIds = ArrayHelper::getColumn($items, $this->pkId);

        if (empty($itemIds))
            return static::result(ErrCode::NOT_EXIST, '当前操作的数据不存在或已被删除');

        // 删除前
        $result_before = $this->removeBefore($items, $itemIds);
        if (Util::isError($result_before))
            return static::result(ErrCode::UNKNOWN, $result_before['errmsg'] ?: '删除数据失败，请稍后再试');

        $transaction = Yii::$app->db->beginTransaction();

        if (!call_user_func("$this->modelClass::deleteAll", $where)) {
            $transaction->rollBack();
            return static::result(ErrCode::UNKNOWN, '删除失败，未知错误');
        }

        // 删除后
        $result_after = $this->removeAfter($itemIds, $items);
        if (Util::isError($result_after)) {
            $transaction->rollBack();
            return static::result(ErrCode::UNKNOWN, $result_after['errmsg'] ?: '删除数据失败，请稍后再试');
        }

        try {
            $transaction->commit();
        } catch (Exception $e) {
            return $this->result(ErrCode::DATABASE_TRANSACTION_COMMIT_ERROR, '系统繁忙，请稍后再试', [
                'errcode' => $e->getCode(),
                'errmsg'  => $e->getMessage(),
            ]);
        }

        return $this->removeReturn($itemIds, $items);
    }

    /**
     * 设置被永久删除数据的查询返回字段（重写方法时，务必查询主键！）
     *
     * @author   Bowen
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param $ids
     *
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $items
     * @param array $itemIds
     *
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
     * @author   Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $ids   永久删除的id
     * @param array $items 永久删除的数据
     *
     * @return array|bool
     * @lasttime 2022/9/21 15:06
     */
    public function removeAfter(array $ids = [], array $items = [])
    {
        return true;
    }

    /**
     * 永久删除成功返回
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array $removeIds
     * @param array $removeArr
     *
     * @return string|Response
     * @lasttime: 2023/3/19 11:10 PM
     */
    public function removeReturn(array $removeIds, array $removeArr)
    {
        return static::result(ErrCode::SUCCESS, '永久删除成功', ['removeIds' => $removeIds]);
    }

    // ---------- 其他 ----------/

    /**
     * 将error数组转换Response输出
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param mixed $error
     *
     * @return string|Response
     * @lasttime: 2023/1/13 1:35 PM
     */
    public static function resultError($error = [])
    {
        return (new Util)->resultError($error);
    }

    /**
     * 输出json结构数据到Response中
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|integer $errCode
     * @param string         $errmsg
     * @param mixed          $data
     * @param array          $params
     * @param string         $returnType
     *
     * @return string|Response
     * @lasttime: 2022/8/28 23:17
     */
    public static function result($errCode = ErrCode::UNKNOWN, string $errmsg = '', $data = [], array $params = [], string $returnType = 'response')
    {
        return (new Util)->result($errCode, $errmsg, $data, $params, $returnType);
    }

    /**
     * 输出json字符串
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|integer $errCode
     * @param string         $errmsg
     * @param mixed          $data
     * @param array          $params
     *
     * @return string|Response
     * @lasttime: 2023/1/13 1:33 PM
     */
    public static function result_r($errCode = '0', string $errmsg = '', $data = [], array $params = [])
    {
        return static::result($errCode, $errmsg, $data, $params, 'return');
    }

    /**
     * 检查查询语句中是否包含指定字段
     *
     * @author  Bowen
     * @email bowen@jiuchet.com
     *
     * @param array|string $select   Check the SELECT part of the query.
     * @param string       $field    检查的字段名
     * @param string       $useAlias 本次查询的用处别名
     *
     * @lasttime: 2023/3/19 11:01 PM
     */
    public static function checkFieldExistInSelect($select, string $field, string $useAlias = '')
    {
        // 检查恢复数据的select字段是否包含主键
        $result_checkSelect = false;
        if (is_array($select)) {
            foreach ($select as $item) {
                // 判断是否有数据表别名
                if (strpos($item, '.') !== false)
                    $item = explode('.', $item)[1];
                if ($item == $field) {
                    $result_checkSelect = true;
                    break;
                }
            }
        } elseif (is_string($select)) {
            // 判断是否有数据表别名
            if (strpos($select, '.') !== false)
                $select = explode('.', $select)[1];
            if ($select == $field)
                $result_checkSelect = true;
        } else {
            throw new RuntimeException($useAlias . 'select字段类型错误');
        }
        if (!$result_checkSelect)
            throw new RuntimeException($useAlias . 'select字段不包含主键');
    }
}
