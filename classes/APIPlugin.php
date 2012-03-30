<?php

interface APIPlugin
{
    function describeRoutes();
    function routes(&$router);
}
