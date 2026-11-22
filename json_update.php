<?php

require 'settings_manager.php';

if (isset($_POST['board']) && isset($_POST['thread']) && isset($_POST['posts'])) {
    $feed = false;
    $board = $_POST['board'];
    $thread = $_POST['thread'];
} elseif (isset($_POST['feed']) && isset($_POST['lasttimestamp']) && isset($_POST['full']) && isset($_POST['posts']) && $_POST['feed'] == '1') {
    $feed = true;
    $lasttimestamp = $_POST['lasttimestamp'];
} else {
    die(json_encode(array('state' => 'неправильное использование json_get - его должна вызывать яваскриптовая часть курисабы')));
}

$posts = json_decode($_POST['posts'], true);
if ($posts === null) { // || array_pop($posts) != 0 || array_shift($posts) != $thread
    // $posts may be empty, but not null
    die(json_encode(array('state' => 'неправильно сконструирован массив постов')));
}

require KU_ENGINEDIR . 'lib/functions.php';
require KU_ENGINEDIR . 'lib/classes/board-post.class.php';

require KU_ENGINEDIR . 'lib/classes/bans.class.php';
$bans_class = new Bans();
if ($bans_class->BanCheckSilent(KU_REMOTE_ADDR, ($feed ? '' : $board), true)) {
    die(json_encode(array('state' => ($feed ? 'забаненным хотя бы на одной доске IP-адресам недоступен однопоток' : 'этот IP-адрес забанен на выбранной доске'))));
}

function construct_board($board)
{
    global $tc_db;
    $board_class = new Board($board);
    $board_class->board['filetypes'] = array();
    $embeds = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
    foreach ($embeds as $embed) {
        $board_class->board['filetypes'][] .= $embed['filetype'];
    }
    return $board_class;
}

$boards = array();
if($feed) {
    $results = $tc_db->GetAll("SELECT `name` FROM `".KU_DBPREFIX."boards`");
    foreach($results as $board) {
        $board_class = construct_board($board['name']);
        $boards[$board_class->board['id']] = $board_class;
    }
} else {
    $results = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)." LIMIT 1");
    if ($results == 0) {
        die(json_encode(array('state' => 'неверное имя борды')));
    }

    $board_class = construct_board($board);
    $boards[$board_class->board['id']] = $board_class;

    $results = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND `id` = " . $tc_db->qstr($thread) . " AND `IS_DELETED` = 0");
    if ($results == 0) {
        die(json_encode(array('state' => 'тред удалён')));
    }
}

// Pregenerate replies array and make $return_replymap
$etalon_md5 = md5(KU_REMOTE_ADDR);
$return_replymap = array();
$limthread = ($feed ? " AND b.timestamp >= " . $tc_db->qstr($lasttimestamp) : " AND `to_boardid` = '" . $board_class->board['id'] . "' AND `to_parentid` = " . $tc_db->qstr($thread));
$pregen_replies_q = $tc_db->GetAll("SELECT `to_boardname`, `to_id`, `from_boardname`, `from_id`, `from_parentid`, c.ipmd5 AS ipmd5 FROM `".KU_DBPREFIX."answers` INNER JOIN `".KU_DBPREFIX."posts` AS `b` ON `to_id` = b.id AND `to_boardid` = b.boardid INNER JOIN `".KU_DBPREFIX."posts` AS `c` ON `from_id` = c.id AND `from_boardid` = c.boardid WHERE b.IS_DELETED = 0 AND c.IS_DELETED = 0" . $limthread);
if (count($pregen_replies_q) > 0) {
    foreach ($pregen_replies_q as $reply_entry) {
        $postid = ($feed ? "/" . $reply_entry['to_boardname'] . "/" : "") . $reply_entry['to_id'];
        if (!key_exists($postid, $return_replymap)) {
            $return_replymap[$postid] = array();
        }
        // Raw map; remove duplicates and sort on the client side.
        $a = array(
            'boardname'   =>  $reply_entry['from_boardname'],
            'id'          =>  $reply_entry['from_id'],
            'parentid'    => ($reply_entry['from_parentid'] == 0) ? $reply_entry['from_id'] : $reply_entry['from_parentid']
        );
        if ($etalon_md5 == $reply_entry['ipmd5']) {
            $a['you'] = 1;
        }
        array_push($return_replymap[$postid], $a);
    }
}

// Count added and deleted posts
$n = ($feed ? 1 : 2); // First reply in thread is #2, and first post in feed is 1
$return_posts = array();
$return_delposts = $posts;
$posts_to_print = array();
$return_notify = false;
$return_addposts = '';
$limthread = ($feed ? " AND `timestamp` >= " . $tc_db->qstr($lasttimestamp) . " ORDER BY `timestamp` DESC" : " AND `boardid` = '" . $board_class->board['id'] ."' AND `parentid` = " . $tc_db->qstr($thread) . " ORDER BY `id` ASC");
$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `IS_DELETED` = 0" . $limthread);

if (count($results) > 0) {
    foreach ($results as $post) {
        $postid = ($feed ? "/" . $boards[$post['boardid']]->board['name'] . "/" : "") . $post['id'];
        array_push($return_posts, $postid);
        if (in_array($postid, $return_delposts)) {
            unset($return_delposts[array_search($postid, $return_delposts)]);
        }
        if (!in_array($postid, $posts)) {
            $post['n'] = $n;
            $post_board_class = $boards[$post['boardid']];
            $post['filetypes'] = $post_board_class->board['filetypes'];
            $post['board'] = $post_board_class->board;
            $post['file_path'] = KU_BOARDSPATH . '/' . $post_board_class->board['name'];
            $country_restrict = $boards[$post['boardid']]->board['country_restrict'];
            if ($country_restrict != '') {
                if ((getUserMode() != 1) && in_array(client_country(), explode(',', strtoupper(str_replace(' ', '', $country_restrict))))) {
                    continue;
                }
            }
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
            $post = $boards[$post['boardid']]->BuildPost($post, false, $pregen_replies_q);
            array_push($posts_to_print, $post);
        }
        ++$n;
    }
}
$return_delposts = array_values($return_delposts);
natsort($return_delposts);

// Print new posts
if (count($posts_to_print) > 0) {
    $return_notify = true;

    $board_class->InitializeDwoo();
    $board_class->dwoo_data->assign('isupdate', true);
    $board_class->dwoo_data->assign('isread', true);
    if(!$feed) {
        $board_class->dwoo_data->assign('board', $board_class->board);
        $board_class->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $board_class->board['name']);
        $board_class->dwoo_data->assign('replythread', $thread);
        $board_class->dwoo_data->assign('filetypes', $board_class->board['filetypes']);
    }
    $board_class->dwoo_data->assign('posts', $posts_to_print);
    if ($feed) $board_class->dwoo_data->assign('forceexternalboard', true);
    $return_addposts = $board_class->dwoo->get(KU_TEMPLATEDIR . '/img_thread.tpl', $board_class->dwoo_data);
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
