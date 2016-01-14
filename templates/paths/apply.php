<?php

$loc = "path/apply";

if ( $this->config->profile->area === "submit" ) {
	
	
	
} else {

	$this->templateModify('mainBody', '
	
		<div class="header">
			Contributor Application
		</div>
		
		Thank you for your interest in helping us expand this list! Please fill out the form below and we will get back to you.
		
		<br><br>...
	
	');
	
}

?>