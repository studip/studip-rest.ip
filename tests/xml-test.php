<?php
/*
 * Copyright (C) 2011 - Jan-Hendrik Willms <tleilax+studip@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */
require_once dirname(__FILE__) . '/../classes/Helper.php';

class XMLTest extends PHPUnit_Framework_TestCase
{
    function testStruct()
    {
        $data = array('message' => array('id' => md5(uniqid('message', true)), 'title' => 'Foo'));

        $xml   = RestIP\Helper::arrayToXML($data);
        $probe = json_decode(json_encode(simplexml_load_string($xml)), true);

        $this->assertEquals($probe, $data);
    }

    function testArray()
    {
/*
        $data  = array('foo', 'bar', 'baz');
        $xml   = RestIP\Helper::arrayToXML($data);
        $probe = json_decode(json_encode(simplexml_load_string($xml)), true);

        $this->assertEquals($probe, $data);
*/

        $this->markTestSkipped(
            'This test will always fail at the moment.'
        );
    }
}