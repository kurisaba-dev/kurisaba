<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
<script type="text/javascript"><!--
		var react_api = '{%KU_CLI_REACT_API}';
		var react_ena = '{%KU_REACT_ENA}';
		var react_sitename = {if %KU_REACT_SITENAME}'{%KU_REACT_SITENAME}:'{else}''{/if};
		var this_board_dir = '{$board.name}';
		var ku_boardspath = '{%KU_BOARDSPATH}';
		var ku_boardsdir = '{%KU_BOARDSDIR}';
		var ku_cgipath = '{%KU_CGIPATH}';
		var ku_defaultboard = '{%KU_DEFAULTBOARD}';
		var ku_maxfilesize = {$maxfilesize};
		var style_cookie = "kustyle";
		var locale = '{$locale}';
		var search_phrases = Array('{$search_phrases}');
{if $replythread != 0}
		var ispage = false;
{else}
		var ispage = true;
{/if}
//--></script>
{if $replythread ne '0'}
<link rel="canonical" href="{%KU_WEBPATH}/{$board.name}/res/{$replythread}.html" />
{/if}
<!-- <link href='https://fonts.googleapis.com/css?family=Roboto:400,700,400italic,700italic&subset=latin,cyrillic' rel='stylesheet' type='text/css'> -->
<link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/css/img_global.css?v={%KU_CSSVER}" />
{loop $ku_styles}
	<link rel="{if $ neq $__.ku_defaultstyle}alternate {/if}stylesheet" type="text/css" href="{%KU_WEBPATH}/css/styles/{$}.css?v={%KU_CSSVER}" title="{$|capitalize}" />
{/loop}
<link href="{$cwebpath}css/prettify.css" type="text/css" rel="stylesheet" />
{if $locale eq 'ja'}
	{literal}
	<style type="text/css">
		*{
			font-family: IPAMonaPGothic, Mona, 'MS PGothic', YOzFontAA97 !important;
			font-size: 1em;
		}
	</style>
	{/literal}
{/if}
<!-- <script src="//cdnjs.cloudflare.com/ajax/libs/headjs/1.0.3/head.load.min.js"></script> -->
<script type="text/javascript" src="{$cwebpath}lib/javascript/gettext.js"></script>
<!-- <script src="//cdnjs.cloudflare.com/ajax/libs/headjs/1.0.3/head.load.min.js"></script> -->
<script>
if (!window.head) {
	document.write('<script src="/lib/javascript/head.load.min.js"><\/script>');
}
</script>
<!-- <script src="//cdnjs.cloudflare.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script> -->
<script>
if (!window.jQuery) {
	document.write('<script src="/lib/javascript/jquery-1.11.1.min.js"><\/script>');
}
</script>
<!-- <script src="//cdnjs.cloudflare.com/ajax/libs/prettify/r298/prettify.min.js"></script> -->
<script>
if (!window.prettyPrint) {
	document.write('<script src="/lib/javascript/prettify/prettify.js"><\/script>');
}
</script>
<!-- <script src="{%KU_WEBPATH}/lib/javascript/kusaba.new.js?v={%KU_JSVER}"></script> -->
<script>
	document.write('<script src="/lib/javascript/kusaba.new.js?v={%KU_JSVER}"><\/script>');
</script>
{if %KU_REACT_ENA}
<script src="{%KU_CLI_REACT_API}/socket.io/socket.io.js"></script>
{/if}
<!-- <script src="//cdnjs.cloudflare.com/ajax/libs/prettify/r298/prettify.min.js"></script> -->
<style>
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: local('Roboto'), local('Roboto-Regular'), url('/css/fonts/Roboto-Regular.woff') format('woff');
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: local('Roboto Bold'), local('Roboto-Bold'), url('/css/fonts/Roboto-Bold.woff') format('woff');
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 400;
  src: local('Roboto Italic'), local('Roboto-Italic'), url('/css/fonts/Roboto-Italic.woff') format('woff');
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 700;
  src: local('Roboto Bold Italic'), local('Roboto-BoldItalic'), url('/css/fonts/Roboto-BoldItalic.woff') format('woff');
}

