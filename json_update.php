<?php
require 'config.php';
if (!isset($_POST['board']) || !isset($_POST['thread']) || !isset($_POST['posts']))
{
	die(json_encode(array('state'=>'неправильное использование json_get - его должна вызывать яваскриптовая часть курисабы')));
}
else
{
	$board   = $_POST['board'];
	$thread  = $_POST['thread'];
	$posts   = json_decode($_POST['posts'], true);
	if ($posts === null) // || array_pop($posts) != 0 || array_shift($posts) != $thread
	{
		// $posts may be empty, but not null
		die(json_encode(array('state'=>'неправильно сконструирован массив постов')));
	}
}
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';

$results = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)." LIMIT 1");
if ($results == 0) {
	die(json_encode(array('state'=>'неверное имя борды')));
}

$board_class = new Board($board);
$board_class->board['filetypes'] = Array();
$results = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
foreach ($results as $line) {
	$board_class->board['filetypes'][] .= $line[0];
}

$results = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND `id` = " . $tc_db->qstr($thread) . " AND `IS_DELETED` = 0");
if ($results == 0)
{
	die(json_encode(array('state'=>'тред удалён')));
}

// Pregenerate replies array and make $return_replymap
$return_replymap = array();
$pregen_replies_q = $tc_db->GetAll("SELECT `to_id`, `from_boardname`, `from_id`, `from_parentid` FROM `".KU_DBPREFIX."answers` JOIN `posts` ON `to_id` = `id` AND `to_boardid` = `boardid` WHERE `to_boardid` = '" . $board_class->board['id'] . "' AND `to_parentid` = " . $tc_db->qstr($thread) . " AND `IS_DELETED` = 0");
if (count($pregen_replies_q) > 0)
{
	foreach($pregen_replies_q as $reply_entry)
	{
		$postid = $reply_entry['to_id'];
		if(!key_exists($postid,$return_replymap))
		{
			$return_replymap[$postid]=array();
		}
		// Raw map; remove duplicates and sort on the client side.
		array_push($return_replymap[$postid],array
		(
			'boardname'   =>  $reply_entry['from_boardname'],
			'id'          =>  $reply_entry['from_id'],
			'parentid'    => ($reply_entry['from_parentid'] == 0) ? $reply_entry['from_id'] : $reply_entry['from_parentid']
		));
	}
}

// Count added and deleted posts
$n = 2; // First reply in thread is #2
$return_posts = array();
$return_delposts = $posts;
$posts_to_print = array();
$return_notify = false;
$return_addposts = '';
$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $board_class->board['id'] ."' AND `parentid` = " . $tc_db->qstr($thread) . " AND `IS_DELETED` = 0 ORDER BY `id` ASC");

if (count($results) > 0)
{
	foreach($results as $post)
	{
		array_push($return_posts,$post['id']);
		if (in_array($post['id'],$return_delposts))
		{
			unset($return_delposts[array_search($post['id'],$return_delposts)]);
		}
		if (!in_array($post['id'],$posts))
		{
			$post['n'] = $n;
			$post = $board_class->BuildPost($post, false, $pregen_replies_q);
			array_push($posts_to_print,$post);
		}
		++$n;
	}
}
sort($return_delposts,SORT_NUMERIC);

// Print new posts
if (count($posts_to_print) > 0)
{
	$return_notify = true;

	$board_class->InitializeDwoo();
	$board_class->dwoo_data->assign('board', $board_class->board);
	$board_class->dwoo_data->assign('isupdate', true);
	$board_class->dwoo_data->assign('isread', true);
	$board_class->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $board_class->board['name']);
	$board_class->dwoo_data->assign('replythread', $thread);
	if ($board_class->board['type'] == 0) {
		$embeds = $tc_db->GetAll("SELECT filetype FROM `" . KU_DBPREFIX . "embeds`");
		foreach ($embeds as $embed) {
			$board_class->board['filetypes'][] .= $embed['filetype'];
		}
		$board_class->dwoo_data->assign('filetypes', $board_class->board['filetypes']);
	}	
	$board_class->dwoo_data->assign('posts', $posts_to_print);
	$return_addposts = $board_class->dwoo->get(KU_TEMPLATEDIR . '/' . $board_class->board['text_readable'] . '_thread.tpl', $board_class->dwoo_data);
}

// Return final array
die(json_encode(array(
	'state'    => 'ok',
	'posts'    => $return_posts,
	'notify'   => $return_notify,
	'delposts' => $return_delposts,
	'addposts' => $return_addposts,
	'replymap' => $return_replymap
)));

?>