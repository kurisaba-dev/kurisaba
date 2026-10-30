<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset={%KU_CHARSET}" />
<title>{%KU_NAME}</title>
<link rel="icon" type="image/ico" href="{%KU_WEBPATH}/static/images/favicon.ico" sizes="32x32">
<link rel="shortcut icon" href="{%KU_WEBPATH}/static/images/favicon.ico" />
<link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/static/css/menu_global.css?v={%KU_JS_CSS_VER}" />

<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
<script type="text/javascript"><!--
		var this_board_dir = '{$board.name}';
		var ku_boardspath = '{%KU_BOARDSPATH}';
		var ku_webpath = '{%KU_WEBPATH}';
		var ku_defaultboard = '{%KU_DEFAULTBOARD}';
		var ku_maxfilesize = 0;
		var ku_js_css_ver = {%KU_JS_CSS_VER};
		var ku_captchaenabled = '{%KU_CAPTCHAENABLED}';
		var locale = '{$locale}';
		var embeds = Array();
{if $replythread > 0}
		var ispage = false;
{else}
		var ispage = true;
{/if}
//--></script>
<link rel="stylesheet" type="text/css" href="{%KU_WEBPATH}/static/css/img_global.css?v={%KU_JS_CSS_VER}" />

{loop $styles}
	<link rel="{if $ neq %KU_DEFAULTSTYLE}alternate {/if}stylesheet" type="text/css" href="{%KU_WEBPATH}/static/css/styles/menu_{$}.css?v={%KU_JS_CSS_VER}" title="{$|capitalize}" />
{/loop}

{loop $ku_styles}
	<link rel="{if $ neq $__.ku_defaultstyle}alternate {/if}stylesheet" type="text/css" href="{%KU_WEBPATH}/static/css/styles/{$}.css?v={%KU_JS_CSS_VER}" title="{$|capitalize}" />
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
	document.write('<script src="{%KU_WEBPATH}/static/javascript/jquery-1.11.1.min.js"><\/script>');
}
</script>
<script>
	document.write('<script src="{%KU_WEBPATH}/static/javascript/kusaba.new.js?v={%KU_JS_CSS_VER}"><\/script>');
</script>	

<!-- <script src="{%KU_WEBPATH}/static/javascript/kusaba.new.js?v={%KU_JS_CSS_VER}"></script> -->
		
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
