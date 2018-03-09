<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <link rel="icon" type="image/ico" href="{%KU_WEBPATH}/favicon.ico" sizes="32x32">
  <link rel="stylesheet" type="text/css" href="css/front/{$cssfile}.css?v={%KU_CSSVER}">

  <!-- <script src="lib/javascript/jquery-1.11.1.min.js"></script>
  <script src="lib/javascript/snowfall.jquery.js"></script> -->
  <script>
	var front_mode = "{$front_mode}";


	$(document).ready(function(){
		if ( front_mode != "newyear" ) return;
		//$(document).snowfall({ deviceorientation : true, round : true, minSize: 6, maxSize:10,  flakeCount : 150 });
	});

  </script>
  <title>KURISU;IMAGEBOARD</title>
</head>

<body style="background: url('images/front/{$bgpath}/bg/{$bgname}.jpg') #000 no-repeat center; background-size: cover;">
  <div id="wrapper">
    <h1>KURISU;IMAGEBOARD</h1>
    <div id="c">
There is no end though there is a start in space. — Infinity.<br>
It has its own power, it ruins, and it goes though there is a start also in the star. — Finite.<br>
Only the person who has wisdom can read the most foolish one from the history.<br>
The fish that lives in the sea doesn't know the world in the land. It also ruins and goes if they have wisdom.<br>
It is funnier that man exceeds the speed of light than fish start living in the land.<br>
<strong>It can be said that this is a final ultimatum from the god to the people who can fight.</strong><br>
<br>Posts in /sg/: {$postcount + 121} | Disk space: {math "$disktotal - $diskfree" %.2f} / {math "$disktotal" %.2f} MB | Past 24h: {$postcountz} <span style="color: #1f1f1f">| Past hour: {$postcounth}</span>
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
			<li><a href="/sg/">/sg/ - steins;gate</a></li>
			<li><a href="/vg/">/vg/ - video;games</a></li>
			<li><a href="/kusaba.php" target="_top">frameset</a></li>
			<li><a href="/single.php" target="_top">feed</a></li>
			<li><a href="/faq/" target="_top" style="color: #ffff00; font-weight: bold;">FAQ</a></li>
		</ul>
	</div>
</body>

</html>