.scroll
{
position: fixed;
top: 40%;
right: 0%;
z-index: 110;
}

.scroll div
{
width: 10px;
color: #006ab9;
text-align: center;
padding: 7px 7px;
margin-top: 10px;
background:#333;
border-radius: 3px;
box-shadow: 0px 2px 2px #000000;
cursor: pointer;
z-index: inherit;
}
</style>
<script>
function scrolling(elem, direction) {

		if (direction == 'down') {
			var amount = 2;
		} else {
			var amount = -2;
		}

		elem.onmouseout = function () {
			clearInterval(scroll_timer);
		}

		elem.onclick = function () {
			window.getSelection().removeAllRanges();
			amount = amount * 10;
		}

		function stop_scroll() {

			if (window.pageYOffset == window.scrollMaxY || window.pageYOffset == 0) {
				clearInterval(scroll_timer);
			}

		}

		function scroll_it() {
			window.scrollBy(0, amount);
			stop_scroll();

				if (amount > 2 && window.pageYOffset > window.scrollMaxY - 100 || amount < -2 && window.pageYOffset < 100) {
					amount = amount / 10;
				}

		}

	var scroll_timer = setInterval(scroll_it, 7);
}
</script>
</head>
<body>
<div class="scroll"><div onmouseover="scrolling(this, 'up');">∧</div><div onmouseover="scrolling(this, 'down');">∨</div></div>

<div id="boardlist_header_toggler">
	<div id="overlay_menu_toggler" class="content-background overlay-menu">
		<span class="olm-link">[<a href="#" onclick="$('#overlay_menu').slideToggle();return false;"> &gt; </a>]</span>
	</div>
</div>

