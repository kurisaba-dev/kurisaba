{if not $onlyclone}
	<hr />
	<div id="formdown" style="position: relative; float: right; text-align: right; right: 0px;">
		<a onclick="javascript:rswap.swap();return true;" href="#boardlist_footer"><img src="/images/down.gif"></a>
	</div>
{/if}

<div class="postarea">
	{if not $onlyclone}
		<p></p>
		<a id="postbox"></a>
	{/if}
	
	<form name="postform" id="postform" {if $onlyclone}style="display:none"{/if} action="{%KU_CGIPATH}/board.php" method="post" enctype="multipart/form-data" onsubmit="return js_send_postbox();">
	<input type="hidden" name="ffdata_savedon" value="" />
	<input type="hidden" name="board" value="{$board.name}" />
	<input type="hidden" name="replythread" value="<!sm_threadid>" />
	<input type="hidden" name="deletepostb" value="">
	<input type="hidden" name="deletepostp" value="">
	{if $board.maximagesize > 0}
		<input type="hidden" name="MAX_FILE_SIZE" value="{$board.maximagesize}" />
	{/if}
	<input type="text" name="email" size="28" maxlength="{%KU_MAXEMAILLENGTH}" value="" style="display: none;" />
	<script>smilies_array = new Array();</script>
	<table class="postform">
		<tbody>
		
			<tr style="display: none;" id="editwarning">
				<td colspan="2">
					<span style="color: #ff0000; font-weight: bold;">Переотправка ОП-поста удалит этот тред и создаст новый вместо него!</span>
				</td>
			</tr>
			
			{if $board.forcedanon neq 1}
				<tr>
					<td class="postblock">
						{t}Name{/t}</td>
					<td>
						<input type="text" name="name" size="76" maxlength="{%KU_MAXNAMELENGTH}" accesskey="n" style="width: 500px;"/>
						<a href="#" onclick="javascript:emgr_ui_onclick();return false;" title="История записей" style="vertical-align: middle;">
							<img src="/css/icons/blank.gif" border="0" class="spritebtn editmgr">
						</a>
					</td>
				</tr>
			{/if}
			
			<span class="extrabtns postboxcontrol" style="display: none; padding-top: 5px;">
				<span class="qrpinner">
					<a href="#" onclick="javascript:$('#postform').pin();return false;" title="Прикрепить / Открепить">
						<img src="/css/icons/blank.gif" border="0" class="spritebtn pinner">
					</a>
				</span>
				&nbsp;
				<a href="#" onclick="javascript:quickreply_hide();return false;" title="Закрыть">
					<img src="/css/icons/blank.gif" border="0" class="closebox spritebtn">
				</a>
			</span>
			
			<tr>
				<td class="postblock">
					{t}Subject{/t}
				</td>
				<td>
					{strip}
						<input type="text" name="subject" size="48" maxlength="{%KU_MAXSUBJLENGTH}" accesskey="s" style="width: 330px;" />&nbsp;
					<input type="submit" value="
						{if %KU_QUICKREPLY && $replythread eq 0}
							{t}Submit{/t}" accesskey="z" />&nbsp;<small id="posttypeindicator">({t}new thread{/t})</small>
						{elseif %KU_QUICKREPLY && $replythread neq 0}
							{t}Reply{/t}" accesskey="z" />&nbsp;<small id="posttypeindicator">({t}reply to{/t} <!sm_threadid>)</small>
						{else}
							{t}Submit{/t}" accesskey="z" />
						{/if}
					{/strip}
				</td>
			</tr>

			<tr>
				<td class="postblock">
					{t}Message{/t}
				</td>
				<td>
					<textarea name="message" cols="58" rows="5" accesskey="m" id="message" style="width: 520px;"></textarea>
					<div id="quickeditloading" style="display: none;"><img src="/images/loading.gif"></div>
					<div class="markupbtns">
						<nobr style="font-size: 16px;">
							<a title="{t}Bold{/t}" href="#" class="uibutton uib-mup" data-mups="**" data-mupe="**"><b>Жирный</b></a>
							<a title="{t}Italic{/t}" href="#" class="uibutton uib-mup" data-mups="*" data-mupe="*"><i>Курсив</i></a>
							<a title="{t}Undeline{/t}" href="#" class="uibutton uib-mup" data-mups="[u]" data-mupe="[/u]"><u>Подчёркнутый</u></a>
							<a title="{t}Strike{/t}" href="#" class="uibutton uib-mup" data-mups="[s]" data-mupe="[/s]"><s>Зачёркнутый</s></a>
							<a title="{t}Spoiler{/t}" href="#" class="uibutton uib-mup" data-mups="%%" data-mupe="%%">Спойлер</a>
							<a title="{t}Greenquoting{/t}" href="#" class="uibutton uib-mup" data-mups=">>>" data-mupe="<<<"><span class="uib-imply">&gt;Цитата</span></a>
							<a title="{t}Code{/t}" href="#" class="uibutton uib-mup" data-mups="[code]" data-mupe="[/code]"><span class="uib-code">Код();</span></a>
						</nobr>
					</div>
				</td>
			</tr>

			{if $board.uploadtype eq 0 || $board.uploadtype eq 1 || $board.uploadtype eq 2}
				<tr>
					<td class="postblock">
						Прикрепить
					</td>
					<td>
						<input type="radio" name="attach_type" value="file" checked onchange="showembedfield(this);"> Открыть файл 
						<input type="radio" name="attach_type" value="drop" onchange="showembedfield(this);"> Перетащить файл
						{if ($board.uploadtype eq 1 || $board.uploadtype eq 2) && $board.embeds_allowed neq ''}
							<input type="radio" name="attach_type" value="embed" onchange="showembedfield(this);"> Видео
						{/if}
						<input type="radio" name="attach_type" value="link" onchange="showembedfield(this);"> Ссылка
					</td>
				</tr>
				<tr id="attachfile_tr" style="height: 26px;">
					<td class="postblock">
						{t}File{/t}
					</td>
					<td>
						<input type="file" name="imagefile" id="imagefile" style="width: 440px;" accesskey="f" />
						{if $replythread eq 0 && $board.enablenofile eq 1 }
							[<input type="checkbox" name="nofile" id="nofile" accesskey="q" /><label for="nofile"> {t}No File{/t}</label>]
						{/if}
						<input type="button" value="Очистить" onclick="document.forms['postform'].imagefile.value='';" style="width: 80px;" >
					</td>
				</tr>
				<tr id="attachdrop_tr" style="height: 26px; display: none;">
					<td class="postblock">
						{t}File{/t}
					</td>
					<td>
						<div id="dropZone">
							Перетащи файл или вставь картинку сюда
						</div>
						<input type="hidden" name="drop_file_name" id="drop_file_name" value="" />
					</td>
				</tr>
				
				{if ($board.uploadtype eq 1 || $board.uploadtype eq 2) && $board.embeds_allowed neq ''}
					<tr id="attachembed_tr" style="height: 26px; display: none;">
						<td class="postblock">
							<select name="embedtype" style="margin-top: -2px; margin-bottom: -2px;">
								{foreach name=embed from=$embeds item=embed}
									{if in_array($embed.filetype,explode(',' $board.embeds_allowed))}
										<option value="{$embed.name|lower}">{$embed.name}</option>
									{/if}
								{/foreach}
							</select>
						</td>
						<td>
							<input type="text" name="embed" size="76" maxlength="76" accesskey="e" style="width: 520px;" />
						</td>
					</tr>
				{/if}

				<tr id="attachlink_tr" style="height: 26px; display: none;">
					<td class="postblock">
						Ссылка</td>
					<td>
						<input type="text" name="embedlink" value="" size="76" style="width: 520px;" />
					</td>
				</tr>
			{/if}

			<tr>
				<td class="postblock">
					<span class="captcha_status"></span>
					<div class="captchawrap">
						<img src="/captcha.php?act=postbox&captchaid={$captchaid}" class="captchaimage content-background" onclick="javascript:refreshCaptcha();" valign="middle" border="0" alt="Captcha image">
					</div>
				</td>
				<td>
					<nobr>
						<input type="text" name="captcha" size="76" accesskey="c" style="width: 520px;">
						<input type="hidden" class="captchaid" name="captchaid" value="{$captchaid}">
					</nobr>
				</td>
			</tr>

			<input type="hidden" name="displaystaffstatus" value="true">

			<tr class="smilies_tr">
				<td class="postblock">
					Смайл
				</td>
				<td>
					{$smile_images}
				</td>
			</tr>

			<tr>
				<td class="postblock">
					Опции</td>
				<td>
					<label for="sage">
						<input id="sage" type="checkbox" name="em" value="sage" style="vertical-align: middle;">sage
					</label>
					<label for="gotothread">
						<input id="gotothread" type="checkbox" checked name="redirecttothread" value="1" style="vertical-align: middle;">noko
					</label>
					<label for="picspoiler">
						<input id="picspoiler" type="checkbox" name="picspoiler" value="1" style="vertical-align: middle;">картинку под спойлер
					</label>
					<input id="submit_through_js" type="checkbox" checked name="submit_through_js" value="1" style="display:none;">
					&nbsp;&nbsp;&nbsp;&nbsp;<a href="#" onclick="post_preview(event,document.getElementById('postform'));return false;">Предпросмотр поста</a>
				</td>
			</tr>
			<tr>
				<td class="postblock">
					{t}Password{/t}
				</td>
				<td>
					<input type="text" style="display: none;" name="fakeandgay"/>
					<input type="password" name="postpassword" size="38" accesskey="p" autocomplete="on" style="width: 270px;" />&nbsp;{t}(for post and file deletion){/t}
				</td>
			</tr>

			<tr>
				<td colspan="2" class="blotter">
					<div class="blotterhead">[<a href="#" onclick="toggleblotter();return false;" class="xlink"><b>{t}Info{/t}</b></a>]</div>
					<ul style="margin-left: 0; margin-top: 0; margin-bottom: 0; padding-left: 0;" class="blotter-entries">
						<li>{t}Supported file types are{/t}:
							{if $board.filetypes_allowed neq ''}
								{foreach name=files item=filetype from=$board.filetypes_allowed}
									{$filetype.0|upper}{if $.foreach.files.last}{else}, {/if}
								{/foreach}
							{else}
								{t}None{/t}
							{/if}
						</li>
						<li>{t}Maximum file size allowed is{/t} {math "round(x/1024)" x=$board.maximagesize} KB.</li>
						<li>Максимальный размер поста 30 KB.</li>
						<li>Запрещен постинг ЦП.</li>
						<li>Запрещены вайп и реклама.</li>
						<li>Шитпостинг не нужен. Это не /b/.</li>
						<li>Обо всём остальном смотри <a href="/faq/">FAQ</a>.</li>
						<li>?????</li>
						<li>El Psy Congroo.</li>
					</ul>
					<script type="text/javascript">
						if (getCookie('ku_showblotter') != '1')
						{
							hideblotter();
						}
					</script>
				</td>
			</tr>
		</tbody>
	</table>
	</form>
</div>
