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

function ku_is_public_ip($ip)
{
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function ku_remote_url_ips($host)
{
	if (!is_string($host) || $host === '') {
		return false;
	}

	$host = trim($host, "[] \t\n\r\0\x0B");
	if (filter_var($host, FILTER_VALIDATE_IP)) {
		return array($host);
	}

	if (!preg_match('/^[A-Za-z0-9.-]+$/', $host)) {
		return false;
	}

	$ips = array();
	$records = @dns_get_record($host, DNS_A + DNS_AAAA);
	if (is_array($records)) {
		foreach ($records as $record) {
			if (isset($record['ip'])) {
				$ips[] = $record['ip'];
			}
			if (isset($record['ipv6'])) {
				$ips[] = $record['ipv6'];
			}
		}
	}

	if (empty($ips)) {
		$ipv4 = @gethostbynamel($host);
		if (is_array($ipv4)) {
			$ips = array_merge($ips, $ipv4);
		}
	}

	$ips = array_values(array_unique($ips));
	return empty($ips) ? false : $ips;
}

function ku_validate_remote_url($url)
{
	if (!is_string($url) || strlen($url) > 2048) {
		return array(false, 'Invalid URL');
	}

	$parts = parse_url($url);
	if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
		return array(false, 'Invalid URL');
	}

	$scheme = strtolower($parts['scheme']);
	if ($scheme !== 'http' && $scheme !== 'https') {
		return array(false, 'Only http and https URLs are allowed');
	}

	if (isset($parts['user']) || isset($parts['pass'])) {
		return array(false, 'URLs with credentials are not allowed');
	}

	$ips = ku_remote_url_ips($parts['host']);
	if ($ips === false) {
		return array(false, 'Unable to resolve host');
	}

	foreach ($ips as $ip) {
		if (!ku_is_public_ip($ip)) {
			return array(false, 'Remote URL resolves to a private or reserved address');
		}
	}

	return array(true, '');
}

function ku_build_redirect_url($base_url, $location)
{
	if (!is_string($location) || $location === '') {
		return false;
	}

	if (parse_url($location, PHP_URL_SCHEME) !== null) {
		return $location;
	}

	$base = parse_url($base_url);
	if ($base === false || empty($base['scheme']) || empty($base['host'])) {
		return false;
	}

	$authority = $base['scheme'] . '://' . $base['host'];
	if (isset($base['port'])) {
		$authority .= ':' . $base['port'];
	}

	if (substr($location, 0, 2) === '//') {
		return $base['scheme'] . ':' . $location;
	}

	if (substr($location, 0, 1) === '/') {
		return $authority . $location;
	}

	$path = isset($base['path']) ? $base['path'] : '/';
	$dir = preg_replace('#/[^/]*$#', '/', $path);
	return $authority . $dir . $location;
}

function ku_curl_apply_common_options($ch)
{
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Kurisaba remote upload');

	$protocols = 0;
	if (defined('CURLPROTO_HTTP')) {
		$protocols |= CURLPROTO_HTTP;
	}
	if (defined('CURLPROTO_HTTPS')) {
		$protocols |= CURLPROTO_HTTPS;
	}
	if ($protocols !== 0) {
		curl_setopt($ch, CURLOPT_PROTOCOLS, $protocols);
		if (defined('CURLOPT_REDIR_PROTOCOLS')) {
			curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $protocols);
		}
	}
}

