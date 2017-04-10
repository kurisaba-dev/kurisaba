<?php

// You may send data with:
// 1. Form data (GET or POST);
// 2. JSON string as form data ("json" parameter);
// 3. JSON raw data.

require 'config.php';
//require KU_ROOTDIR . 'inc/functions.php';
//require KU_ROOTDIR . 'inc/classes/board-post.class.php';

function get_boardid_by_name($request_id, $boardname)
{
	global $tc_db;

	$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($boardname));
	if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "No such board found", $request_id);
	
	return $dbdata[0]['id'];
}

function determine_reflinks($request)
{
	if (!isset($request['skipreflinks']))   return false;
	if ($request['skipreflinks'] === true)  return true;
	if ($request['skipreflinks'] === false) return false;
	json_exit(400, "Incorrect skipreflinks value", $request['id']);
}

function determine_msgfield($request)
{
	if (!isset($request['msgtype']))     return false;
	if ($request['msgtype'] == 'source') return true;
	if ($request['msgtype'] == 'parsed') return false;
	json_exit(400, "Incorrect msgtype value", $request['id']);
}

function gen_posts($skipreflinks, $msgsource, $boardid, $dbdata, $extended = false, $previewnum = 0)
{
	global $tc_db;
	
	$result = Array();
	
	foreach ($dbdata as $dbentry)
	{
		if (!$skipreflinks)
		{
			$reflinks = Array();
			$dbdata2 = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "answers` WHERE `to_boardid` = " . $boardid . " AND `to_id` = " . $tc_db->qstr($dbentry['id']));
			if (is_array($dbdata2) && count($dbdata2) > 0)
			{
				foreach ($dbdata2 as $dbentry2)
				{
					array_push($reflinks, Array
					(
						"board" => $dbentry2['from_boardname'],
						"id"    => $dbentry2['from_id']
					));
				}
			}
		}
	
		if ($extended)
		{
			$numreplies = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `parentid` = "  . $tc_db->qstr($dbentry['id']) . " AND IS_DELETED = 0");
			$numpicreplies = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `parentid` = "  . $tc_db->qstr($dbentry['id']) . " AND `file_type` IN ('jpg', 'png', 'gif') AND IS_DELETED = 0");
			
			$lastreplies = Array();
			$dbdata3 = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `parentid` = " . $tc_db->qstr($dbentry['id']) . " AND IS_DELETED = 0 ORDER BY `id` DESC LIMIT " . $previewnum);
			if (is_array($dbdata3) && count($dbdata3) > 0)
			{
				$lastreplies = array_reverse(gen_posts($skipreflinks, $msgsource, $boardid, $dbdata3),true);
			}
			
			array_push($result, Array
			(
				"opflags" => Array
				(
					"stickied" => $dbentry['stickied'],
					"locked"   => $dbentry['locked']
				),
				"numreplies"    => $numreplies,
				"numpicreplies" => $numpicreplies,
				"op"            => Array
				(
					"id"       => $dbentry['id'],
					"thread"   => (($dbentry['parentid'] == 0)? $dbentry['id'] : $dbentry['parentid']),
					"subject"  => $dbentry['subject'],
					"name"     => $dbentry['name'],
					"tripcode" => $dbentry['tripcode'],
					"email"    => $dbentry['email'],
					"datetime" => $dbentry['timestamp'],
					"filename" => $dbentry['file'],
					"filetype" => $dbentry['file_type'],
					"filesize" => $dbentry['file_size'],
					"pic_w"    => $dbentry['image_w'],
					"pic_h"    => $dbentry['image_h'],
					"thumb_w"  => $dbentry['thumb_w'],
					"thumb_h"  => $dbentry['thumb_h'],
					"animated" => $dbentry['pic_animated'],
					"spoiler"  => $dbentry['pic_spoiler'],
					"text"     => ($msgsource ? $dbentry['message_source'] : $dbentry['message']),
					"reflinks" => ($skipreflinks ? null : $reflinks)
				),
				"lastreplies"   => $lastreplies
			));
		}
		else
		{
			$result[$dbentry['id']] = Array
			(
				"id"       => $dbentry['id'],
				"thread"   => (($dbentry['parentid'] == 0)? $dbentry['id'] : $dbentry['parentid']),
				"subject"  => $dbentry['subject'],
				"name"     => $dbentry['name'],
				"tripcode" => $dbentry['tripcode'],
				"email"    => $dbentry['email'],
				"datetime" => $dbentry['timestamp'],
				"filename" => $dbentry['file'],
				"filetype" => $dbentry['file_type'],
				"filesize" => $dbentry['file_size'],
				"pic_w"    => $dbentry['image_w'],
				"pic_h"    => $dbentry['image_h'],
				"thumb_w"  => $dbentry['thumb_w'],
				"thumb_h"  => $dbentry['thumb_h'],
				"animated" => $dbentry['pic_animated'],
				"spoiler"  => $dbentry['pic_spoiler'],
				"text"     => ($msgsource ? $dbentry['message_source'] : $dbentry['message']),
				"reflinks" => ($skipreflinks ? null : $reflinks)
			);
		}
	}
	return $result;
}

