<?php

##  cryptography
class crCryptoTools {
	
	var $parent;
	var $self;
	var $crCryptoKeys;
	var $configImport;
	function __construct(&$parent) { 
	
		$this->parent =& $parent; 
		$this->crCryptoKeys = new stdClass;
		$this->crCryptoKeys->init = false;
		$this->crCryptoKeys->index = (object)array();
		$this->configImport = (object)array(
			'crCryptoPrefix' => 'crc2_',
			'crCryptoRunMetrics' => true,
			'crCryptoShutdownSequence' => (object)array(
				'enabled' => true,
				'vars' => (object)array(
					'crCryptoKeys'
				)
			)
		);
		
	}
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	//  setup config
	function crCryptoSetup($keys) {
	
		//  set our variable
		$allowedKeys = array('cookie', 'system', 'user', 'anonymous');
		$keyDataKeys = array('keyid', 'eckey');
		
		//  check if an array was provided
		if ( is_object($keys) ) {
		
			foreach ( $keys as $keyName => $keyData ) {
			
				if ( in_array($keyName, $allowedKeys) ) {
				
					//  check if the object has any stray keys
					foreach ( $keyData as $name => $value ) if ( !in_array($name, $keyDataKeys) ) {
					
						$this->webLog($this->crLanguage('generic', 'invalidKey', array("keys::{$keyName}::{$name}", implode("|", $keyDataKeys))), __METHOD__, 'fatal');
						$this->shutdown($this->crLanguage('generic', 'runtimeError'));
						return false;  //  this won't actually happen as shutdown has occurred
						
					}
					
					$this->crCryptoKeys->{$keyName} = $keyData;
					
				}
				
			}
			
		} else {
		
			$this->webLog($this->crLanguage(__CLASS__, 'missingKeyType', "keys"), __METHOD__, 'error');
			return false;
			
		}
		
		$this->crCryptoKeys->init = true;
		return true;
		
	}
	
	//  check if the crypto class has been properly setup or not
	function crCryptoCheckInit() {
		
		if ( $this->crCryptoKeys->init === false ) {
		
			$this->webLog(__CLASS__ . " has been been properly setup! Please run " . __CLASS__ . "::crCryptoSetup(array $keys) first.", __METHOD__, 'error');
			return false;
			
		} else return true;
		
	}
	
	//  delete an encrypted cookie
	function crCryptoDeleteCookie($name) {
	
		//  run startup checks
		if ( $this->crCryptoCheckInit() === false ) return false;
		if ( !is_string($name) ) {
		
			$this->webLog($this->crLanguage(__CLASS__, 'missingKeyType', "name"), __METHOD__, 'error');
			return false;
			
		}
		
		//  create the cookie
		$encname = $this->config->crCryptoPrefix . $this->crCryptoEncrypt($name, 'cookie');
		return setcookie("{$encname}", '', time()-86400);
	
	}
	//  get an encrypted cookie
	function crCryptoGetCookie($name) {
	
		$encname = hash('sha256', $name);
		if ( isset($_COOKIE[$encname]) == false ) {
		
			$this->webLog("Could not read encrypted cookie with name '{$name}'", __METHOD__, 'warn');	
			return false;
			
		}
		
		$this->webLog("Reading encrypted cookie with name '{$name}'", __METHOD__);
		return $this->crCryptoDecrypt($_COOKIE[$encname], 'cookie');
	
	}
	
	//  set an encrypted cookie
	function crCryptoSetCookie($name, $value, $expires=false) {
	
		$defaultTtl = ( isset($this->config->cookieTtl) ) ? (int)$this->config->cookieTtl : 0;
		$ttl = ( is_numeric($expires) === false ) ? $defaultTtl : $expires;
		$encname = hash('sha256', $name);
		$encvalue = $this->crCryptoEncrypt($value, 'cookie');
		$this->webLog("Sending encrypted cookie '{$name}' with a payload of " . strlen($value) . " bytes and a {$ttl}s TTL", __METHOD__);
		return setcookie("{$encname}", "{$encvalue}", time()+$ttl, '/', false, 0);
		
	}
	
	//  store analytical data based on supplied information
	function crCryptoAnalytics($keyid, $nonce, $dir, $cipher, $input, $runTime) {
		
		//  sanity checks
		//  seriously finish this later -- 2015/12/15 12:56am CST
		if ( !is_numeric($input) ) {
			
			$this->webLog($this->crLanguage('generic', 'invalidKey', array("input", "type - numeric")), __METHOD__, 'error');
			return false;
			
		}
		
		if ( !is_numeric($runTime) ) {
			
			$this->webLog($this->crLanguage('generic', 'invalidKey', array("runTime", "type - numeric")), __METHOD__, 'error');
			return false;
			
		}
		
		$storage = (object)array(
			'keyid' => $keyid,
			'nonce' => hash('sha512', $nonce),
			'method' => $dir,
			'cipher' => $cipher,
			'inputLength' => $input,
			'runTime' => $runTime
		);
		
	}
	
