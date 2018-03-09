<script type="text/javascript">
	boardid = '{$board.id}';
	(function()
	{
		var built = {time();} + 10800;
		var lastvisits = localStorage['lastvisits'] ? (JSON.parse(localStorage['lastvisits']) || { }) : { };
		var last_ts = lastvisits.hasOwnProperty(boardid) ? parseInt(lastvisits[boardid]) : 0;
		if(last_ts < built)
		{
			lastvisits[boardid] = built;
			localStorage.setItem('lastvisits', JSON.stringify(lastvisits));
		}
	})();
</script>

{$is_board_page = 1}

<form id="delform" action="{%KU_CGIPATH}/board.php" method="post" enctype="multipart/form-data" onsubmit="return js_send_delform(this);">
	<input type="hidden" name="board" value="{$board.name}" />
	{foreach name=thread item=postsa from=$posts}
		<hr />
			{foreach key=postkey item=post from=$postsa}

				{if $post.parentid eq 0}
					<span id="unhidethread{$post.id}{$board.name}" style="display: none;">
						{t}Thread{/t}
						<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{$post.id}.html">{$post.id}</a>
						{t}hidden.{/t}
						<a href="#" onclick="javascript:togglethread('{$post.id}');return false;" title="{t}Раскрыть тред{/t}">
							<img src="{$cwebpath}css/icons/blank.gif" border="0" class="unhidethread spritebtn" alt="{t}Раскрыть тред{/t}" />
						</a>
					</span>
					<div id="thread{$post.id}{$board.name}">
						<script type="text/javascript">
							if (localStorage['hiddenThreads.' + '{$board.name}'] && in_array('{$post.id}', localStorage['hiddenThreads.' + '{$board.name}'].split(',') ) )
							{
								document.getElementById('unhidethread{$post.id}{$board.name}').style.display = 'inline-block';
								document.getElementById('thread{$post.id}{$board.name}').style.display = 'none';
							}
						</script>
				{/if}

				{include "img_post.tpl"}
				
				{if $post.parentid eq 0}
					<table width="100%" class="postnode">
						<tr>
							<td style="margin: 0px; padding: 0px;">
								<div id="replies{$post.id}{$board.name}" class="replies">
									{if $post.replies}
										<span class="omittedposts">
											{if %KU_EXPAND}<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}.html" onclick="javascript:expandthread('{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}','{$board.name}');return false;" title="{t}Expand Thread{/t}">{/if}
											{if $locale == 'ru'}
												{omitted_syntax($post.replies, $post.images)}
											{else}
												{if $post.stickied eq 0}
													{$post.replies}
													{if $post.replies eq 1}
														{t lower="yes"}Post{/t} 
													{else}
														{t lower="yes"}Posts{/t} 
													{/if}
												{else}
													{$post.replies}
													{if $post.replies eq 1}
														{t lower="yes"}Post{/t} 
													{else}
														{t lower="yes"}Posts{/t} 
													{/if}
												{/if}
												{if $post.images > 0}
													{t}and{/t} {$post.images}
													{if $post.images eq 1}
														{t lower="yes"}Image{/t} 
													{else}
														{t lower="yes"}Images{/t} 
													{/if}
												{/if}
												{t}omitted{/t}.{/if}{if %KU_EXPAND}</a>
											{/if}
										</span>
									{/if}
				{/if}
			{/foreach}
								</div>
							</td>
						</tr>
					</table>
		</div>
	{/foreach}
	<table class="userdelete" width="100%" style="float:none; margin-top: 5px;">
		<tbody>
			<tr>
				<td>
				{*</td>*}
			{*</tr>*}
		{*</tbody>*}
	{*</table>*}
{*</form>*}
