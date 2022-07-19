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
class ErrCode
{
    /** @var int 系统繁忙 */
    const SYSTEM_BUSY = -1;
    /** @var int 操作成功 */
    const SUCCESS = 0;
    /** @var int 未知错误 */
    const UNKNOWN = 1;
    /** @var int 不可操作的状态 */
    const INOPERABLE_STATE = 2;
    /** @var int 失去连接 */
    const LOST_CONNECTION = 3;
    /** @var int 维护中 */
    const UNDER_MAINTENANCE = 4;
    /** @var int 无效的签名 */
    const INVALID_SIGN = 2;
    /** @var int 无效的时间戳 */
    const INVALID_TIMESTAMP = 6;

    /** @var int 登录失效 */
    const LOGIN_INVALID = 1001;
    /** @var int 账号或密码错误 */
    const INCORRECT_USERNAME_OR_PASSWORD = 1002;
    /** @var int 权限不足 */
    const NO_PERMISSION = 1004;
    /** @var int 禁用 */
    const DISABLE = 1005;

    /** @var int 参数错误 */
    const PARAMETER_ERROR = 9001001;
    /** @var int 无效参数 */
    const PARAMETER_INVALID = 9001002;
    /** @var int 部分参数为空 */
    const PARAMETER_EMPTY = 9001003;

    /** @var int 已存在 */
    const EXISTED = 9002001;
    /** @var int 不存在或已被删除 */
    const NOT_EXIST = 9002002;
    /** @var int 和已有数据冲突 */
    const CONFLICT_WITH_EXISTING = 9002003;

    /** @var int 不合法的访问 */
    const ILLEGAL_ACCESS = 9003000;
    /** @var int 不合法的格式 */
    const ILLEGAL_FORMAT = 9003001;
    /** @var int 不合法的类型 */
    const ILLEGAL_TYPE = 9003002;
    /** @var int 不合法的大小 */
    const ILLEGAL_SIZE = 9003003;
    /** @var int 不合法的凭证 */
    const ILLEGAL_CERTIFICATE = 9003004;

    /** @var int 未授权 */
    const UNAUTHORIZED = 9004001;
    /** @var int 无效的授权信息 */
    const INVALID_AUTHORIZATION_INFORMATION = 9004002;

    /** @var int 服务到期 */
    const EXPIRATION_SERVICE = 9005001;
    /** @var int 大更新到期 */
    const EXPIRATION_UPGRADE = 9005002;
    /** @var int 小更新到期 */
    const EXPIRATION_UPDATE = 9005003;
    /** @var int 使用权到期 */
    const EXPIRATION_USE = 9005004;
    /** @var int 使用权到期 */
    const EXPIRATION_API = 9005005;
}
