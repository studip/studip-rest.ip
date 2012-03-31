<?php

/**
 *
 **/
interface APIPlugin
{
    public function describeRoutes();
    public function routes(&$router);
}
