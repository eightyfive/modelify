<?php
namespace Eyf\Modelify\Entity;

interface EntityInterface
{
    public function getId();
    public function setAttribute($key, $value);
    public function getAttributes();
}