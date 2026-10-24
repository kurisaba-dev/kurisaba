<?php

header('Content-type: text/html; charset=utf-8');

require 'settings_manager.php';
session_start(['cookie_samesite' => 'Strict']);

if (isset($_GET['v'])) {
    $board  = $_GET['b'];
    $textstr = '%' . $_GET['v'] . '%';
} elseif (!isset($_GET['b']) || !isset($_GET['t']) || !isset($_GET['p'])) {
    if (!isset($_SERVER['PATH_INFO'])) {
        die();
    }

    $pairs = explode('/', $_SERVER['PATH_INFO']);
    if (count($pairs) < 4) {
        die();
    }

    $board  = $pairs[1];
    $thread = $pairs[2];
    $posts  = $pairs[3];
} else {
    $board  = $_GET['b'];
    $thread = $_GET['t'];
    $posts  = $_GET['p'];
}

$singlepost = (isset($_GET['single'])) ? true : false;
$issearch = (isset($textstr)) ? true : false;

require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/bans.class.php';
$bans_class = new Bans();
if ($bans_class->BanCheckSilent(KU_REMOTE_ADDR, $board, true)) {
    do_redirect(KU_BOARDSPATH . '/banned.php');
}

require KU_ROOTDIR . 'inc/classes/board-post.class.php';

$executiontime_start = microtime_float();

$results = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)." LIMIT 1");
if ($results == 0) {
    die('Invalid board.');
}
$board_class = new Board($board);
$country_restrict = $board_class->board['country_restrict'];
if ($country_restrict != '') {
    if ((getUserMode() != 1) && in_array(client_country(), explode(',', strtoupper(str_replace(' ', '', $country_restrict))))) {
        http_response_code(451);
        die('This material is unavailable in your country.');
    }
}

if (!$issearch) {
    $postids = getQuoteIds($posts, $replies);
    if (count($postids) == 0) {
        die('No valid posts specified.');
    }
}

if (!$issearch) {
    $postidquery = '';
    if (count($postids) > 1 && (!empty($postids[1]) || !empty($postids['BETWEEN']))) {
        $i = 0;
        foreach ($postids as $key => $postid) {
            if (is_numeric($key)) {
                if (intval($postid) < 0) {
                    if ($singlepost) {
                        die("invalid post id");
                    } else {
                        exitWithErrorPage("invalid post id");
                    }
                }
                if ($i != 0) {
                    $postidquery .= " OR ";
                }
                $postidquery .= "(`id` = ".intval($postid)." AND ";
                if ($postids[$key] == $thread) {
                    $postidquery .= "(`id` = ".$tc_db->qstr($thread)." AND `parentid` = 0))";
                } else {
                    $postidquery .= "`parentid` = " . $tc_db->qstr($thread) . " ) ";
                }
            } elseif ($key == 'BETWEEN') {
                if (count($postids['BETWEEN'] > 0)) {
                    foreach ($postids['BETWEEN'] as $key2 => $pid) {
                        if ($key2 != 0 || $i != 0) {
                            $postidquery .= " OR ";
                        }
                        if ($pid[0] == $thread) {
                            $postidquery .= "(`id` = ".$tc_db->qstr($thread)." AND `parentid` = 0) OR (";
                        } else {
                            $postidquery .= "(`parentid` = " . $tc_db->qstr($thread) . " AND ";
                        }
                        $end = intval(array_pop($pid));
                        if ($pid[0] < $end) {
                            $postidquery .= "`id` BETWEEN ".(intval($pid[0]))." AND ".$end."";
                        } else {
                            $postidquery .= "`id` BETWEEN ".$end." AND ".(intval($pid[0]))."";
                        }
                        if ($pid[0] == $thread) {
                            $postidquery .= " AND `parentid` = " . $tc_db->qstr($thread) . ")";
                        } else {
                            $postidquery .= ")";
                        }
                    }
                }
            }
            $i++;
        }
    } else {
        $postidquery .= "`id` = ".intval($postids[0]);
    }
} else {
    $postidquery = "`message` LIKE " . $tc_db->qstr($textstr);
}

$board_class->InitializeDwoo();
$board_class->dwoo_data->assign('board', $board_class->board);
if ($issearch) {
    $board_class->dwoo_data->assign('issearch', true);
}
$board_class->dwoo_data->assign('isread', true);
$board_class->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $board_class->board['name']);

$page = '';

