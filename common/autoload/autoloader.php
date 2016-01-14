<?php

class jAutoloader {

	var $parentClass;
	var $table = array('classes' => array(), 'index' => array());
	var $children;
	var $debugTools;
	var $crLang;
	var $shutdownCommands = array();
	var $timer;
	function __construct($parentClass, $libs, $config) {
		
		//  startup bits
		$this->timer = (object)array('start' => microtime(true));
		$this->debugTools = (object)array(
			'init' => false,
			'startup' => array(),
			'log' => array()
		);
		
		$this->crLang = (object)array(
			'init' => false
		);
		
		$this->debugTools->startup[] = array('Autoloader is beginning execution', __METHOD__, 'debug', microtime(true));
		if ( !is_array($libs) ) $libs = array();
		if ( !class_exists($parentClass) ) {
			
			$this->debugTools->startup[] = array("The parent class '{$parentClass}' was not found! Aborting.", __METHOD__, 'debug', microtime(true));
			$this->shutdown();
			
		}
		
		//  initialize the new parent
		$this->children = new stdClass;

		//  load in all children classes
		foreach ( $libs as $childClass ) {
		
			$this->debugTools->startup[] = array("Loading childClass '{$childClass}'", __METHOD__, 'debug', microtime(true));
			require $config->autoloadPath . "/{$childClass}.autoload.php";
			$this->children->{$childClass} = new $childClass($this);
			
		}
		
		//  scan all loaded classes and build a methods and property table
		foreach ( $this->children as $class => $data ) {
		
			$this->table['classes'][$class] = array(
				'properties' => array(),
				'methods' => array()
			);
			
			//  create a reflection of the class
			$ref = new ReflectionClass($class);
			
			//  get its methods
			$params = $ref->getMethods();
			foreach ( $params as $data ) if ( !preg_match("/(__construct)/", $data->name) ) $this->table['classes'][$class]['methods'][] = $data->name;
			
			//  get its properties
			$props = $ref->getProperties();
			foreach ( $props as $data ) if ( !preg_match("/(parent|configImport|self)/", $data->name) )  $this->table['classes'][$class]['properties'][] = $data->name;
			
			//  build our index
			$this->table['index'] = array();
			foreach ( $this->table['classes'] as $class => $data ) {
				
				if ( count($data['methods']) > 0 ) foreach ( $data['methods'] as $method ) $this->table['index'][$method] = $class;
				if ( count($data['properties']) > 0 ) foreach ( $data['properties'] as $property ) $this->table['index'][$property] = $class;
				
			}
			
		}
		
		//  initiate the parent class
		$this->parentClass = new $parentClass($this, $config);
		
		//  now load childClass defaults
		foreach ( $libs as $childClass ) {
			
			//  import configuration keys from the child class
			if ( isset($this->children->{$childClass}->configImport) ) {
			 
				$this->debugTools->startup[] = array("childClass '{$childClass}' is loading default configuration data", __METHOD__, 'debug', microtime(true));
				$this->setDefaultConfig($this->children->{$childClass}->configImport);
				
			}
					
		}
		
		//  check if we need to determine the client IP
		if ( $this->config->clientIp == 'auto' ) $this->determineClientIP();
			
		//  finish up
		$this->webLog("Program is initialized", __METHOD__);	
		
		//  if debugging is turned on then log headers
		if ( $this->config->debugLogging['enabled'] === true ) if ( $this->config->debugLogging['httpHeaders'] === true ) $this->webLogHeaders();
		
	}
	
	//  these magic methods allow us to orchestrate the internal routing of each class's $this
	function __call($method, $args) {
		
		if ( isset($this->table['index'][$method]) ) {
			
			//  this method exists within a child
			return call_user_func_array(array($this->children->{$this->table['index'][$method]}, $method), $args);
			
		} else {
			
			//  check if the method exists in the parent
			$result = NULL;
			( method_exists($this->parentClass, $method) ) ?
			
				//  method found
				$result = call_user_func_array(array($this->parentClass, $method), $args) :
				
				//  cannot find it, and we don't allow subclass access to our methods
				$this->autousage("Invalid method {$method} was requested");
				
			return $result;
		}
		
	}
	
	function __get($name) { 
		
		//  does the requested property exist in our index
		$result = ( isset($this->table['index'][$name]) ) ?
		
			//  the requested property is located in an index
			$this->children->{$this->table['index'][$name]}->{$name} :
			
			//  check if the property is located on the parentClass
			( isset($this->parentClass->{$name}) ) ? 
				$this->parentClass->{$name} :
				$this->autousage("Invalid property was requested");
			
		return $result;
	
	}
	
	function __isset($name) {
	
		if ( isset($this->table['index'][$name]) ) { return true; } 
		else ( isset($this->parentClass->{$name}) ) ?
				true :
				isset($this->{$name});
				
	}
	
