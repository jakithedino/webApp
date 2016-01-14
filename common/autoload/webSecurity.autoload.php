<?php

class webSecurity {
	
	//  necessary for autoloader
	var $parent;
	function __construct(&$parent) { $this->parent =& $parent; }
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	//  run website security procedures
	function webSecurityRun($run=false) {
		
		//  the _REQUEST var is useless to us so let's just remove it
		//  we rely on _GET and _POST
		unset($_REQUEST);
		
		//  sanitize the ip, yeah?
		if ( !preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $this->config->clientIp) ) {
		
			//  uh oh
			$this->webLog("Client IP Address is invalid! Supplied address was: {$this->config->clientIp}", __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		}
		
		//  our list of potential security procedures
		$checkList = array('ipWhitelist', 'ipBlacklist', 'requestRouting');
		
		//  determine which checks to be executed
		if ( $run === false ) $runList = $checkList;
		if ( is_string($run) ) {
		
			//  if the specific request is not in the master list we throw an error and run all security procedures
			if ( !in_array($run, $checkList) ) {
				
				$run = addslashes($run);
				$this->webLog("At runtime " . __METHOD__ . " encountered an invalid \$run argument of '{$run}'", "fatal");
				$this->shutdown("A fatal error has occurred and the program has been terminated. Check the debug log for more information.");
				
			} else $runList = array($run);
				
		}
		
		//  execute each item in the run list in order
		foreach ( $runList as $securityTask ) {
		
			//  check if the task exists
			$taskName = "securityTask{$securityTask}";
			if ( method_exists($this, $taskName) ) {
				
				//  run the task and check for false responses
				if ( $this->{$taskName}() === 'error' ) {
				
					$this->webLog("Security exception detected in " . __CLASS__ . "::{$taskName}", __METHOD__, 'fatal');
					$this->shutdown("An access violation has occurred and the request has been terminated.");
						
				}
				
			} else {
			
				//  this is a non-fatal error but should be warned
				$this->webLog("Unknown securityTask '{$taskName}' was requested", __METHOD__, "warn");
				
			}
			
		}
		
	}
	
	//  whitelist routing
	function securityTaskipWhitelist() {
	
		return $this->securityTaskCheckLists('whitelist');
		
	}
	
	//  blacklist routing
	function securityTaskipBlacklist() {
	
		return $this->securityTaskCheckLists('blacklist');
		
	}
	
	//  check if the clientIp is on the whitelist
	function securityTaskCheckLists($list) {
		
		//  check if the security config exists
		//$this->webLog("Performing {$list} scan", __METHOD__);
		
		//  check if the ipWhitelist config setting is present
		$uList = ucfirst($list);
		$eList = "enforce{$uList}";
		if ( !isset($this->config->security->settings->{$eList}) ) {

			//  we do not provide a default value and it must be explicitly set
			$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::settings::{$eList}"), __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		} else {
				
			//  check if the type is correct
			if ( !is_bool($this->config->security->settings->{$eList}) ) {
			
				$this->webLog($this->crLanguage('generic', 'missingConfigKeyType', "config::security::settings::{$eList}"), __METHOD__, 'fatal');
				$this->shutdown($this->crLanguage('generic', 'startupError'));
				
			}
			
			//  check if we're enforcing the ipWhitelist
			if ( $this->config->security->settings->{$eList} === true ) {
				
				//  check if the specific lists exist
				if ( !isset($this->config->security->ip) ) {
		
					$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::ip"), __METHOD__, 'fatal');	
					$this->shutdown($this->crLanguage('generic', 'startupError'));
					
				} else {
				
					//  validate the type of the object
					if ( !is_object($this->config->security->ip) ) {
						
						$this->webLog($this->crLanguage('generic', 'missingConfigKeyType', "config::security::ip"), __METHOD__, 'fatal');
						$this->shutdown($this->crLanguage('generic', 'startupError'));
						
					} else {
							
						//  check if the specific security list exists
						if ( !isset($this->config->security->ip->{$list}) ) {
							
							$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::ip::{$list}"), __METHOD__, 'fatal');
							$this->shutdown($this->crLanguage('generic', 'startupError'));
							
						}
			
					}
					
				}
				
				//  check if the key exists
				if ( !isset($this->config->security->ip->{$list}) ) {
				
					$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::ip::{$list}"), __METHOD__, 'fatal');
					$this->shutown($this->crLanguage('generic', 'startupError'));
					
				} else {
				
					//  and check if the key is the right data type
					if ( !is_array($this->config->security->ip->{$list}) ) {
					
						$this->webLog($this->crLanguage('generic', 'missingConfigKeyType', "config::security::ip::{$list}"), __METHOD__, 'fatal');
						$this->shutdown($this->crLanguage('generic', 'startupError'));
						
					} else {
					
						//  check if the clientIp exists in this list
						$secuResult = in_array($this->config->clientIp, $this->config->security->ip->{$list});
						if ( ($list == 'whitelist' && !$secuResult) || ($list == 'blacklist' && $secuResult) ) {
							
							$this->webLog("Client IP address fails the ip{$uList}", __METHOD__, 'warn');
							return 'error';
							
						} else {
						
							//  everything checks out
							$this->webLog("Client IP address passes the ip{$uList}", __METHOD__);
							return true;
							
						}
						
					}
						
				}
				
			} else {
			
				$this->webLog("Skipping disabled securityTask ip{$uList}", __METHOD__);
				return false;
						
			}

		}
		
	}
	
