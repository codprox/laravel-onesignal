<?php

namespace CodproX\OneSignal\Tests;

use PHPUnit\Framework\TestCase;
use CodproX\OneSignal\MyOneSignal;

class MyOneSignalTest extends TestCase
{
    public function testNormalizeSegmentName()
    {
        $oneSignal = new MyOneSignal('test_app', 'test_key', 'test_icon');
        $this->assertEquals('etudiants', $oneSignal->normalizeSegmentName('UsersTests'));
    }
}