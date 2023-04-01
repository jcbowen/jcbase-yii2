<?php

namespace Jcbowen\JcbaseYii2\components;

/**
 * Class ErrCode
 *
 * @author Bowen
 * @email bowen@jiuchet.com
 * @lasttime: 2022/7/18 9:35 AM
 * @package Jcbowen\JcbaseYii2\components
 */
// 9001 为服务器错误
// 9002 为客户端错误
// 9003 未定义错误方的错误
class ErrCode
{
    /** @var int 操作成功 */
    const SUCCESS = 0;
    /** @var int 未知错误 */
    const UNKNOWN = 1;
    /** @var int 网络错误 */
    const NETWORK_ERROR = 2;

    /** @var int 系统繁忙 */
    const SYSTEM_BUSY = 9001001;
    /** @var int 维护中 */
    const UNDER_MAINTENANCE = 9001002;
    /** @var int 无效的配置 */
    const INVALID_CONFIG = 9001003;
    /** @var int 没有配置 */
    const NO_CONFIG = 9001004;

    /** @var int 失去连接 */
    const LOST_CONNECTION = 9002001;
    /** @var int 无效的签名 */
    const INVALID_SIGN = 9002002;
    /** @var int 无效的时间戳 */
    const INVALID_TIMESTAMP = 9002003;
    /** @var int 登录失效 */
    const LOGIN_INVALID = 9002004;
    /** @var int 无效的token */
    const INVALID_TOKEN = 9002028;
    /** @var int 权限不足 */
    const NO_PERMISSION = 9002005;
    /** @var int 账号或密码错误 */
    const INCORRECT_USERNAME_OR_PASSWORD = 9002006;
    /** @var int 禁用 */
    const DISABLE = 9002007;
    /** @var int 参数错误 */
    const PARAMETER_ERROR = 9002008;
    /** @var int 无效参数 */
    const PARAMETER_INVALID = 9002009;
    /** @var int 参数缺失 */
    const PARAMETER_MISSING = 9002010;
    /**
     * @var int 部分参数为空
     * @deprecated 请使用 PARAMETER_ERROR
     */
    const PARAMETER_EMPTY = 9002008;
    /** @var int 已存在 */
    const EXISTED = 9002011;
    /** @var int 不存在或已被删除 */
    const NOT_EXIST = 9002012;
    /** @var int 和已有数据冲突 */
    const CONFLICT_WITH_EXISTING = 9002013;
    /** @var int 不合法的访问 */
    const ILLEGAL_ACCESS = 9002014;
    /** @var int 不合法的格式 */
    const ILLEGAL_FORMAT = 9002015;
    /** @var int 不合法的类型 */
    const ILLEGAL_TYPE = 9002016;
    /** @var int 不合法的大小 */
    const ILLEGAL_SIZE = 9002017;
    /** @var int 不合法的凭证 */
    const ILLEGAL_CERTIFICATE = 9002018;
    /** @var int 未授权 */
    const UNAUTHORIZED = 9002019;
    /** @var int 无效的授权信息 */
    const INVALID_AUTHORIZATION_INFORMATION = 9002020;
    /** @var int 服务到期 */
    const EXPIRATION_SERVICE = 9002021;
    /** @var int 大更新到期 */
    const EXPIRATION_UPGRADE = 9002022;
    /** @var int 小更新到期 */
    const EXPIRATION_UPDATE = 9002023;
    /** @var int 使用权到期 */
    const EXPIRATION_USE = 9002024;
    /** @var int 使用权到期 */
    const EXPIRATION_API = 9002025;
    /** @var int 不可操作的状态 */
    const INOPERABLE_STATE = 9002026;
    /** @var int 暂不支持 */
    const NOT_SUPPORTED = 9002027;
    /** @var int 网络连接-错误 */
    const NETWORK_CONNECTION_ERROR = 9002028;
    /** @var int 网络连接-超时 */
    const NETWORK_CONNECTION_TIMEOUT = 9002029;
    /** @var int 网络连接-中断 */
    const NETWORK_CONNECTION_INTERRUPT = 9002030;
    /** @var int 网络连接-拒绝 */
    const NETWORK_CONNECTION_REFUSED = 9002031;
    /** @var int 网络连接-重置 */
    const NETWORK_CONNECTION_RESET = 9002032;
    /** @var int 没有绑定手机号 */
    const NO_BIND_PHONE = 9002033;
    /** @var int 没有绑定邮箱 */
    const NO_BIND_EMAIL = 9002034;
    /** @var int 没有绑定微信 */
    const NO_BIND_WECHAT = 9002037;
    /** @var int 没有绑定微信小程序 */
    const NO_BIND_WECHAT_MINI_PROGRAM = 9002038;
    /** @var int 没有绑定支付宝 */
    const NO_BIND_ALIPAY = 9002039;
    /** @var int 没有设置密码 */
    const NO_SET_PASSWORD = 9002035;
    /** @var int 没有设置支付密码 */
    const NO_SET_PAY_PASSWORD = 9002036;