	//  determine the proper routing for this request
	function securityTaskrequestRouting() {
		
		$this->webLog("Determining request routing information", __METHOD__);
		
		//  check that the routing table exists
		if ( !isset($this->config->security->routing) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::routing"), __METHOD__, 'fatal');
			$this->shutown($this->crLanguage('generic', 'startupError'));
			
		} else {
		
			//  check that the routing table is valid
			if ( !is_object($this->config->security->routing) ) {
			
				$this->webLog($this->crLanguage('generic', 'missingConfigKeyType', "config::security::ip::{$list}"), __METHOD__, 'fatal');
				$this->shutown($this->crLanguage('generic', 'startupError'));
			
			//  okay time to analyze	
			} else {
			
				//  no profile data should exist yet
				if ( isset($this->config->profile) ) {
				
					$this->webLog("Profile key already exists for config::profile; please remove it", __METHOD__, 'fatal');
					$this->shutdown($this->crLanguage('generic', 'startupError'));
					
				} else {
				
					//  basic profile object
					$this->config->profile = (object)array(
						'userid' => false,  //  userid?
						'username' => false, //  username?
						'name' => false, //  real name
						'email' => false, //  user email
						'group' => false,  //  user group
						'path' => false,  //  request path
						'area' => false,  //  request area
						'do' => false,  //  request action
						'internal' => false,  //  set for internal pages
						'customPerms' => false  //  custom permissions
					);
					
					//  a default group should exist
					if ( !isset($this->config->security->settings->defaultGroup) ) {
						
						$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::settings::defaultGroup"), __METHOD__, 'fatal');
						$this->shutdown($this->crLanguage('generic', 'startupError'));
						
					}
					
					//  get the user's login group
					$group = false;
					if ( $loginObject = $this->checkLoginStatus() ) {
						
						//  verify that the clientIp matches the IP in the loginObject
						if ( $group === false ) if ( $this->config->clientIp != $loginObject->i ) {
							
							$group = 'error';
							$this->webLog("IP Address provided via loginObject does not match ClientIP ({$loginObject->i} != {$this->config->clientIp})", __METHOD__, "error");
							
						}
						
						//  sanitize the supplied userid
						if ( $group === false ) if ( !preg_match($this->config->security->regex->general->alphanumeric, $loginObject->u) ) {
						
							$group = 'error';
							$this->webLog("UserID provided via loginObject is not alphanumeric", __METHOD__, "error");
							
						}
						
						//  grab profile data with the supplied userid
						if ( $group === false ) {
							
							if ( $result = $this->mysql_query("SELECT `group`, username, name, email, customPerms FROM users WHERE userid='{$loginObject->u}' LIMIT 1", array('direct' => true)) ) {
								
								$data = $result->fetch_object();
								$group = $data->group;
								$this->config->profile->userid = $loginObject->u;
								$this->config->profile->username = $data->username;
								$this->config->profile->name = $data->name;
								$this->config->profile->email = $data->email;
								$this->config->profile->customPerms = ( $object = json_decode($data->customPerms) ) ? $object : (object)array();
								
							} else {
							
								//  the userid doesn't exist
								$group = 'error';
								$this->webLog("UserID provided via loginObject was not located in the database", __METHOD__, "error");
								
							}

						}
						
					}
					
					$this->config->profile->group = ( $group === false ) ?
						$this->config->security->settings->defaultGroup :
						$group;
					$group = $this->config->profile->group;
					
					$this->webLog("User group is '{$group}'", __METHOD__);
					header("X-CR-Group: {$group}");
						
					//  build an acceptable paths object
					$this->config->profile->routable = $this->buildRoutingTable((object)array('paths' => array()), $group);
					
					//  include inherited permissions
					if ( is_array($this->config->security->routing->groups->{$group}->inherits) ) {
					
						foreach ( $this->config->security->routing->groups->{$group}->inherits as $sGroup ) 
							$this->config->profile->routable = $this->buildRoutingTable($this->config->profile->routable, $sGroup);
						
					}
					
					$this->webLog("Routing table built with " . count($this->config->profile->routable->paths) . " paths", __METHOD__);
						
					//  process request parameters
					$this->config->profile->path = $this->getGetData('path');
					$this->config->profile->area = $this->getGetData('area');
					$this->config->profile->do = $this->getGetData('do');
					
					//  validate that the user is allowed to access this path
					if ( $this->config->profile->path === false ) $this->config->profile->path = $this->config->security->routing->groups->{$group}->defaultPath;
					if ( !in_array($this->config->profile->path, $this->config->profile->routable->paths) ) {
						
						//  generate our error text
						$this->config->profile->error = $this->crLanguage(__CLASS__, 'accessViolation', $this->config->profile->path);
						$this->webLog("Access violation for unauthorized request - '" . json_encode($_GET) . "'", __METHOD__, 'warn');
						
						//  log any attempted post data
						if ( count($_POST) > 0 ) {
						
							$this->webLog("Deleting supplied POST data: " . json_encode($_POST), __METHOD__, 'warn');
							unset($_POST);
							
						}
						
						$this->webLog("Sending user to defaultPath - '{$this->config->security->routing->groups->{$group}->defaultPath}'", __METHOD__, 'warn');
						
						//  reset our request data
						$this->config->profile->path = $this->config->security->routing->groups->{$group}->defaultPath;
						$this->config->profile->area = false;
						$this->config->profile->do = false;
				
					}
					
				}
				
			}
			
		}
		
	}
	
