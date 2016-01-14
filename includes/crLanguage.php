<?php

$crLanguage = (object)array(
	'generic' => (object)array(
		'startupError' => "An error occurred while running the startup sequence.",
		'runtimeError' => "An error occurred while processing the request.",
		'missingConfigKeyType' => "Configuration value for d[0] is not a valid type",
		'missingConfigKey' => "Missing configuration key - d[0]",
		'missingKeyType' => "The value type for this key is not valid: d[0]",
		'missingKey' => "A required key was not provided: d[0]",
		'tooManyKeys' => "Only d[0] key are allowed; received d[1] keys; valid keys are: d[2]",
		'invalidKey' => "Key d[0] is invalid! Valid keys are: d[1]"
	),
	'webDebugTools' => (object)array(
		'defaultKeyAdd' => "Key 'd[0]' has been loaded from the default values list"
	),
	'crLanguage' => (object)array(
		'dIsInvalid' => "crLanguage Internal Error: Argument 3 is supposed to be a string or an array."
	),
	'webSecurity' => (object)array(
		'accessViolation' => "You do not have permission to access the path 'd[0]'",
		'sanityFailure' => "The input provided failed to pass its sanity test",
	),
	'cr' => (object)array(
		'loginWelcome' => "
		
			<div class='header2'>Welcome, d[0].</div>
					
			<div>You may now proceed to <a href='dashboard.html'>your dashboard</a>.</div>
					
		"
	)
);

?>