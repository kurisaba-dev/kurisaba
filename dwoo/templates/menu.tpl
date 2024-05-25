<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<script> var is_menu_frame = true; </script><script> var search_phrases = Array('{$search_phrases}'); </script>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>{%KU_NAME} Navigation</title>
{if %KU_MENUTYPE eq 'normal'}
  <link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/css/menu_global.css?v={%KU_CSSVER}" />
  {loop $styles}
    <link rel="{if $ neq %KU_DEFAULTSTYLE}alternate {/if}stylesheet" type="text/css" href="{%KU_WEBPATH}/css/styles/menu_{$}.css?v={%KU_CSSVER}" title="{$|capitalize}" />
  {/loop}
{else}
	{literal}<style type="text/css">body { margin: 0px; } h1 { font-size: 1.25em; } h2 { font-size: 0.8em; font-weight: bold; color: #CC3300; } ul { list-style-type: none; padding: 0px; margin: 0px; } li { font-size: 0.8em; padding: 0px; margin: 0px; }</style>{/literal}
{/if}

<script type="text/javascript">
	var style_cookie = "kustyle";
	var ku_boardspath = '{%KU_BOARDSPATH}';
	var ku_defaultboard = '{%KU_DEFAULTBOARD}';
</script>
<link rel="shortcut icon" href="{%KU_WEBFOLDER}favicon.ico" />
<script type="text/javascript" src="{%KU_WEBFOLDER}lib/javascript/gettext.js"></script>
<!-- <script type="text/javascript" src="{%KU_WEBFOLDER}lib/javascript/menu.js"></script> -->
<script src="{%KU_WEBFOLDER}lib/javascript/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="{%KU_WEBFOLDER}lib/javascript/kusaba.new.js?v={%KU_JSVER}"></script>
<script type="text/javascript"><!--
var _SITENAME = '{%KU_NAME}';
//--></script>
	<script src="lib/javascript/frame.js?v={%KU_JSVER}"></script>
<script type="text/javascript"><!--
var ku_boardspath = '{%KU_BOARDSPATH}';
{if $showdirs eq 0 && $files.0 neq $files.1 }
	if (getCookie(tcshowdirs) == yes) {
		window.location = '{%KU_BOARDSPATH}/{$files.1}';
	}
{/if}

function showstyleswitcher() {
	var switcher = document.getElementById('sitestyles');
	var state = switcher.getAttribute("data-expanded");
	if (state == "1") {
		document.getElementById('sitestyles-expanded').style.display = "none";
		document.getElementById('sitestyles-normal').style.display = "";
		switcher.setAttribute("data-expanded", "0");
	} else {
		document.getElementById('sitestyles-expanded').style.display = "";
		document.getElementById('sitestyles-normal').style.display = "none";
		switcher.setAttribute("data-expanded", "1");
	}
	return false;
}
{literal}
function toggle(button, area) {
	var tog=document.getElementById(area);
	if(tog.style.display)	{
		tog.style.display="";
	} else {
		tog.style.display="none";
	}
	button.innerHTML=(tog.style.display)?'+':'&minus;';
	setCookie('nav_show_'+area, tog.style.display?'0':'1', 30);
}

function removeframes() {
	var boardlinks = document.getElementsByTagName("a");
	for(var i=0;i<boardlinks.length;i++) if(boardlinks[i].className == "boardlink") boardlinks[i].target = "_top";

	document.getElementById("removeframes").innerHTML = '{/literal}{t}Frames removed{/t}{literal}.';
}
function reloadmain() {
	if (parent.main) {
		parent.main.location.reload();
	}
}
{/literal}
function hidedirs() {
	setCookie('tcshowdirs', '', 30);
	{if $files.0 eq $files.1}
		location.reload(true)
	{else}
		window.location = '{%KU_WEBFOLDER}{$files.0}';
	{/if}
}
function showdirs() {
	setCookie('tcshowdirs', 'yes', 30);
	{if $files.0 eq $files.1}
		location.reload(true)
	{else}
		window.location = '{%KU_WEBFOLDER}{$files.1}';
	{/if}
}
{literal}
function updatenewpostscount() {
	if (!localStorage['lastvisits']) return;
    $.ajax({
        url: '/api.php?id=0&method=get_new_posts_count&params={"timestamps":'+localStorage['lastvisits']+'}',
        success: function(data) {
            iter_obj(data.result, function(brd, val) {
            	if(val != 0 || $('#newposts_'+brd).text() !== '') {
            		var newtext = (val == 0) ? '' : ' ('+val+')';
            		$('#newposts_'+brd).text(newtext);
            	}
            });
        },
        error: function() {
            alert(_.oops);
        }
    });
}
{/literal}
function iter_obj(object, callback) {
    for (var property in object) {
        if (object.hasOwnProperty(property)) {
            callback(property, object[property]);
        }
    }
}
//--></script>
<base target="main" />
</head>
<body style="overflow: auto; padding: 8px; margin: 0px; width: unset;">
<h1><a href="{%KU_WEBFOLDER}" target="_top" title="{t}Front Page{/t}">{%KU_NAME}</a></h1>
<ul>
{if $faq_enabled}
    <li><a href="/faq/" class="boardlink">[ FAQ ]</a></li>
{/if}
{if $showdirs eq 0}
	<li><a onclick="showdirs();" href="{$files.1}" target="_self">[{t}Show Directories{/t}]</a></li>
{else}
	<li><a onclick="hidedirs();" href="{$files.0}" target="_self">[{t}Hide Directories{/t}]</a></li>
{/if}
{if %KU_STYLESWITCHER && %KU_MENUTYPE eq 'normal'}
	<li id="sitestyles" data-expanded="0"><span id="sitestyles-normal"><a onclick="showstyleswitcher(); return false;" href="#" target="_self">[{t}Site Styles{/t}]</a></span>
<span id="sitestyles-expanded" style="display:none">
	<a onclick="showstyleswitcher(); return false;" href="#" target="_self">[{t}Styles{/t}]</a>:
	{loop $styles}
		[<a href="#" title="{$|capitalize}" onclick="Styles.change('{$|capitalize}', false, true);/*reloadmain();*/ return false;" style="display: inline;" target="_self">{$|substr:0:1|upper}</a>]{if !$dwoo.loop.default.last} {/if}
	{/loop}
</span></li>
{/if}
{* if %KU_MENUTYPE eq 'normal'}
	<li id="removeframes"><a href="#" onclick="removeframes(); return false;" target="_self">[{t}Remove Frames{/t}]</a></li>
{/if *}
<li id="refreshnewposts"><a href="#" onclick="updatenewpostscount(); return false;" target="_self">Обновить</a></li>
<li><a href="{%KU_BOARDSPATH}/single.php" class="boardlink">[ Однопоток постов ]</a></li>
</ul>
{if empty($boards)}
	<ul>
		<li>{t}No visible boards{/t}</li>
	</ul>
{else}

	{foreach name=sections item=sect from=$boards}
	
		{if %KU_MENUTYPE eq 'normal'}
			<h2>
		{else}
			<h2 style="display: inline;"><br />
		{/if}
		{if %KU_MENUTYPE eq 'normal'}
			<span class="plus" onclick="toggle(this, '{$sect.abbreviation}');" title="{t}Click to show/hide{/t}">{if $sect.hidden eq 1}+{else}&minus;{/if}</span>&nbsp;
		{/if}
		{$sect.name}</h2>
		{if %KU_MENUTYPE eq 'normal'}
			<div id="{$sect.abbreviation}"{if $sect.hidden eq 1} style="display: none;"{/if}>
		{/if}
		<ul>
		{if count($sect.boards) > 0}
			{foreach name=brds item=brd from=$sect.boards}
				<li><table class="boardlink{if $brd.trial eq 1} trial{/if}{if $brd.popular eq 1} pop{/if}" style="border-collapse: collapse;"><tr><td width="100%" style="padding: 0px;">				<a href="{%KU_BOARDSPATH}/{$brd.name}/">
				{if $showdirs eq 1}
					/{$brd.name}/ - 
				{/if}
				{$brd.desc}
				{if $brd.locked eq 1}
					<img src="{%KU_BOARDSPATH}/css/images/locked.gif" border="0" alt="{t}Locked{/t}">
				{/if}
				<span id="newposts_{$brd.name}"></span>
				</a></td><td style="padding: 0px;">				<a href="{%KU_BOARDSPATH}/{$brd.name}/catalog.html">[Каталог]</a>				</td></tr></table>				</li>
			{/foreach}
		{else}
			<li>{t}No visible boards{/t}</li>
		{/if}
		</ul>
		{if %KU_MENUTYPE eq 'normal'}
			</div>
		{/if}
	{/foreach}
{/if}
<h2><span class="plus" onclick="toggle(this, 'ilinks');" title="Нажмите Показать/Спрятать">&minus;</span>&nbsp;Треды</h2>
<div id="ilinks">
<ul>
{$special_threads}
</ul>
</div>

<h2><span class="plus" onclick="toggle(this, 'search');" title="Нажмите Показать/Спрятать">&minus;</span>&nbsp;Поиск</h2>
<div id="search">
<ul>
<li>Перейти к посту:<form method="get" action="/read.php">
<input type="text" size="3" name="b" value="{%KU_SEARCH_BOARD}"></input>
<input type="text" size="7" name="p" value="{%KU_SEARCH_THREAD}" class="defaultfield" id="searchpostmenu" onfocus="check_field('searchpostmenu',true);" onblur="check_field('searchpostmenu',false);"></input>
<input type="submit" value="Go!"></input>
<input type="hidden" name="t" value="0"></input>
<input type="hidden" name="issearch" value="true"></input>
</form>
<span style="font-size: 8px">&nbsp;</span></li>
<li>Поиск текста на борде:<form method="get" action="/read.php">
<input type="text" size="3" name="b" value="{%KU_SEARCH_BOARD}"></input>
<input type="text" size="7" name="v" value="{$search_phrase}" class="defaultfield" id="searchtextmenu" onfocus="check_field('searchtextmenu',true);" onblur="check_field('searchtextmenu',false);"></input>
<input type="submit" value="Go!"></input>
</form>
<span style="font-size: 8px">&nbsp;</span></li>
</ul>
</div>

<script type="text/javascript">
$(document).ready(function() {
	updatenewpostscount();
	$('#refreshnewposts').click(updatenewpostscount);
})</script>
</body>
</html>
