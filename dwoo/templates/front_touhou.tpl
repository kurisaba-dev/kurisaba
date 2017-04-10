<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="icon" type="image/ico" href="{%KU_WEBPATH}/favicon.ico" sizes="32x32">
  <link rel="stylesheet" type="text/css" href="css/{$cssfile}.css?v={%KU_CSSVER}">

  <!-- <script src="lib/javascript/jquery-1.11.1.min.js"></script>
  <script src="lib/javascript/snowfall.jquery.js"></script> -->
  <script>
	var front_mode = "{$front_mode}";


	$(document).ready(function(){
		if ( front_mode != "newyear" ) return;
		//$(document).snowfall({ deviceorientation : true, round : true, minSize: 6, maxSize:10,  flakeCount : 150 });
	});

  </script>
  <title>TOUHOU;HIJACKED;IMAGEBOARD</title>
</head>

<body style="background: url('images/front/{$bgpath}/bg/{$bgname}.jpg') #000 no-repeat center; background-size: cover;">
  <div id="wrapper">
    <h1>TOUHOU;HIJACKED;IMAGEBOARD</h1>
    <div id="c">
&nbsp;1. There is no end though there is a start in previously unknown desolate and haunted plains and mountains. — Yatsugatake.<br>
&nbsp;2. It now has its own Hakurei Barrier, but it goes though everything here is started by Yukari. — Gensokyo.<br>
&nbsp;3. Only persons who are courageous enough, or youkai themselves can enter the most mystic place from the history.<br>
&nbsp;4. The one who lives there doesn't know the world outside the land. But for a replacement they may visit either Higan, Misty Lake, or even Eientei (if Kaguya allows, of course).<br>
&nbsp;5. Beware of the Red Devil!<br>
<strong>⑨. Baka.</strong><br>
<br>Donations for Reimu: {$postcount + 121} | Content stolen by Marisa: {math "$disktotal - $diskfree" %.2f} / {math "$disktotal" %.2f} MB | Past 24h: {$postcountz} <span style="color: #1f1f1f">| Past hour: {$postcounth}</span>
    </div>
  </div>
<div class="last" id="posts" style="display: block;">
<!--<h3>last posts from /sg/:</h3>
		<ul>
			{foreach $last_posts last_post}
				<li style="border:1px rgba(255,255,255,0.5) dashed;">
					{date_format $last_post.timestamp "%H:%M"} {$last_post.name}   
					<a href="/{$last_post.board}/sg/res/{$last_post.parentid}.html#{$last_post.id}">#{$last_post.id}</a> 
					{truncate strip_tags($last_post.message) 400}
				</li>	
			{/foreach}
		</ul>-->
</div>
  {$charimg}
	<div class="bottom" id="bottom">
		<ul id="bottom_menu">
			<li><a href="/sg/">/gs/ - gen;sokyo</a></li>
			<li><a href="/vg/">/hg/ - haku;gyokurou</a></li>
			<li><a href="/kusaba.php" target="_top">shrine signpost</a></li>
			<li><a href="/single.php" target="_top">kourindou</a></li>
			<li><a href="/faq/" target="_top" style="color: #ffff00; font-weight: bold;">spell card rules</a></li>
		</ul>
	</div>
</body>

</html>
