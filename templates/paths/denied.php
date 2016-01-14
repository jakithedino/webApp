<?php

$log = "path/denied";
$this->templateModify('mainBody', '
	
	<div class="header">
		Error Processing Request
	</div>
	
	There has been an error processing your request. If this continues, try clearing your cache and try again.
	
');

?>