    /** @var int 数据存储错误 */
    const STORAGE_ERROR = 9003001;
    /** @var int 数据库错误 */
    const DATABASE_ERROR = 9003002;
    /** @var int 数据库连接错误 */
    const DATABASE_CONNECTION_ERROR = 9003003;
    /** @var int 数据库查询错误 */
    const DATABASE_QUERY_ERROR = 9003004;
    /** @var int 数据库写入错误 */
    const DATABASE_WRITE_ERROR = 9003005;
    /** @var int 数据库更新错误 */
    const DATABASE_UPDATE_ERROR = 9003006;
    /** @var int 数据库删除错误 */
    const DATABASE_DELETE_ERROR = 9003007;
    /** @var int 数据库事务错误 */
    const DATABASE_TRANSACTION_ERROR = 9003008;
    /** @var int 数据库存储过程错误 */
    const DATABASE_STORED_PROCEDURE_ERROR = 9003009;
    /** @var int 数据库触发器错误 */
    const DATABASE_TRIGGER_ERROR = 9003010;
    /** @var int 数据库视图错误 */
    const DATABASE_VIEW_ERROR = 9003011;
    /** @var int 数据库函数错误 */
    const DATABASE_FUNCTION_ERROR = 9003012;
    /** @var int 数据库索引错误 */
    const DATABASE_INDEX_ERROR = 9003013;
    /** @var int 数据库序列错误 */
    const DATABASE_SEQUENCE_ERROR = 9003014;
    /** @var int 数据库约束错误 */
    const DATABASE_CONSTRAINT_ERROR = 9003015;
    /** @var int 数据库锁错误 */
    const DATABASE_LOCK_ERROR = 9003016;
    /** @var int 数据库事务隔离级别错误 */
    const DATABASE_TRANSACTION_ISOLATION_LEVEL_ERROR = 9003017;
    /** @var int 数据库事务锁定错误 */
    const DATABASE_TRANSACTION_LOCK_ERROR = 9003018;
    /** @var int 数据库事务超时错误 */
    const DATABASE_TRANSACTION_TIMEOUT_ERROR = 9003019;
    /** @var int 数据库事务死锁错误 */
    const DATABASE_TRANSACTION_DEADLOCK_ERROR = 9003020;
    /** @var int 数据库事务回滚错误 */
    const DATABASE_TRANSACTION_ROLLBACK_ERROR = 9003021;
    /** @var int 数据库事务提交错误 */
    const DATABASE_TRANSACTION_COMMIT_ERROR = 9003022;
    /** @var int 数据库事务保存点错误 */
    const DATABASE_TRANSACTION_SAVEPOINT_ERROR = 9003023;
    /** @var int 数据库事务回滚到保存点错误 */
    const DATABASE_TRANSACTION_ROLLBACK_TO_SAVEPOINT_ERROR = 9003024;
    /** @var int 数据库事务释放保存点错误 */
    const DATABASE_TRANSACTION_RELEASE_SAVEPOINT_ERROR = 9003025;
    /** @var int 数据库事务回滚到释放保存点错误 */
    const DATABASE_TRANSACTION_ROLLBACK_TO_RELEASE_SAVEPOINT_ERROR = 9003026;

    /** @var int 其他错误 */
    const OTHER = 9999999;
}
