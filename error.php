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
	global $tc_db, $error, $errorcode;
	if ($tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)) > 0)
	{
		$board_class = new Board($board);
		$country_restrict = $board_class->board['country_restrict'];
		if($country_restrict != '')
		{
			if (in_array(client_country(), explode(',', strtoupper(str_replace(' ', '', $country_restrict)))))
			{
				http_response_code(451); $errorcode = 451;
				$error = _gettext('This material is unavailable in your country.');
				return false;
			}
		}
		return $board_class;
	}
	http_response_code(404); $errorcode = 404;
	$error = '404: Такой борды не существует.';
	return false;
}

function geoblocked($address)
{
	global $tc_db;
	preg_match("/^\/([A-Za-z0-9]+)\/(res|thumb)\/([0-9]+)[a-z]?\.([A-Za-z0-9]+)$/", $address, $matches);
	$board = $matches[1];
	$file = $matches[3];
	$ext = $matches[4];
	$records = $tc_db->GetAll("SELECT `".KU_DBPREFIX."posts`.`country_restrict_file`, `".KU_DBPREFIX."boards`.`id`, `".KU_DBPREFIX."boards`.`name`, `".KU_DBPREFIX."posts`.`IS_DELETED`, `".KU_DBPREFIX."posts`.`boardid`, `".KU_DBPREFIX.
	if(count($records) < 1) return true; // Block pics from deleted or missing posts
	foreach($records as $record)
	{
		$country_restrict = $record['country_restrict_file'];
		if($country_restrict != '')
		{
			if (in_array(client_country(), explode(',', strtoupper(str_replace(' ', '', $country_restrict))))) return true;
		}
	}
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

// Offload engine
if(KU_OFFLOAD)
{
	$filetypes = $tc_db->GetAll("SELECT `filetype`, `mime` FROM `" . KU_DBPREFIX . "filetypes`");
	foreach ($filetypes as $filetype)
	{
		if (geoblocked($address))
		{
			http_response_code(451);
			header('Content-type: image/jpeg');
			echo file_get_contents(KU_ROOTDIR . 'images/451.jpg');
			exit();
		}
		if (preg_match("/^\/[a-z]+\/(src|thumb)\/[0-9]+[a-z]?\." . $filetype['filetype'] . "$/", $address, $matches))
		{
			$content = file_get_contents(KU_ROOTDIR . $address);
			if ($content !== false)
			{
				http_response_code(200); header("Status: 200 OK");
				header('Content-type: ' . $filetype['mime']);
				echo $content;
				exit();
			}
			else
			{
				http_response_code($errorcode);
				header('Content-type: image/jpeg');
				echo file_get_contents(KU_ROOTDIR . 'images/404.jpg');
				exit();
			}
		}
	}
}

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
	else if ($errorcode == 451)
	{
		request_log("Получил 451 при обращении к ".$_SERVER['REQUEST_URI']);
		$error = '451: Забанено <s>кровавой гэбнёй</s> Организацией!';
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
