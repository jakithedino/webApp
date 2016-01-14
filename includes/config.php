<?php

//  set any php ini directives here
ini_set('display_errors', 'off');
ini_set('date.timezone', 'America/Los_Angeles');

//  this is our base config
$config = (object)array(
	  
	//  generic config settings
	'path' => '/home/web/jakithedino.blue',
	'hostname' => 'jakithedino.blue',
	'program' => 'JakiBlue',
	'title' => 'JakiBlue',  //  Title for the website
	'titleSpacer' => ' // ',  //  the text between the title and subtitle
	'version' => '0.1-pre',  //  program version
	'releaseDate' => '2016-01-13 7:12PM CST',  //  program release date
	'microtime' => microtime(true),  //  current time in ms
	'timestamp' => date("Ymd-hia"),  //  current datestamp
	'runtimeID' => substr(hash('sha256', microtime(true)), 57, 64),  //  generate a runtime id
	'logPath' => '/home/web/logs/jakithedino.blue/webLog.log',  //  path to store log files
	'sqlPath' => '/home/web/sql/jakithedino.blue',  //  path to sql files
	'crLanguageFile' => $path . '/includes/crLanguage.php',  //  path to the crLanguage file
	'clientIp' => 'auto',  //  set to either a server variable or 'auto',
	'cookieTtl' => 3600,  //  default time in seconds before a cookie expires
	'dbConfig' => array(),  //  basic config array for the dbConfig
	'precision' => 6,  //  number_format precision,
	'passwordHashDefaults' => array('cost' => 15),  //  options for password_hash(),
	'passwordHashVersion' => 1,  //  version of the algorithm used to generate a password hash
	'newUserDefaultProfile' => array(  //  default values for a new user
		'name' => 'Default User',
		'email' => 'root@localhost',
		'customPerms' => array(),
		'passwordResetLink' => '',
		'passwordResetExpires' => 0
	),
	
	//  security config settings
	//  loading this from a database in the future would be nice
	'security' => (object)array(
		'settings' => (object)array(
			'enforceWhitelist' => false,
			'enforceBlacklist' => true,
			'defaultGroup' => 'public'
		),
		'routing' => (object)array(
			'groups' => (object)array(
				'public' => (object)array(
					'groupid' => 1,
					'inherits' => false,
					'perms' => 'none',
					'defaultPath' => 'index',
					'paths' => array(
						'login',
						'basicHelp',
						'error',
						'index',
						'initSetup',
						'sodium',
						'cheaters',
						'incs',
						'scammers',
						'apply'
					),
					'displayErrors' => true
				),
				'error' => (object)array(
					'groupid' => 2,
					'inherits' => false,
					'perms' => 'ro',
					'defaultPath' => 'denied',
					'paths' => array(
						'denied'
					),
					'displayErrors' => false
				),
				'readOnly' => (object)array(
					'groupid' => 3,
					'inherits' => array('public'),
					'perms' => 'ro',
					'defaultPath' => 'index',
					'paths' => array(
						//  no new paths yet
					),
					'displayErrors' => true
				),
				'admin' => (object)array(
					'groupid' => 4,
					'inherits' => array('public', 'readOnly'),
					'perms' => 'rw',
					'defaultPath' => 'index',
					'paths' => array(
						'cpAccounts',
						'customerAccounts'
					),
					'displayErrors' => true
				)
			)
		),
		'ip' => (object)array(
			'whitelist' => array('127.0.0.1'),
			'blacklist' => array()
		),
		'regex' => (object)array(
			'http' => (object)array(
				'path' => "/^[a-z0-9-]*$/i"
			),
			'general' => (object)array(
				'alphanumeric' => '/^[a-z0-9-]*$/i',
				'base64' => '^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$'
			)
		)
	),
	
	//  encryption keys
	'crCryptoKeys' => (object)array(
		'system' => false,
		'cookie' => false
	),
	
	//  autoload config settings
	'autoloadPath' => $path . "/common/autoload",  //  path to the autoload folder
	'autoloadClasses' => array('webDebugTools', 'crCryptoTools', 'webSecurity', 'webHTMLTemplate', 'crMysql'),  //  array of classes to be included in this program
	'autoloadPlugins' => array(), //  array('logging' => array('esLogging')),  //  array of callbacks to plugin and modify the behavior of autoload classes
	  //  so ^^^ this is new  //  the main array contains keys naming methods within the autoload program
	  //  The inner array is a list of methods to be executed at the end of the main code
	
);

//  additional config keys
$config->curl = array(
	'0' => array('const' => CURLOPT_USERAGENT, 'value' => "{$config->program}/{$config->version}"),
	'1' => array('const' => CURLOPT_RETURNTRANSFER, 'value' => true),
	'2' => array('const' => CURLOPT_HEADER, 'value' => false)
);

?>