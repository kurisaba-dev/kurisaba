<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 * +------------------------------------------------------------------------------+
 * kusaba - http://www.kusaba.org/
 * Written by Trevor "tj9991" Slocum
 * http://www.tj9991.com/
 * tslocum@gmail.com
 * +------------------------------------------------------------------------------+
 */
/**
 * Board operations which available to all users
 *
 * This file serves the purpose of providing functionality for all users of the
 * boards. This includes: posting, reporting posts, and deleting posts.
 *
 * @package kusaba
 */

session_start(['cookie_samesite' => 'Strict']);
require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';
require KU_ROOTDIR . 'inc/classes/bans.class.php';
require KU_ROOTDIR . 'inc/classes/posting.class.php';
require KU_ROOTDIR . 'inc/classes/parse.class.php';

 // STAGE 1: Check type of operation and specifically format return actions.
 
if (isset($_POST['through_js']))
{
	function kurisaba_exit($errormsg, $extended = '', $posttext = '', $template = '/error.tpl', $boardname = '')
	{
		if ($extended != '') $extended = "<br />" .$extended;
		die(json_encode(Array("error" => $errormsg . $extended)));
	}

	function kurisaba_redirect($url, $ispost = false, $file = '', $newpostnum = -1)
	{
		die(json_encode(Array("redirect_to" => $url, "newpostnum" => $newpostnum)) );
	}
}
else
{
	function kurisaba_exit($errormsg, $extended = '', $posttext = '', $template = '/error.tpl', $boardname = '')
	{
		exitWithErrorPage($errormsg, $extended, $posttext, $template, $boardname);
	}

	function kurisaba_redirect($url, $ispost = false, $file = '', $newpostnum = -1)
	{
		do_redirect($url, $ispost, $file);
	}
}

if(isset($_POST['preview_mode'])) $preview = true;

// Test for a spam bot (which sends 'email' through POST)
if (isset($_POST['email']) && !empty($_POST['email'])) { kurisaba_exit('Spam bot detected. (probably, you need to remove "remember password" for this website in browser?)'); }

// STAGE 2: Do the primary initializations

$bans_class = new Bans();
$parse_class = new Parse();
$posting_class = new Posting();
modules_load_all();

// FUNCTIONS

function notify($id="?????", $newthreadid = '') {

	if(KU_REACT_ENA) {
		$data_string = json_encode(array('srvtoken' => KU_REACT_SRVTOKEN, 'room' => $id, 'clitoken' => $_POST['token'], 'timestamp' => time() + KU_ADDTIME, 'newthreadid' => $newthreadid ));                                                                            
		$suckTo = KU_REACT_SITENAME ? KU_LOCAL_REACT_API.'/qr/'.KU_REACT_SITENAME : KU_LOCAL_REACT_API;
		$ch = curl_init($suckTo);                                                                      
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_PROXY, "");    
		curl_setopt($ch, CURLOPT_TIMEOUT, 0.5);                                                                      
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                                                  
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                                      
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
		    'Content-Type: application/json',                                                                                
		    'Content-Length: ' . strlen($data_string))                                                                       
		);  
		curl_exec($ch);
		if(curl_errno($ch)) error_log('Curl error during Notify: ' . curl_error($ch).' (Error code: '.curl_errno($ch).')');
	}
}

// STAGE 3: Check and setup parameters

// 3.1. Set up board value.
if (isset($_POST['board']) || isset($_GET['board'])) $_POST['board'] = (isset($_GET['board'])) ? $_GET['board'] : $_POST['board'];