<div id="boardlist_header">
	<div id="overlay_menu" class="content-background overlay-menu">
		<span class="olm-link">[<a href="#" onclick="$('#overlay_menu').slideToggle();return false;"> &lt; </a>]</span>
		{if !$skipheader}
			<a href="{%KU_CGIPATH}/{$board.name}/">/{$board.name}/</a> - <strong>{$board.desc}</strong>
			&nbsp;[<a href="/{$board.name}/catalog.html">Каталог</a>]
		{else}
			<strong>Однопоток постов</strong>
		{/if}

		&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		
		<span class="olm-link">[<a target="_top" href="{%KU_BOARDSFOLDER}">Главная</a>]</span>
		{if %KU_MAINPAGE ne 'kusaba.php'}
			[<a href="{%KU_WEBPATH}/kusaba.php" target="_top">{t}Фрейм{/t}</a>]
		{/if}
		[<a href="{%KU_WEBPATH}/single.php">Однопоток</a>]
		{if $faq_enabled}
			[<a href="{%KU_WEBPATH}/faq/">FAQ</a>]
		{/if}
		<span class="mobile-nav-disabled" id="mn-normalboards" style="display:none"> 
			<select onchange="javascript:if(selectedIndex != 0) location.href='{%KU_WEBPATH}/' + this.options[this.selectedIndex].value;">
				<option><strong>{t}Boards{/t}</strong></option>
				{foreach name=sections item=sect from=$boardlist}
					{foreach name=brds item=brd from=$sect}							
						{if $brd.name neq $board.name}
							{if isset($brd.desc) and is_array($brd)}							
								<option value="{$brd.name}">/{$brd.name}/ - {$brd.desc}</option>
							{/if}
						{/if}						
					{/foreach}	
				{/foreach}	
			</select>
		</span>
		
		{foreach name=sections item=sect from=$boardlist}
			<b  class="olm-link">[<a href="#" class="sect-exr" id="menubrdlist" onclick="return menuClickExpandElem(this);" data-toexpand="{$sect.abbreviation}">{$sect.nick}</a>]</b>
		{/foreach}
		
		<span class="olm-link">[<a href="#" class="sect-exr" onclick="return menuClickExpandElem(this);" data-toexpand="_options">Настройки</a>]</span>
		<span class="olm-link newsearch">[<a href="#" class="sect-exr" onclick="return menuClickExpandElem(this);" data-toexpand="_search">Поиск</a>]</span>

		{foreach name=sections item=sect from=$boardlist}
			<div class="menu-sect" id="ms-{$sect.abbreviation}">
				{foreach name=brds item=brd from=$sect}
					{if isset($brd.desc) and is_array($brd)}
					<a class="menu-item" title="{$brd.desc}" href="{%KU_BOARDSFOLDER}{$brd.name}/">/{$brd.name}/ - {$brd.desc}</a>
					{/if}
				{/foreach}
			</div>
		{/foreach}

		<div class="menu-sect" style="max-width:600px" id="ms-_options">
			<div>Опции:</div>
			<a href="#" onclick="javascript:menu_pin();return false;">{t}Pin/Unpin{/t}</a>
			<div id="js_settings"></div>
			<div>Скрывать посты с текстом (через точку с запятой):<br><input id="wordstohide" type="text" size="30"> <input type="button" onclick="hide_by_words();" value="Применить"></div>
			<div>Стили:</div>
			{loop $ku_styles}
				[<a href="#" onclick="javascript:Styles.change('{$|capitalize}');return false;">{$|capitalize}</a>]
			{/loop}<br />
		</div>

		<div class="menu-sect" id="ms-_search">
			<table><tr><td>Перейти к посту:</td><td>
				<form style="display: inline;" action="/read.php" method="get">
					<input size="3" name="b" value="{%KU_SEARCH_BOARD}" type="text">
					<input size="7" name="p" value="{%KU_SEARCH_THREAD}" type="text" class="defaultfield" id="searchposttop" onfocus="check_field('searchposttop',true);" onblur="check_field('searchposttop',false);">
					<input value="Перейти" type="submit">
					<input name="t" value="0" type="hidden">
					<input name="issearch" value="true" type="hidden">
				</form>
			</td></tr>
			<tr><td>Поиск по тексту поста:</td><td>
				<form style="display: inline;" method="get" action="/read.php">
					<input size="3" name="b" value="{%KU_SEARCH_BOARD}" type="text">
					<input size="25" name="v" value="{$search_phrase}" type="text" class="defaultfield" id="searchtexttop" onfocus="check_field('searchtexttop',true);" onblur="check_field('searchtexttop',false);">
					<input value="Искать" type="submit">
				</form>
			</td></tr></table>
		</div>
	</div>
</div>
{if $isfeed eq '1'}
<script>var isfeed = true;</script>
{/if}

<table width="98%" border="0" align="center" cellpadding="0" cellspacing="0" class="maintable">
	<tbody>
		<tr>
			<td width="13" class="border-left"></td>
			<td class="content-background" style="padding:9px">
				<span class="oldsearch" style="float: right;">
					Перейти:
					<form style="display: inline;" action="/read.php" method="get">
						<input size="3" name="b" value="{%KU_SEARCH_BOARD}" type="text">
						<input size="7" name="p" value="{%KU_SEARCH_THREAD}" type="text" class="defaultfield" id="searchpostmain" onfocus="check_field('searchpostmain',true);" onblur="check_field('searchpostmain',false);">
						<input value="Перейти" type="submit">
						<input name="t" value="0" type="hidden">
						<input name="issearch" value="true" type="hidden">
					</form>
					Поиск:
					<form style="display: inline;" method="get" action="/read.php">
						<input size="3" name="b" value="{%KU_SEARCH_BOARD}" type="text">
						<input size="25" name="v" value="{$search_phrase}" type="text" class="defaultfield" id="searchtextmain" onfocus="check_field('searchtextmain',true);" onblur="check_field('searchtextmain',false);">
						<input value="Искать" type="submit">
					</form>
				</span>
				<script>Settings.oldSearch(false);</script>
				<br>
				<span class="adminbar"></span>
