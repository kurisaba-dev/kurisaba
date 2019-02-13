{*<table>*}
	{*<tbody>*}
		{*<tr>*}
			{*<td>*}
				{if not $isexpand and not $isread and not $iscatalog}
					{*<form>*}
						{*<table>*}
							{*<tbody>*}
								{*<tr>*}
									{*<td>*}
										<div style="float:right; text-align: right; vertical-align: top;">
											&nbsp;●&nbsp;
											Причина:
											<input name="reportreason" size="10" type="text">
											<input name="reportpost" value="Репорт" type="submit" onclick="delform_submit = 'Report'; return js_send_delform();">	
										</div>
										<div style="float:right; text-align: right; vertical-align: top;">
											Пароль:
											<input name="postpassword" size="8" type="password">
											<input name="deletepost" value="Удалить пост" type="submit" onclick="delform_submit = 'Delete'; return js_send_delform();">
											[<label><input name="fileonly" id="fileonly" value="on" class="stchkbox" type="checkbox"><span> только файл</span></label>]
										</div>
									</td>
								</tr>
							</tbody>
						</table>
						<hr />
					</form>
					<script type="text/javascript"><!--
						set_delpass("delform");
					//--></script>
				{/if}

				{if not $iscatalog and $replythread eq 0}
					<table border="1">
						<tbody>
							<tr>
								<td>
									{if $thispage eq 0}
										{t}Previous{/t}
									{else}
										<form method="get" action="{%KU_BOARDSFOLDER}{$board.name}/{if ($thispage-1) neq 0}{$thispage-1}.html{/if}">
											<input value="{t}Previous{/t}" type="submit" />
										</form>
									{/if}
								</td>
								<td>
									{strip}
										&#91;
										{if $thispage neq 0}
											<a href="{%KU_BOARDSPATH}/{$board.name}/">
										{/if}
										0
										{if $thispage neq 0}
											</a>
										{/if}
										&#93;
									{/strip}
									{section name=pages loop=$numpages}
										{strip}
											&#91;
											{if $.section.pages.iteration neq $thispage}
												<a href="{%KU_BOARDSFOLDER}{$board.name}/{$.section.pages.iteration}.html">
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
										<form method="get" action="{%KU_BOARDSPATH}/{$board.name}/{$thispage+1}.html">
											<input value="{t}Next{/t}" type="submit" />
										</form>
									{/if}
								</td>
							</tr>
						</tbody>
					</table>
				{/if}

				<br />

				{if $boardlist}
					<div id="boardlist_footer" class="navbar">
						{if %KU_GENERATEBOARDLIST}
							{foreach name=sections item=sect from=$boardlist}
								{if $sect.abbreviation neq '20'}
									{strip}
										[&nbsp;
											{foreach name=brds item=brd from=$sect}
												{if isset($brd.desc) and is_array($brd)}
													<a title="{$brd.desc}" href="{%KU_BOARDSFOLDER}{$brd.name}/">
														{$brd.name}
													</a>
													{if $.foreach.brds.last}
													{else}
														&nbsp;/&nbsp;
													{/if}
												{/if}
											{/foreach}
										&nbsp;]
									{/strip}
								{else}
									<span style="float: right">
										{strip}
											[&nbsp;
												<select onchange="javascript:if(selectedIndex != 0) location.href='{%KU_WEBPATH}/' + this.options[this.selectedIndex].value;">
													<option>2.0</option>
													{foreach name=brds item=brd from=$sect}
														{if isset($brd.desc) and is_array($brd)}
															<option value="{$brd.name}">
																/{$brd.name}/ - {$brd.desc}
															</option>
															{if $.foreach.brds.last}
															{else}
																&nbsp;/&nbsp;
															{/if}
														{/if}
													{/foreach}
												</select>
											&nbsp;]
										{/strip}
									</span>
								{/if}
							{/foreach}
						{else}
							{if is_file($boardlist)}
								{include $boardlist}
							{/if}
						{/if}
					</div>
				{/if}
				</td>
			<td width="13" class="border-right"></td>
		</tr>
	</tbody>
</table>
<table width="98%" border="0" align="center" cellpadding="0" cellspacing="0" class="maintable">
	<tbody>
		<tr>
	        <td width="20" height="84" class="bottom-left"></td>
	        <td height="84" class="bottom-center">&nbsp;</td>
	        <td width="19" height="84" class="bottom-right"></td>
		</tr>
	</tbody>
</table>
