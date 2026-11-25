<?php

header('Content-type: text/html; charset=utf-8');

require 'settings_manager.php';

require KU_ENGINEDIR . 'lib/functions.php';
require KU_ENGINEDIR . 'lib/classes/board-post.class.php';

// Users that banned at at least one board, can't read the feed.
require KU_ENGINEDIR . 'lib/classes/bans.class.php';
$bans_class = new Bans();
if ($bans_class->BanCheckSilent(KU_REMOTE_ADDR, '', true)) {
    do_redirect(KU_WEBPATH . '/banned.php');
}

$executiontime_start = microtime_float();

// Do our work only for image-type boards
$embeds = $tc_db->GetAll("SELECT `name`, `filetype` FROM `" . KU_DBPREFIX . "embeds`");
$boards_data = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."boards`");
$boards = array();

foreach ($boards_data as $board_data) {
    $boards[$board_data['id']] = new Board($board_data['name']);
    foreach ($embeds as $embed) {
        $boards[$board_data['id']]->board['filetypes'][] .= $embed['filetype'];
    }
}
$board_class = end($boards);

$all = isset($_REQUEST["all"]);
$fullfeed = isset($_GET['fullfeed']) && $_GET['fullfeed'] == 1;

$board_class->InitializeDwoo();
$board_class->dwoo_data->assign('isfeed', true);
$board_class->dwoo_data->assign('isall', $all);
$board_class->dwoo_data->assign('issearch', true);
$board_class->dwoo_data->assign('isread', true);
$board_class->dwoo_data->assign('skipheader', true);
$board_class->dwoo_data->assign('embeds', $embeds);

$thispage = (isset($_REQUEST["p"]) && is_numeric($_REQUEST["p"])) ? intval($_REQUEST["p"]) : 0;