if ($issearch) {
    $page .= $board_class->PageHeader(-1, 0, -1, "Поиск постов");
    $board_class->dwoo_data->assign('replythread', -1);
    $page .= $board_class->dwoo->get(KU_TEMPLATEDIR . '/img_reply_header.tpl', $board_class->dwoo_data);
}

if ($issearch) {
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND (" . $postidquery . ") AND `IS_DELETED` = 0 ORDER BY `id` DESC");
    if (count($results) == 0) {
        exitWithErrorPage('Ничего такого на этой борде не обнаружено.');
    }

    if (count($results) > KU_MAXSEARCHRESULTS) {
        $howmany = count($results) . ' постов найдено (показаны первые ' . KU_MAXSEARCHRESULTS . ')';
        $results = array_slice($results, 0, KU_MAXSEARCHRESULTS, true);
    } else {
        $howmany = count($results) . ' постов найдено';
    }

    $page .= '<h3 style="text-align: center;">' . $howmany . ':</h2>' . "\n";
} else {
    $results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND (" . $postidquery . ") AND `IS_DELETED` = 0 ORDER BY `id` ASC");
    if (count($results) == 0) {
        if ($thread == 0) {
            if (isset($_GET['issearch'])) {
                exitWithErrorPage('Ничего такого на этой борде не обнаружено.');
            } else {
                die('&nbsp;Пост не существует или удалён.&nbsp;');
            }
        }
    }
}

$embeds = $tc_db->GetAll("SELECT filetype FROM `" . KU_DBPREFIX . "embeds`");
foreach ($embeds as $embed) {
    $board_class->board['filetypes'][] .= $embed['filetype'];
}
$board_class->dwoo_data->assign('filetypes', $board_class->board['filetypes']);
$i = 0;
foreach ($results as $key => $post) {
    if ($issearch || !$singlepost) {
        $post['n'] = ++$i;
    } else {
        if ($post['parentid'] == 0) {
            $post['n'] = 1;
        } else {
            $post['n'] = 2 + $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND `IS_DELETED` = 0 AND `id` < " . $tc_db->qstr($post['id']) . " AND `parentid` = " . $tc_db->qstr($post['parentid']));
        }
    }
    if (isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] == KU_WEBPATH . '/single.php') {
        $extname = '/'.$post_board_class->board['name'].'/'.$thread;
        if ($post['parentid'] == 0 && $post['subject'] != "") {
            $extname = $post['subject'];
        } else {
            $parent_subject = $tc_db->GetOne("SELECT `subject` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND `IS_DELETED` = 0 AND `id` = " . $tc_db->qstr($post['parentid']));
            if ($parent_subject != "") {
                $extname = $parent_subject;
            }
        }
        $post['externalreference'] = '[<a href="' . KU_WEBPATH . '/' . $board . '/res/' . $thread . '.html#' . $post['id'] . '">'. $extname .'</a>]';
        $results[$key] = $board_class->BuildPost($post, false, false, false, true);
    } else {
        $results[$key] = $board_class->BuildPost($post, false);
    }
}

$board_class->dwoo_data->assign('posts', $results);

if (!$issearch && !$singlepost) {
    $page .= $board_class->PageHeader(($thread == 0) ? -1 : $thread, 0, -1, "Поиск постов");
    $board_class->dwoo_data->assign('replythread', $thread);
    if ($thread == 0) {
        $newresults = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] ." AND `id` = " . $tc_db->qstr($results[0]['id']));
        if (count($newresults) != 0) {
            $board_class->dwoo_data->assign('backtothread', $newresults[0]['parentid']);
        } else {
            $board_class->dwoo_data->assign('backtothread', 0);
        }
    }
    $page .= $board_class->dwoo->get(KU_TEMPLATEDIR . '/img_reply_header.tpl', $board_class->dwoo_data);
    $page .= '<br />' . "\n";
} else {
    $tpl['title'] = '';
    $tpl['head'] = '';
}

$board_class->dwoo_data->assign('replink', $_GET['replink']);
$page .= $board_class->dwoo->get(KU_TEMPLATEDIR . '/img_thread.tpl', $board_class->dwoo_data);

if ($issearch || !$singlepost) {
    $page .= '<br clear="left">' . "\n";
}

if ($issearch || !$singlepost) {
    $board_class->dwoo_data->assign('replythread', -1);
    $page .= '<hr />' . "\n" . $board_class->Footer(microtime_float() - $executiontime_start);
}

$postnum_rounded_to_1k = floor($tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = 9") / 1000) * 1000;
$page = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $page);

$board_class->PrintPage('', $page, true);
