<?php

namespace Jcbowen\JcbaseYii2\components\sdk\aop\schema;

class ServiceSchemaFactory
{
    public static function createAttribute($id = null, $name = null, $type = null, $valueType = null)
    {
        $attribute = new XMLAttribute();
        $attribute->setId($id);
        $attribute->setName($name);
        $attribute->setType($type);
        $attribute->setValueType($valueType);
        return $attribute;
    }

    public static function createRule($type = null, $name = null, $value = null)
    {
        return new AttributeRule($type, $name, $value);
    }

    public static function createOption($displayName = null, $value = null)
    {
        return new Option($displayName, $value);
    }
}