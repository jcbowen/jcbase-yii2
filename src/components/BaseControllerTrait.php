<?php

namespace Jcbowen\JcbaseYii2\components;

use Yii;
use yii\base\InvalidArgumentException;
use yii\filters\AccessControl;
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
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class'        => AccessControl::className(),
                'rules'        => [
                    [
                        'allow'   => false,
                        'actions' => $this->denyAction,
                    ],
                    [
                        'allow'   => true,
                        'actions' => $this->allowAction,
                    ],
                ],
                'denyCallback' => function ($rule, $action) {
                    if (!Yii::$app instanceof \yii\console\Application) {
                        throw new NotFoundHttpException(Yii::t('yii', 'Page not found.'));
                    } else {
                        throw new InvalidArgumentException(Yii::t('yii', 'Action not found.'));
                    }
                },
            ],
        ];
    }
}