function file_get_contents_remote($url, $checksize)
{
	$redirects = 0;
	$current_url = $url;

	while ($redirects <= 4) {
		$url_check = ku_validate_remote_url($current_url);
		if ($url_check[0] === false) {
			return array(false, $url_check[1]);
		}

		$ch = curl_init($current_url);
		if (($err = curl_error($ch)) != '') {
			return array(false, 'curl_init(): ' . $err);
		}
		ku_curl_apply_common_options($ch);

		if ($checksize) {
			curl_setopt($ch, CURLOPT_NOBODY, true);
		}

		if (KU_CURL_PROXY == "interface") {
			curl_setopt($ch, CURLOPT_INTERFACE, KU_CURL_INTERFACE);
			if (($err = curl_error($ch)) != '') {
				return array(false, 'curl_setopt(CURLOPT_INTERFACE): ' . $err);
			}
		} else if (KU_CURL_PROXY == "vpnbook") {
			curl_setopt($ch, CURLOPT_COOKIEJAR, "");
			if (($err = curl_error($ch)) != '') {
				return array(false, 'curl_setopt(CURLOPT_COOKIEJAR): ' . $err);
			}
			curl_setopt($ch, CURLOPT_URL, "https://frproxy.vpnbook.com/includes/process.php?action=update");
			if (($err = curl_error($ch)) != '') {
				return array(false, 'curl_setopt(CURLOPT_URL): ' . $err);
			}
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array('u' => $current_url, 'webproxylocation' => 'random'));
			if (($err = curl_error($ch)) != '') {
				return array(false, 'curl_setopt(CURLOPT_POSTFIELDS): ' . $err);
			}
		} else if (KU_CURL_PROXY != "none") {
			return array(false, "curl: insufficient KU_CURL_PROXY");
		}

		$ret = curl_exec($ch);
		if (($err = curl_error($ch)) != '') {
			curl_close($ch);
			return array(false, 'curl_exec(): ' . $err);
		}

		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		$headers = substr($ret, 0, $header_size);
		$body = substr($ret, $header_size);

		if ($status >= 300 && $status <= 308) {
			if (!preg_match('/^Location:\s*(.+)$/im', $headers, $matches)) {
				return array(false, 'Redirect response without Location header');
			}
			$next_url = ku_build_redirect_url($current_url, trim($matches[1]));
			if ($next_url === false) {
				return array(false, 'Invalid redirect URL');
			}
			$current_url = $next_url;
			$redirects++;
			continue;
		}

		if ($status < 200 || $status >= 300) {
			return array(false, 'Request failed with status code ' . $status);
		}

		if ($checksize) {
			if (preg_match('/^Content-Length:\s*(\d+)$/im', $headers, $matches)) {
				return array(true, (int) $matches[1]);
			}
			return array(false, 'Unable to determine content length when checking file size');
		}

		return array(true, $body);
	}

	return array(false, 'Too many redirects');
}

function ku_normalize_base_dir($dir)
{
	$real = realpath($dir);
	if ($real === false) {
		return false;
	}
	return rtrim(str_replace('\\', '/', $real), '/') . '/';
}

function ku_path_in_dir($path, $dir)
{
	$base = ku_normalize_base_dir($dir);
	$real = realpath($path);
	if ($base === false || $real === false) {
		return false;
	}
	$real = str_replace('\\', '/', $real);
	return strpos($real, $base) === 0;
}

function ku_safe_attachment_filename($filename)
{
	return is_string($filename)
		&& $filename !== ''
		&& basename($filename) === $filename
		&& strpos($filename, '..') === false
		&& preg_match('/^[A-Za-z0-9._-]+$/', $filename);
}

function ku_safe_unserialize_array($payload)
{
	if (!is_string($payload) || $payload === '') {
		return array();
	}

	if (PHP_VERSION_ID >= 70000) {
		$data = @unserialize($payload, array('allowed_classes' => false));
	} else {
		$data = @unserialize($payload);
	}

	return is_array($data) ? $data : array();
}

function ku_random_token($bytes = 32)
{
	if (function_exists('random_bytes')) {
		return bin2hex(random_bytes($bytes));
	}
	if (function_exists('openssl_random_pseudo_bytes')) {
		return bin2hex(openssl_random_pseudo_bytes($bytes));
	}
	return md5(uniqid(mt_rand(), true));
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
		'video/mp4'  => '.mp4',
		'video/x-m4v'  => '.m4v'
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

function isValidMd5($md5 = '')
{
    return preg_match('/^[a-f0-9]{32}$/', $md5);
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
		$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "modlog` ( `entry` , `user` , `category` , `timestamp` ) VALUES ( " . $tc_db->qstr($entry) . " , " . $tc_db->qstr($username) . " , " . $tc_db->qstr($category) . " , '" . (time() + KU_ADDTIME) . "' )");
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
