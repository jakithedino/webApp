<?php

$loc = "path/cheaters";

$this->templateLoadTitle('Cheaters List');
$this->templateModify('mainBody', '
	
	<div class="header">
		Jaki Blue Cheaters List
	</div>
	
	<div id="mainBodyContent">
	
		This list is maintained by a select group of <a href="/members">individuals</a>. It\'s goal is to be open and transparent.
		
		<br><br>If you would like to apply to join the list of approved reporters, please <a href="apply">send us an application</a>.
		
		<br><br>The names included in this list were visually spotted hacking or admitting they hack. The O / S Score stands for the Objective and Subjective Scores. 
		
		<br><br>All confirmed hacks are added together to create the O Score. The S Score is the the net number of Yes votes multiplied by the O Score.
		
		<div class="header1">
		<br>Cheater List
		</div>
	
');

//  if this user has addition permission, add the content to the page
if ( $this->config->profile->customPerms->sitePerms->cheaters->add === true ) {
	
	//  generates ui feature js and insert it
	$this->jbMakeAddCheater();
	
}

$this->jbMakeCheaterList();
	
$this->templateModify('mainBody', '	

	<div class="header2">
	<br>JakiBlue API
	</div>
	
	Did you know that you can query our cheater database via an API? <a href="/apiHelp">Click here</a> for more information.

	</div>
	
');



?>