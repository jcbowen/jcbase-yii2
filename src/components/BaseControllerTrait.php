<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

trait BaseControllerTrait
{
    /** @var array 每个控制器禁止访问的action */
    public $denyAction = [];

    /**
     * 只允许访问allowAction中的action，其他的将报错404
     * 如果与denyAction冲突，以denyAction为准
     * @var array 允许访问的action
     */
    public $allowAction = [];

    /**
     * {@inheritdoc}
     * @throws NotFoundHttpException|BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        $beforeAction = parent::beforeAction($action);

        if (!$beforeAction) return false;

        // 如果属于denyAction，则抛出404异常
        // 如果配置了allowAction，但当前action不在allowAction中，则抛出404异常
        if (
            in_array($action->id, $this->denyAction)
            || (!empty($this->allowAction) && !in_array($action->id, $this->allowAction))
        )
            if (!Yii::$app instanceof \yii\console\Application) {
                throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
            } else {
                throw new InvalidArgumentException(Yii::t('yii', 'Action not found.'));
            }

        return true;
    }
}
