<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset={%KU_CHARSET}" />
<title>{%KU_NAME}</title>
<link rel="icon" type="image/ico" href="{%KU_WEBFOLDER}favicon.ico" sizes="32x32">
<link rel="shortcut icon" href="{%KU_WEBFOLDER}favicon.ico" />
<link rel="stylesheet" type="text/css" href="{%KU_BOARDSPATH}/css/menu_global.css?v={%KU_CSSVER}" />

<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
<script type="text/javascript"><!--
		var react_api = '{%KU_CLI_REACT_API}';
		var react_ena = '{%KU_REACT_ENA}';
		var react_sitename = {if %KU_REACT_SITENAME}'{%KU_REACT_SITENAME}:'{else}''{/if};
		var this_board_dir = '{$board.name}';
		var ku_boardspath = '{%KU_BOARDSPATH}';
		var ku_cgipath = '{%KU_CGIPATH}';
		var ku_defaultboard = '{%KU_DEFAULTBOARD}';
		var ku_maxfilesize = 0;
		var style_cookie = "kustyle";
		var locale = '{$locale}';
{if $replythread > 0}
		var ispage = false;
{else}
		var ispage = true;
{/if}
//--></script>
<link rel="stylesheet" type="text/css" href="{$cwebpath}css/img_global.css?v={%KU_CSSVER}" />

{loop $styles}
	<link rel="{if $ neq %KU_DEFAULTSTYLE}alternate {/if}stylesheet" type="text/css" href="{%KU_WEBFOLDER}css/styles/menu_{$}.css?v={%KU_CSSVER}" title="{$|capitalize}" />
{/loop}

{loop $ku_styles}
	<link rel="{if $ neq $__.ku_defaultstyle}alternate {/if}stylesheet" type="text/css" href="{$__.cwebpath}css/styles/{$}.css?v={%KU_CSSVER}" title="{$|capitalize}" />
{/loop}

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

<script>
if (!window.jQuery) {
	document.write('<script src="{$cwebpath}lib/javascript/jquery-1.11.1.min.js"><\/script>');
}
</script>
<script>
	document.write('<script src="{$cwebpath}lib/javascript/kusaba.new.js?v={%KU_JSVER}"><\/script>');
</script>	

<!-- <script src="{%KU_WEBPATH}/lib/javascript/kusaba.new.js?v={%KU_JSVER}"></script> -->
		
<style type="text/css">{literal}
body {
	width: 100% !important;
}
{/literal}</style>
</head>
<body>
<h2 style="font-size: 2em;font-weight: bold;text-align: center;">
{$errormsg}
</h2>
{$errormsgext}
<div style="text-align: center;width: 100%;position: absolute;bottom: 10px;">
<br />
<a href="{%KU_BOARDSPATH}/{$boardname}">Back to the <strike>future</strike> board</a>
<br />
<div class="footer" style="clear: both;">
	<div class="legal">	- Kurisaba {%KU_VERSION} -
</div>
</div>
</body>
</html>
