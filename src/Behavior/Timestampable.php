<?php
namespace Eyf\Modelify\Behavior;

interface Timestampable
{
    public function getCreatedAt();
    public function setCreatedAt(\DateTime $datetime);
    public function getUpdatedAt();
    public function setUpdatedAt(\DateTime $datetime);
}