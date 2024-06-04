<?php
header('Content-type: text/html; charset=utf-8');

require 'config.php';

require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/board-post.class.php';

$executiontime_start = microtime_float();

// Do our work only for image-type boards
$embeds = $tc_db->GetAll("SELECT filetype FROM `" . KU_DBPREFIX . "embeds`");
$boards_data = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."boards` WHERE 'type' = 0");
$boards = array();

foreach ($boards_data as $board_data)
{
	$boards[$board_data['id']] = new Board($board_data['name']);
	foreach ($embeds as $embed) {
		$boards[$board_data['id']]->board['filetypes'][] .= $embed['filetype'];
	}
}
$board_class = end($boards);

$noboardlist = false;
$hide_extra = false;
$replies = false;
$board_class->InitializeDwoo();
$board_class->dwoo_data->assign('isfeed', true);
$board_class->dwoo_data->assign('issearch', true);
$board_class->dwoo_data->assign('isread', true);
$board_class->dwoo_data->assign('skipheader', true);
$embeds = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "embeds`");
$board_class->dwoo_data->assign('embeds', $embeds);
$page ='';
$page .= $board_class->PageHeader(-1, 0, -1, "Однопоток");
$page .= "<hr>";

$board_class->dwoo_data->assign('onlyclone', true);
$page .= $board_class->Postbox();

$results = $tc_db->GetAll("SELECT A.*, B.subject AS parent_subject FROM (SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `IS_DELETED` = 0 ORDER BY `timestamp` DESC LIMIT " . KU_FEEDLENGTH . ") as A LEFT JOIN `" . KU_DBPREFIX . "posts` AS B ON B.id = A.parentid AND A.parentid != 0 AND B.boardid = A.boardid ORDER BY A.`timestamp` DESC");

if (count($results) == 0) { exitWithErrorPage('Постов в базе нет!'); }

$i = 0;
foreach ($results as $key=>$post)
{
	$post['n'] = ++$i;
	$post_board_class = $boards[$post['boardid']];
	$post['filetypes'] = $post_board_class->board['filetypes'];
	$post['board'] = $post_board_class->board;
	$post['file_path'] = KU_BOARDSPATH . '/' . $post_board_class->board['name'];
	$thread = ($post['parentid'] == 0) ? $post['id'] : $post['parentid'];
	$extname = '/'.$post_board_class->board['name'].'/'.$thread;
	if($post['parentid'] == 0 && $post['subject'] != "") $extname = $post['subject'];
	elseif ($post['parent_subject'] !== null)
		if ($post['parent_subject'] != "")
			$extname = $post['parent_subject'];
	$post['externalreference'] = '[<a href="' . $post['file_path'] . '/res/' . $thread . '.html#' . $post['id'] . '">'. $extname .'</a>]';

	$results[$key] = $post_board_class->BuildPost($post, false, false, false, true);
}

$board_class->dwoo_data->assign('posts', $results);
$board_class->dwoo_data->assign('forceexternalboard', true);

$page .= $board_class->dwoo->get(KU_TEMPLATEDIR . '/img_thread.tpl', $board_class->dwoo_data);

$page .= '<br clear="left">' . "\n";
$page .= '<hr />' . "\n" . $board_class->Footer($noboardlist, (microtime_float() - $executiontime_start), $hide_extra);

$postnum_rounded_to_1k = floor($tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = 9") / 1000) * 1000;
$page = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $page);
$board_class->PrintPage('', $page, true);
?>
