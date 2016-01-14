<?php

$loc = "path/cheaters";

$this->templateLoadTitle('Cheaters List');
$this->templateModify('mainBody', '
	
	<div class="header">
		Jaki Blue Cheaters List
	</div>
	
	This list is maintained by a select group of individuals. It\'s goal is to be open and transparent.
	
	<br><br>If you would like to apply to join the list of approved reporters, please <a href="apply">send us an application</a>.
	
	<div class="header2">
	<br>Cheater List
	</div>
	
	| <a href="incs">Inc Storm Center</a> | <a href="cheaters">Cheater List</a> | <a href="scammers">Scammer List</a> |
	
');

?>