	function __set($name, $value) {
	
		//  look up properties in children
		( isset($this->table['index'][$name]) ) ?
			$this->children->{$this->table['index'][$name]} = $value :
			( isset($this->parentClass->{$name}) ) ?
				$this->parentClass->{$name} = $value :
				$this->{$name} = $value;
	
	}
	
	function autousage($errText) {
	
		print $errText . "\n";
		die;
		
	}
	
	//  provide default configuration settings if none are provided
	function setDefaultConfig($default) {
		
		//  loop thru the list of default keys
		foreach ( $default as $key => $data ) {
		
			//  check if a key of the same name was already provided
			if ( property_exists($this->config, $key) ) {
		
				if ( gettype($this->config->{$key}) === gettype($data) ) {
					
					//  set the key
					$this->config->{$key} = $data;
					$this->debugTools->startup[] = array($this->crLanguage('webDebugTools', 'defaultKeyAdd', $key), __METHOD__, 'debug', microtime(true));
					
				} else {
				
					
					
				}
				
			} else {
			
				//  set the key
				$this->config->{$key} = $data;
				$this->debugTools->startup[] = array("Key '{$key}' has been loaded from the default values list", __METHOD__, 'debug', microtime(true));
				
			}
			
		}
		
	}
	
	function mergeDefaultValues($input, $defaults, $responseType='object') {
	
		//  must be a valid response type requested
		if ( !in_array($responseType, array('object', 'array')) ) {
			
			$this->webLog($this->crLanguage('generic', 'missingKeyType', 'responseType'), __METHOD__, 'error');
			return $input;
			
		}
		
		//  the supplied defaults must be an array
		if ( !is_array($defaults) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKeyType', 'defaults'), __METHOD__, 'error');
			return $input;
			
		}
				
		//  validate the input type
		if ( !is_array($input) && !is_object($input) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKeyType', 'input'), __METHOD__, 'error');
			return $input;
			
		} else {
				
			//  loop thru the default list and check if it exists
			$count = 0;
			foreach ( $defaults as $key => $value ) {
				
				//  we know for a fact it is one or the other
				if ( is_array($input) ) {
					
					if ( !isset($input[$key]) ) {
						
						$count++;
						$input[$key] = $value;
						
					}
					
				} else {
					
					if ( !isset($input->{$key}) ) {
						
						$count++;
						$input->{$key} = $value;
						
					}
					
				}
				
			}
							
			//  finish up
			$this->webLog("Loaded {$count} values into input", __METHOD__);
			return ( $responseType === 'object' ) ? (object)$input : (array)$input;

		}
		
	}
	
	//  determine the client IP address
	function determineClientIP() {
	
		if ( isset($_SERVER['HTTP_CF_CONNECTING_IP']) ) {
			
			$this->config->clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'];
			$this->config->clientIpSource = 'CF';
		
		} else {
			
			if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
				
				$this->config->clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
				$this->config->clientIpSource = 'Proxy';
				
			} else {
				
				$this->config->clientIp = $_SERVER['REMOTE_ADDR'];
				$this->config->clientIpSource = 'Direct';
			
			}
			
		}
		
		$this->webLog("Running IP Address auto detection - {$this->config->clientIp} via {$this->config->clientIpSource}", __METHOD__, 'info');
		
	}
	
	function shutdown($message=false) {
	
		//  loop thru each command
		foreach ( $this->shutdownCommands as $array ) {
			
			//  execute the command
			if ( call_user_func_array($array[0], $array[1]) ) {
			
				$this->webLog("Successfully ran shutdown command: " . json_encode($array[0]) . " with args " . json_encode($array[1]), __METHOD__);
				
			} else {
				
				//  non-fatal error
				$this->webLog("Command failure: " . json_encode($array[0]) . " with args " . json_encode($array[1]), __METHOD__, 'error');
				
			}
			
		}
	
		//  finish up
		$this->timer->stop = microtime(true);
		$this->timer->total = number_format($this->timer->stop-$this->timer->start, 6);
		$this->webLog("Execution time: {$this->timer->total} seconds", __METHOD__	);
		if ( $message !== false ) print $message;
		if ( $message === false ) $message = "Execution finished successfully.";
		$this->webLog("Shutting down with message: {$message}", __METHOD__);
		die;
		
	}
	
	//  add a command to the shutdown sequence
	//  >> THIS DOES NOT WORK << >> FIX IT FIX IT FIX IT <<
	function addShutdownCommand($array, $params=false) {

		//  check if this is a valid callback	
		if ( is_callable($array) ) {
			
			$this->webLog("Registered shutdown function '" . get_class($array[0]) . "'", __METHOD__);
			$this->shutdownCommands[] = array($array, $params);
			return true;

		} else {
		
			$this->webLog("Invalid callback provided '{$array[0]}'", __METHOD__, 'error');
			return false;
			
		}
		
	}
	
}

?>