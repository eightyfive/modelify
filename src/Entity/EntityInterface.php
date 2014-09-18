<?php
namespace Eyf\Modelify\Entity;

interface EntityInterface
{
    public function setFromArray(array $attrs);
    public function toArray();
}