if (isset($_GET['v'])) {
    $numposts = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `message` LIKE " . $tc_db->qstr('%' . $_GET['v'] . '%') . " AND `IS_DELETED` = 0");
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `message` LIKE " . $tc_db->qstr('%' . $_GET['v'] . '%') . " AND `IS_DELETED` = 0 ORDER BY `id` DESC LIMIT " . KU_FEEDLENGTH . " OFFSET " . ($thispage * KU_FEEDLENGTH));
    $title = "Поиск постов";
    $notfound = "Не найдено постов, содержащих строку \"" . $_GET['v'] . "\".";
    $print_summary = true;
} elseif (isset($_GET['n'])) {
    $numposts = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `name` = " . $tc_db->qstr($_GET['n']) . " AND `IS_DELETED` = 0");
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `name` = " . $tc_db->qstr($_GET['n']) . " AND `IS_DELETED` = 0 ORDER BY `id` DESC LIMIT " . KU_FEEDLENGTH . " OFFSET " . ($thispage * KU_FEEDLENGTH));
    $title = "Поиск постов автора";
    $notfound = "Пользователь " . $_GET['n'] . " ничего не писал на борде, или всё его творчество удалено.";
    $print_summary = true;
} elseif (isset($_GET['r'])) {
    $numposts = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` AS F LEFT JOIN `" . KU_DBPREFIX . "answers` AS A ON F.boardid = A.from_boardid AND F.id = A.from_id LEFT JOIN `" . KU_DBPREFIX . "posts` AS T ON T.boardid = A.to_boardid AND T.id = A.to_id WHERE T.name = " . $tc_db->qstr($_GET['r']) . " AND F.IS_DELETED = 0 AND T.IS_DELETED = 0");
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` AS F LEFT JOIN `" . KU_DBPREFIX . "answers` AS A ON F.boardid = A.from_boardid AND F.id = A.from_id LEFT JOIN `" . KU_DBPREFIX . "posts` AS T ON T.boardid = A.to_boardid AND T.id = A.to_id WHERE T.name = " . $tc_db->qstr($_GET['r']) . " AND F.IS_DELETED = 0 AND T.IS_DELETED = 0 ORDER BY F.id DESC LIMIT " . KU_FEEDLENGTH . " OFFSET " . ($thispage * KU_FEEDLENGTH));
    $query = "SELECT * FROM `" . KU_DBPREFIX . "posts` AS F LEFT JOIN `" . KU_DBPREFIX . "answers` AS A ON F.boardid = A.from_boardid AND F.id = A.from_id LEFT JOIN `" . KU_DBPREFIX . "posts` AS T ON T.boardid = A.to_boardid AND T.id = A.to_id WHERE T.name = " . $tc_db->qstr($_GET['r']) . " AND F.IS_DELETED = 0 AND T.IS_DELETED = 0 ORDER BY F.id DESC";
    $title = "Поиск ответов на посты автора";
    $notfound = "Нет ответов на посты, которые оставил пользователь " . $_GET['r'] . ".";
    $print_summary = true;
} elseif ($all) {
    $title = "Все треды";
    $notfound = "Постов в базе нет!";
    $print_summary = false;
    $exclude = '';
    $excludeboards = '';
    foreach ($boards as $board) {
        if ($board->board['hiddenthreads'] !== '') {
            $exclude .= " AND NOT (`boardid` = " . $board->board['id'] . " AND `id` IN (" . $board->board['hiddenthreads'] . "))";
        }
        if (!$fullfeed && !$board->board['enablefeed']) {
            $excludeboards .= " AND NOT `boardid` = " . $board->board['id'];
        }
    }
    $numposts = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `parentid` = 0 AND `IS_DELETED` = 0 " . $exclude . $excludeboards);
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `parentid` = 0 AND `IS_DELETED` = 0 " . $exclude . $excludeboards . " ORDER BY `bumped` DESC LIMIT ". KU_THREADS ." OFFSET ". ($thispage * KU_THREADS));
} else {
    $title = "Однопоток";
    $notfound = "Постов в базе нет!";
    $print_summary = false;
    if (isset($_GET['boardlist'])) {
        $excludeboards = "AND C.name IN (" . implode(", ", array_map(function($x) {global $tc_db; return $tc_db->qstr($x);}, explode("|", $_GET['boardlist']))) . ")";
    } elseif ($fullfeed) {
        $excludeboards = "";
    } else {
        $excludeboards = "AND C.enablefeed = 1";
    }
    $numposts = $tc_db->GetOne("SELECT COUNT(*) FROM (SELECT `boardid`, `parentid` FROM `" . KU_DBPREFIX . "posts` WHERE `IS_DELETED` = 0) as A JOIN `" . KU_DBPREFIX . "boards` AS C ON C.id = A.boardid " . $excludeboards . " LEFT JOIN `" . KU_DBPREFIX . "posts` AS B ON B.id = A.parentid AND A.parentid != 0 AND B.boardid = A.boardid");
    $results = $tc_db->GetAll("SELECT A.*, B.subject AS parent_subject FROM (SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `IS_DELETED` = 0 ORDER BY `timestamp` DESC) as A JOIN `" . KU_DBPREFIX . "boards` AS C ON C.id = A.boardid " . $excludeboards . " LEFT JOIN `" . KU_DBPREFIX . "posts` AS B ON B.id = A.parentid AND A.parentid != 0 AND B.boardid = A.boardid ORDER BY A.`timestamp` DESC LIMIT " . KU_FEEDLENGTH . " OFFSET " . ($thispage * KU_FEEDLENGTH));
}
$lastpage = calculatelastpagenum($numposts, $all ? KU_THREADS : KU_FEEDLENGTH);
$board_class->dwoo_data->assign('thispage', $thispage);
$board_class->dwoo_data->assign('lastpage', $lastpage);
$board_class->dwoo_data->assign('pagination', createPagination($thispage, $lastpage, KU_WEBPATH . "/single.php?fullfeed=" . ($fullfeed ? "1" : "0") . (isset($_GET['v']) ? "&v=" . $_GET['v'] : "") . (isset($_GET['n']) ? "&n=" . $_GET['n'] : "") . (isset($_GET['r']) ? "&r=" . $_GET['r'] : "") . (isset($_GET['boardlist']) ? "&boardlist=" . $_GET['boardlist'] : "") . ($all ? "&all=1" : ""), "&p=", ""));

