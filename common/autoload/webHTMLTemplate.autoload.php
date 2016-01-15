<?php

class webHTMLTemplate {
	
	//  necessary for autoloader
	var $parent;
	var $defaultConfig;
	var $configImport;
	var $self;
		
	function __construct(&$parent) { 
	
		$this->self = (object)array();
		$this->configImport = (object)array(
			'HTMLTemplate' => (object)array(
				'skin' => 'default',
				'pieces' => array(
					'header', 'mainBody', 'menu1', 'css', 'error1', 'footer', 'footerLinks', 'headerJs', 'bodyJs', 'title'
				),
				'expandingBox' => (object)array(
					'defaults' => array(
						'classes' => array('bq2')
					),
					'tracking' => array()
				)
			)
		);
		$this->parent =& $parent; 
		
	}
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	//  load the skin template 
	function templateLoad() {
	
		$this->webLog("Loading skin '{$this->config->HTMLTemplate->skin}'", __METHOD__);
		$this->self->pieces = (object)array();
		$this->self->templateFile = $this->config->path . "/templates/skins/{$this->config->HTMLTemplate->skin}/layout.php";
		
		//  we must find the base template 
		if ( !is_file($this->self->templateFile) ) {
		
			$this->webLog("Base template for skin '{$this->config->HTMLTemplate->skin}' could not be located", __METHOD__, 'fatal');
			$this->shutdown('There was an error loading the base template.');
			
		}
		
		$pieces = array_merge($this->config->HTMLTemplate->pieces);
		foreach ( $pieces as $piece ) {
				
			unset($filePath);
			$this->self->pieces->{$piece} = (object)array('data' => '');
			$filePath = $this->config->path . "/templates/skins/{$this->config->HTMLTemplate->skin}/{$piece}.php";
			if ( !is_file($filePath) ) {
			
				$this->webLog("No base data found for '{$piece}'", __METHOD__);
				$this->self->pieces->{$piece}->filePath = false;
				
			} else {
			
				$this->webLog("Loading base data for '{$piece}'", __METHOD__);
				$this->self->pieces->{$piece}->filePath = $filePath;
				if ( !include($filePath) ) {
				
					$this->webLog("Failed to include base data for piece '{$piece}' at {$filePath}", __METHOD__, 'fatal');
					$this->shutdown('There was an error loading template pieces.');
					
				}
				
			}
			
		}
		
		$this->webLog("Skin loading is finished", __METHOD__);
		
	}
	
	//  build the template with all supplied template data
	function templateBuild() {
	
		//  first we have to load the base template
		if ( $this->self->templateHTML = file_get_contents($this->self->templateFile) ) {
			
			//  next we go thru each piece and load the generated content for it into the template
			foreach ( $this->config->HTMLTemplate->pieces as $piece ) {
			
				//  in some cases we don't display errors
				if ( $piece === 'error1' && $this->config->security->routing->groups->{$this->config->profile->group}->displayErrors === false ) $this->self->pieces->{$piece}->data = '';
				
				//  load the piece
				$this->webLog("Loading piece '{$piece}' into the template with " . strlen($this->self->pieces->{$piece}->data) . " bytes", __METHOD__);
				$this->self->templateHTML = str_replace("[{$piece}]", $this->self->pieces->{$piece}->data, $this->self->templateHTML);
				
			}
			
			//  display the template to the viewer
			$this->webLog("Sending templateHTML to viewer", __METHOD__);
			print $this->self->templateHTML;
			
		//  this will exit the program
		} else {
		
			$this->webLog('Failed to open base template file', __METHOD__, 'fatal');
			$this->shutdown('There was an error loading the base template.');
			
		}
		
	}
	
	//  modify the template piece with new data
	function templateModify($piece, $data, $action='append') {
		
		//  does the requested piece exist
		if ( isset($this->self->pieces->{$piece}) ) {
			
			//  is the requested action valid
			$actions = array('append', 'prefix');
			if ( in_array($action, $actions) ) {
					
				$this->webLog("Piece '{$piece}' has received the instruction '{$action}' with a payload of " . strlen($data) . " bytes", __METHOD__);
				
				//  add new data to the end of the piece
				if ( $action == 'append' ) {
					
					$this->self->pieces->{$piece}->data .= $data;
					
				//  add new data to the beginning of the piece
				} elseif ( $action == 'prefix' ) {
					
					$this->self->pieces->{$piece}->data = $data . $this->self->pieces->{$piece}->data;
					
				} elseif ( $action == 'replace' ) {
					
					$this->self->pieces->{$piece}->data = $data;
					
				}

			} else {
			
				$this->webLog("Piece '{$piece}' received an unknown instruction '{$action}' with a payload of " . strlen($data) . " bytes", __METHOD__, "error");
				
			}

		} else {
		
			$this->webLog("Received instruction '{$action}' for an invalid piece named '{$piece}'", __METHOD__, "error");
			
		}
		
	}
	
