<?php

$loc = "Jaki Blue Home";

$this->templateLoadTitle('Home');
$this->templateModify('mainBody', '
	
	<div class="header">
		Welcome to Jaki Blue!
	</div>
	
	<div id="mainBodyContent">
	
	Hi! I am <a href="https://www.realmeye.com/player/Jakisaurus" target="_new">Jakisaurus</a>. Jaki Blue is a web portal I created as a means to track information and events within the Realm community.
	
	<div class="header2">
	<br>Site Index
	</div>
	
	| <a href="/incs">Inc Storm Center</a> | <a href="/cheaters">Cheater List</a> | <a href="/scammers">Scammer List</a> |
	
	</div>
	
');

?>