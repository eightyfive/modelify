<?php
namespace Eyf\Modelify\Entity;

interface EntityInterface
{
    public function getId();
    public function setFromArray(array $attrs);
    public function toArray();
}