	//  generate a random encryption key
	function crCryptoGenerateKey($cipher='aes256gcm') {
	
		if ( $cipher === 'aes256gcm' ) return \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_AES256GCM_KEYBYTES);
		if ( $cipher === 'chacha' ) return \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES);
		
		//  error, error!
		$this->webLog($this->crLanguage('generic', 'invalidKey', array("cipher", "values: aes256gcm | chacha")), __METHOD__, 'error');
		return false;
		
	}
	
	//  alias helper for the lazy encryption
	function crCryptoEncrypt($input, $eckey=false, $cipher='aes256gcm') {

		$result = $this->crCryptoProcess('encrypt', $input, $eckey, $cipher);
		return $result;
		
	}
	
	//  alias helper for the lazy decryption
	function crCryptoDecrypt($input, $eckey=false, $cipher='aes256gcm') {

		$result = $this->crCryptoProcess('decrypt', $input, $eckey, $cipher);
		return $result;
		
	}
	
	//  perform all encryption and decryption (I can't figure out how to word this)
	function crCryptoProcess($dir, $input, $eckey, $cipher) {
	
		//  sanity checks
		$allowedDir = array('encrypt', 'decrypt');
		$inputTypes = array('string', 'ad');
		$cipherList = array('aes256gcm', 'chacha');
		if ( !in_array($dir, $allowedDir) ) {
		
			$this->webLog($this->crLanguage('generic', 'invalidKey', array('dir', implode('|', $allowedDir))), __METHOD__, 'error');
			return false;
			
		}
		if ( is_string($input) ) $input = (object)array('string' => $input, 'ad' => '');
		if ( is_object($input) ) {
		
			if ( count((array)$input) === 2 ) {
				
				foreach ( $input as $key => $value ) if ( !in_array($key, $inputTypes) ) {
			
					$this->webLog($this->crLanguage('generic', 'invalidKey', array('input', implode('|', $inputTypes))), __METHOD__, 'error');
					return false;
				
				}
		
			} else {
			
				$this->webLog($this->crLanguage('generic', 'tooManyKeys', array(count($inputTypes), count($input), "input::(" . implode('|', $inputTypes) . ")")), __METHOD__, 'error');
				return false;
				
			}
			
		}
		
		/*if ( !is_string($eckey) ) {
		
			$this->webLog($this->crLanguage('generic', 'missingKeyType', 'eckey'), __METHOD__, 'error');
			return false;
			
		}*/
		
		if ( !in_array($cipher, $cipherList) ) {
		
			$this->webLog($this->crLanguage('generic', 'invalidKey', array('cipher', implode('|', $cipherList))), __METHOD__, 'error');
			return false;
			
		}
		
		//  if the eckey matches the name of a stored key we'll map it to the proper eckey
		$keyid = '00-';
		if ( isset($this->crCryptoKeys->{$eckey}) ) {
		
			//  set our keyid
			$keyid = $this->crCryptoKeys->{$eckey}->keyid;
			
			//  create our index
			$this->crCryptoKeys->index->{$keyid} = $eckey;
			
			//  set our eckey to the hex value
			$eckey = $this->crCryptoKeys->{$eckey}->eckey;
			
		}
		
		//  process the request
		$start = microtime(true);
		
		//  if we're decrypting then the input should be nonce.ciphertext
		if ( $dir === 'decrypt' ) {
		
			//  grab our nonce bytes param
			if ( $cipher === 'aes256gcm' ) $bytes = \Sodium\CRYPTO_AEAD_AES256GCM_NPUBBYTES;
			if ( $cipher === 'chacha' ) $bytes = \Sodium\CRYPTO_AEAD_CHACHA20POLY1305_NPUBBYTES;
			
			//  grab our message data
			//  accepts two input types: <keyid>$<ciphertext> or just <ciphertext>
			$data = explode('$', $input->string);
			if ( count($data) === 2 ) {
					
				//  located a keyid and ciphertext
				$keyid = $data[0];
				$message = $data[1];

			//  no keyid was located, so treat it as pure ciphertext
			} else $data = $input->string;
			
			//  parse the message contents
			$message = \Sodium\hex2bin($message);
			$nonce = mb_substr(
				$message,
				0,
				$bytes,
				'8bit'
			);
			$ciphertext = mb_substr(
				$message,
				$bytes,
				null,
				'8bit'
			);
		
			//  on decrypt only: check if a key has been provided yet
			if ( $eckey === false ) {
			
				//  check if the keyid is indexed
				if ( isset($this->crCryptoKeys->index->{$keyid}) ) {
					
					//  found a match; set our eckey
					$eckey = $this->crCryptoKeys->{$this->crCryptoKeys->index->{$keyid}}->eckey;
					
				} else {
					
					//  no match and this far along means we can't decrypt
					$this->webLog("Cannot decrypt ciphertext because no suitable eckey was located", __METHOD__, 'error');
					return false;
				}
				
			}
		
		}
		
		//  if eckey is false, we cannot proceed
		if ( $eckey === false ) {
		
			$this->webLog();
			return false;
			
		}
		
		//  set our eckey to the actual encryption key
		$eckey = \Sodium\hex2bin($eckey);
		
		//  process AES-256-GCM methods
		if ( $cipher === 'aes256gcm' ) {
				
			//$eckey = ( $eckey === false ) ? \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_AES256GCM_KEYBYTES) : \Sodium\hex2bin($eckey);
			
			if ( $dir === 'encrypt' ) {
				
				//  create the ciphertext
				$nonce = \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_AES256GCM_NPUBBYTES);
				$resultString = \Sodium\crypto_aead_aes256gcm_encrypt($input->string, $input->ad, $nonce, $eckey);
				
			} else {
				
				//  decrypt the ciphertext
				$resultString = \Sodium\crypto_aead_aes256gcm_decrypt($ciphertext, $input->ad, $nonce, $eckey);
				
			}

		//  process CHACHA20-POLY1305 methods
		} else if ( $cipher === 'chacha' ) {

			//$eckey = ( $eckey === false ) ? \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES) : \Sodium\hex2bin($eckey);
		
			if ( $dir === 'encrypt' ) {

				//  create the ciphertext
				$nonce = \Sodium\randombytes_buf(\Sodium\CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES);
				$resultString = \Sodium\crypto_aead_chacha20poly1305_encrypt($input->string, $input->ad, $nonce, $eckey);

			} else {
			
				//  decrypt the ciphertext
				$resultString = \Sodium\crypto_aead_chacha20poly1305_decrypt($ciphertext, $input->ad, $nonce, $eckey);
				
			}
			
		}
		
		//  finishing up
		$totalTime = number_format(microtime(true)-$start, $this->config->precison+10);
		$this->webLog("Performed '{$dir}' on a string with {$cipher} in {$totalTime} seconds", __METHOD__);
		$this->crCryptoAnalytics($eckey, $nonce, $dir, $cipher, strlen($input->string), $totalTime);
		\Sodium\memzero($eckey);
			
		//  if decrypt, just send the string
		if ( $dir === 'decrypt' ) {
		
			\Sodium\memzero($nonce);
			if ( $resultString === false ) $this->webLog("Decryption failed!", __METHOD__, 'warn');	
			return $resultString;
			
		}
		
		//  if encrypt, send back the data in case we generated it
		$ciphertext = \Sodium\bin2hex($nonce . $resultString);
		\Sodium\memzero($nonce);
		return $keyid . '$' . $ciphertext;
		
	}
	
	//  generate a random string
	function crCryptoMakeString($length, $lowercase=true, $caps=true, $numbers=true, $symbols=true) {
	
		//  the most annoy part of this function goes here
		$start = microtime(true);
		$chararray = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
		foreach ( $chararray as $value ) $upperarray[] = strtoupper($value);
		$symarray = array(',', '.', '/', ';', '[', ']', '-', '=', '!', '@', '%', '^', '*', '(', ')', '?', '{', '}', '_', '+');
		$numarray = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
		
		$array = array();
		if ( $caps === true ) $array = array_merge($array, $upperarray);
		if ( $numbers  === true ) $array = array_merge($array, $numarray);
		if ( $symbols  === true ) $array = array_merge($array, $symarray);
		if ( $lowercase  === true ) $array = array_merge($array, $chararray);
	
		//  generate a random string
		$string = '';
		while ( strlen($string) < $length && shuffle($array) ) $string .= $array[rand(0, count($array)-1)];
		$runTime = number_format(microtime(true)-$start, $this->config->precision);
		$this->webLog("String generated in {$runTime} seconds", __METHOD__);
		return $string;
	
	}
	
}

?>