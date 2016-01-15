<?php

class jbCheaters {
	
	var $parent;
	var $self;
	var $configImport;
	function __construct(&$parent) { 
	
		$this->parent =& $parent;
		$this->configImport = (object)array(
			'cheaters' => (object)array(
				'resultsPerPage' => 50,
				'cheatList' => array('cheatWallHack', 'cheatNoDebuff', 'cheatInvViewer'),
				'cheatScores' => array(
					'cheatWallHack' => 0.60,
					'cheatNoDebuff' => 0.40,
					'cheatInvViewer' => 0.60
				)
			)
		); 
				
	}
	function __call($method, $args) { return call_user_func_array(array($this->parent, $method), $args); }
	function __get($name) { return $this->parent->{$name}; }
	function __set($name, $value) { $this->parent->{$name} = $value; }
	function __isset($name) { return isset($this->parent->{$name}); }
	function __unset($name) { unset($this->parent->{$name}); }
	
	//  generate a list of cheaters
	function jbMakeCheaterList($pieces=false) {
		
		//  basic sanity
		if ( $pieces === false ) $pieces = (object)array('html' => 'mainBody', 'js' => 'bodyJs');
		if ( !is_object($pieces) ) {
		
			$this->webLog($this->crLanguage(__CLASS__, 'missingKeyType', "pieces"), __METHOD__, 'error');
			$this->templateModify('mainBody', '<div class="error">Failed to initialize addCheater.js</div>');
			return false;
			
		} else {
		
			$keys = array('html', 'js');
			foreach ( $pieces as $key=>$value ) {
			
				//  invalid key detection
				if ( !in_array($key, $keys) ) {
					
					$this->webLog($this->crLanguage('generic', 'invalidKey', array("pieces::{$key}", implode("|", $keys))), __METHOD__, 'error');
					$this->templateModify('mainBody', '<div class="error">Failed to initialize addCheater.js</div>');
					return false;
					
				}
				
			}
			
		}
		
		//  determine what page we're on first
		$pageNum = 1;
		if ( is_numeric($this->config->profile->do) ) $pageNum = $this->config->profile->do;
		$offset = $this->config->cheaters->resultsPerPage * ($pageNum-1);
		
		//  query the cheater list
		if ( $result = $this->mysql_query("SELECT * FROM cheaters ORDER BY timestamp DESC LIMIT {$offset},{$this->config->cheaters->resultsPerPage}", array('direct' => true)) ) {

			$this->templateModify($pieces->html, "There are {$result->num_rows} total entries in the cheater database.");
			
			//  do something if more than 0 results
			if ( $result->num_rows > 0 ) {
			
				//  determine total page count by dividing and then rounding up to next whole number
				$totalPageCount = ceil($result->num_rows/$this->config->cheaters->resultsPerPage);
				$this->templateModify($pieces->html, "
				
					<div id='cheaterList'>
					
				");
				
				//  loop thru the results
				$count = 0;
				$x = 0;
				while ( $data = $result->fetch_object() ) {
				
					//  first run things
					if ( $count === 0 ) {
					
						$this->templateModify('css', "
						
							#cheaterList { min-width: 1024px; }
							.cheaterListColumn { text-align: center; border: #ffffff solid 1px; margin: 2px; padding: 2px; background-color: #1A00FF; float: left; width: 120px; }
							.cheaterListEntry { text-align: center; width: 120px; float: left; margin-left: 3px; margin-right: 3px; padding-left: 2px; padding-right: 2px; }
							.width160 { width: 160px; }
							.width90 { width: 90px; }
							.width100 { width: 100px; }
							.clearLeft { clear: left; }
							.clearRight { clear: right; }
							.hoverBeforeClick { cursor: pointer; }
							.hoverGreen { background-color: #00350C; }
							.hoverRed { background-color: #35001F; }
						
						");
						
						$this->templateModify($pieces->html, "
						
							<div style='clear: both;'>
								<div class='cheaterListColumn cheaterListKey-cheaterName clearLeft'>Username</div>
								<div class='cheaterListColumn cheaterListKey-cheaterGuild width160'>Guild</div>
								<div class='cheaterListColumn cheaterListKey-timestamp width100'>Addition Date</div>
								<div class='cheaterListColumn cheaterListKey-hackCount width90'>Hack Count</div>
								<div class='cheaterListColumn cheaterListKey-objectiveScore width90'>O / S Score</div>
								<div class='cheaterListColumn cheaterListKey-votesYes width90'>Votes Yes</div>
								<div class='cheaterListColumn cheaterListKey-votesNo width90 clearRight'>Votes No</div>
							</div>
						
						");
						
						
					}
					
					//  generate record data
					$additionDate = date("Y-m-d", $data->timestamp);
					$objScore = 0;
					$hackCount = 0;
					foreach ( $this->config->cheaters->cheatList as $cheatName ) if ( $data->{$cheatName} === 'true' ) {
						
						$objScore = $objScore+$this->config->cheaters->cheatScores[$cheatName];
						$hackCount++;
						
					}
					$bgClass = ( $x === 0 ) ? 'cheaterListOne' : '';  //  eh, not working the way I wanted it to
					$subScore = $objScore * ($data->votesYes-$data->votesNo);
					$this->templateModify($pieces->html, "
						
						<div class='clear: both;'>
							<div class='cheaterListEntry cheaterListKey-cheaterName clearLeft'><a href='/cheaters/viewPlayer/{$data->cheaterName}'>{$data->cheaterName}</a></div>
							<div class='cheaterListEntry cheaterListKey-cheaterGuild width160'><a href='/cheaters/viewGuild/{$data->cheaterGuild}'>{$data->cheaterGuild}</a></div>
							<div class='cheaterListEntry cheaterListKey-timestamp width100'>{$additionDate}</div>
							<div class='cheaterListEntry cheaterListKey-hackCount width90'>{$hackCount}</div>
							<div class='cheaterListEntry cheaterListKey-objectiveScore width90'>{$objScore} / {$subScore}</div>
							<div class='cheaterListEntry cheaterListKey-votesYes width90 hoverBeforeClick' hoverClass='hoverGreen'>{$data->votesYes}</div>
							<div class='cheaterListEntry cheaterListKey-votesNo width90 hoverBeforeClick clearRight' hoverClass='hoverRed'>{$data->votesNo}</div>
						</div>						

					");
					
					//  increment our counter
					$count++;
					( $x === 0 ) ? $x++ : $x = 0;
					
				}
				
				$this->templateModify($pieces->html, "
					
					</div>
				
					<div style='clear: both;'>Showing page {$pageNum} of {$totalPageCount}</div>
					
				");
				
				//  add our voting interface for approved users
				if ( $this->config->profile->customPerms->sitePerms->cheaters->vote === true ) {
						
					//  add the js now
					$file = $this->config->privateJsPath . '/voteCheater.js';
					if ( $content = file_get_contents($file) ) {
						
						$this->webLog("Loaded private JS file voteCheater.js", __METHOD__);
						$this->templateModify($pieces->js, $content);
			
					} else {
					
						$this->webLog("Failed to load private JS file {$file}", __METHOD__, "error");
						$this->templateModify('mainBody', '<div class="error">Failed to load voteCheater.js</div>');
						
					}

				}
				
			}
			
			return true;
			
		} else {
		
			//  some error with the query; this is not good
			$this->webLog("Failed to query the cheaters table", __METHOD__, "error");
			$this->templateModify($piece, "Failed to retrieve cheater database!");
			return false;
			
		}
		
	}
	
	//  insert js for cheaterListAdd
	function jbMakeAddCheater($pieces=false) {
	
		//  basic sanity
		if ( $pieces === false ) $pieces = (object)array('html' => 'mainBody', 'js' => 'bodyJs');
		if ( !is_object($pieces) ) {
		
			$this->webLog($this->crLanguage(__CLASS__, 'missingKeyType', "pieces"), __METHOD__, 'error');
			$this->templateModify('mainBody', '<div class="error">Failed to initialize addCheater.js</div>');
			return false;
			
		} else {
		
			$keys = array('html', 'js');
			foreach ( $pieces as $key=>$value ) {
			
				//  invalid key detection
				if ( !in_array($key, $keys) ) {
					
					$this->webLog($this->crLanguage('generic', 'invalidKey', array("pieces::{$key}", implode("|", $keys))), __METHOD__, 'error');
					$this->templateModify('mainBody', '<div class="error">Failed to initialize addCheater.js</div>');
					return false;
					
				}
				
			}
			
		}
		
		//  try to load the required js file
		$file = $this->config->privateJsPath . '/addCheater.js';
		if ( $content = file_get_contents($file) ) {
			
			$this->templateModify($pieces->html, "
	
				<div id='addCheaterBox'>&gt;&gt; <a href='#'>Add Cheater to Database</a></div>
			
			");
			$this->webLog("Loaded private JS file addCheater.js", __METHOD__);
			$this->templateModify($pieces->js, $content);
			return true;

		} else {
		
			$this->webLog("Failed to load private JS file {$file}", __METHOD__, "error");
			$this->templateModify('mainBody', '<div class="error">Failed to load addCheater.js</div>');
			return false;
			
		}
		
	}
	
}

?>