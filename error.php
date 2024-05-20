<?php
$errorcode = $_GET['p'];

require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';
modules_load_all();

function request_log($text)
{
	/*global $tc_db;
	$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "reqlog` ( `request`, `text` , `ip` , `timestamp` ) VALUES ( " .
	$tc_db->qstr($_SERVER['REQUEST_URI']) . " , " .
	$tc_db->qstr($text) . " , " .
	$tc_db->qstr(KU_REMOTE_ADDR) . " , '" .
	(time() + KU_ADDTIME) . "' )");*/
}

function CreateBoard($board)
{
	global $tc_db;
	if ($tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)) > 0)
	{
		$board_class = new Board($board);
		return $board_class;
	}
	$error = '404: Такой борды не существует.';
	return false;
}

$address = explode('?',$_SERVER['REQUEST_URI'])[0]; // Remove request parameters sinse we're mimicking "static" html
// Board Page 0
if(preg_match("/^\/([a-z]+)\/board\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		http_response_code(200); header("Status: 200 OK");
		request_log("Открыл борду ".$matches[1]." через ссылку board.html");
		$board_class->RegenerateAndPrintPage(0);
		exit();
	}
}
// Board Page 0 or special thread
if(preg_match("/^\/([A-Z0-9a-z\/]+?)\/$/", $address, $matches) || preg_match("/^\/([A-Z0-9a-z\/]+?)$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		http_response_code(200); header("Status: 200 OK");
		request_log("Открыл борду ".$matches[1]." через ссылку со слэшем");
		$board_class->RegenerateAndPrintPage(0);
		exit();
	}
	else // Special thread
	{
		request_log("Открыл специальный тред ".$matches[1]." через ссылку со слэшем");
		$special_threads = $tc_db->GetOne("SELECT `value` FROM `" . KU_DBPREFIX . "kurisaba_ext_data` WHERE `name` = 'special_threads'");
		$special_threads = preg_replace('/ +/', ' ', $special_threads);
		$special_threads = explode("\n", $special_threads);
		$redirect_to='';
		$current_board='';
		foreach ($special_threads as $special_thread)
		{
		$special_thread = explode(' ', trim($special_thread), 4);
			if($special_thread[0] == 'BOARD')
			{
				$current_board = $special_thread[1];
			}
			else if($special_thread[0] == 'THREAD' || $special_thread[0] == 'HIDDEN')
			{
				if ($special_thread[2] == '/'.$matches[1].'/') $redirect_to = '/'.$current_board.'/res/'.$special_thread[1].'.html';
			}
		}
		if($redirect_to != '')
		{
			header('Location: ' . KU_WEBPATH . $redirect_to); die();
		}
	}
}

// Board Catalog
else if (preg_match("/^\/([a-z]+)\/catalog\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		http_response_code(200); header("Status: 200 OK");
			request_log("Открыл каталог борды ".$matches[1]);
		$board_class->RegenerateAndPrintCatalog();
		exit();
	}
}

// Board Page 1-...
else if (preg_match("/^\/([a-z]+)\/([0-9]+)\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		$postsperpage = KU_THREADS;
		if     ($board_class->board['type'] == 1) { $postsperpage = KU_THREADSTXT; }
		elseif ($board_class->board['type'] == 3) { $postsperpage = 30; }
		
		$threads_on_page = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `parentid` = 0 AND `IS_DELETED` = 0 ORDER BY `stickied` DESC, `bumped` DESC LIMIT " . ($postsperpage) . " OFFSET " . ($postsperpage * $i));

		if($threads_on_page > 0)
		{
			http_response_code(200); header("Status: 200 OK");
			request_log("Открыл страницу ".$matches[2]." борды ".$matches[1]);
			$board_class->RegenerateAndPrintPage($matches[2]);
			exit();
		}
	}
}

// Thread
else if (preg_match("/^\/([a-z]+)\/res\/([0-9]+)\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		$thread_exist = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `parentid` = 0 AND `IS_DELETED` = 0 AND `id` = " . $matches[2]);

		if($thread_exist > 0)
		{
			http_response_code(200); header("Status: 200 OK");
			request_log("Открыл тред ".$matches[1]."/".$matches[2]);
			$board_class->RegenerateAndPrintThread($matches[2]);
			exit();
		}
	}
}

// Thread +50
else if (preg_match("/^\/([a-z]+)\/res\/([0-9]+)\+50\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		$thread_exist = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `IS_DELETED` = 0 AND `parentid` = " . $matches[2]);

		if($thread_exist > 50) /* OP + 50 replies */
		{
			http_response_code(200); header("Status: 200 OK");
			request_log("Открыл тред ".$matches[1]."/".$matches[2]." (+50)");
			$board_class->RegenerateAndPrintThread($matches[2], '+50');
			exit();
		}
	}
}

// Thread -100
else if (preg_match("/^\/([a-z]+)\/res\/([0-9]+)\-100\.html$/", $address, $matches))
{
	$board_class = CreateBoard($matches[1]);
	if ($board_class)
	{
		$thread_exist = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `IS_DELETED` = 0 AND `parentid` = " . $matches[2]);

		if($thread_exist >= 100) /* OP + 99 replies */
		{
			http_response_code(200); header("Status: 200 OK");
			request_log("Открыл тред ".$matches[1]."/".$matches[2]." (-100)");
			$board_class->RegenerateAndPrintThread($matches[2], '-100');
			exit();
		}
	}
}
// ku_storage

/** pass $ip_resolved as null use DNS */
function curl_get($url, $ip_resolved) {
	$ch = curl_init($url);

	if ($ip_resolved != null) {
		$host = explode ("/", $url , 5);
		$host = $host[2];
		$port = strstr($host, ":");
		if ($port === false)
			$port = ":443";
		curl_setopt($ch, CURLOPT_RESOLVE, array($host . $port . ":" . $ip_resolved, $host . ":80:" . $ip_resolved));
	}
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
	curl_setopt($ch, CURLOPT_TIMEOUT, 25);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$out = curl_exec($ch);

	if(curl_errno($ch))
		return false;

	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($httpCode != 200)
		return false;

	return $out;
}

if (preg_match("/^\/[a-z]+\/src\/[0-9]+\.(jpg|png|gif|webp)$/", $address, $matches))
{
	$content = curl_get(KU_STORAGE_PREFIX . $address, KU_STORAGE_IP);
	if ($content !== false) {
		http_response_code(200); header("Status: 200 OK");
		header('Content-type: image/jpeg');
		echo $content;
		exit();
	}
}

/*$content = curl_get('http://kustorage.local/48677204b6aeaa29d84166ace10c3e68.jpg', "127.0.0.1");
if ($content !== false) {
	header('Content-type: image/jpeg');
	echo $content;
	exit(0);
}*/

// Another error
if ($error == '')
{
	if ($errorcode == 404)
	{
		request_log("Попытался обратиться к несуществующей странице ".$_SERVER['REQUEST_URI']);
		$error = '404: Ничего такого на этой борде не обнаружено.';
	}
	else if ($errorcode == 403)
	{
		request_log("Получил 403 при обращении к ".$_SERVER['REQUEST_URI']);
		$error = '403: Ты не пройдёшь!';
	}
	else
	{
		request_log("Получил код ".$errorcode." при обращении к ".$_SERVER['REQUEST_URI']);
		$error = $errorcode . ': Что-то пошло не так...';
	}
}
header('Content-type: text/html; charset=utf-8');
exitWithErrorPage($error, '<a href="/' . KU_DEFAULTBOARD . '">Вернуться на главную</a>');
?>