	//  return an updated object with additional routing information
	function buildRoutingTable($object, $group) {
	
		//  check if the group exists
		if ( !isset($this->config->security->routing->groups->{$group}) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingConfigKey', "config::security::routing::groups::{$group}"), __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		}
		
		//  add the routing data for this group
		foreach ( $this->config->security->routing->groups->{$group}->paths as $gPath ) $object->paths[] = $gPath;
		
		//  return the object
		return $object;
		
	}
	
	function checkLoginStatus() {
	
		//  check if the login cookie exists
		if ( $cookie = $this->crCryptoGetCookie('login') ) {
		
			if ( $object = json_decode($cookie) ) {
				
				//  loop through the provided keys and check if they were expected
				$keys = array('u', 'i');
				foreach ( $object as $key => $value ) {
				
					//  does this key exist in the expected list
					if ( !in_array($key, $keys) ) {
					
						//  this is suspicious
						$this->webLog("Login cookie contained suspicious data!", __METHOD__, "error");
						$this->webLog("Supplied data was: " . $cookie, __METHOD__, "error");
						$this->shutdown($this->crLanguage('generic', 'startupError'));
						
					}
					
				}
				
				//  this is a valid object
				return $object;
				
			} else {
			
				return false;
				
			}
			
		}

		return false;
		
	}
	
	//  sanitize the input
	function sanitizeInput($input, $options) {
	
		//  get our options
		$types = array("regex");
		if ( is_array($options) ) $options = (object)$options;
		if ( !is_object($options) ) {
		
			$this->webLog($this->webLog($this->crLanguage('generic', 'missingKeyType', "options"), __METHOD__, 'error'));
			return false;
			
		}
		
		//  check if the type was provided
		$x = 0;
		foreach ( $types as $type ) if ( isset($options->{$type}) ) $x++;
		if ( $x === 0 ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKey', "one of options::(" . implode('|', $types) . ")"), __METHOD__, 'error');
			return false;
			
		} else if ( $x > 1 ) {
			
			$this->webLog($this->crLanguage('generic', 'tooManyKeys', array(1, $x, "options::(" . implode('|', $types) . ")")), __METHOD__, 'error');
			return false;
			
		}
		
		//  has regex been provided?
		if ( isset($options->regex) ) {
			
			//  is it the right type
			if ( is_string($options->regex) ) {
			
				//  make sure this is correct too
				if ( isset($options->regexOptions) ) $options->regexOptions = '';
				if ( !is_string($options->regexOptions) ) {
				
					$this->webLog($this->crLanguage('generic', 'missingKeyType', "options::regexOptions"), __METHOD__, 'error');
					return false;
					
				} else {
					
					//  execute the regex
					if ( preg_match("/{$options->regex}/{$options->regexOptions}", $input, $matches) ) {
				
							//  supplied and of the right type?
							if ( isset($options->regexMatches) ) {
								
								if ( is_bool($options->regexMatches) ) {
							
									//  send the matches back
									if ( $options->regexMatches === true ) {
									
										$this->webLog("Sent '" . count($matches) . "' regex matches back on successful sanitization", __METHOD__);
										return $matches;
										
									}
									
								} else {
									
									$this->webLog($this->crLanguage('generic', 'missingKeyType', "options::regexMatches"), __METHOD__, 'error');
									return false;
									
								}
							
							}
							
							//  we've made is this far? then just return the input
							return $input;
						
					} else {
					
						$this->webLog("Sanitization failed on regex pattern /{$options->regex}/", __METHOD__, 'warn');
						return false;
						
					}

				}
				
			} else {
			
				$this->webLog($this->crLanguage('generic', 'missingKeyType', "options::regex"), __METHOD__, 'error');
				return false;
				
			}
		
		//  no known sanity type was requested!
		}
		
	}
	
}

?>