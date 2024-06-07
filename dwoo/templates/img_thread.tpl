{if not $isupdate and not $isread}
	<script type="text/javascript">
		boardid = '{$board.id}';
		boardname = '{$board.name}';
		(function() {
			var built = {time();} + 10800;
			var lastvisits = localStorage['lastvisits'] ? (JSON.parse(localStorage['lastvisits']) || { }) : { };
			var last_ts = lastvisits.hasOwnProperty(boardid) ? parseInt(lastvisits[boardid]) : 0;
			if(last_ts < built) {
				lastvisits[boardid] = built;
				localStorage.setItem('lastvisits', JSON.stringify(lastvisits));
			}
		})();
		var newupd_posts = new Array({foreach key=postkey item=post from=$fullposts name=postsloop}{$post.id},{/foreach}0);
		newupd_posts.pop();newupd_posts.shift(); // Discard OP post and trailing 0
		var newupd_replymap = new Array();
	</script>
{/if}

{$is_board_page = 0}

{if not $isexpand and not $isread and not $iscatalog}
	<form id="delform" class="delform-id" action="{%KU_CGIPATH}/board.php" method="post" enctype="multipart/form-data">
		<input type="hidden" name="board" value="{$board.name}" />
{/if}

		{if not $isexpand and not $isread}
			<hr />
		{/if}

		{if not $isread}
			<span id="unhidethread{$posts[0].id}{$board.name}" style="display: none;">
				{t}Thread{/t}
				<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{$posts[0].id}.html">{$posts[0].id}</a>
				{t}hidden.{/t}
				<a href="#" onclick="javascript:togglethread('{$board.name}','{$posts[0].id}');return false;" title="{t}Раскрыть тред{/t}">
					<img src="{$cwebpath}css/icons/blank.gif" border="0" class="unhidethread spritebtn" alt="{t}Раскрыть тред{/t}" />
				</a>
			</span>
			<div id="thread{$posts[0].id}{$board.name}" class="replies">
		{/if}
		
				{foreach key=postkey item=post from=$posts name=postsloop}
					{if $post.board}
						{$board = $post.board}
					{/if}
				
					{include file="img_post.tpl"}
				{/foreach}

				{if $modifier eq 'first100'}
					<span class="omittedposts" style="float: left">
						{$replycount-100}
						{if $replycount-100 eq 1}
							{t lower="yes"}Post{/t} 
						{else}
							{t lower="yes"}Posts{/t} 
						{/if}
						{t}omitted{/t}. {t}First 100 shown{/t}.
					</span>
				{/if}
		
		{if not $isread}
			</div>
		{/if}

{if not $isexpand and not $isread and not $iscatalog}
		<table class="userdelete" width="100%" style="float:none; margin-top: 5px;">
			<tbody>
				<tr>
					<td>
						<div style="float:left">
							<div id="newposts_get">
								<a href="#" onclick="return getnewposts()"><img src="{$cwebpath}css/icons/blank.gif" border="0" class="getnewposts spritebtn" alt="refresh"> Получить новые посты (если есть)</a> <span id="newposts_seconds" style="display: none;">(off)</span>
							</div>
							<div id="newposts_load" style="display:none;">
								<img src="{%KU_WEBPATH}/images/loading16x16.gif" style="vertical-align: text-bottom;"> Загрузка...
							</div>
						</div>
					{*</td>*}
				{*</tr>*}
			{*</tbody>*}
		{*</table>*}
	{*</form>*}
{/if}
