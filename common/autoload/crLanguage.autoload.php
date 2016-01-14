<?php

class crLanguage {
	
	//  necessary for autoloader
	var $parent;
	function __construct(&$parent) { $this->parent =& $parent; }
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }

	function crLanguage($group, $name, $d) {
	
		//  process $d
		if ( is_string($d) ) $d = array($d);
		if ( !is_array($d) ) {
		
			//$this->webLog($this->crLanguage('crLanguage', 'dIsInvalid'), 'warn');
			$d = array();
			
		}
		
		$lang = array(
			'webDebugTools' => array(
				'defaultKeyAdd' => "{$d[0]} startup: Key '{$d[1]}' has been loaded from the default values list"
			),
			'crLanguage' => array(
				'dIsInvalid' => "crLanguage Internal Error: Argument 3 is supposed to be a string or an array. Got type: " . gettype($d)
			)
		);
		
	}

}

?>