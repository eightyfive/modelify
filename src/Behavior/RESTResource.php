<?php
namespace Eyf\Modelify\Behavior;

interface RESTResource
{
    public function getId();
    public function getName();
    public function getResourceSlug();
}