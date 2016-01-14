<?php

class crMysql {

	//  necessary for autoloader
	var $parent;
	var $sql;
	var $configImport;
	function __construct(&$parent) { 
	
		$this->parent =& $parent; 
		$this->sql = (object)array(
			'log' => (object)array(),
			'latest' => (object)array()
		);
		$this->configImport = (object)array('defaultMySQLOptions' =>
			array('expires' => false, 'exec' => true, 'direct' => false)
		);
	
	}
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }

	function mysql_init($dbConfig=false) {
	
		//  include the config for mysql
		if ( $dbConfig === false ) require $this->config->path . "/includes/config-db.php";
		if ( $dbConfig === false ) {
			
			$this->webLog($this->crLanguage('generic', 'missingConfigKey', "dbConfig"), __METHOD__, 'fatal');
			$this->shutdown($this->crLanguage('generic', 'startupError'));
			
		} else {
			
			//  validate the dbConfig object
			$dbConfigList = array();
			$singleConn = false;
			if ( !is_array($dbConfig->templates) ) {
			
				//  to support post-runtime connections, we should do one more check to try and validate the object
				if ( count($dbConfig) === 1 ) if ( is_object($dbConfig[current($dbConfig)]) ) {
				
					//  if we receive a 'connName' => (object)array() we will treat it as a post-runtime connection
					$dbConfigList = $dbConfig;
					$singleConn = true;
					$this->webLog("Running post-init connection for profile '" . current($dbConfig) . "'", __METHOD__, 'debug');
					$this->config->dbConfig = array_merge($this->config->dbConfig, $dbConfigList);
					
				} else {
						
					//  nope
					$this->webLog($this->crLanguage('generic', 'missingConfigKeyType', "dbConfig::templates"), __METHOD__, 'fatal');
					$this->shutdown($this->crLanguage('generic', 'startupError'));

				}
				
			} else {
				
				$this->webLog("Runtime connection list is initialized", __METHOD__, 'debug');
				$this->config->dbConfig = array_merge($this->config->dbConfig, $dbConfig->templates);
				$dbConfigList = $this->config->dbConfig;
				
			}

		}
	
		//  construct our mysqli object
		unset($dbConfig);
		if ( !isset($this->sql->defaultConn) ) $this->sql->defaultConn = false;
		foreach ( $dbConfigList as $connName => $dbConfig ) {

			if ( !isset($this->sql->{$connName}->conn) ) {
					
				//  if we're in the runtime init, only connect if autoconnect=true
				if ( $singleConn === false && isset($dbConfig->autoconnect) ) if ( $dbConfig->autoconnect === false ) continue;
					
				//  determine our default connection
				if ( $this->sql->defaultConn === false ) $this->sql->defaultConn = $connName;
				if ( isset($dbConfig->primary) ) if ( $dbConfig->primary === true ) $this->sql->defaultConn = $connName;
				
				//  build our connection profile
				$this->sql->{$connName} = $dbConfig;
				$this->sql->{$connName}->conn = new mysqli($dbConfig->host, $dbConfig->username, $dbConfig->password, $dbConfig->db, $dbConfig->port, $dbConfig->socket);
				$this->sql->{$connName}->query = (object)array();
				$this->sql->{$connName}->latest = false;

				//  we don't actually use this anywhere else, so let's hide the password
				$this->sql->{$connName}->password = '***';
			
				//  check for a connection error
				if ( $this->sql->{$connName}->conn->connect_error ) {
				
					$this->webLog("Failed to connect with errno {$this->mysql_get_error('connect_errno')}: {$this->mysql_get_error('connect_error')}", __METHOD__, 'fatal', $connName);
					$this->shutdown($this->crLanguage('generic', 'startupError'));
					
				} else {
				
					$this->webLog("Connected to MySQL successfully", __METHOD__, false, $connName);
					
				}
				
			} else {
			
				$this->webLog("Skipping connection as we're already connected", __METHOD__, false, $connName);
				
			}
			
		}
		
		$this->webLog("The default connection is MySQL/{$this->sql->defaultConn}", __METHOD__);
		
		//  register our shutdown function (not fatal if errors because it will do this on its own lazily)
		//  this method is busted atm - $this->addShutdownCommand(array('mysqli', 'close'));
		
	}
	
	function mysql_get_error($name, $connName=false) {
	
		if ( $connName === false ) $connName = $this->sql->defaultConn;
		if ( !isset($this->sql->{$connName}->conn) ) {
		
			$this->webLog("No connection found for {$connName}", __METHOD__, 'warn');
			return false;
		
		} else {
			
			return $this->sql->{$connName}->conn->{$name};
			
		}
		
	}
	
	function mysql_getQueryId($hash, $connName, $salt=false) {
	
		//  we create a hash which is unique to the connection profile and any salt
		$input = hash('sha256', $hash . $connName . $salt);
		return substr($input, strlen($input)-8, 8);
		
	}
	
	//  return the most recent queryId executed
	function mysql_getLastId() {
	
		return $this->sql->lastQueryId;
		
	}
	
	//  return the error of the most recently failed query
	function mysql_getLastError() {
	
		$connName = $this->sql->log->{$this->sql->lastQueryId};
		return $this->sql->{$connName}->query->{$this->sql->lastQueryId}->error;
		
	}
	
	//  return the errno of the most recently failed query
	function mysql_getLastErrno() {
	
		$connName = $this->sql->log->{$this->sql->lastQueryId};
		return $this->sql->{$connName}->query->{$this->sql->lastQueryId}->errno;
		
	}
	
	//  returns the insert id for the specific query
	function mysql_getInsertId($qid=false) {
	
		$qid = ( $qid === false ) ? $this->sql->lastQueryId : $qid;
		$connName = $this->sql->log->{$qid};
		return $this->sql->{$connName}->query->{$qid}->insert_id;
		
	}
	
	//  execute a mysql query and return the result resource
	function mysql_query($query, $options=false) {

		//  prepare our base options object
		if ( $options === false ) $options = (object)array();  //  build our base object if nothing was provided
		if ( is_string($options) ) $options = (object)array('connName' => $options);  //  allow for connName to be supplied easily
		if ( is_array($options) ) $options = (object)$options;  //  we expect this to be an object
		if ( isset($cmd->query->{$query}) ) $options->connName = $this->sql->log->{$query};  //  if $query is a queryId, lookup to connection details from the journal2
		if ( !isset($options->connName) && isset($options->connection) ) $options->connName = $options->connection;  //  provide an alias
		if ( !isset($options->connName) && isset($options->profile) ) $options->connName = $options->profile;  //  provide an alias

		//  make sure our connection exists
		if ( !isset($options->connName) ) {
		
			//  no connection specified; use the default
			$options->connName = $this->sql->defaultConn;	
			$connName =& $options->connName;
			
		}
		if ( !isset($this->sql->{$connName}) ) if ( !is_object($this->sql->{$connName}->conn) ) {
			
			$this->webLog("Cannot execute query on non-existent connection", __METHOD__, 'error', $connName);
			return false;
			
		}
		
		//  check if we were supplied a queryId
		if ( isset($this->sql->log->{$query}) ) {
			
			$connName = $this->sql->log->{$query};
			$hash = $this->sql->{$connName}->query->{$query}->hash;
			$queryId = $query;
			$options = $this->sql->{$connName}->query->{$query}->options;
			
		} else {
				
			$hash = hash('sha256', $query);
			$queryId = $this->mysql_getQueryId($hash, $connName);

		}
		
		//  shorten things up a bit
		$cmd =& $this->sql->{$connName};
		
		//  accounting
		$this->sql->lastHash = $hash;
		$this->sql->lastQueryId = $queryId;
		$this->sql->log->{$queryId} = $connName;
		$cmd->latest = $queryId;
		
		//  configure our execution options
		//  1. if no options supplied, create load them from config if available
		if ( !is_object($options) ) {
			
			//  incorporate our global default options with any profile-specific options
			$options = ( isset($cmd->defaultOptions) ) ? 
				$this->mergeDefaultValues($cmd->defaultOptions, $this->config->defaultMySQLOptions) :
				$this->config->defaultMySQLOptions;
			
		} else {
			
			//  2. if options are supplied, we must merge them with profile-specific and the default global options
			if ( isset($cmd->defaultOptions) ) $options = $this->mergeDefaultValues($options, $cmd->defaultOptions);
			$options = $this->mergeDefaultValues($options, $this->config->defaultMySQLOptions);

		}
		
		//  just because
		$options->connName = $connName;
		
		//  expire immediately if requested
		if ( $options->direct === true ) if ( isset($cmd->query->{$queryId}) ) $cmd->query->{$queryId}->expires = 0;
		
		//  check if this query has already been called
		if ( isset($cmd->query->{$queryId}) ) if ( $cmd->query->{$queryId}->options->direct === false ) {
		
			//  check if this expires
			if ( isset($cmd->query->{$queryId}->expires) ) if ( is_numeric($cmd->query->{$queryId}->expires) && (time() >= $cmd->query->{$queryId}->expires) ) {
			
				$expTime = number_format(microtime(true)-($cmd->query->{$queryId}->timestamp+$options->expires), $this->config->precision);
				$this->webLog("Query expired {$expTime} seconds ago", __METHOD__, 'info', $queryId);
				//unset($cmd->query->{$queryId});
				
			} else {
			
				$this->webLog("Serving query from resource cache", __METHOD__, 'info', $queryId);
				
				//  a failed query must return false
				return ( $cmd->query->{$queryId}->status === false ) ? false : $queryId;
				
			}
			
		}
		
		//  initiate our local resource cache for this query
		if ( !isset($cmd->query->{$queryId}) ) {
		
			$cmd->query->{$queryId} = (object)array(
				'hash' => $hash,
				'timestamp' => microtime(true),
				'exec' => false,
				'options' => $options,
				'query' => $query
			);
			
			if ( isset($options->exec) ) if ( $options->exec === true ) {
				
				//  set our expiration time if requested
				$cmd->query->{$queryId}->expires = ( is_numeric($options->expires) ) ? time()+$options->expires : false;
				
			}
		
		}
		
		//  no cache exists; execute the query
		$this->webLog("Query runtime options: " . json_encode($options), __METHOD__, false, $queryId);
		$start = microtime(true);
		if ( isset($options->exec) ) if ( $options->exec === true ) {
			
			//  update the stored options
			$cmd->query->{$queryId}->options = $options;
			
			//  we use the longer query key because this might be a query getting executed after being introduced
			if ( $cmd->query->{$queryId}->result = $cmd->conn->query($cmd->query->{$queryId}->query) ) {
			
				$totalTime = number_format(microtime(true)-$start, $this->config->precision);
				$cmd->query->{$queryId}->runTime = $totalTime;
				$cmd->query->{$queryId}->status = true;
				$cmd->query->{$queryId}->insert_id = $cmd->conn->insert_id;
				$this->webLog("Query executed successfully in '{$cmd->query->{$queryId}->runTime}' seconds", __METHOD__, false, $queryId);
				
				//  return either the result or the queryId
				//  $options->direct === true causes the query to be expired immediately for future use
				return ( $options->direct === true ) ? $cmd->query->{$queryId}->result : $queryId;
				
			} else {
			
				//  the query failed for some reason
				$cmd->query->{$queryId}->status = false;
				$cmd->query->{$queryId}->error = $cmd->conn->error;
				$cmd->query->{$queryId}->errno = $cmd->conn->errno;
				$this->webLog("Query execution failed; outputting information --", __METHOD__, 'warn', $queryId);
				$this->webLog("Query: {$query}", __METHOD__, 'warn', $queryId);
				$this->webLog("Error Number: {$cmd->conn->errno}", __METHOD__, 'warn', $queryId);
				$this->webLog("Error Text: {$cmd->conn->error}", __METHOD__, 'warn', $queryId);
				return false;
				
			}
			
		} return $queryId;
		
	}
	
	//  return the MySQLi result resource
	function mysql_returnResource($queryId=false) {
	
		//  if no queryId is provided then we assume it's the latest query
		if ( $queryId === false ) $queryId = $this->sql->lastQueryId;
	
		//  $queryId is supposed to be a string...
		if ( is_string($queryId) ) {
				
			//  obtain the profile name
			if ( $connName = $this->sql->log->{$queryId} ) {
				
				$cmd =& $this->sql->{$connName};
				if ( is_object($cmd->query->{$queryId}->result) ) {
				
					$this->webLog("Sending resource to user", __METHOD__, false, $queryId);
					return $cmd->query->{$queryId}->result;
					
				} else {
				
					$this->webLog("QueryId exists but there is no result resource available.", __METHOD__, 'warn', $queryId);
					return false;
					
				}
				
			} else {
			
				$this->webLog("Invalid resource requested; query does not exist", __METHOD__, 'error', $queryId);
				return false;
				
			}
			
		} else {
		
			//  if it is an object, then it's entirely likely they provided the resource to us
			//  this is possible if the query was called with option direct:true
			
			//  if it is an object, we can try and verify what it is
			if ( is_object($queryId) ) {
			
				//  if this method exists, it's likely a MySQLi result resource
				if ( method_exists($queryId, 'fetch_object') ) {

					$this->webLog("Supplied QueryId is a MySQLi resource! This is a mistake. Returning result.", __METHOD__, "warn");
					return $queryId;
					
				//  we can't identify it
				} else {
				
					$this->webLog("Supplied QueryId is invalid and cannot be identified; dumping data --", __METHOD__, "error");
					$this->webLog(json_encode($queryId), __METHOD__, "error");
					return false;
					
				}
				
			} else {
			
				//  also cannot be identified
				$this->webLog("Supplied QueryId is unidentifiable with type '" . gettype($queryId) . "'", __METHOD__, "error");
				return false;
				
			}
			
		}
		
	}
	
}

?>