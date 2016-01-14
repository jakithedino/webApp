<?php

//  This script is taken from the jAutoload program written by Jaki
//  It will create a unified class containing all the pieces necessary to
//  run this program

//  include our config and base program
$path = __DIR__;
require $path . "/includes/config.php";
require $path . "/includes/JakiBlue.php";

//  suppose this script needs more than the base config offers
//$config->autoloadClasses[] = 'newClass1';
//$config->autoloadClasses[] = 'newClass2';

//  load web application
require $path . '/common/autoload/autoloader.php';
$cr = new jAutoloader('JakiBlue', $config->autoloadClasses, $config);

//  execute the program		
$cr->runStartup();

//  run request routing
//print $cr->status();

?>
