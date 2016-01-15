<!DOCTYPE HTML>
<html>
<head>
<meta charset="utf-8">
<title>[title]</title>
<style>
	body { font-family: calibri; margin: 5px; font-size: 16px; background-color: #000000; color: #ffffff; }
	input { border: #000000 solid 1px; padding: 2px; background-color: #6B93C2; }
	.inputColumnsTwo input { width: 100%; }
	input[type=submit] { background-color: #6B93C2; }
	i { cursor: pointer; }
	a { color: #0060FF; text-decoration: none;}
	a:visited { color: #0060FF; }
	a:hover { color: #FFCC00; }
	
	/* ids */
	#menu1 { float: left; margin-left: 4px; min-width: 250px; border: #FFFFFF 1px solid; padding: 5px; }
	#mainBody { float: left; padding: 5px; min-width: 1000px; max-width: 1000px; }
	#mainBodyContent { background-color:#001433; padding-left: 5px; padding-right: 5px; }
	#footer { float: left; width: 100%; margin-top: 10px; padding-top: 1px; padding-left: 1px; color: #3C6291 ; border-top: #FFFFFF solid 1px; }
	#loginDiv {  }
	
	/* classes */
	.header { font-weight: bold; font-size: 22px; padding-bottom: 2px; border-bottom: #1A00FF solid 2px; margin-bottom: 10px; }
	.header1 { font-weight: bold; font-size: 20px; padding-bottom: 2px; border-bottom: #FFFFFF solid 1px; margin-bottom: 5px; width: 80%; color:#4385FE; }
	.header2 { font-weight: bold; font-size: 18px; padding-bottom: 2px; color: #8CB8FF; }
	.menuHeaderMain { margin: 5px; padding: 2px; font-weight: bold; text-align: center; background-color: #003399; border: #0099FF solid 1px; }
	.menuContent { margin: 5px; }
	.inputColumnsTwo { padding: 5px; }
	.inputColumnsTwo div:first-child { float: left; clear: left; margin-bottom: 5px; }
	.inputColumnsTwo div:last-child { float: right; clear: right; text-align: right; margin-bottom: 5px; }
	.error { color: #FF0000; }
	.bq1 { position: relative; margin: 10px; padding: 10px; background-color: #DDDDDD; border: #000000 solid 1px; overflow: hidden; }
	.bq2 { position: relative; margin: 10px; padding: 10px; background-color: #BBBBBB; border: #000000 solid 1px; overflow: hidden; }
	.bq3 { position: relative; margin: 10px; padding: 10px; background-color: #999999; border: #000000 solid 1px; overflow: hidden; }
	.bq4 { position: relative; margin: 10px; padding: 10px; background-color: #666666; border: #FFFFFF solid 1px; color: #FFFFFF; overflow: hidden; }
	
	/* animation classes */
	.collapseIcon { position: absolute; top: 30px; right: 15px; z-index: 99999; }
	.collapsed { height: 30px; }
	
	/* extra goodies */
	[css]
</style>

[header]

<script src="//code.jquery.com/jquery-2.2.0.min.js"></script>

<script language="javascript">
[headerJs]
</script>

</head>
<body>

<div style="width: 100%; height: 100%; min-width: 1300px; overflow: auto;">

	<div id="mainBody">

		<div style="float: left; clear: both;"><div class="error">[error1]</div></div>
    	
        <div style="float: left; clear: both; width: 100%;">
        [mainBody]
        
        [footer]
		</div>

    </div>
    
    <div id="menu1">
    [menu1]
    </div>

</div>

<script language="javascript" src="/js/login.js"></script>

<script language="javascript">
[bodyJs]
</script>

</body>
</html>