function json_exit($code, $message, $id = null)
{
	http_response_code($code);
	header('Content-Type: application/json');
	die(json_encode(Array("result" => Array("message" => $message), "error" => $code, "id" => $id)));
}

$api_function = Array
(
	// Special objects:
	// <BOARD>: "id":string =>  {"name":string, "captchaenabled":bool}
	// <POST>:  "id":integer => {"id":integer, "thread":integer, "subject":string, "name":string, "tripcode":string, "email":string,
	//          "datetime":unixtime, "filename":string(or ''), "filetype":string(or ''), "filesize":integer, "pic_w":integer, "pic_h":integer,
	//          "thumb_w":integer, "thumb_h":integer, "text":string, "reflinks":Array of {"board":string, "id":integer}}

	'get_boards' => function($request, $request_id)
	{
		// Get board list.
		// Request: none.
		// Response: Array of <BOARD>s.

		global $tc_db;

		$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "boards`");
		if (count($dbdata) == 0) json_exit(404, "get_boards(): No boards defined", $request_id);
		
		$result = Array();
		foreach ($dbdata as $dbentry)
		{
			$result[$dbentry['name']] = Array
			(
				"name" =>           $dbentry['desc'],
				"captchaenabled" => $dbentry['enablecaptcha']
			);
		}
		return $result;
	},

	'get_thread' => function($request, $request_id)
	{
		// Get every post in a thread.
		// Request: {"board":string, "thread_id":integer}
		// Response: Array of <POST>s (including OP).
		
		global $tc_db;

		if(!isset($request['board']) || !isset($request['thread_id'])) json_exit(400, "get_thread(): Required field(s) missing", $request_id);

		$boardid      = get_boardid_by_name($request_id, $request['board']);
		$skipreflinks = determine_reflinks($request);
		$msgsource    = determine_msgfield($request);
		
		$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND ((`id` = " . $tc_db->qstr($request['thread_id']) . " AND `parentid` = 0) OR `parentid` = " . $tc_db->qstr($request['thread_id']) . ") AND IS_DELETED = 0 ORDER BY `id` ASC");
		if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "get_thread(): No such thread found", $request_id);

		return gen_posts($skipreflinks, $msgsource, $boardid, $dbdata);
	},

	'get_updates_to_thread' => function($request, $request_id)
	{
		// Get every post in a thread after chosen timestamp.
		// Request: {"board":string, "thread_id":integer, "timestamp":unixtime}
		// Response: Array of <POST>s(with timestamp > "timestamp" from request).

		global $tc_db;

		if(!isset($request['board']) || !isset($request['thread_id']) || !isset($request['timestamp'])) json_exit(400, "get_updates_to_thread(): Required field(s) missing", $request_id);
		if(!is_numeric($request['timestamp'])) json_exit(400, "get_updates_to_thread(): Timestamp is not numeric", $request_id);

		$boardid      = get_boardid_by_name($request_id, $request['board']);
		$skipreflinks = determine_reflinks($request);
		$msgsource    = determine_msgfield($request);
		
		$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `timestamp` > " . $request['timestamp'] . " AND `parentid` = " . $tc_db->qstr($request['thread_id']) . " AND IS_DELETED = 0 ORDER BY `id` ASC");
		if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "get_thread(): No new posts", $request_id);

		return gen_posts($skipreflinks, $msgsource, $boardid, $dbdata);
	},

	'get_thread_ids' => function($request, $request_id)
	{
		// Get every post number in a thread.
		// Request: {"board":string, "thread_id":integer}
		// Response: Array of integers representing post numbers ([0] = OP).

		global $tc_db;

		if(!isset($request['board']) || !isset($request['thread_id'])) json_exit(400, "get_thread_ids(): Required field(s) missing", $request_id);

		$boardid = get_boardid_by_name($request_id, $request['board']);
		
		$dbdata = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND (`id` = " . $tc_db->qstr($request['thread_id']) . " OR `parentid` = " . $tc_db->qstr($request['thread_id']) . ") AND IS_DELETED = 0 ORDER BY `id` ASC");
		if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "get_thread(): No such thread found", $request_id);

		$result = Array();
		foreach ($dbdata as $dbentry)
		{
			array_push($result, $dbentry['id']);
		}
		
		return $result;
	},

	'get_posts_by_id' => function($request, $request_id)
	{
		// Get specified posts from a board.
		// Request: {"board":string, "ids":Array of integers}
		// Response: Array of <POST>s.

		global $tc_db;

		if(!isset($request['board']) || !isset($request['ids'])) json_exit(400, "get_posts_by_id(): Required field(s) missing", $request_id);
		if(!is_array($request['ids'])) json_exit(400, "get_posts_by_id(): Post list is not array", $request_id);
		foreach ($request['ids'] as $id)
		{
			if(!is_numeric($id)) json_exit(400, "get_posts_by_id(): Post id(s) are not numeric", $request_id);
		}
		$postlist = '('.implode(",", $request['ids']).')';
		
		$boardid      = get_boardid_by_name($request_id, $request['board']);
		$skipreflinks = determine_reflinks($request);
		$msgsource    = determine_msgfield($request);
		
		$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `id` IN " . $postlist . " AND IS_DELETED = 0 ORDER BY `id` ASC");
		if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "get_posts_by_id(): No relevant posts found", $request_id);

		return gen_posts($skipreflinks, $msgsource, $boardid, $dbdata);
	},

	'get_part_of_board' => function($request, $request_id)
	{
		// Get part of board.
		// Request: {"board":string, "start":integer(number of thread to start from, counting from 0), "threadnum":integer(how much threads), "previewnum":integer}
		// Response: Array[threadnum(max)] of ("opflags":{"stickied":bool,"locked":bool}, "numreplies":integer(OP does not count), "numpicreplies":integer(OP does not count), "op":<POST>, "lastreplies":Array[previewnum(max)] of <POST>s).

		global $tc_db;

		if(!isset($request['board']) || !isset($request['start']) || !isset($request['threadnum']) || !isset($request['previewnum'])) json_exit(400, "get_part_of_board(): Required field(s) missing", $request_id);
		if(!is_numeric($request['start'])) json_exit(400, "get_posts_by_id(): \"start\" is not numeric", $request_id);
		if(!is_numeric($request['threadnum'])) json_exit(400, "get_posts_by_id(): \"threadnum\" is not numeric", $request_id);
		if(!is_numeric($request['previewnum'])) json_exit(400, "get_posts_by_id(): \"previewnum\" is not numeric", $request_id);

		$boardid      = get_boardid_by_name($request_id, $request['board']);
		$skipreflinks = determine_reflinks($request);
		$msgsource    = determine_msgfield($request);

		$dbdata = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $boardid . " AND `parentid` = 0 AND IS_DELETED = 0 ORDER BY `bumped` DESC LIMIT " . $request['start'] . ", " . $request['threadnum']);
		if (!is_array($dbdata) || count($dbdata) == 0) json_exit(404, "get_part_of_board(): No relevant threads found", $request_id);

		return gen_posts($skipreflinks, $msgsource, $boardid, $dbdata, true, $request['previewnum']);
	},

	'get_new_posts_count' => function($request, $request_id)
	{
		// Get count of new posts on every board from specifed timestamps.
		// Request: {"timestamps":{boardid:last timestamp,boardid:last timestamp,...}}
		// Response: {boardname:integer,boardname:integer,...}

		global $tc_db;
		
		if(!isset($request['timestamps'])) json_exit(400, "get_new_posts_count(): Required field missing", $request_id);
		if(!is_array($request['timestamps'])) json_exit(400, "get_new_posts_count(): Timestamp list is not array", $request_id);
		
		$result = array();
		foreach ($request['timestamps'] as $boardid => $timestamp)
		{
			if(!is_numeric($boardid)) json_exit(400, "get_new_posts_count(): Board ID(s) are not numeric", $request_id);
			if(!is_numeric($timestamp)) json_exit(400, "get_new_posts_count(): Timestamp(s) are not numeric", $request_id);
			
			$board = $tc_db->GetOne('SELECT `name` FROM `' . KU_DBPREFIX . 'boards` WHERE `id` = ' . $boardid);
			if(empty($board)) json_exit(400, "get_new_posts_count(): Board ID(s) are not valid", $request_id);
			$result[$board] = $tc_db->GetOne('SELECT COUNT(1) FROM `posts` WHERE `boardid` = '.$boardid.' AND`timestamp` > '.$timestamp);
		}
		
		return $result;
	},

	'get_stats' => function($request, $request_id)
	{
		// Get statistical values.
		// Request: {"type":"postslastday"|"postslasthour"|"postsboard"}
		// Response: {"result":integer}

		global $tc_db;
		
		if(!isset($request['type'])) json_exit(400, "get_posts_by_id(): Required field(s) missing", $request_id);

		switch ($request['type'])
		{
			case "postslastday":
				$result = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE timestamp > " . (time() + KU_ADDTIME - 86400) . " ORDER BY `id` ASC");
				break;
			case "postslasthour":
				$result = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE timestamp > " . (time() + KU_ADDTIME - 3600) . " ORDER BY `id` ASC");
				break;
			case "postsboard":
				$boardid      = get_boardid_by_name($request_id, $request['board']);
				$result = $tc_db->GetOne("SELECT MAX(id) FROM posts WHERE boardid = " . $boardid);
				break;
			default:
				json_exit(501, "get_stats(): Requested stats type is not implemented.", $request_id);
		}
		if(!is_numeric($result)) json_exit(500, "get_stats(): Getting stats failed.", $request_id);
		
		return Array("result" => (int)$result);
	}
);

