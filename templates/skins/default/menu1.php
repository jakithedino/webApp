<?php

$this->templateModify('menu1', '

	<div class="menuHeaderMain">
	Site Menu
	</div>

	<div class="menuContent">

');

if ( $this->config->profile->group === 'public' ) {

	$this->templateModify('menu1', '
	
		<div style="text-align: center;">Please login</div>
	
	');
	
	$this->templateMakeLoginBox('menu1');
		
} else {

	$this->templateModify('menu1', "
	
		Welcome, {$this->config->profile->name}!
		
		<br><br><a href='/index'>Home Page</a>
		<br><a href='/incs'>Inc Storm Center</a>
		<br><a href='/cheaters'>Cheater List</a>
		<br><a href='/scammers'>Scammer List</a>
	
	");
	
}

$this->templateModify('menu1','</div>');

?>