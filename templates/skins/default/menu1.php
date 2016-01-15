<?php

if ( $this->config->profile->group === 'public' ) {

	$this->templateModify('menu1', '

		<div class="menuHeaderMain">
		Account Login
		</div>
	
		<div class="menuContent">
	
			<div style="text-align: center;">
			
				<div><a href="#" class="expandLoginBox">Click to login</a></div>
				
			</div>
	
	');
	
	$this->templateMakeLoginBox('menu1', false, true);
	
	$this->templateModify('menu1', '
		
			<div style="text-align: center; clear: both;">
			
				<div style="text-decoration: italic;"><a href="/login">Problems with the above link?</a></div>
				
			</div>
			
		</div>
	
	');
		
}

$this->templateModify('menu1', '

	<div class="menuHeaderMain">
	Site Menu
	</div>

	<div class="menuContent">

');

if ( $this->config->profile->group != 'public' ) {

	$this->templateModify('menu1', "
	
		Welcome, {$this->config->profile->name}!
	
		<br><br>
	
	");
	
}

$this->templateModify('menu1', "

		<a href='/index'>Home Page</a>
		<br><a href='/incs'>Inc Storm Center</a>
		<br><a href='/cheaters'>Cheater List</a>
		<br><a href='/scammers'>Scammer List</a>
		<br><a href='/apiHelp'>JakiBlue API</A>

	</div>

");

//  members menu options
if ( $this->config->security->routing->groups->{$this->config->profile->group}->groupid >= $this->config->security->settings->memberGroupId ) {
	
	$this->templateModify('menu1', '

		<div class="menuHeaderMain">
		Member Menu
		</div>
	
		<div class="menuContent">

			<a href="/account">Your Profile</a>
			<br><a href="/memberHowto">How to and Help</a>
			<br><a href="/account/logout">Logout</a>

		</div>
	
	');
	
}

//  admin menu options
if ( $this->config->security->routing->groups->{$this->config->profile->group}->groupid >= $this->config->security->settings->adminGroupId ) {
	
	$this->templateModify('menu1', '

		<div class="menuHeaderMain">
		Admin Menu
		</div>
	
		<div class="menuContent">

			<a href="/admin/members">Member List</a>
			<br><a href="/admin/addUser">Add User</a>
			<br><a href="/admin/settings">System Settings</a>
			<br><a href="/admin/logs">System Logs</a>

		</div>
	
	');
	
}

?>