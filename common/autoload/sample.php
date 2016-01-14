#!/usr/bin/php
<?php

//  configuration
ini_set('date.timezone', 'America/Los_Angeles');
$path = __DIR__;

//  do you have an amazon aws config file?
require $path . '/aws-config.php';

//  this is our base config
$config = (object)array(
	'autoloadPath' => $path,  //  path to the autoload folder
	'autoloadClasses' => array('utilities', 'transfer', 'metadata', 'cli'),  //  array of classes to be included in this program
	'version' => '0.1-pre',  //  program version
	'releaseDate' => '2015-02-20 9:10PM CST',  //  program release date
	'timestamp' => date("Ymd-hia"),  //  current datestamp
	'runtimeID' => substr(hash('sha256', microtime(true)), 59, 64),  //  generate a runtime id
	'logPath' => $path . '/logs',  //  path to store log iles
	'archivePath' => $path . '/archive',  //  path to store archives (if any)
	'metadataFile' => $path . '/metadata.json',  //  path to the local metadata file
	's3FolderSearch' => array(  //  list of filename prefixes which get separated into their own folders when transmitting
		'FilenamePrefix1',
		'FilenamePrefix2'
	),
	'awsConfig' => $awsConfig,  //  the aws config object included above
	'chunkSize' => 100*1000*1000,  //  the size, in bytes, of the aws multipart upload chunk size
	'singleExecArray' => array()  //  an array containing all singleExec commands
);

//  startup
require $path . '/autoloader.php';
//require $path . '/../aws-sdk-php/aws-autoloader.php';
//use Aws\Common\Exception\MultipartUploadException;
//use Aws\S3\Model\MultipartUpload\UploadBuilder;
//use Aws\S3\S3Client;
$jAutoloader = new jAutoloader('MyClass', $config->autoloadClasses, $config);
$jAutoloader->runProgram();

class MyClass {

	var $parent;
	var $s3Client;
	var $flags;
	var $config;
	var $singleExec;
	var $metadata;
	var $uploadTimer = array();
	
	//  required for autoloader
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	function __construct(&$parent, &$config) {
	
		//  prepare
		$this->parent =& $parent;
		$this->config =& $config;
		
	}
	
	function runProgram() {
	
		//  populate some required vars
		$this->metadata = $this->readMetadata();
		//$this->s3Client = S3Client::factory($this->config->awsConfig->credentials);
		$this->flags = $this->parse_flags();

		//  check and set default values
		if ( isset($this->flags['help']) == false ) $this->flags['help'] = false;
		if ( isset($this->flags['process']) == false ) $this->flags['process'] = false;
		if ( isset($this->flags['logrotate']) == false ) $this->flags['logrotate'] = false;
		if ( isset($this->flags['verbose']) == false ) $this->flags['verbose'] = false;
		if ( isset($this->flags['help']) == false ) $this->flags['help'] = false;
		if ( isset($this->flags['clear']) == false ) $this->flags['clear'] = false;
		if ( isset($this->flags['force']) == false ) $this->flags['force'] = false;
		if ( isset($this->flags['dry']) == false ) $this->flags['dry'] = false;
		if ( isset($this->flags['debug']) == false ) $this->flags['debug'] = 0;
		if ( isset($this->flags['read']) == false ) $this->flags['read'] = false;
		if ( isset($this->flags['key']) == false ) $this->flags['key'] = false;
		if ( isset($this->flags['set']) == false ) $this->flags['set'] = false;
		if ( isset($this->flags['value']) == false ) $this->flags['value'] = false;
		if ( isset($this->flags['log-path']) == false ) $this->flags['log-path'] = "{$this->config->logPath}/cli.log";
		if ( isset($this->flags['chunkSize']) == false ) $this->flags['chunkSize'] = ( isset($this->config->chunkSize) ) ? $this->config->chunkSize : 5*1000*1000;
		if ( isset($this->flags['concurrency']) == false ) $this->flags['concurrency'] = 5;
		if ( isset($this->flags['destination']) == false ) $this->flags['destination'] = 's3';
		if ( isset($this->flags['objectName']) == false ) $this->flags['objectName'] = false;
		if ( isset($this->flags['objectPath']) == false ) $this->flags['objectPath'] = false;
		if ( isset($this->flags['metaclean']) == false ) $this->flags['metaclean'] = false;
		if ( isset($this->flags['delayShutdown']) == false ) $this->flags['delayShutdown'] = 0;
		
		//  conditional settings
		$this->flags['aux'] = "{$this->config->logPath}/{$this->config->runtimeID}-{$this->config->timestamp}.log";
		if ( $this->flags['dry'] === true ) $this->flags['verbose'] = true;
		
		//  print usage help if requested
		if ( $this->flags['help'] === true ) $this->usage();
	
		//  run singleExec to begin program execution
		$this->singleExec($this->config->singleExecArray);
	
	}
	
	function usage($extra=false) {
	
		$errText = '';
		if ( $extra !== false ) if ( is_string($extra) ) $errText = "Error: {$extra}\n\n";
		
		die ("{$errText}MyProgram {$this->config->version}
Release date {$this->config->releaseDate}

Usage: {$_SERVER['_']} ...
		
");
		
	}
	
	//  rest of the program methods go here
	
}

?>
