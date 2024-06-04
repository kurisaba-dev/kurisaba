<a name="{$post.id}"></a><span class="span_parent_id" style="display:none;">{$post.parentid}</span><span class="span_board_name" style="display:none;">{$board.name}</span>
<label>
	<input class="stchkbox" type="checkbox" name="post[]" value="{$post.id}" />
	<span></span>

	{if $post.subject neq ''}
		<span class="filetitle">
			{$post.subject}
		</span>
	{/if}

	{strip}
		{if $post.email neq ''}
			<a href="mailto:{$post.email}">
		{/if}

		{if $post.name eq '' && $post.tripcode eq ''}
			<span class="postername hideablename">{$board.anonymous}</span>
		{elseif $post.name eq '' && $post.tripcode neq ''}
		{else}
			<span class="postername hideablename">{$post.name}</span>
		{/if}

		{if $post.tripcode neq ''}
			<span class="postertrip">!{$post.tripcode}</span>
		{/if}
		
		{if $post.email neq ''}
			</a>
		{/if}

		{$post.youindicator}

		{if $post.name neq ''}
			&nbsp;
			<a class="extrabtns" href="#" onclick="hide_poster('{$post.name}');return false;" title="Скрыть этого неймфага">
				<img src="{$boardpath}css/icons/blank.gif" border="0" class="hidethread spritebtn" alt="Скрыть этого неймфага" />
			</a>
		{/if}

	{/strip}

	{if $post.posterauthority eq 1}
		<span class="admin">
			&#35;&#35;&nbsp;{t}Admin{/t}&nbsp;&#35;&#35;
		</span>
	{elseif $post.posterauthority eq 4}
		<span class="mod">
			&#35;&#35;&nbsp;{t}Super Mod{/t}&nbsp;&#35;&#35;
		</span>
	{elseif $post.posterauthority eq 2}
		<span class="mod">
			&#35;&#35;&nbsp;{t}Mod{/t}&nbsp;&#35;&#35;
		</span>
	{/if}
	<span class="datetime">{$post.timestamp_formatted}</span>
</label>
{$post.externalreference}
<span class="reflink">
	{$post.reflink}
</span>
{strip}
	{if $post.parentid neq 0}
		<a class="extrabtns" href="#" onclick="hidepost_num('{$board.name}',{$post.id});return false;" title="Скрыть пост">
			<img src="{$boardpath}css/icons/blank.gif" border="0" class="hidethread spritebtn" alt="Скрыть пост" />
		</a>
	{else}
		<span id="hide{$post.id}">
			<a href="#" onclick="javascript:togglethread('{$post.id}');return false;" title="Скрыть тред">
				<img src="{$boardpath}css/icons/blank.gif" border="0" class="hidethread spritebtn" alt="Скрыть тред" />
			</a>
		</span>
	{/if}
{/strip}
<span class="postnumber">
	[ <span class="postnumber_green">{$post.n}</span> ]
</span>
{if $board.showid}
	<img src="data:image/png;base64,{"{"}rainbow($post.ipmd5, {if $post.parentid eq 0}$post.id{else}$post.parentid{/if});{"}"}" />
{/if}
<span class="extrabtns">
	{if $post.id != '?????'}
		{if %KU_QUICKREPLY}
			{strip}
				<a href="#" data-parent="{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}" data-forceexternalboard="{if $forceexternalboard}yes{else}no{/if}" data-boardname="{$board.name}" data-maxfilesize="{$board.maximagesize}" data-postnum="{$post.id}" class="qr-btn qrl" title="{t}Quick Reply{/t}{if not $is_board_page}  в тред {if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}{/if}">
					<img src="{$cwebpath}css/icons/blank.gif" border="0" class="quickreply spritebtn" alt="quickreply">
				</a>
				<a href="#" data-parent="{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}" data-forceexternalboard="{if $forceexternalboard}yes{else}no{/if}" data-boardname="{$board.name}" data-maxfilesize="{$board.maximagesize}" data-postnum="{$post.id}" class="qed" style="margin-left: 4px;" title="Переотправить пост {$post.id}">
					<img src="{$cwebpath}css/icons/blank.gif" border="0" class="quickedit spritebtn" alt="quickedit">
				</a>
			{/strip}
		{/if}
	{/if}

	{if $board.balls}
		<img class="_country_" src="{%KU_WEBPATH}/images/flags/{$post.country}.png">
	{/if}
	{if $post.parentid neq 0}
		{if $board.showid}
			<img src="data:image/png;base64,{rainbow($post.ipmd5, $post.id);}" />
		{/if}
	{/if}
	{if $post.locked eq 1}
		<img style="border: 0;" src="{$boardpath}css/images/locked.gif" alt="{t}Locked{/t}" />
	{/if}
	{if $post.stickied eq 1}
		<img style="border: 0;" src="{$boardpath}css/images/sticky.gif" alt="{t}Stickied{/t}" />
	{/if}
</span>
{if $post.parentid eq 0}
	<span id="dnb-{$board.name}-{$post.id}-y"></span>
	{strip}
		{if $is_board_page}
			&nbsp;[<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}.html">{t}Reply{/t}</a>]
		{/if}
		{if $is_board_page and %KU_FIRSTLAST && (($post.stickied eq 1 && $post.replies + %KU_REPLIESSTICKY > 50) || ($post.stickied eq 0 && $post.replies + %KU_REPLIES > 50))}
			{if (($post.stickied eq 1 && $post.replies + %KU_REPLIESSTICKY > 100) || ($post.stickied eq 0 && $post.replies + %KU_REPLIES > 100))}
				[
					<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{if $post.parentid eq 0}{$post.id}{else}{$post.parentid}{/if}-100.html">{t}First 100 posts{/t}</a>
				]
			{/if}
			[
				<a href="{%KU_BOARDSFOLDER}{$board.name}/res/{$post.id}+50.html">{t}Last 50 posts{/t}</a>
			]
		{/if}
	{/strip}
	<br />
{else}
	<span id="dnb-{$board.name}-{$post.id}-n"></span>
{/if}
