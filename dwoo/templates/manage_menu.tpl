<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>{t}Manage Boards{/t}</title>
	<link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/css/manage_page.css?v={%KU_CSSVER}" />
	<link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/css/manage_menu.css?v={%KU_CSSVER}" />
<link rel="shortcut icon" href="{%KU_WEBPATH}/favicon.ico" />
{literal}
<script type="text/javascript">
function toggle(button, area) {
	var tog=document.getElementById(area);
	if(tog.style.display)	{
		tog.style.display="";
	} else {
		tog.style.display="none";
	}
	createCookie('nav_show_'+area, tog.style.display?'0':'1', 365);
}
</script>
{/literal}
<base target="manage_main" />
</head>
<body>
<div id="navbar-container" style="left: 0px; ">
<h1 id="title">{t}Manage Boards{/t}</h1>
<ul>
	{$links}
</ul>
</div>
</body>
</html>
