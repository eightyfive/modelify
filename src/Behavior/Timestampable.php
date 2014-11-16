<?php
namespace Eyf\Modelify\Behavior;

interface Timestampable
{
    const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function setCreatedAt(\DateTime $datetime);
    public function setUpdatedAt(\DateTime $datetime);
}