$page = '';
$page .= $board_class->PageHeader(-1, 0, -1, $title);
if (!$all) {
    if($print_summary) $page .= '<h3 style="text-align: center;">' . $numposts . ' постов найдено (страница ' . $thispage . ' из ' . ($lastpage + 1) . '):</h3>' . "\n";
    $page .= "<hr>";
}

$board_class->dwoo_data->assign('onlyclone', true);
$page .= $board_class->Postbox();


if (count($results) == 0) {
    exitWithErrorPage($notfound);
}

$i = 0;
$newposts = array();
foreach ($results as $key => $post) {
    $country_restrict = $boards[$post['boardid']]->board['country_restrict'];
    if ($country_restrict != '') {
        if ((getUserMode() != 1) && in_array(client_country(), explode(',', strtoupper(str_replace(' ', '', $country_restrict))))) {
            continue;
        }
    }
    $post['n'] = $all ? 1 : ++$i;
    $post_board_class = $boards[$post['boardid']];
    $post['filetypes'] = $post_board_class->board['filetypes'];
    $post['board'] = $post_board_class->board;
    $post['file_path'] = KU_BOARDSPATH . '/' . $post_board_class->board['name'];
    if(!$all) {
        $thread = ($post['parentid'] == 0) ? $post['id'] : $post['parentid'];
        $extname = '/'.$post_board_class->board['name'].'/'.$thread;
        if ($post['parentid'] == 0 && $post['subject'] != "") {
            $extname = $post['subject'];
        } elseif ($post['parent_subject'] !== null) {
            if ($post['parent_subject'] != "") {
                $extname = $post['parent_subject'];
            }
        }
        $post['externalreference'] = '[<a href="' . $post['file_path'] . '/res/' . $thread . '.html#' . $post['id'] . '">'. $extname .'</a>]';
    }

    $results[$key] = $post_board_class->BuildPost($post, false, false, false, true);

    if($all) {
        $subposts_all = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $post_board_class->board['id'] . " AND `parentid` = ". $post['id'] ." AND `IS_DELETED` = 0 ORDER BY `id` ASC");
        $n_stickied = ($results[$key]['stickied'] == 1) ? KU_REPLIESSTICKY : KU_REPLIES;
        $subposts = array();
        $nlast = count($subposts_all) + 1;
        for ($j = 0; $j < $n_stickied; $j++) {
            $subpost = array_pop($subposts_all);
            if ($subpost == null) {
                break;
            }
            $subpost['n'] = $nlast - $j;
            $subpost['filetypes'] = $post_board_class->board['filetypes'];
            $subpost['board'] = $post_board_class->board;
            $subpost['file_path'] = KU_BOARDSPATH . '/' . $post_board_class->board['name'];
            array_unshift($subposts, $post_board_class->BuildPost($subpost, true, false, false, true));
        }
        array_unshift($subposts, $results[$key]);
        $newposts[] = $subposts;
        $replycount = count($subposts_all);
        $imgcount = 0;
        foreach ($subposts_all as $subpost_test) { // Ending posts are popped out already.
            if ($subpost_test['file_md5'] != '') {
                $imgcount++;
            }
        }
        $newposts[$key][0]['replies'] = $replycount;
        $newposts[$key][0]['images'] = $imgcount;
    }
}

$board_class->dwoo_data->assign('posts', $all ? $newposts : $results);
$board_class->dwoo_data->assign('forceexternalboard', true);
if (isset($_GET['v']) || isset($_GET['n']) || isset($_GET['r'])) {
    $board_class->dwoo_data->assign('modifier', "search");
}

$page .= $board_class->dwoo->get(KU_TEMPLATEDIR . ($all ? '/img_board_page.tpl' : '/img_thread.tpl'), $board_class->dwoo_data);

$page .= '<br clear="left">' . "\n";
$page .= '<hr />' . "\n" . $board_class->Footer(microtime_float() - $executiontime_start);

$postnum_rounded_to_1k = floor($tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = 9") / 1000) * 1000;
$page = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $page);
$board_class->PrintPage('', $page, true);