header('Content-Type: application/json');

if (isset($_REQUEST['json']))
{
	$data = $_REQUEST['json'];
	if ($data == '') json_exit(400, "JSON parameter mode: Empty request");
	$input_parameters = json_decode($data,true);
	if ($input_parameters === NULL) json_exit(400, "JSON parameter mode: Error decoding JSON");
}
else if (isset($_REQUEST['method']))
{
	$input_parameters = $_REQUEST;
}
else
{
	$data = file_get_contents('php://input');
	if ($data == '') json_exit(400, "Raw JSON mode: Empty request");
	$input_parameters = json_decode($data,true);
}

if ($input_parameters === NULL) json_exit(400, "Raw JSON mode: Error decoding JSON");
if (!is_array($input_parameters)) json_exit(400, "Parameters are not array");
if (!isset($input_parameters['method']) || !isset($input_parameters['id']))
{
	json_exit(501, "At least JSON RPC/1.0 required", (isset($input_parameters['id'])?$input_parameters['id']:null));
}

$request_id     = $input_parameters['id'];
$request_method = $input_parameters['method'];

if (isset($input_parameters['params']))
{
	$request    = $input_parameters['params'];
}
else
{
	$request    = $input_parameters;
}

if (!is_array($request))
{
	$request = json_decode($request,true);
	if ($request === NULL) json_exit(400, "Unable to decode form-handled JSON");
}

if ($request === NULL) $request = Array();

if (!isset($api_function[$request_method])) 	json_exit(501, "Unknown method", $request_id);

$response = $api_function[$request_method]($request, $request_id);

// Return resulting $response
http_response_code(200);
header('Content-Type: application/json');
die(json_encode(Array("result" => $response, "error" => null, "id" => $request_id)));

?>