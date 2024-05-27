<?php

// Calculate hash for ban
function md5_image($file)
{
	$imageDim = getimagesize($file);
	$image_md5 = '';
	$img_tmp = false;
	if      ($imageDim[2] == IMAGETYPE_JPEG) $img_tmp = imagecreatefromjpeg($file);
	else if ($imageDim[2] == IMAGETYPE_PNG)  $img_tmp = imagecreatefrompng($file);
	else if ($imageDim[2] == IMAGETYPE_WEBP) $img_tmp = imagecreatefromwebp($file);
	else if ($imageDim[2] == IMAGETYPE_GIF)  $img_tmp = imagecreatefromgif($file);
	if ($img_tmp !== false)
	{
		ob_start();
		imagepng($img_tmp);
		$contents = ob_get_contents();
		ob_end_clean();
		$image_md5 = md5($contents);
		imagedestroy($img_tmp);
	}
	return $image_md5;
}

function file_get_contents_remote($url)
{
	$ch = curl_init($url);
	if(($err = curl_error($ch)) != '') return 'curl_init(): '. $err;
	curl_setopt($ch, CURLOPT_HEADER, false);
	if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_HEADER): '. $err;
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_RETURNTRANSFER): '. $err;
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
	if (KU_CURL_PROXY == "interface") {
		curl_setopt($ch, CURLOPT_INTERFACE, /*"venet0:0"*/ /*"tunb"*/ KU_CURL_INTERFACE );
		if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_INTERFACE): '. $err;
	} else if (KU_CURL_PROXY == "vpnbook") {
		curl_setopt($ch, CURLOPT_COOKIEJAR, "");
		if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_COOKIEJAR): '. $err;
		curl_setopt($ch, CURLOPT_URL, "https://frproxy.vpnbook.com/includes/process.php?action=update");
		if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_URL): '. $err;
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array('u' => $url, 'webproxylocation' => 'random'));
		if(($err = curl_error($ch)) != '') return 'curl_setopt(CURLOPT_POSTFIELDS): '. $err;
	} else if ((KU_CURL_PROXY != "none")) {
		return "curl: insufficient KU_CURL_PROXY";
	}
	$ret = curl_exec($ch);
	if(($err = curl_error($ch)) != '') return 'curl_exec(): '. $err;
	curl_close($ch);
	return $ret;
}

function changeLocale($newlocale) {
	global $CURRENTLOCALE, $EMULATEGETTEXT, $text_domains;
	$CURRENTLOCALE = $newlocale;
	$EMULATEGETTEXT = 1;
	_textdomain('kusaba');
	_setlocale(LC_ALL, $newlocale);
	_bindtextdomain('kusaba', KU_ROOTDIR . 'inc/lang', $newlocale);
	_bind_textdomain_codeset('kusaba', KU_CHARSET);

}

function mime_to_extension($mime_type)
{
	$mime_types = array
	(
		'image/gif'  => '.gif',
		'image/jpeg' => '.jpg',
		'image/webp' => '.webp',
		'image/png'  => '.png',
		'audio/mp3'  => '.mp3',
		'audio/x-m4a'  => '.m4a',
		'audio/ogg'  => '.ogg',
		'video/webm' => '.webm',
		'video/mp4'  => '.mp4'
	);
	if (array_key_exists($mime_type, $mime_types))
	{
		return $mime_types[$mime_type];
	}
	return '';
}

function replies_cmp($a, $b)
{
    if ($a['boardname'] == $b['boardname'])
	{
		if ($a['id'] == $b['id'])
		{
			return 0;
		}
		return ($a['id'] < $b['id']) ? -1 : 1;
    }
    return ($a['boardname'] < $b['boardname']) ? -1 : 1;
}

function array_in_array($some, $all) {
	if(count(array_intersect($some, $all)) == count($some)) return true;
	else return false;
}

function exitWithErrorPage($errormsg, $extended = '', $posttext = '', $template = '/error.tpl', $boardname = '') {
	global $dwoo, $dwoo_data, $board_class;
	if (!isset($dwoo)) {
		require_once KU_ROOTDIR . 'lib/dwoo.php';
		$dwoo = new Dwoo();
		$dwoo_data = new Dwoo_Data();
		$dwoo_data->assign('cwebpath', KU_WEBPATH . '/');
		$dwoo_data->assign('boardpath', KU_BOARDSPATH . '/');
	}
	if (!isset($board_class)) {
		require_once KU_ROOTDIR . 'inc/classes/board-post.class.php';
		$board_class = new Board('');
	}

	$dwoo_data->assign('styles', explode(':', KU_STYLES));
	$dwoo_data->assign('errormsg', $errormsg);
	$dwoo_data->assign('boardname', $boardname);

	if ($posttext != '') {
		$postmsg = '<br /><center><table width=80%><tr><td>Содержимое поста:</td></tr><tr><td><form><textarea rows=4 cols=48>'. $posttext .'</textarea></form></td></tr></table></center>';
	}

	if ($extended != '') {
		$dwoo_data->assign('errormsgext', '<br /><div style="text-align: center;font-size: 1.25em;">' . $extended . $postmsg . '</div>');
	} else {
		$dwoo_data->assign('errormsgext', $extended . $postmsg);
	}
	
	echo $dwoo->get(KU_TEMPLATEDIR . $template, $dwoo_data);

	die();
}

