<?php

use App\Service\MultiDump;

if (!function_exists('mdump')) {
   
    function mdump($var, $section = 'secondary', $customTitle = null)
    {
        MultiDump::dump($var, $section, $customTitle);
        return $var;
    }
}