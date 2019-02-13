{if $replythread > 0 && not $isread}
	<table class="userdelete">
	<tbody>
	<tr>
	<td>
	{t}Delete post{/t}
	[<label><input class="stchkbox" type="checkbox" name="fileonly" id="fileonly" value="on" /><span> {t}File Only{/t}</span></label>]<br />{t}Password{/t}
	<input type="password" name="postpassword" size="8" />&nbsp;<input name="deletepost" value="{t}Delete{/t}" type="submit" />

	{if $board.enablereporting eq 1}
		</td>
		</tr>
		<tr>
		<td>
		{t}Report post{t}<br />
		{t}Reason{/t}
		<input type="text" name="reportreason" size="10" />&nbsp;<input name="reportpost" value="{t}Report{/t}" type="submit" />	
	{/if}

	</td>
	</tr>
	</tbody>
	</table>
	</form>
{/if}
<script type="text/javascript"><!--
	set_delpass("delform");
//--></script>
{if $replythread eq 0}
	<table border="1">
	<tbody>
		<tr>
			<td>
				{if $thispage eq 0}
					{t}Previous{/t}
				{else}
					<form method="get" action="{%KU_BOARDSFOLDER}{$board.name}/{if ($thispage-1) neq 0}{$thispage-1}.html{/if}">
						<input value="{t}Previous{/t}" type="submit" /></form>
				{/if}
			</td>
			<td>
				&#91;{if $thispage neq 0}<a href="{%KU_BOARDSPATH}/{$board.name}/">{/if}0{if $thispage neq 0}</a>{/if}&#93;
				{section name=pages loop=$numpages}
				{strip}
					&#91;
					{if $.section.pages.iteration neq $thispage}<a href="{%KU_BOARDSFOLDER}{$board.name}/{$.section.pages.iteration}.html">
					{/if}
					
					{$.section.pages.iteration}
					
					{if $.section.pages.iteration neq $thispage}
					</a>
					{/if}
					&#93;
				{/strip}
				{/section}	
			</td>
			<td>
				{if $thispage eq $numpages}
					{t}Next{/t}
				{else}
					<form method="get" action="{%KU_BOARDSPATH}/{$board.name}/{$thispage+1}.html"><input value="{t}Next{/t}" type="submit" /></form>
				{/if}
	
			</td>
		</tr>
	</tbody>
	</table>
{/if}
<br />
{if $boardlist}
	<div class="navbar">
	{if %KU_GENERATEBOARDLIST}
		{foreach name=sections item=sect from=$boardlist}
			[
			{foreach name=brds item=brd from=$sect}
				<a title="{$brd.desc}" href="{%KU_BOARDSFOLDER}{$brd.name}/">{$brd.name}</a>{if $.foreach.brds.last}{else} / {/if}
			{/foreach}
			]
		{/foreach}
	{else}
		{if is_file($boardlist)}
			{include $boardlist}
		{/if}
	{/if}
	</div>
{/if}
<br />
<div class="footer" style="clear: both;">
	<div class="legal">	- Kurisaba {%KU_VERSION} -
	{if $executiontime neq ''} + {t}Took{/t} {$executiontime}s -{/if}
</div>