function AnswerMapPerformAddition($query)
{
	global $tc_db;
	$query_text = "INSERT INTO `" . KU_DBPREFIX . "answers` (`from_id`, `from_boardid`, `from_parentid`, `from_boardname`, `to_id`, `to_boardid`, `to_parentid`, `to_boardname`) ";
	$first = true;
	foreach ($query as $record)
	{	
		$query_text .= ($first? "VALUES " : ", ");
		$first = false;
		$query_text .= "('" . $record['id'] . "', '" . $record['boardid'] . "', '" . $record['parentid'] . "', '" . $record['boardname'] . "', '" . $record['answer_id'] . "', '" . $record['answer_boardid'] . "', '" . $record['answer_parentid'] . "', '" . $record['answer_boardname'] . "')";
	}
	$tc_db->Execute($query_text);
}

function AnswerMapAdd($ans_req, $boardids)
{
	$query = array();
	$altered_threads = array();
	foreach($ans_req as $request)
	{
		// Get each data record from input array
		$id        = $request['id'];
		$boardid   = $request['boardid'];
		$boardname = $request['boardname'];
		$parentid  = $request['parentid'];
		$message   = $request['message'];
		
		// Analyze each message
		preg_match_all('/class=\\\"ref\|(.+?)\|(.+?)\|(.+?)\\\"/',$message, $arr);
		for ($i = 0; $i < count($arr[0]); $i++)
		{
			$answer_boardname = $arr[1][$i];
			$answer_parentid  = $arr[2][$i];
			$answer_id        = $arr[3][$i];
			$answer_boardid   = $boardids[$answer_boardname];
			
			// Note: $parentid id is 0 for in-post threads; but $answer_parentid is = $answer_id for in-post threads.
			if (!in_array($answer_parentid, $altered_threads)) array_push($altered_threads, $answer_parentid);

			// Pack resulted data into output array
			array_push($query, array('id' => $id, 'boardid' => $boardid, 'parentid' => $parentid, 'boardname' => $boardname, 'answer_id' => $answer_id, 'answer_boardid' => $answer_boardid, 'answer_parentid' => $answer_parentid, 'answer_boardname' => $answer_boardname));
			
			if (count($query) >= 500)
			{
				AnswerMapPerformAddition($query);
				$query = array();
			}
		}
	}
	if (count($query) > 0)
	{
		AnswerMapPerformAddition($query);
	}
	return $altered_threads;
}

function AnswerMapDelete($id, $boardid, $parentid = 0)
{
	global $tc_db;
	if ($id == 0) // Delete the whole thread subposts
	{
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "answers` WHERE `from_parentid` = '" . $parentid . "' AND `from_boardid` = '" . $boardid . "'");
	}
	else // Delete only one post
	{
		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "answers` WHERE `from_id` = '" . $id . "' AND `from_boardid` = '" . $boardid . "'");
	}
}


/**
 * Add an entry to the modlog
 *
 * @param string $entry Entry text
 * @param integer $category Category to file under. 0 - No category, 1 - Login, 2 - Cleanup/rebuild boards and html files, 3 - Board adding/deleting, 4 - Board updates, 5 - Locking/stickying, 6 - Staff changes, 7 - Thread deletion/post deletion, 8 - Bans, 9 - News, 10 - Global changes, 11 - Wordfilter
 * @param string $forceusername Username to force as the entry username
 */
function management_addlogentry($entry, $category = 0, $forceusername = '') {
	global $tc_db;

	$username = ($forceusername == '') ? $_SESSION['manageusername'] : $forceusername;

	if ($entry != '') {
		$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "modlog` ( `entry` , `user` , `category` , `timestamp` ) VALUES ( " . $tc_db->qstr($entry) . " , '" . $username . "' , " . $tc_db->qstr($category) . " , '" . (time() + KU_ADDTIME) . "' )");
	}
}

function sendStaffMail($subject, $message) {
	$emails = split(':', KU_APPEAL);
	$expires = ($line['until'] > 0) ? date("F j, Y, g:i a", $line['until']) : 'never';
	foreach ($emails as $email) {
		@mail($email, $subject, $message, 'From: "' . KU_NAME . '" <kusaba@noreply' . KU_DOMAIN . '>' . "\r\n" . 'Reply-To: kusaba@noreply' . KU_DOMAIN . "\r\n" . 'X-Mailer: kurisaba' . KU_VERSION . '/PHP' . phpversion());
	}
}

/* Depending on the configuration, use either a meta refresh or a direct header */
function do_redirect($url, $ispost = false, $file = '') {
	global $board_class;
	$headermethod = true;

	if ($headermethod) {
		/* setcookie_strict('tothread', $gtt, 0, '/', KU_DOMAIN); - Was this even working at all? */
		if ($ispost) {
			header('Location: ' . $url);
		} else {
			die('<meta http-equiv="refresh" content="1;url=' . $url . '">');
		}
	} else {
		if ($ispost && $file != '') {
			echo sprintf(_gettext('%s uploaded.'), $file) . ' ' . _gettext('Updating pages.');
		} elseif ($ispost) {
			echo _gettext('Post added.') . ' ' . _gettext('Updating pages.'); # TEE COME BACK
		} else {
			echo '---> ---> --->';
		}
		die('<meta http-equiv="refresh" content="1;url=' . $url . '">');
	}
}
