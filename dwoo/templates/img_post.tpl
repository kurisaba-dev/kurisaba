<div{if $post.nodolls ne 1} id="repl_kukloshit"{/if}{if $post.parentid eq 0} class="oppost workaround"{/if}>
{if $post.nodolls eq 1}<div id="de-main"></div>{/if}
<table {if $post.parentid eq 0}id="optable"{/if} class="postnode" width="100%">
	<tbody>
		<tr>
			{if not $post.parentid eq 0}
				<td class="doubledash" style="display: none;">
				</td>
			{/if}

			<td {if $post.parentid eq 0}class="postnode op reply_board_{$board.name}"{else}class="reply reply_parent{$post.parentid} reply_board_{$board.name}"{/if} id="reply{$post.id}">
				{if $post.parentid eq 0}<a name="s{$.foreach.thread.iteration}"></a>{/if}
				{if $post.parentid neq 0}{include file="img_post_info.tpl"}{/if}
				{if ($post.file neq '' || $post.file_type neq '' ) && (($post.videobox eq '' && $post.file neq '') && $post.file neq 'removed')}
					{if $post.parentid neq 0}<br />{/if}
					<span class="filesize">
						{if $post.file_type eq 'mp3' or $post.file_type eq 'ogg' or $post.file_type eq 'm4a'}
							{t}Audio{/t}
						{else}
							{t}File{/t}
						{/if}
						<a
							{if %KU_NEWWINDOW}
								target="_blank"
							{/if}
							href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/{if $istempfile}tmp{else}src{/if}/{$post.file}.{$post.file_type}"
						>
						{strip}
							{if isset($post.id3.comments_html)}
								{if $post.id3.comments_html.artist.0 neq ''}
									{$post.id3.comments_html.artist.0}
									{if $post.id3.comments_html.title.0 neq ''}
										&nbsp;—&nbsp;
									{/if}
								{/if}
								{if $post.id3.comments_html.title.0 neq ''}
									{$post.id3.comments_html.title.0}
								{/if}
								{if $post.id3.comments_html.title.0 eq '' and $post.id3.comments_html.artist.0 eq ''}
									{$post.file_original}.{$post.file_type}
								{/if}
							{else}
								{if $post.file_type eq 'webm' or $post.file_type eq 'mp3' or $post.file_type eq 'ogg' or $post.file_type eq 'm4a' or $post.file_type eq 'mp4'}
									{$post.file_original}.{$post.file_type}
								{else}
									{$post.file}.{$post.file_type}
								{/if}
							{/if}
							</a>
							&nbsp;—&nbsp;
							(
								{$post.file_size_formatted}
								{if $post.id3.audio.bitrate neq 0}
									&nbsp;—&nbsp;
									{round($post.id3.audio.bitrate / 1000)}&nbsp;kbps
								{/if}
								{if $post.id3.audio.sample_rate neq 0}
									&nbsp;—&nbsp;
									{$post.id3.audio.sample_rate / 1000} kHz
								{/if}
								{if $post.image_w > 0 && $post.image_h > 0}
									, {$post.image_w}x{$post.image_h}
								{/if}
							)
							{if $post.file_type eq 'jpg' or $post.file_type eq 'gif' or $post.file_type eq 'png' or $post.file_type eq 'webp'}
								&nbsp;
								<a class="extrabtns" target="_blank" href="https://www.google.com/searchbyimage?image_url={$file_path}/src/{$post.file}.{$post.file_type}" title="Искать картинку в гугле">
									<img src="{$boardpath}css/icons/blank.gif" border="0" class="searchpicg spritebtn sb-l" alt="Искать картинку в гугле">
								</a>
								<a class="extrabtns" target="_blank" href="https://iqdb.org/?url={$file_path}/src/{$post.file}.{$post.file_type}" title="Искать картинку в iqdb">
									<img src="{$boardpath}css/icons/blank.gif" border="0" class="searchpici spritebtn sb-c" alt="Искать картинку в iqdb">
								</a>
								<a class="extrabtns" target="_blank" href="https://www.tineye.com/search?url={$file_path}/src/{$post.file}.{$post.file_type}" title="Искать картинку в TinEye">
									<img src="{$boardpath}css/icons/blank.gif" border="0" class="searchpict spritebtn sb-r" alt="Искать картинку в TinEye">
								</a>
							{/if}
							{if $post.id3.playtime_string neq ''}
								&nbsp;{t}Length{/t}: {$post.id3.playtime_string}
							{/if}
						{/strip}
					</span>
					{if %KU_THUMBMSG}
						<span class="thumbnailmsg"> 
							{if $post.file_type neq 'jpg' && $post.file_type neq 'gif' && $post.file_type neq 'webp' && $post.file_type neq 'png' && $post.videobox eq ''}
								{t}Extension icon displayed, click image to open file.{/t}
							{else}
								{t}Thumbnail displayed, click image for full size.{/t}
							{/if}
						</span>
					{/if}
					{if $post.parentid eq 0}<br />{/if}
				{/if}
				{if $post.videobox eq '' && $post.file neq '' && ( $post.file_type eq 'jpg' || $post.file_type eq 'gif' || $post.file_type eq 'webp' || $post.file_type eq 'png')}
					{if $post.parentid neq 0}<br />{/if}
					{if $post.file eq 'removed'}
						<div id="thumblink{$post.id}" class="nothumb">
							{t}File<br />Removed{/t}
						</div>
					{else}
						{if $istempfile}
							<a id="thumblink{$post.id}"
								{if %KU_NEWWINDOW}
									target="_blank"
								{/if}
								onclick="javascript:return expandimg(this, '{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/thumb/{$post.file}s.{$post.file_type}{/if}',
								'{$post.image_w}', '{$post.image_h}', '{$post.thumb_w}', '{$post.thumb_h}');" 
								href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/{$post.file}.{$post.file_type}"
							>
								<span id="thumb{$post.id}">
									{if $post.pic_animated and not $post.pic_spoiler}
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/thumb/{$post.file}s.{$post.file_type}{/if}" alt="{$post.id}" class="thumb unanimated" height="{$post.thumb_h}" width="{$post.thumb_w}" />
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/thumb/{$post.file}a.{$post.file_type}{/if}" alt="{$post.id}" class="thumb animated" style="display: none;" height="{$post.thumb_h}" width="{$post.thumb_w}" />
									{else}
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/thumb/{$post.file}s.{$post.file_type}{/if}" alt="{$post.id}" class="thumb" height="{$post.thumb_h}" width="{$post.thumb_w}" />
									{/if}
								</span>
							</a>
						{else}
							<a id="thumblink{$post.id}"
								{if %KU_NEWWINDOW}
									target="_blank"
								{/if}
								onclick="javascript:return expandimg(this, '{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/thumb/{$post.file}s.{$post.file_type}{/if}',
								'{$post.image_w}', '{$post.image_h}', '{$post.thumb_w}', '{$post.thumb_h}');" 
								href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/src/{$post.file}.{$post.file_type}"
							>
								<span id="thumb{$post.id}">
									{if $post.pic_animated and not $post.pic_spoiler}
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/thumb/{$post.file}s.{$post.file_type}{/if}" alt="{$post.id}" class="thumb unanimated" height="{$post.thumb_h}" width="{$post.thumb_w}" />
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/thumb/{$post.file}a.{$post.file_type}{/if}" alt="{$post.id}" class="thumb animated" style="display: none;" height="{$post.thumb_h}" width="{$post.thumb_w}" />
									{else}
										<img src="{if $post.nonstandard_file neq ''}{$post.nonstandard_file}{else}{if $file_path}{$file_path}{else}{$post.file_path}{/if}/thumb/{$post.file}s.{$post.file_type}{/if}" alt="{$post.id}" class="thumb" height="{$post.thumb_h}" width="{$post.thumb_w}" />
									{/if}
								</span>
							</a>
						{/if}
					{/if}
				{elseif $post.nonstandard_file neq ''}
					{if $post.parentid neq 0}<br />{/if}
					{if $post.file eq 'removed'}
						<div id="thumblink{$post.id}" class="nothumb">
							{t}File<br />Removed{/t}
						</div>
					{else}
						{if $post.file_type eq 'webm' or $post.file_type eq 'mp4'}
						<a href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/{if $istempfile}tmp{else}src{/if}/{$post.file}.{$post.file_type}" onclick="return false;" class="workaround">
							<video id="thumblink{$post.id}" preload="metadata" controls="" width="480" style="max-height: 360px;" class="thumb" {if $post.id == '?????'}onclick="skip_close_preview = 2;"{/if}>
									<source src="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/{if $istempfile}tmp{else}src{/if}/{$post.file}.{$post.file_type}" type='video/{$post.file_type}'>
							</video>
						</a>
						{else}
							<a id="thumblink{$post.id}"
								{if $post.file_type eq 'mp3' or $post.file_type eq 'ogg' or $post.file_type eq 'm4a'} class="audiowrap" {/if}
								{if %KU_NEWWINDOW}
									target="_blank"
								{/if}								
								{if $istempfile}
									href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/tmp/{$post.file}.{$post.file_type}"
								{else}
									href="{if $file_path}{$file_path}{else}{$post.file_path}{/if}/src/{$post.file}.{$post.file_type}"
								{/if}
							>
								<span id="thumb{$post.id}">
									<img src="{$post.nonstandard_file}" alt="{$post.id}" class="thumb" height="{$post.thumb_h}" width="{$post.thumb_w}" />
								</span>
							</a>
						{/if}
					{/if}
				{/if}
				{if $post.parentid eq 0}{include file="img_post_info.tpl"}{/if}

				<blockquote class="postmessage">
					{if $post.videobox}
						{$post.videobox}
					{/if}
					{$post.message}
				</blockquote>

				{if $post.do_repliesmap}
					<div class="replieslist">
						<br>
						{foreach key=replykey item=reply from=$post.repliesmap}
							{$reply.start}<a class="ref-reply" onclick="javascript:highlight('{$reply.id}', true);" href="/{$reply.boardname}/res/{$reply.parentid}.html#{$reply.id}">&gt;&gt;{if $board.name != $reply.boardname}{$reply.boardname}/{/if}{$reply.id}{if $reply.you}<span class="youindicator"> (You)</span>{/if}</a>{/foreach}
						<script>
							if (typeof newupd_replymap !== 'undefined')
							{"{"}
								newupd_replymap[{$post.id}]=new Array();
								{foreach key=replykey item=reply from=$post.repliesmap}
									newupd_replymap[{$post.id}].push({"{"}'id':{$reply.id},'boardname':'{$reply.boardname}','parentid':{$reply.parentid}{"}"});
								{/foreach}
							{"}"}
						</script>
					</div>		
				{/if}

				{if $modifier eq 'last50' and $post.parentid eq 0}
					<div class="omittedposts" style="margin-top: 5px;">
						{$replycount-50}
						{if $replycount-50 eq 1}
								{t lower="yes"}Post{/t} 
						{else}
								{t lower="yes"}Posts{/t} 
						{/if}
						{t}omitted{/t}. {t}Last 50 shown{/t}.
					</div>
				{/if}			
			</td>
		</tr>
	</tbody>
</table>
</div>
