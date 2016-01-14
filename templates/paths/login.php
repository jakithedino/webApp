<?php

$loc = "path/login";

if ( $this->config->profile->userid === false ) {
	
	//  a signin request w/ post values set
	if ( $this->config->profile->area === "signin" && isset($_POST['loginUsername'], $_POST['loginPassword']) ) {
		
		//  
		//  Right here would be a good spot for a memcached-based security feature to prevent brute forcing
		//  $this->securityLoginIncrementAttempts('global');
		//
		
		//  set our title and header
		$this->templateLoadTitle('Account Login Authorization');
		$this->templateModify('mainBody', '
			
			<div class="header">
				Account Login
			</div>
			
		');
		
		//  make the userObject
		$userLoginObject = (object)array('username' => '', 'password' => $_POST['loginPassword'], 'rememberMe' => false);
		
		//  sanitize the username
		if ( !preg_match($this->config->security->regex->general->alphanumeric, $_POST['loginUsername']) ) {
			
			$this->webLog("Supplied username failed sanity tests - " . json_encode($_POST['loginUsername']), $loc, "error");
			$this->shutdown($this->crLanguage('generic', 'runtimeError'));
			
		} else $userLoginObject->username = $_POST['loginUsername'];
		
		//  sanitize the rememberMe
		if ( isset($_POST['rememberMe']) ) {
			
			if ( $_POST['rememberMe'] !== 'true' ) {
				
				$this->webLog("Supplied rememberMe failed sanity tests - " . json_encode($_POST['rememberMe']), $loc, "error");
				$this->shutdown($this->crLanguage('generic', 'runtimeError'));
	
			} else $userLoginObject->rememberMe = true;
			
		}
		
		//  find user in db
		$result = $this->mysql_query("SELECT userid, username, passwordHash, salt, `group`, loginFailures, name FROM users WHERE username='{$userLoginObject->username}' LIMIT 1", array('direct' => true));
		if ( $result === false || $result->num_rows === 0 ) {
			
			$this->webLog("Login failed for user '{$userLoginObject->username}' because it does not exist.", $loc, "notice");
			$this->templateLoadTitle(' - Access Denied');
			$this->templateMakeError("There was an error with your login attempt.");
			$this->templateMakeLoginBox('mainBody');
			
		} else {
		
			//  retrieve the userData
			$userData = $result->fetch_object();
				
			//  check the password
			if ( $this->crVerifyPassword($userData->passwordHash, $userLoginObject->password, $userData->salt) ) {
									
				//  generate a log
				$cookieTtl = ( $userLoginObject->rememberMe === true ) ? 86400*14 : 0;
				$this->crCryptoSetCookie('login', json_encode(array('u' => $userData->userid, 'i' => $this->config->clientIp)), $cookieTtl);
				$this->crLoginHistoryAdd($userData->userid, 1, $userData->username);
					
				//  we will issue a redirect, but just in case let's draw some text
				$this->templateLoadTitle(' - Access Granted');
				$this->templateModify('mainBody', $this->crLanguage('cr', 'loginWelcome', $userData->name));
	
			} else {
			
				//
				//  Another spot for a security checkpoint
				//  $this->securityLoginIncrementAttempts('user', $userData->userid);
				//
				
				//  generate a log
				$this->securityLoginHistoryAdd($userData->userid, 0, $userData->username);
				
				//  draw our response
				$this->webLog("Login failed for user '{$userLoginObject->username}' because the passwords do not match.", $loc, "notice");
				$this->templateLoadTitle(' - Access Denied');
				$this->templateMakeError("There was an error with your login attempt.");
				$this->templateMakeLoginBox('mainBody');
				
			}
			
		}
		
	} else if ( $this->config->profile->area === 'forgotPassword' ) {
		
		$this->templateLoadTitle('Lost Password Recovery');
		$this->templateModify('mainBody', "
			
			<div class='header'>
				Lost Password Recovery
			</div>
	
		");
		
		if ( $this->config->profile->do === 'submit' && isset($_POST['username'])) {
			
			$this->templateModify('mainBody', "
			
				If the supplied user account exists an email has been sent to it with a password reset link.
				
			");
			
		} else {
				
			if ( $this->config->profile->do === 'submit' ) $this->config->profile->error = "Please supply a valid username to proceed.";
				
			$this->templateModify('mainBody', "
				
				Please enter your username to receive a password reset link.
				
				<form id='lostPassword' action='login/forgotPassword/submit.html' method='POST'>
				<input type='text' name='username' style='width: 200px;'>
				<input type='submit' value='Request Link' style='width: 150px;'>
				</form>
			
			");
	
		}
		
	} else {
		
		$this->templateLoadTitle('Account Login');
		
		if ( $this->config->profile->area === 'signin' ) $this->templateModify('error1', 'Please provide both a username and a password to login!');
		
		$this->templateModify('mainBody', '
			
			<div class="header">
				Account Login
			</div>
		
		');
		
		$this->templateMakeLoginBox('mainBody');
	
	}

} else {

	$this->templateModify('mainBody', '
	
		<div class="header">
			Account Login
		</div>
		
		You are already logged in!
	
	');
	
}

?>