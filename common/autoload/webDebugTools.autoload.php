<?php

class webDebugTools {
	
	//  necessary for autoloader
	var $parent;
	var $defaultConfig;
	var $configImport = array(
		'debugLogging' => array(
			'enabled' => true,
			'httpHeaders' => false,
			'keys' => array('_SERVER', '_GET', '_POST'),
			'minimumLevelToWrite' => 'debug',  //  when writing to a file, this is the minimum severity logged
			'levels' => array(
				'debug', 'info', 'notice', 'warn', 'error', 'fatal'  //  this is in order of severity
			)
		)
	);
		
	function __construct(&$parent) { $this->parent =& $parent; }
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	function webLog($message, $loc=false, $level=false, $ts=false) {
		
		if ( $level === false ) $level = 'debug';
		if ( $loc === false ) $loc = "Global/Unknown";
		if ( !in_array($level, $this->config->debugLogging['levels']) ) $level = 'progerror';
		if ( is_string($ts) ) { 
		
			$loc .= "/{$ts}";
			$ts = false;
			
		}
		
		//  process message queue from before initialization
		if ( count($this->debugTools->startup) > 0 && $this->debugTools->init === false ) {
		
			$this->debugTools->init = true;
			$this->debugTools->startup[] = array("Processed init messages into the log file", __METHOD__, 'debug', microtime(true));
			foreach ( $this->debugTools->startup as $data ) $this->webLog($data[0], $data[1], $data[2], $data[3]);
			$this->debugTools->startup = array();
			
		}
		
		//  determine the time of this message
		$time = ( $ts === false || !is_numeric($ts) ) ? microtime(true) : $ts;

		//  generate our log object
		$this->debugTools->log[$this->config->runtimeID][] = (object)array(
			'ts' => $time,
			'l' => $level,
			'ip' => $this->config->clientIp,
			's' => $this->config->clientIpSource,
			'message' => $message
		);
		
		//  write the log file if necessary
		if ( 
			array_search($level, $this->config->debugLogging['levels']) 
		>= 
			array_search($this->config->debugLogging['minimumLevelToWrite'], $this->config->debugLogging['levels']) 
		) {
				
			// [{$this->config->clientIp}/{$this->config->clientIpSource}]
			$buffer = '';
			for ( $x = 0; $x < (6-strlen($level)); $x++ ) $buffer .= ' ';
			@error_log(
				"[{$this->config->runtimeID}] [" . date("Y/m/d h:i:s A", $time) . "] [{$level}]{$buffer} [{$loc}] {$message}\n",
				3,
				$this->config->logPath
			);
			
		}
		
		return true;
		
	}
	
	
	function webLogHeaders() {
	
		$keys = $this->config->debugLogging['keys'];
		foreach ( $keys as $key ) foreach ( $GLOBALS[$key] as $sKey => $data ) $this->webLog("{$sKey} => " . trim(chop($data)), __METHOD__);
		
	}
	
}

?>