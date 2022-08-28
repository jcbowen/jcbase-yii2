<?php
namespace Jcbowen\JcbaseYii2\components;


use yii\web\Response;

trait BaseControllerTrait
{

    /**
     * 输出json结构数据
     *
     * @author Bowen
     * @email bowen@jiuchet.com
     *
     * @param string|integer $errCode
     * @param string $errmsg
     * @param mixed $data
     * @param array $params
     * @param string $returnType
     * @return string|Response
     * @lasttime: 2022/8/28 23:17
     */
    public function result($errCode = '0', string $errmsg = '', $data = [], array $params = [], string $returnType = 'exit')
    {
        return (new Util)->result($errCode, $errmsg, $data, $params, $returnType);
    }

    public function result_r($errcode = '0', $errmsg = '', $data = [], $params = [])
    {
        return $this->result($errcode, $errmsg, $data, $params, 'return');
    }
}