// 3.2. Exit if supplied board name bad
if (isset($_POST['board'])) {
	$board_name = $tc_db->GetOne("SELECT `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
	if ($board_name !== false) {
		$board_class = new Board($board_name);
		if (!empty($board_class->board['locale'])) {
			changeLocale($board_class->board['locale']);
		}
	} else {
		kurisaba_exit('Такой борды не существует.');
	}
} else {
	kurisaba_exit('Не задано имя борды.');
}

// 3.3. Check for an IP ban (and remove if expired).
if (isset($_POST['through_js']))
{
	if($bans_class->BanCheckSilent(KU_REMOTE_ADDR))
	{
		die("YOU ARE BANNED FOR SOME POST");
	}
}
else
{
	$bans_class->BanCheck(KU_REMOTE_ADDR, $board_class->board['name']);
}
	
// 3.4. Oekaki stuff
$oekaki = $posting_class->CheckOekaki();
$is_oekaki = empty($oekaki) ? false : true;

// 3.5. Ensure that UTF-8 is used on some of the post variables
$posting_class->UTF8Strings();

// 3.6. Determine mode of posting/deleting
$operation_delete = false;
$operation_post = false;
if ($posting_class->CheckValidPost($is_oekaki))
{
	// Generic post
	$operation_post = true;
}
elseif ((isset($_POST['deletepost']) || isset($_POST['reportpost']) || isset($_POST['moddelete'])) && isset($_POST['post']))
{
	// Generic delete/report.
	$operation_delete = true;
	$delete_post = $_POST['post'];
}
elseif (isset($_GET['postoek']))
{
	// Oekaki stuff?
	$board_class->OekakiHeader($_GET['replyto'], $_GET['postoek']);
	die();
}
else
{
	// Indeterminate operation.
	kurisaba_exit('Неправильный вызов board.php.');
	//kurisaba_redirect(KU_BOARDSPATH . '/' . $board_class->board['name'] . '/');
}

// STAGE 4. Delete

if($operation_delete) // currently `noreturn`.
{
	$ismod = false;
	$backtothread = -1;
	foreach ($delete_post as $val)
	{
		$post_class = new Post($val, $board_class->board['name'], $board_class->board['id']);
		if ($backtothread != 0) $backtothread = $post_class->post['parentid'];
		
		// 4.1. REPORTING
		if (isset($_POST['reportpost']))
		{
			// They clicked the Report button
			if ($board_class->board['enablereporting'] == 1) {
				$post_reported = $post_class->post['isreported'];

				if ($post_reported === 'cleared') {
					$success = _gettext('That post has been cleared as not requiring any deletion.');
				} elseif ($post_reported) {
					$success = _gettext('That post is already in the report list.');
				} else {
					if ($post_class->Report()) {
						$success = _gettext('Post successfully reported.');
					} else {
						kurisaba_exit(_gettext('Unable to report post. Please go back and try again.'),'','','/error.tpl',$board_class->board['name']);
					}
				}
			} else {
				kurisaba_exit(_gettext('This board does not allow post reporting.'),'','','/error.tpl',$board_class->board['name']);
			}
		}
		
		// 4.2. DELETE or MODDELETE
		elseif (isset($_POST['postpassword']) || ( isset($_POST['moddelete']) && (require_once KU_ROOTDIR . 'inc/classes/manage.class.php') && Manage::CurrentUserIsModeratorOfBoard($board_class->board['name'], $_SESSION['manageusername']) && $ismod = true))
		{
			// They clicked the Delete button
			if ($_POST['postpassword'] != '' || $ismod) {
				if (md5($_POST['postpassword']) == $post_class->post['password'] || $ismod) {
					if (isset($_POST['fileonly'])) {
						if ($post_class->post['file'] != '' && $post_class->post['file'] != 'removed') {
							$post_class->DeleteFile();
							$success = _gettext('Image successfully deleted from your post.');
						} else {
							$success = _gettext('Your post already doesn\'t have an image!');
						}
					} else {
						if ($post_class->Delete(isset($_POST['savepicture']))) {
							$success = _gettext('Post successfully deleted.');
						} else {
							kurisaba_exit(_gettext('There was an error in trying to delete your post'),'','','/error.tpl',$board_class->board['name']);
						}
					}
				} else {
					kurisaba_exit(_gettext('Incorrect password.'),'','','/error.tpl',$board_class->board['name']);
				}
			} else {
				kurisaba_exit('Пустой пароль.','','','/error.tpl',$board_class->board['name']);
			}
		}
	}
	if ($backtothread > 0 && $_COOKIE['tothread'] == 'on')
	{
		if (isset($_POST['plus50'])) $backtothread .= '+50';
		if (isset($_POST['minus100'])) $backtothread .= '-100';
		kurisaba_redirect(KU_BOARDSPATH . '/' . $board_class->board['name'] . '/res/' . $backtothread . '.html#boardlist_footer');
		die();
	}
	kurisaba_redirect(KU_BOARDSPATH . '/' . $board_class->board['name'] . '/');
	die();
}

// STAGE 5. Post

if($operation_post) // it's `noreturn`.
{
	if (!$preview) $tc_db->Execute("START TRANSACTION");
	
	$_POST['modpassword'] = $_POST['captcha'];
	list($user_authority, $flags) = $posting_class->GetUserAuthority();
	
	if (!$preview) // Don't check captcha on preview
	{
		if ($user_authority == 0)
		{
			$captcha_msg = $posting_class->CheckCaptcha($_POST['message']);
	
			if ($captcha_msg != '')
			{
				kurisaba_exit($captcha_msg,'',$post_message);
			}
		}
		
		if($posting_class->CheckReplyTime($_POST['message']))
		{
			kurisaba_exit(_gettext('Please wait a moment before posting again.'), _gettext('You are currently posting faster than the configured minimum post delay allows.'),$post_message);
		}
	
		if($_POST['replythread'] == 0)
		{
			if($posting_class->CheckNewThreadTime($_POST['message']))
			{
				kurisaba_exit(_gettext('Please wait a moment before posting again.'), _gettext('You are currently posting faster than the configured minimum post delay allows.'),$post_message);		
			}
			
			if ($posting_class->HowManyThreadsToday() >= KU_MAXTHREADSADAY)
			{
				kurisaba_exit('Похоже, борду флудят - слишком много новых тредов. Создание тредов временно отключено.', 'По всем вопросам — к Ханако-сан или Хомуре-нян.', $post_message);
			}
		}
	}

	if($posting_class->CheckMessageLength($_POST['message']))
	{
		kurisaba_exit(sprintf(_gettext('Sorry, your message is too long. Message length: %d, maximum allowed length: %d'), strlen($_POST['message']), $board_class->board['messagelength']),'',$post_message);
	}

	$post_isreply_prev = $posting_class->CheckIsReply();
	
	if ($post_isreply_prev == -1)
	{
		kurisaba_exit(_gettext('Invalid thread ID.'), _gettext('That thread may have been recently deleted.'));
	}
	$post_isreply = ($post_isreply_prev == 1)? true : false;
	
	$imagefile_name = isset($_FILES['imagefile']) ? $_FILES['imagefile']['name'] : '';

	if ($post_isreply) {
		list($thread_replies, $thread_locked, $thread_replyto) = $posting_class->GetThreadInfo($_POST['replythread']);
		if ($thread_replies == -1)
		{
			kurisaba_exit(_gettext('Invalid thread ID.'), _gettext('That thread may have been recently deleted.'));
		}
	} else {
		// type: 0=image, 1=text, 2=oekaki, 3=upload; uploadtype: 0=none, 1=image/video, 2=video
		if ($board_class->board['type'] != 1)
		{
			if(($board_class->board['uploadtype'] == '1' || $board_class->board['uploadtype'] == '2') && $board_class->board['embeds_allowed'] != '')
			{
				if (!isset($_POST['embed']))
				{
					if ($board_class->board['uploadtype'] == '2')
					{
						kurisaba_exit('ОП-пост должен начинаться с видео; твой клиент не поддерживает их загрузку.','',$_POST['message']);
					}
					else if ($board_class->board['uploadtype'] == '1' && $imagefile_name == '')
					{
						kurisaba_exit('ОП-пост нельзя отправить без картинки или видео.','',$_POST['message']);
					}
				}
				else
				{
					if (isset($_POST['attach_type'])) // new behavior
					{
						if ($_POST['attach_type'] == 'file' && $imagefile_name == '')
						{
							kurisaba_exit('Нельзя просто так взять и отправить ОП-пост.<br>Выбери картинку для отправки со своего компьютера.','',$_POST['message']);
						}
						else if ($_POST['attach_type'] == 'drop' && (!isset($_POST['drop_file_name']) || $_POST['drop_file_name'] == ''))
						{
							kurisaba_exit('Нельзя просто так взять и отправить ОП-пост.<br>Перетащи картинку со своего компьютера на поле отправки.','',$_POST['message']);
						}
						else if ($_POST['attach_type'] == 'embed' && $_POST['embed'] == '')
						{
							kurisaba_exit('Нельзя просто так взять и отправить ОП-пост.<br>Введи ID видео с какого-либо сайта для встраивания в пост.','',$_POST['message']);
						}
						else if ($_POST['attach_type'] == 'link' && $_POST['embedlink'] == '')
						{
							kurisaba_exit('Нельзя просто так взять и отправить ОП-пост.<br>Введи ссылку на изображение, которое нужно встроить в пост.','',$_POST['message']);
						}
					}
					else // old behavior; if there is an embed or imagefile_name - is to be determined.
					{
						if ($_POST['embed'] == '')
						{
							if ($board_class->board['uploadtype'] == '2')
							{
								kurisaba_exit('ОП-пост должен начинаться с видео.','',$_POST['message']);
							}
							else if ($board_class->board['uploadtype'] == '1' && $imagefile_name == '')
							{
								kurisaba_exit('ОП-пост нельзя отправить без картинки или видео.','',$_POST['message']);
							}
						}
					}
				}
			}
		}

		$thread_replies = 0;
		$thread_locked = 0;
		$thread_replyto = 0;
	}

	list($post_name, $post_email, $post_subject, $post_tag) = $posting_class->GetFields();
	$post_password = isset($_POST['postpassword']) ? $_POST['postpassword'] : '';

	if ($board_class->board['type'] == 1) {
		if ($post_isreply) {
			$post_subject = '';
		} else {
			if($posting_class->CheckNotDuplicateSubject($post_subject))
			{
				exitWithErrorPage(_gettext('Duplicate thread subject'), _gettext('Text boards may have only one thread with a unique subject. Please pick another.'),'',$_POST['message']);
			}
		}
	}

	$post_fileused = false;
	$post_autosticky = false;
	$post_autolock = false;
	$post_displaystaffstatus = false;
	$file_is_special = false;

	if (isset($_POST['formatting'])) {
		if ($_POST['formatting'] == 'aa') {
			$_POST['message'] = '[aa]' . $_POST['message'] . '[/aa]';
		}

		if (isset($_POST['rememberformatting'])) {
			setcookie_strict('kuformatting', urldecode($_POST['formatting']), time() + KU_ADDTIME + (365 * 24 * 3600), '/', KU_DOMAIN);
		}
	}

	$results = $tc_db->GetAll("SELECT id FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " ORDER BY id DESC LIMIT 1");
	if (count($results) > 0)
		$nextid = $results[0]['id'] + 1;
	else
		$nextid = 1;
	$parse_class->id = $nextid;
	$ua = ($board_class->board['useragent']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT']) : false;
	$dice = ($board_class->board['dice']) ? true : false;
	$ipmd5 = md5(KU_REMOTE_ADDR);

	if ($parse_class->CountSmilies($_POST['message']) > KU_MAXSMILIES)
	{
		kurisaba_exit('В посте слишком много смайлов. Можно не более '.KU_MAXSMILIES.'.');
	}

	// If they are just a normal user, or vip...
	if (isNormalUser($user_authority)) {
		// If the thread is locked
		if ($thread_locked == 1) {
			// Don't let the user post
			kurisaba_exit(_gettext('Sorry, this thread is locked and can not be replied to.'),'',$_POST['message']);
		}
		
		$post_message = $parse_class->ParsePost($_POST['message'], $board_class->board['name'], $board_class->board['type'], $thread_replyto, $board_class->board['id'], false, $ua, $dice, $ipmd5);

	// Or, if they are a moderator/administrator...
	} else {
		// If they checked the D checkbox, set the variable to tell the script to display their staff status (Admin/Mod) on the post during insertion
		if (isset($_POST['displaystaffstatus'])) {
			$post_displaystaffstatus = true;
		}

		// If they checked the RH checkbox, set the variable to tell the script to insert the post as-is...
		if (isset($_POST['rawhtml'])) {
			$post_message = $_POST['message'];
		// Otherwise, parse it as usual...
		} else {
			$post_message = $parse_class->ParsePost($_POST['message'], $board_class->board['name'], $board_class->board['type'], $thread_replyto, $board_class->board['id'], false, $ua, $dice, $ipmd5);
			// (Moved) check against blacklist and detect flood
		}

		// If they checked the L checkbox, set the variable to tell the script to lock the post after insertion
		if (isset($_POST['lockonpost'])) {
			$post_autolock = true;
		}

		// If they checked the S checkbox, set the variable to tell the script to sticky the post after insertion
		if (isset($_POST['stickyonpost'])) {
			$post_autosticky = true;
		}
		if (isset($_POST['usestaffname'])) {
			$_POST['name'] = md5_decrypt($_POST['modpassword'], KU_RANDOMSEED);
			$post_name = md5_decrypt($_POST['modpassword'], KU_RANDOMSEED);
		}
	}

	$parse_result = $posting_class->postParseCheckText($post_message, $board_class->board['name'], $board_class->board['id']);
	
	if ($parse_result == 2)
	{
		kurisaba_exit('Ссылка в посте находится в чёрном списке.');
	}

	if ($parse_result == 1)
	{
		kurisaba_exit(_gettext('Flood Detected'), _gettext('You are posting the same message again.'));
	}
	
	if($posting_class->CheckBadUnicode($post_name, $post_email, $post_subject, $post_message))
	{
		kurisaba_exit(_gettext('Your post contains one or more illegal characters.'));
	}

	$post_tag = $posting_class->GetPostTag();

	if ($post_isreply) {
		if (!$is_oekaki && $post_message == '')
		if (($_POST['attach_type'] == 'embed' && $_POST['embed'] == '') ||
				($_POST['attach_type'] == 'file' && $imagefile_name == '') ||
				($_POST['attach_type'] == 'drop' && (!isset($_POST['drop_file_name']) || $_POST['drop_file_name'] == '')) ||
				($_POST['attach_type'] == 'link' && $_POST['embedlink'] == '')) {
			kurisaba_exit(_gettext('An image, video, or message, is required for a reply.'),'',$_POST['message']);
		}
	} else {
		if ($imagefile_name == '' && !$is_oekaki  && $_POST['embed'] == '' && $_POST['embedlink'] == '' && $_POST['drop_file_name'] == '' && ((!isset($_POST['nofile'])&&$board_class->board['enablenofile']==1) || $board_class->board['enablenofile']==0) && ($board_class->board['type'] == 0 || $board_class->board['type'] == 2 || $board_class->board['type'] == 3)) {
			if (!isset($_POST['embed']) && $board_class->board['uploadtype'] != 1) {
				kurisaba_exit('ОП-пост нельзя отправить без картинки или видео.','',$_POST['message']);
			}
		}
	}

	if (isset($_POST['nofile'])&&$board_class->board['enablenofile']==1) {
		if ($post_message == '') {
			kurisaba_exit('Нужно ввести текст сообщения или приложить картинку или видео.','',$_POST['message']);
		}
	}

	if ($board_class->board['type'] == 1 && !$post_isreply && $post_subject == '') {
		kurisaba_exit('Новый тред обязан иметь заголовок.','',$_POST['message']);
	}

	if ($board_class->board['locked'] == 0 || ($user_authority > 0 && $user_authority != 3)) {
		require_once KU_ROOTDIR . 'inc/classes/upload.class.php';
		$upload_class = new Upload();
		if ($post_isreply) {
			$upload_class->isreply = true;
		} 

		if ((!isset($_POST['nofile']) && $board_class->board['enablenofile'] == 1) || $board_class->board['enablenofile'] == 0) {
			$upload_log = $upload_class->HandleUpload($_POST['message'], $preview);
			if($upload_log != '')
			{
				$extra = ($upload_log == _gettext('Unable to read uploaded file during thumbnailing.')) ? _gettext('A common cause for this is an incorrect extension when the file is actually of a different type.') : '';
				kurisaba_exit($upload_log,$extra,$_POST['message']);
			}
			if(isset($upload_class->file_location) && $posting_class->CheckBannedHashNew($upload_class->file_location))
			{
				kurisaba_exit(_gettext('Этот файл забанен.'));
			}
		}

		if ($board_class->board['forcedanon'] == '1') {
			if ($user_authority == 0 || $user_authority == 3) {
				$post_name = '';
				/*$post_subject = '';*/
			}
		}

		$nameandtripcode = calculateNameAndTripcode($post_name);
		if (is_array($nameandtripcode)) {
			$name = $nameandtripcode[0];
			$tripcode = $nameandtripcode[1];
		} else {
			$name = $post_name;
			$tripcode = '';
		}
		$filetype_withoutdot = substr($upload_class->file_type, 1);
		$post_passwordmd5 = ($post_password == '') ? '' : md5($post_password);

		if ($post_autosticky == true) {
			if ($thread_replyto == 0) {
				$sticky = 1;
			} else {
				if (!$preview)
				{
					$result = $tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `stickied` = '1' WHERE `boardid` = " . $board_class->board['id'] . " AND `id` = '" . $thread_replyto . "'");
				}
				$sticky = 0;
			}
		} else {
			$sticky = 0;
		}

		if ($post_autolock == true) {
			if ($thread_replyto == 0) {
				$lock = 1;
			} else {
				if (!$preview)
				{
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `locked` = '1' WHERE `boardid` = " . $board_class->board['id'] . " AND `id` = '" . $thread_replyto . "'");
				}
				$lock = 0;
			}
		} else {
			$lock = 0;
		}

		if (!$post_displaystaffstatus && $user_authority > 0 && $user_authority != 3) {
			$user_authority_display = 0;
		} elseif ($user_authority > 0) {
			$user_authority_display = $user_authority;
		} else {
			$user_authority_display = 0;
		}

		if ((file_exists(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . $upload_class->file_type) && file_exists(KU_BOARDSDIR . $board_class->board['name'] . '/thumb/' . $upload_class->file_name . 's' . $upload_class->file_type)) || ($file_is_special && file_exists(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . $upload_class->file_type)) || $post_fileused == false) {
			$post = array();

			//#snivystuff country!
			$post['country'] = isset($_SERVER["HTTP_CF_IPCOUNTRY"]) ? strtolower($_SERVER["HTTP_CF_IPCOUNTRY"]) : 'xx';

			$post['board'] = $board_class->board['name'];
			$post['name'] = mb_substr($name, 0, KU_MAXNAMELENGTH);
			$post['name_save'] = true;
			$post['tripcode'] = $tripcode;
			$post['email'] = mb_substr($post_email, 0, KU_MAXEMAILLENGTH);
			// First array is the converted form of the japanese characters meaning sage, second meaning age
			$ords_email = unistr_to_ords($post_email);
			if (strtolower($_POST['em']) != 'sage' && $ords_email != array(19979, 12370) && strtolower($_POST['em']) != 'age' && $ords_email != array(19978, 12370) && $_POST['em'] != 'return' && $_POST['em'] != 'noko') {
				$post['email_save'] = true;
			} else {
				$post['email_save'] = false;
			}
			$post['subject'] = mb_substr($post_subject, 0, KU_MAXSUBJLENGTH);
			$post['message'] = $post_message;
			$post['message_source'] = $_POST['message'];
			$post['pic_spoiler'] = intval($_POST['picspoiler']);
			$post['pic_animated'] = intval($upload_class->animated);

			$post = hook_process('posting', $post);

			if ($is_oekaki) {
				if (file_exists(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . '.pch')) {
					$post['message'] .= '<br /><small><a href="' . KU_CGIPATH . '/animation.php?board=' . $board_class->board['name'] . '&amp;id=' . $upload_class->file_name . '">' . _gettext('View animation') . '</a></small>';
				}
			}

			if ($thread_replyto != '0') {
				if ($post['message'] == '' && KU_NOMESSAGEREPLY != '') {
					$post['message'] = KU_NOMESSAGEREPLY;
				}
			} else {
				if ($post['message'] == '' && KU_NOMESSAGETHREAD != '') {
					$post['message'] = KU_NOMESSAGETHREAD;
				}
			}

			$post_class = new Post(0, $board_class->board['name'], $board_class->board['id'], true);

			if($preview) // Should be noreturn!
			{
				$post['id'] = '?????';
				$post['parentid'] = $thread_replyto;
				$post['boardid'] = $board_class->board['id'];
				$post['message'] = addslashes($post['message']);
				$post['file'] = $upload_class->file_name;
				$post['file_original'] = $upload_class->original_file_name;
				$post['file_type'] = $filetype_withoutdot;
				$post['file_md5'] = $upload_class->file_md5;
				$post['image_w'] = $upload_class->imgWidth;
				$post['image_h'] = $upload_class->imgHeight;
				$post['file_size'] = $upload_class->file_size;
				$post['file_size_formatted'] = ConvertBytes($upload_class->file_size);
				$post['thumb_w'] = $upload_class->imgWidth_thumb;
				$post['thumb_h'] = $upload_class->imgHeight_thumb;
				$post['password'] = $post_passwordmd5;
				$post['timestamp'] = time() + KU_ADDTIME;
				$post['bumped'] = time() + KU_ADDTIME;
				$post['ip'] = md5_encrypt(KU_SAVEIP ? KU_REMOTE_ADDR : '0.0.0.0', KU_RANDOMSEED);
				$post['ipmd5'] = md5(KU_REMOTE_ADDR);
				$post['posterauthority'] = $user_authority_display;
				$post['stickied'] = $sticky;
				$post['locked'] = $lock;
				$post['n'] = 'N/A';
				
				// The following code is borrowed from read.php.

				$board_class->InitializeDwoo();
				$board_class->dwoo_data->assign('board', $board_class->board);
				$board_class->dwoo_data->assign('isread', true);
				$board_class->dwoo_data->assign('istempfile', true);
				$board_class->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $board_class->board['name']);

				$page ='';

				if ($board_class->board['type'] == 0)
				{
					$embeds = $tc_db->GetAll("SELECT filetype FROM `" . KU_DBPREFIX . "embeds`");
					foreach ($embeds as $embed)
					{
						$board_class->board['filetypes'][] .= $embed['filetype'];
					}
					$board_class->dwoo_data->assign('filetypes', $board_class->board['filetypes']);
				}

				$results = array($board_class->BuildPost($post, false, array(), true));
					
				$board_class->dwoo_data->assign('posts', $results);
				$board_class->dwoo_data->assign('replink', $_GET['replink']);

				$page .= $board_class->dwoo->get(KU_TEMPLATEDIR . '/' . $board_class->board['text_readable'] . '_thread.tpl', $board_class->dwoo_data);
				
				$board_class->PrintPage('', $page, true);
				die;
			}

			// Fromatting of topic subject
			$patterns = array(
				'`\(c\)`',
				'`\(C\)`',
				'`\(с\)`',
				'`\(С\)`',
				'`\(tm\)`',
				'`\(тм\)`',
				'`-&gt;`',
				'`&lt;-`',
				'`- `',
				'`\*(.+?)\*`is',
				'`%%(.+?)%%`is',
				'`~~(.+?)~~`is',
				'`&quot;(.+?)&quot;`is'
			);
			$replaces =  array(
				'&copy;',
				'&copy;',
				'&copy;',
				'&copy;',
				'&trade;',
				'&trade;',
				'&rarr;',
				'&larr;',
				'&mdash; ',
				'<i>\\1</i>',
				'<span class="spoiler">\\1</span>',
				'<strike>\\1</strike>',
				'«\\1»'
			);
			$post['subject'] = preg_replace($patterns, $replaces, $post['subject']);

			$post_id = $post_class->Insert($thread_replyto, $post['name'], $post['tripcode'], $post['email'], $post['subject'], addslashes($post['message']), $post['message_source'], $upload_class->file_name, $upload_class->original_file_name, $filetype_withoutdot, $upload_class->file_md5, $upload_class->image_md5, $upload_class->imgWidth, $upload_class->imgHeight, $upload_class->file_size, $upload_class->imgWidth_thumb, $upload_class->imgHeight_thumb, $post_passwordmd5, time() + KU_ADDTIME, time() + KU_ADDTIME, KU_SAVEIP ? KU_REMOTE_ADDR : '0.0.0.0', $user_authority_display, $sticky, $lock, $board_class->board['id'], $post['country'], $post['pic_spoiler'], $post['pic_animated']);
			if (!$post_id)
			{
				kurisaba_exit('Не получилось добавить пост в базу.');
			}
			if ($post_id == -1)
			{
				// Don't leave orphan files
				@unlink(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . '.pch');
				@unlink(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . $upload_class->file_type);
				@unlink(KU_BOARDSDIR . $board_class->board['name'] . '/thumb/' . $upload_class->file_name . 's' . $upload_class->file_type);
				@unlink(KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $upload_class->file_name . $upload_class->file_type);
				kurisaba_exit('Слишком много сложной разметки в посте, например, ссылок.','',$_POST['message']);
			}

			if ($user_authority > 0 && $user_authority != 3) {
				$modpost_message = 'Modposted #<a href="' . KU_BOARDSFOLDER . $board_class->board['name'] . '/res/';
				if ($post_isreply) {
					$modpost_message .= $thread_replyto;
				} else {
					$modpost_message .= $post_id;
				}
				$modpost_message .= '.html#' . $post_id . '">' . $post_id . '</a> in /'.$_POST['board'].'/ with flags: ' . $flags . '.';
				management_addlogentry($modpost_message, 1, md5_decrypt($_POST['modpassword'], KU_RANDOMSEED));
			}

			if ($post['name_save'] && isset($_POST['name'])) {
				setcookie_strict('name', urldecode($_POST['name']), time() + KU_ADDTIME + (365 * 24 * 3600), '/', KU_DOMAIN);
			}

			if ($post['email_save']) {
				setcookie_strict('email', urldecode($post['email']), time() + KU_ADDTIME + (365 * 24 * 3600), '/', KU_DOMAIN);
			}

			setcookie_strict('postpassword', urldecode($_POST['postpassword']), time() + KU_ADDTIME + (365 * 24 * 3600), '/');
		} else {
			kurisaba_exit(_gettext('Could not copy uploaded image.'),'',$_POST['message']);
		}

		// If the user replied to a thread, and they weren't sage-ing it...
		if ($thread_replyto != '0' && strtolower($_POST['em']) != 'sage' && unistr_to_ords($_POST['em']) != array(19979, 12370)) {
			// And if the number of replies already in the thread are less than the maximum thread replies before perma-sage...
			if ($thread_replies <= $board_class->board['maxreplies']) {
				// Bump the thread
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `bumped` = '" . (time() + KU_ADDTIME) . "' WHERE `boardid` = " . $board_class->board['id'] . " AND `id` = '" . $thread_replyto . "'");
			}
		}

		$tc_db->Execute("COMMIT");

		// Trim any threads which have been pushed past the limit, or exceed the maximum age limit
		TrimToPageLimit($board_class->board);

		if ($thread_replyto == '0') {
			notify($board_class->board['name'].':newthreads', $post_id);
		} else {
			notify($board_class->board['name'].':'.$thread_replyto);
		}
	} else {
		kurisaba_exit(_gettext('Sorry, this board is locked and can not be posted in.'),'',$_POST['message']);
	}

	if( $_POST['redirecttothread'] == 1 || $_POST['em'] == 'return' || $_POST['em'] == 'noko') {
		setcookie_strict('tothread', 'on', time() + KU_ADDTIME + (365 * 24 * 3600), '/');
		$tothread_num = $thread_replyto;
		if ($tothread_num == "0") $tothread_num = $post_id;
		if (isset($_POST['plus50'])) $tothread_num .= '+50';
		if (isset($_POST['minus100'])) $tothread_num .= '-100';
		kurisaba_redirect(KU_BOARDSPATH . '/' . $board_class->board['name'] . '/res/' . $tothread_num . '.html#boardlist_footer', true, $imagefile_name, $post_id);
	} else {
		setcookie_strict('tothread', 'off', time() + KU_ADDTIME + (365 * 24 * 3600), '/');
		kurisaba_redirect(KU_BOARDSPATH . '/' . $board_class->board['name'] . '/', true, $imagefile_name, $post_id);
	}
}
