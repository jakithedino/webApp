<?php

//  the main parent class for the autoloader
class JakiBlue {

	//  do not change any of the following unless you're familiar with what you are doing!
	var $parent;
	var $s3Client;
	var $flags;
	var $config;
	var $singleExec;
	var $metadata;
	var $uploadTimer = array();
	var $routing;
	var $db;
	
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
		$this->routing = (object)array('path', 'do', 'what');
		
	}
	
	////////*****************////////
	/////// Begin User Section //////
	////////*****************////////
	//  user program below this point
	
	//  status output
	function status() {
	
		return __CLASS__ . " v{$this->config->version} / {$this->config->timestamp}";
		
	}
	
	//  this command will initiate index startup and routing
	function runStartup($engine='webApp') {
		
		//  set our basic headers
		//  this header will be provided only to non-guests: header("X-CR-Program: {$this->config->program}/v{$this->config->version}");
		header("X-CR-RuntimeID: {$this->config->runtimeID}");
		header("X-CR-Engine: {$engine}");
		
		//  first and foremost we run security checks
		$this->webLog("Engine '{$engine}' beginning startup procedures", __METHOD__);
		
		if ( $engine === 'webApp' ) {
				
			//  initiate mysql
			$this->mysql_init();
			
			//  load encryption keys
			$this->crLoadEncKeys();
				
			//  run security checks
			$this->webSecurityRun();
			
			//  next we load the template which builds our base HTML
			$this->templateLoad();
			
			//  if there was an accessViolation we will put the error into the error handler
			if ( isset($this->config->profile->error) ) $this->templateModify('error1', $this->config->profile->error);
			
			//  now we would run page processing for the request
			if ( !include($this->config->path . "/templates/paths/{$this->config->profile->path}.php") ) {
			
				$this->webLog("Failed to load path file - '{$this->config->path}/templates/paths/{$this->config->profile->path}.php'", __METHOD__, 'fatal');
				$this->shutdown($this->crLanguage('generic', 'startupError'));
				
			}
			
			//  build our title bit
			if ( isset($this->config->profile->loadTitle) ) if ( $this->config->profile->loadTitle === true ) $this->templateModify('title', $this->config->title . $this->config->titleSpacer, 'prefix');
			if ( !isset($this->config->profile->loadTitle) ) $this->templateModify('title', $this->config->title, 'prefix');
			
			//  finally we build and display the page
			$this->templateBuild();
			
		} else {
		
			$this->webLog("Engine '{$engine}' is an invalid engine type", __METHOD__, "fatal");
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		}
		
		//  run shutdown
		$this->shutdown();
		
	}
	
	function getGetData($key) {
		
		//  check if the key exists in the request
		if ( !isset($_GET[$key]) ) return false;
		return $this->getRequestData($_GET[$key]);
		
	}
	
	function getPostData($key) {
		
		//  check if the key exists in the request
		if ( !isset($_POST[$key]) ) return false;
		return $this->getRequestData($_POST[$key]);
		
	}
	
	//  retrieve and sanitize input request data
	function getRequestData($key) {
	
		//  check if a regex pattern is defined for this key
		if ( isset($this->config->security->regex->http->{$key}) ) {
			
			//  regex is supplied, let's check it
			if ( !preg_match($this->config->security->regex->http->{$key}, $key) ) {
				
				$this->webLog("Request key '{$key}' failed its regex test with content: {$key}", __METHOD__, 'fatal');
				$this->shutdown($this->crLanguage('generic', 'startupError'));
				
			} else {
			
				return $key;
				
			}
			
		} else {
		
			//  a basic sanitization is in order
			return addslashes($key);
			
		}
		
	}
	
	function getCookie($name, $secure=false) {
	
		//  
		if ( isset($_COOKIE[$name]) ) {
		
			$this->webLog("Cookie found: {$name}", __METHOD__);
			return $_COOKIE[$name];
			
		}
		
		$this->webLog("Cookie not found: {$name}", __METHOD__);
		return false;
		
	}
	
	function setCookie($name, $value, $ttl=false, $secure=false) {
		
		$ttl = ( !is_numeric($ttl) ) ? $this->config->cookieTtl : $ttl;
		$sec = ( $secure === false ) ? "unencrypted" : "encrypted";
		$this->webLog("Setting {$sec} cookie '{$name}' with a payload of " . strlen($value) . " bytes and a TTL of '{$ttl}' seconds", __METHOD__);	
		return setcookie($name, $value, time()+$ttl, NULL, NULL, $secure, true);
		
	}
	
	//  generate a password
	function crGeneratePasswordHash($password, $salt) {
	
		$start = microtime(true);
		$this->webLog("Beginning password hash with a cost of {$this->config->passwordHashDefaults['cost']}", __METHOD__);
		if ( $hash = password_hash($this->crGenerateBasePasswordHash($password, $salt), PASSWORD_DEFAULT, $this->config->passwordHashDefaults) ) {
			
			$runTime = number_format(microtime(true)-$start, $this->config->precision);
			$this->webLog("Password hash generated in {$runTime} seconds", __METHOD__);
			return $hash;

		} else {
		
			$this->webLog("Password hash failed to generate!", __METHOD__, "error");
			return false;
			
		}
		
	}
	
	//  create a base hash for a supplie password and hash
	function crGenerateBasePasswordHash($password, $salt, $version=1) {
	
		if ( $version === 1 ) {
			
			$result = hash('sha512', hash('sha512', hash('whirlpool', $salt) . hash('sha512', $password) . hash('sha512', $salt)));
			
		} else {
		
			//  fatal error; the dev should be ashamed of themself
 			$this->webLog("Requested version '{$version}' is not valid", __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'runtimeError'));
			
		}
		
		return $result;
		
	}
	
	//  verify a password matches a hash
	function crVerifyPassword($hash, $password, $salt) {
	
		$start = microtime(true);
		$this->webLog("Testing password hash ...", __METHOD__);
		if ( password_verify($this->crGenerateBasePasswordHash($password, $salt), $hash) ) {
		
			$runTime = number_format(microtime(true)-$start, $this->config->precision);
			$this->webLog("Password hash verified in {$runTime} seconds", __METHOD__);
			return true;
			
		} else {
			
			$this->webLog("Password hash failed to verify!", __METHOD__, "warn");
			return false;
			
		}
		
	}
	
	//  add a new user to the database
	function crAddUser($username, $password, $group, $profile=false) {
	
		//  generate our base user profile
		if ( $profile === false ) $profile = array();
		if ( !is_object($profile) && !is_array($profile) ) {
		
			$this->webLog($this->crLanguage(__CLASS__, 'missingKeyType', "profile"), __METHOD__, 'error');
			return false;
			
		}
		$profile = $this->mergeDefaultValues($profile, $this->config->newUserDefaultProfile);
		
		//  check if the username already exists in the database
		$result = $this->mysql_query("SELECT id FROM `users` WHERE `username` = '{$username}' LIMIT 1", array('direct' => true));
		if ( $result->num_rows > 0 ) {
		
			$this->webLog("Cannot add user: User '{$username}' already exists!", __METHOD__, "error");
			return false;
		
		} 
		
		//  determine userId prefix
		$prefix = $this->crGetNewUserIdPrefix($username);
			
		//  generate user-specific profile data
		$profile->userid = $this->crGenerateUserId($username, $prefix);
		$profile->username = $username;
		$profile->salt = $this->crCryptoMakeString(16);
		$profile->passwordHash = $this->crGeneratePasswordHash($password, $profile->salt);
		$profile->group = $group;
		$profile->psk = $this->crCryptoMakeString(128);
		if ( $profile->group === false ) return false;
		
		//  build the sql query data
		$fieldList = '';
		$valueList = '';
		foreach ( $profile as $key => $value ) {
		
			$fieldList .= "`{$key}`, ";
			if ( is_string($value) || is_numeric($value) ) $valueList .= "'{$value}', ";
			if ( is_array($value) || is_object($value) ) $valueList .= "'" . json_encode($value) . "', ";
			
		}
		
		$fieldList = substr($fieldList, 0, strlen($fieldList)-2);
		$valueList = substr($valueList, 0, strlen($valueList)-2);
		
		//  insert the account into the database
		if ( $qid = $this->mysql_query("INSERT INTO `users` ({$fieldList}) VALUES ({$valueList})") ) {
			
			$profile->userTableId = $this->mysql_getInsertId($qid);
			
			//  clean the profile data before returning
			$profile->psk = '***';
			return $profile;

		} else {
		
			$this->webLog("Failed to add user '{$username}' to the database! Check DB logs.", __METHOD__, "error");
			return false;
			
		}
		
	}
	
	//  generate a userId from a username
	function crGenerateUserId($username, $prefix=false) {
		
		if ( $prefix === false ) $prefix = '';
		$unHash = hash('sha256', $username);
		return $prefix . substr($unHash, strlen($unHash)-6, 6);
		
	}
	
	//  generate a unique id for a new encryption key
	function crGenerateKeyId($userId, $keyName) {
		
		$hash = hash('sha256', $userId . $keyName . microtime(true));
		return substr($hash, strlen($hash)-8, 8);
		
	}
	
	//  determine the a unique prefix for a username
	function crGetNewUserIdPrefix($username) {
	
		$userid = $this->crGenerateUserId($username);
		return ( $query = $this->mysql_query("SELECT count(*) AS `count` FROM `users` WHERE `userid` LIKE '%{$userid}'") ) ?
			$this->mysql_returnResource($query)->num_rows :
			0;
		
	}
	
	//  provide the group id for the group name provided
	function crGetGroupIdByName($group) {
	
		if ( isset($this->config->security->routing->groups->{$group}) ) {
			
			return $this->config->security->routing->groups->{$group}->groupid;
			
		} else {
		
			$this->webLog("Invalid group name provided: {$group}", __METHOD__, 'error');
			return false;
			
		}
		
	}
	
	//  insert a loginHistory object
	function crLoginHistoryAdd($userId, $result, $username=false) {
	
		//  sanitize the inputs
		if ( !preg_match($this->config->security->regex->general->alphanumeric, $userId) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKeyType', "userId"), __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		}
		
		if ( !is_numeric($result) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKeyType', "result"), __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		}
		
		if ( $username === false ) {
			
			$username = 'unknown';
			
		} else {
			
			if ( !preg_match($this->config->security->regex->general->alphanumeric, $username) ) {
			
				$this->webLog($this->crLanguage('generic', 'missingKeyType', "userId"), __METHOD__, 'fatal');
				$this->shutdown($this->crLanguage('generic', 'startupError'));
				
			}

		}
		
		//  create our object
		$loginHistoryObject = array(
			'userid' => $userId,
			'timestamp' => time(),
			'username' => $username,
			'ipAddr' => $this->config->clientIp,
			'result' => $result
		);
		
		//  generate our sql
		$columnList = '';
		$valueList = '';
		foreach ( $loginHistoryObject as $key => $value ) {
		
			$columnList .= "`{$key}`, ";
			$valueList .= "'{$value}', ";
			
		}
		
		//  attempt to insert
		if ( $result = $this->mysql_query("INSERT INTO `loginHistory` (" . substr($columnList, 0, strlen($columnList)-2) . ") VALUES (" . substr($valueList, 0, strlen($valueList)-2) . ")", array('direct' => true)) ) {
			
			$this->webLog("loginHistory record has been inserted.", __METHOD__);
			return true;
			
		} else {
		
			$this->webLog("Failed to insert loginHistory record.", __METHOD__, "error");
			return false;
		
		}
		
	}
	
	function crCreateCookie($type, $userid, $key) {
	
		
		
	}
	
	function crReadCookie($name, $psk) {
		
	}
	
	//  provide a language manager
	function crLanguage($group, $name, $d=false) {
	
		//  on first run we must include our library of language
		if ( $this->crLang->init === false ) {
		
			$this->crLang->init = true;
			require $this->config->crLanguageFile;
		
			if ( isset($crLanguage) ) {
			
				$this->crLang->lang = $crLanguage;
				unset($crLanguage);
				
			} else {
				
				/////
				
			}
			
		}
	
		//  process $d
		if ( is_string($d) ) $d = array($d);
		if ( !is_array($d) ) {
		
			//$this->webLog($this->crLanguage('crLanguage', 'dIsInvalid'), 'warn');
			$d = array();
			
		}
		
		$result = $this->crLang->lang->{$group}->{$name};
		foreach ( $d as $id => $string ) $result = str_replace("d[{$id}]", $string, $result);
		return $result;
		
	}
	
	//  load encryption keys from the database
	function crLoadEncKeys() {
	
		$this->webLog("Loading encryption keys.", __METHOD__);
		
		//  if the user is not logged in we will only load the anonymous key
		if ( $this->config->profile->group === 'public' ) $this->config->crCryptoKeys = (object)array('anonymous' => false, 'cookie' => false);
		
		//  some internal pages allow unauthenticated users to run admin tools and only needs the system key
		if ( $this->config->profile->internal === true ) $this->config->crCryptoKeys = (object)array('system' => false);
		
		//  loop through each expected key
		foreach ( $this->config->crCryptoKeys as $keyName => $value ) {
		
			//  check if the key is loaded already or not
			if ( $value === false ) {
				
				$this->webLog("Found unloaded key with name '{$keyName}'", __METHOD__);
				
				//  check whether or not a key exists in the db
				$this->config->crCryptoKeys->{$keyName} = (object)array(
					'loaded' => false,
					'keyid' => false,
					'eckey' => false
				);
				$userid = 'systemwide';
				if ( $query = $this->mysql_query("SELECT `keyid`, `key` FROM `keys` WHERE `userid`='{$userid}' AND `name`='{$keyName}' ORDER BY `id` DESC LIMIT 1") ) {
					
					$result = $this->mysql_returnResource($query);
					$data = $result->fetch_object();
					if ( $result->num_rows === 1 ) {
					
						//  key is found, load it up
						$this->webLog("Located existing key for '{$keyName}' with keyid '{$data->keyid}'", __METHOD__, 'info');
						$this->config->crCryptoKeys->{$keyName}->keyid = $data->keyid;
						$this->config->crCryptoKeys->{$keyName}->eckey = $data->key;
						$this->config->crCryptoKeys->{$keyName}->loaded = true;
						
					}
				
				}
				
				//  check if no key was found
				if ( $this->config->crCryptoKeys->{$keyName}->loaded === false ) {
					
					$this->webLog("No key was located! Generating a new one.", __METHOD__);
					$this->config->crCryptoKeys->{$keyName}->keyid = $this->crGenerateKeyId($userid, $keyName);
					$this->config->crCryptoKeys->{$keyName}->eckey = \Sodium\bin2hex($this->crCryptoGenerateKey());
					if ( 
						$this->mysql_query("INSERT INTO `keys` (`timestamp`, `userid`, `keyid`, `name`, `key`) VALUES 
							(
								'" . time() . "',
								'{$userid}',
								'{$this->config->crCryptoKeys->{$keyName}->keyid}',
								'{$keyName}',
								'{$this->config->crCryptoKeys->{$keyName}->eckey}'
							)
						")
					) {
							
						$this->webLog("Created new encryption key with keyid '{$this->config->crCryptoKeys->{$keyName}->keyid}'", __METHOD__);
						
					} else {
					
						$this->webLog("Failed to create new key with keyid '{$this->config->crCryptoKeys->{$keyName}->keyid}'", __METHOD__, "fatal");
						$this->shutdown($this->crLanguage('generic', 'runtimeError'));
						
					}
					
				}
				
				//  remove our tracking key
				unset($this->config->crCryptoKeys->{$keyName}->loaded);
				
			} else {
			
				//  if it's not a string then we have a problem
				if ( !is_object($value) ) {
				
					$this->webLog($this->crLanguage('generic', 'invalidKey', array("config::crCryptoKeys::{$keyName}", "false or object")), __METHOD__, 'fatal');
					$this->shutdown($this->crLanguage('generic', 'runtimeError'));
					
				} else {
				
					$this->webLog("Doesn't actually yet support providing the keys via configuration; all DB managed!", __METHOD__, "fatal");
					$this->shutdown($this->crLanguage('generic', 'runtimeError'));
					
				}
				
			}
			
		}
		
		//  pass the keys along to crCrypto
		$this->crCryptoSetup($this->config->crCryptoKeys);
		
	}
	
}

?>