	function templateDisplay() {
	
		$this->webLog("Displaying template to the viewer", __METHOD__);
		print $this->self->templateHTML;
		
	}
	
	//  load title text and set the profile value
	function templateLoadTitle($text, $replace=false) {
	
		$this->config->profile->loadTitle = true;
		$action = ( $replace === false ) ? 'append' : 'replace';
		$this->templateModify('title', $text, $action);
		
	}
	
	//  look for a string or pattern in a template piece
	function templateContentExists($piece, $search) {
		
		//  does the piece exist?
		if ( isset($this->self->pieces->{$piece}) ) {
			
			$response = (object)array('state' => false);
			if ( preg_match("/{$search}/i", $this->self->pieces->{$piece}->data, $matches) ) {
				
				$response->state = true;
				$response->matches = $matches;
				
			}
			
			return ( $response->state === false ) ? false : $response;
			
		} else {
		
			$this->webLog("Piece '{$piece}' does not exist!", __METHOD__, 'error');
			return false;
			
		}
		
	}
	
	//  generate a collapsable box
	function templateMakeExpandingBox($piece, $options, $content, $returnPiece=false) {
	
		//  if a string is provided, we assume it is the divId
		if ( is_string($options) ) $options = array('divId' => $options);

		//  generate our options list		
		$options = $this->mergeDefaultValues($options, $this->config->HTMLTemplate->expandingBox->defaults);
		$divId = $options->divId;

		//  before we can run this we need to make sure the tracking var is already provided
		if ( count($this->config->HTMLTemplate->expandingBox->tracking) === 0 ) {
		
			$this->webLog("Initializing expanding boxes JS", __METHOD__);
			$this->templateModify('bodyJs', '
			
				var initSetupTrack = {};
			
			');
			
		}
		
		//  check if this was already inserted
		if ( in_array($options->divId, $this->config->HTMLTemplate->expandingBox->tracking) ) {
			
			$this->webLog("Content already inserted with this id!", __METHOD__, 'error', $options->divId);
			
		} else $this->config->HTMLTemplate->expandingBox->tracking[] = $options->divId;
	
		//  generate the pieceData
		$pieceData = "
		
			<i class='collapseIcon fa fa-angle-up fa-lg'></i>
			<div class='" . implode(" ", $options->classes) . " collapsable collapsed' id='{$divId}-text'>
				{$content}
			</div>
		
		";
		
		//  add the pieceData if it is not set to be returned
		if ( $returnPiece === false ) $this->templateModify($piece, $pieceData);
		
		//  insert the associated js
		$this->templateModify('bodyJs', '
		
			initSetupTrack["'.$divId.'"] = {state: false}
			$("#'.$divId.' i").click(function(e) {
			
				$("#'.$divId.'-text").toggleClass("collapsed");
				if ( initSetupTrack["'.$divId.'"].state === false ) {
				
					$("#'.$divId.' i").removeClass("fa-angle-up").addClass("fa-angle-down");
					initSetupTrack["'.$divId.'"].state = true;
					
				} else {
				
					$("#'.$divId.' i").removeClass("fa-angle-down").addClass("fa-angle-up");
					initSetupTrack["'.$divId.'"].state = false;
					
				}
				
			});
		
		');
		
		//  return piece data instead of sending it ourselves
		if ( $returnPiece === true ) return $pieceData;
		
	}
	
	function templateMakeLoginBox($piece, $returnPiece=false, $hidden=false) {
		
		//  generate the pieceData
		$hiddenPiece = ( $hidden === true ) ? 'style="display: none;"' : "";
		$pieceData = '
		
			<div id="loginDiv" ' . $hiddenPiece . '>
		
				<form id="loginForm" action="/login/signin.html" method="POST">
				<div class="inputColumnsTwo">
					<div>
						Username
					</div>
					<div>
						<input type="text" name="loginUsername">
					</div>
				</div>
				
				<div class="inputColumnsTwo">
					<div>
						Password
					</div>
					<div>
						<input type="password" name="loginPassword">
					</div>
				</div>
				
				<div style="float: right; clear: right;"><div style="float: left; clear: left;">Remember Me</div> <input style="float: left; clear: right;" type="checkbox" value="true" name="rememberMe"></div>
				
				<input type="submit" value="Login" style="float: left; clear: left; width: 40%;"> <div style="float: right; clear: right;"><a href="login/forgotPassword.html">Forgot Password</a></div>
				</form>
			
			</div>
		
		';
		
		//  add the pieceData if it is not set to be returned
		if ( $returnPiece === false ) $this->templateModify($piece, $pieceData);
		if ( $returnPiece === true ) return $pieceData;
		
	}
	
	function templateMakeError($errorText, $piece='mainBody', $returnPiece=false) {
	
		if ( $returnPiece === false ) $this->templateModify($piece, "<div class='error'>{$errorText}</div>");
		if ( $returnPiece === true ) return "<div class='error'>{$errorText}</div>";
		
	}
	
}

?>