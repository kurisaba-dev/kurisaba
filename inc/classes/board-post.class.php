<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */
/**
 * Board and Post classes
 *
 * @package kusaba
 */
/**
 * Board class
 *
 * Contains all board configurations.  This class handles all board page
 * rendering, using the templates
 *
 * @package kusaba
 *
 * TODO: replace repetitive code blocks with functions.
 */

class Board {
	/* Declare the public variables */
	var $board = array();
	var $dwoo;
	var $dwoo_data;
	var $boardids;
	
	/**
	 * Initialization function for the Board class, which is called when a new
	 * instance of this class is created. Takes a board directory as an
	 * argument
	 *
	 * @param string $board Board name/directory
	 * @param boolean $extra grab additional data for page generation purposes. Only false if all that's needed is the board info.
	 * @return class
	 */
	function __construct($board, $extra = true) {
		global $tc_db, $CURRENTLOCALE;

		// If the instance was created with the board argument present, get all of the board info and configuration values and save it inside of the class
		if ($board!='') {
			$query = "SELECT * FROM `".KU_DBPREFIX."boards` WHERE `name` = ".$tc_db->qstr($board)." LIMIT 1";
			$results = $tc_db->GetAll($query);
			foreach ($results[0] as $key=>$line) {
				if (!is_numeric($key)) {
					$this->board[$key] = $line;
				}
			}
			
			// Also create board id dictionary
			$this->boardids = array();
			$boards_list = $tc_db->GetAll("SELECT `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
			foreach ($boards_list as $line)
			{
				$this->boardids[$line['name']] = $line['id'];
			}
			
			// Type
			$types = array('img', 'txt', 'oek', 'upl');
			$this->board['text_readable'] = $types[$this->board['type']];
			if ($extra) {
				// Boardlist
				$this->board['boardlist'] = $this->DisplayBoardList();

				// Get the unique posts for this board
				$this->board['uniqueposts']   = $tc_db->GetOne("SELECT COUNT(DISTINCT `ipmd5`) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id']. " AND  `IS_DELETED` = 0");
			
				if($this->board['type'] != 1) {
					$this->board['filetypes_allowed'] = $tc_db->GetAll("SELECT ".KU_DBPREFIX."filetypes.filetype FROM ".KU_DBPREFIX."boards, ".KU_DBPREFIX."filetypes, ".KU_DBPREFIX."board_filetypes WHERE ".KU_DBPREFIX."boards.id = " . $this->board['id'] . " AND ".KU_DBPREFIX."board_filetypes.boardid = " . $this->board['id'] . " AND ".KU_DBPREFIX."board_filetypes.typeid = ".KU_DBPREFIX."filetypes.id ORDER BY ".KU_DBPREFIX."filetypes.filetype ASC;");
				}
				
				if ($this->board['locale'] && $this->board['locale'] != KU_LOCALE) {
					changeLocale($this->board['locale']);
				}
			}
		}
	}

	function __destruct() {
		changeLocale(KU_LOCALE);
	}

	/**
	 * Regenerate and print page
	 */
	function RegenerateAndPrintPage($i)
	{
		global $tc_db, $CURRENTLOCALE;

		$this->InitializeDwoo();
		$this->board['filetypes'] = Array();
		$results = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
		foreach ($results as $line) {
			$this->board['filetypes'][] .= $line[0];
		}
		$this->dwoo_data->assign('filetypes', $this->board['filetypes']);
		$maxpages = $this->board['maxpages'];
		
		$exclude = '';
		if ($this->board['hiddenthreads'] !== '')
		{
			$exclude = ' AND `id` NOT IN ('.$this->board['hiddenthreads'].')';
		}

		
		$numposts = $tc_db->GetAll("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . $exclude . " AND `parentid` = 0 AND `IS_DELETED` = 0");

		if ($this->board['type'] == 1) {
			$postsperpage = KU_THREADSTXT;
		} elseif ($this->board['type'] == 3) {
			$postsperpage = 30;
		} else {
			$postsperpage = KU_THREADS;
		}

		$liststooutput = 0;
		$totalpages = calculatenumpages($this->board['type'], ($numposts[0][0]-1));
		if ($totalpages == '-1') {
			$totalpages = 0;
		}
		$this->dwoo_data->assign('numpages', $totalpages);

		$executiontime_start_page = microtime_float();
		$newposts = Array();
		$this->dwoo_data->assign('thispage', $i);
		$threads = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . $exclude . " AND `parentid` = 0 AND `IS_DELETED` = 0 ORDER BY `stickied` DESC, `bumped` DESC LIMIT ". ($postsperpage)." OFFSET ". $postsperpage * $i);

		$pregen_replies_q = false;
		if (count($threads) > 0)
		{
			$threads_string = "";
			$glue = "'";
			foreach ($threads as $k=>$thread)
			{
				 $threads_string .= $glue . $thread['id'];
				 $glue = "', '";
			}
			$threads_string .= "'";
			$pregen_replies_q = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."answers` WHERE `to_boardid` = '" . $this->board['id'] . "' AND `to_parentid` IN (" . $threads_string . ")");
		}

		foreach ($threads as $k=>$thread) {
			// If the thread is on the page set to mark, && hasn't been marked yet, mark it
			if ($thread['deleted_timestamp'] == 0 && $this->board['markpage'] > 0 && $i >= $this->board['markpage']) {
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `deleted_timestamp` = '" . (time() + KU_ADDTIME + 7200) . "' WHERE `boardid` = " . $tc_db->qstr($this->board['id'])." AND `id` = '" . $thread['id'] . "'");
				clearPostCache($thread['id'], $this->board['name']);
				$this->dwoo_data->assign('replythread', 0);
			}
			$thread['n'] = 1;
			$thread = $this->BuildPost($thread, true, $pregen_replies_q);

			$posts_all = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id']." AND `parentid` = ".$thread['id']." " . (($this->board['type'] != 1) ? ("AND `IS_DELETED` = 0") : ("")) . " ORDER BY `id` ASC ");
			$n_stickied = ($thread['stickied'] == 1) ? (KU_REPLIESSTICKY) : (KU_REPLIES);
			if ($this->board['type'] != 3) {
				$posts = array();
				$nlast = count($posts_all) + 1;
				for ($j = 0; $j < $n_stickied; $j++)
				{
					$post = array_pop($posts_all);
					if ($post == NULL) break;
					$post['n'] = $nlast - $j;
					array_unshift($posts, $this->BuildPost($post, true, $pregen_replies_q));
				}
				//array_reverse($posts);
				array_unshift($posts, $thread);
				$newposts[] = $posts;
			} else {
				if (!$thread['tag']) $thread['tag'] = '*';
				$newposts[] = $thread;
			}
			$replycount = count($posts_all);
			$imgcount = 0;
			foreach($posts_all as $post_test) // Ending posts are popped out already.
			{
				if ($post_test['file_md5'] != '') $imgcount++;
			}

			// Workaround for upload boards
			if ($this->board['type'] == 3) {
				$newposts[$k]['replies'] = $replycount;
			} else {
				$newposts[$k][0]['replies'] = $replycount;
				$newposts[$k][0]['images'] = $imgcount;
			}
		}

		if ($this->board['type'] == 0 && !isset($embeds)) {
			$embeds = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "embeds`");
			$this->dwoo_data->assign('embeds', $embeds);
		}
		if (!isset($header)){
			$header = $this->PageHeader();
			$header = str_replace("<!sm_threadid>", 0, $header);
		}
		if (!isset($postbox)) {
			$postbox = $this->Postbox();
			$postbox = str_replace("<!sm_threadid>", 0, $postbox);
		}

		$this->dwoo_data->assign('posts', $newposts);
		$this->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $this->board['name']);

		$content = $this->dwoo->get(KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_board_page.tpl', $this->dwoo_data);
		$footer = $this->Footer(false, (microtime_float() - $executiontime_start_page), (($this->board['type'] == 1) ? (true) : (false)));
		$content = $header.$postbox.$content.$footer;

		$content = str_replace("\t", '',$content);
		$content = str_replace("&nbsp;\r\n", '&nbsp;',$content);

		print $content;
	}
	
	function RegenerateAndPrintCatalog()
	{
		global $tc_db, $CURRENTLOCALE;
		//if (!isset($this->dwoo)) { $this->dwoo = New Dwoo; $this->dwoo_data = new Dwoo_Data(); $this->InitializeDwoo(); }		
		$this->InitializeDwoo();
		$executiontime_start_catalog = microtime_float();
		$catalog_head = $this->PageHeader().
		'&#91;<a href="' . KU_BOARDSFOLDER . $this->board['name'] . '/">'._gettext('Return').'</a>&#93; <div class="catalogmode">'._gettext('Catalog Mode').'</div>' . "\n" .
		'<table border="1" align="center">' . "\n" . '<tr>' . "\n";
		$catalog_page = '';

		$exclude = '';
		if ($this->board['hiddenthreads'] !== '')
		{
			$exclude = ' AND `id` NOT IN ('.$this->board['hiddenthreads'].')';
		}

		$results = $tc_db->GetAll("SELECT `id` , `subject` , `file` , `file_type` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . $exclude . " AND `IS_DELETED` = 0 AND `parentid` = 0 ORDER BY `stickied` DESC, `bumped` DESC");
		$numresults = count($results);
		if ($numresults > 0)
		{
			$columns = 4;
			$celnum = 0;
			$trbreak = 0;
			$row = 1;
			// Calculate the number of rows we will actually output
			//$maxrows = max(1, (($numresults - ($numresults % $columns)) / $columns));
			foreach ($results as $line)
			{
				$celnum++;
				$trbreak++;
				if (($trbreak == $columns + 1) /*&& $celnum != $numresults*/)
				{
					$catalog_page .= '</tr>' . "\n" . '<tr>' . "\n";
					$row++;
					$trbreak = 1;
				}
				//if ($row <= $maxrows)
				//{
					$replies = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."posts` WHERE `boardid` = " . $this->board['id'] . " AND `IS_DELETED` = 0 AND `parentid` = " . $line['id']);
					$catalog_page .= '<td class="catalogtd" align="center" valign="middle" style="max-width: 368px">' . "\n" .
					'<a class="catalog-entry" href="' . KU_BOARDSFOLDER . $this->board['name'] . '/res/' . $line['id'] . '.html"';
					if ($line['subject'] != '')
					{
						$catalog_page .= ' title="' . $line['subject'] . '"';
					}
					$catalog_page .= '>';
					if ($line['file'] == '')
					{
						$catalog_page .= 'Файла нет</a><br />';
					}
					elseif ($line['file'] == 'removed')
					{
						$catalog_page .= 'Фейл удалён</a><br />';
					}
					else
					{
						if ($line['file_type'] == 'jpg' || $line['file_type'] == 'png' || $line['file_type'] == 'webp' || $line['file_type'] == 'gif')
						{
							$catalog_page .= '<img src="' . KU_BOARDSFOLDER . $this->board['name'] . '/thumb/' . $line['file'] . 's.' . $line['file_type'] . '" class="raw-thumb" alt="' . $line['id'] . '" border="0" /></a><br />';
						}
						else if ($line['file_type'] == 'webm' || $line['file_type'] == 'mp4')
						{
							$catalog_page .= '<video preload="metadata" controls="" class="raw-thumb" width="368">';
							$catalog_page .= '<source src="' . KU_BOARDSFOLDER . $this->board['name'] . '/src/' . $line['file'] . '.' . $line['file_type'] . "\" type='video/" . $line['file_type'] . "'>";
							$catalog_page .= '</video></a><br />';
						}
						else
						{
							$catalog_page .= '</a><div style="max-width: 368px; overflow: hidden;">';
							$catalog_page .= embeddedVideoBox($line, true);
							$catalog_page .= '</div>';
							
						}
					}
					if ($line['subject'] != '')
					{
						$catalog_page .= '<a class="catalog-entry" href="' . KU_BOARDSFOLDER . $this->board['name'] . '/res/' . $line['id'] . '.html">' . $line['subject'] . '</a><br />';
					}
					else
					{
						$catalog_page .= '<a class="catalog-entry" href="' . KU_BOARDSFOLDER . $this->board['name'] . '/res/' . $line['id'] . '.html">(Без названия)</a><br />';
					}
					$catalog_page .= '<small>' . $replies . ' ответов</small>' . "\n" . '</td>' . "\n";
				//}
			}
		} else {
			$catalog_page .= '<td>' . "\n" .
			_gettext('No threads.') . "\n" .
			'</td>' . "\n";
		}

		$this->dwoo_data->assign('iscatalog', 1);
		$catalog_page .= '</tr>' . "\n" . '</table><br /><hr />' .
		$this->Footer(false, (microtime_float()-$executiontime_start_catalog));
		print $catalog_head.$catalog_page;
		$this->dwoo_data->assign('iscatalog', 0);
	}
	
	function RegenerateAndPrintThread($id, $mode = '') {
		global $tc_db, $CURRENTLOCALE;
		require_once(KU_ROOTDIR."lib/dwoo.php");
		if (!isset($this->dwoo)) { $this->dwoo = New Dwoo; $this->dwoo_data = new Dwoo_Data(); $this->InitializeDwoo(); }
		$embeds = Array();
		$numimages = 0;
		if ($this->board['type'] != 1 && !$embeds) {
				$embeds = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "embeds`");
				$this->dwoo_data->assign('embeds', $embeds);
				foreach ($embeds as $embed) {
					$this->board['filetypes'][] .= $embed['filetype'];
				}
				$this->dwoo_data->assign('filetypes', $this->board['filetypes']);
		}

		$executiontime_start_thread = microtime_float();
		// Build only that thread
		$thread = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . " AND (`id` = " . $id . " OR `parentid` = " . $id . ") " . (($this->board['type'] != 1) ? ("AND `IS_DELETED` = 0") : ("")) . " ORDER BY `id` ASC");
		$pregen_replies_q = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."answers` WHERE `to_boardid` = '" . $this->board['id'] . "' AND `to_parentid` = " . $id);

		if (count($thread) > 0 && ($this->board['type'] != 1 || ((isset($thread[0]['IS_DELETED']) && $thread[0]['IS_DELETED'] == 0) || (isset($thread[0]['is_deleted']) && $thread[0]['is_deleted'] == 0))))
		{ 
			foreach ($thread as $key=>$post)
			{
				if (($post['file_type'] == 'jpg' || $post['file_type'] == 'gif' || $post['file_type'] == 'webp' || $post['file_type'] == 'png') && $post['parentid'] != 0)
				{
					$numimages++;
				}
				$post['n'] = $key + 1;
				$thread[$key] = $this->BuildPost($post, false, $pregen_replies_q);
			}
			$this->dwoo_data->assign('replythread', $id);
			$header = $this->PageHeader($id);
			$postbox = $this->Postbox($id);
			$this->dwoo_data->assign('numimages', $numimages);
			$header = str_replace("<!sm_threadid>", $id, $header);

			if ($this->board['type'] != 2)
			{
				$postbox = str_replace("<!sm_threadid>", $id, $postbox);
			}

			$this->dwoo_data->assign('threadid', $thread[0]['id']);
			$this->dwoo_data->assign('fullposts', $thread);
			$this->dwoo_data->assign('posts', $thread);
			$this->dwoo_data->assign('file_path', KU_BOARDSPATH . '/' . $this->board['name']);
			
			$postbox = $this->dwoo->get(KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_reply_header.tpl', $this->dwoo_data).$postbox;
			if (!isset($footer)) $footer = $this->Footer(false, microtime_float() - $executiontime_start_thread, $this->board['type'] == 1);

			$postnum_rounded_to_1k = floor($tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = 9") / 1000) * 1000;

			if ($mode == "+50")
			{
				$replycount = (count($thread)-1);
				if ($replycount > 50)
				{
					$this->dwoo_data->assign('replycount', $replycount);
					$this->dwoo_data->assign('modifier', "last50");
					$posts50 = array_slice($thread, -50, 50);
					array_unshift($posts50, $thread[0]); // Add the OP-post to the top of this, since it wont be included in the result
					$this->dwoo_data->assign('posts', $posts50);
					$content = $this->dwoo->get(KU_TEMPLATEDIR . '/img_thread.tpl', $this->dwoo_data);
					$content = $header.$postbox.$content.$footer;
					$content = str_replace("\t", '',$content);
					$content = str_replace("&nbsp;\r\n", '&nbsp;',$content);
					unset($posts50);					
					$content = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $content);
					
					print $content;
					$this->dwoo_data->assign('modifier', "");
				}
				else
				{
					exitWithErrorPage('Слишком мало постов в треде', 'Убери из строки запроса модификаторы +50 или -100.');
				}
			}
			else if ($mode == "-100")
			{





				$replycount = (count($thread)-1);
				if ($replycount > 100) {
					$this->dwoo_data->assign('replycount', $replycount);
					$this->dwoo_data->assign('modifier', "first100");
					$posts100 = array_slice($thread, 0, 100);
					$this->dwoo_data->assign('posts', $posts100);
					$content = $this->dwoo->get(KU_TEMPLATEDIR . '/img_thread.tpl', $this->dwoo_data);
					$content = $header.$postbox.$content.$footer;
					$content = str_replace("\t", '',$content);
					$content = str_replace("&nbsp;\r\n", '&nbsp;',$content);
					unset($posts100);
					$content = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $content);

					print $content;
					$this->dwoo_data->assign('modifier', "");
				}
				else
				{
					exitWithErrorPage('Слишком мало постов в треде', 'Убери из строки запроса модификаторы +50 или -100.');
				}
			}
			else
			{
				$content = $this->dwoo->get(KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_thread.tpl', $this->dwoo_data);
				$content = $header.$postbox.$content.$footer;
				$content = str_replace("\t", '',$content);
				$content = str_replace("&nbsp;\r\n", '&nbsp;',$content);
				$content = str_replace("{\$postnum_rounded_to_1k}", $postnum_rounded_to_1k, $content);
				
				print $content;
			}
		}
	}
	
	function replace_reflinks($matches)
	{
		global $tc_db;
		$etalon_md5 = md5(KU_REMOTE_ADDR);
		$board_name = $matches[1];
		$board_id = $this->boardids[$board_name];
		$ref_id = $matches[3];
		$comparing_md5 = $tc_db->GetOne("SELECT `ipmd5` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($board_id) . " AND `id` = " . $tc_db->qstr($ref_id));
		if ($etalon_md5 != $comparing_md5) return $matches[0];
		return str_replace('</a>', '<span class="youindicator"> (You)</span></a>', $matches[0]);
	}
	
	function BuildPost($post, $page, $pregen_replies_q = false, $temporary = false, $single = false) {
		global $tc_db, $CURRENTLOCALE;

		$post['board'] = $this->board;
		
		if ($temporary)
		{
			$srcdir = 'tmp';
			$thumbdir = 'tmp/thumb';
		}
		else
		{
			$srcdir = 'src';
			$thumbdir = 'thumb';
		}

		// (You) indicator in head
		$post['youindicator'] = '';
		if (md5(KU_REMOTE_ADDR) == $post['ipmd5'])
		{
			$post['youindicator'] = '<span class="youindicator youpost"> <strong>(You)</strong></span>';
		}

		// (You) indicator in message
		$post['message'] = preg_replace_callback('/\<a [^\<\>]* class\=\\\"ref\|([^\|]*)\|([^\|]*)\|([^"]*)\\\"\>.*?\<\/a\>/', array(&$this, 'replace_reflinks'), $post['message']);
		
		if ($this->board['type'] == 1 && ((isset($post['IS_DELETED']) && $post['IS_DELETED'] == 1) || (isset($post['is_deleted']) && $post['is_deleted'] == 1)))
		{ 
			$post['name'] = '';
			$post['email'] = '';
			$post['tripcode'] .= _gettext('Deleted');
			$post['message'] = '<font color="gray">'._gettext('This post has been deleted.').'</font>';
		}
		$dateEmail = (empty($this->board['anonymous'])) ? $post['email'] : 0;
		//by Snivy
		if(KU_CUTPOSTS) {
			$post['message'] = stripslashes(formatLongMessage($post['message'], $this->board['name'], (($post['parentid'] == 0) ? ($post['id']) : ($post['parentid'])), $page, $post['id']));
		}
		else {
			$post['message'] = stripslashes($post['message']);
		}
		$post['timestamp_formatted'] = formatDate($post['timestamp'], 'post', $CURRENTLOCALE, $dateEmail);
		$post['reflink'] = formatReflink($this->board['name'], (($post['parentid'] == 0) ? ($post['id']) : ($post['parentid'])), $post['id'], $CURRENTLOCALE, $single);
		if (isset($this->board['filetypes']) && in_array($post['file_type'], $this->board['filetypes'])) {
			$post['videobox'] = embeddedVideoBox($post);
		}
		if ($post['file_type'] == 'mp3' || $post['file_type'] == 'ogg' || $post['file_type'] == 'mp4' || $post['file_type'] == 'webm' || $post['file_type'] == 'm4a') {
			//Grab the ID3 info.
			// include getID3() library

			require_once(KU_ROOTDIR . 'lib/getid3/getid3.php');

			// Initialize getID3 engine
			$getID3 = new getID3;
			$getID3->encoding_id3v1 = KU_ID3_ENCODING;

			$post['id3'] = $getID3->analyze(KU_BOARDSDIR.$this->board['name'].'/' . $srcdir . '/' . $post['file'] . '.' . $post['file_type']);
			getid3_lib::CopyTagsToComments($post['id3']);
		}
		if ($post['file_type']!='jpg'&&$post['file_type']!='gif'&&$post['file_type']!='webp'&&$post['file_type']!='png'&&$post['file_type']!=''&&!in_array($post['file_type'], $this->board['filetypes'])) {
			if(!isset($filetype_info[$post['file_type']])) $filetype_info[$post['file_type']] = getfiletypeinfo($post['file_type']);
			$post['nonstandard_file'] = KU_WEBPATH . '/inc/filetypes/' . $filetype_info[$post['file_type']][0];
			if($post['thumb_w']!=0&&$post['thumb_h']!=0)
			{
				if(file_exists(KU_BOARDSDIR.$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.jpg'))
					$post['nonstandard_file'] = KU_WEBPATH . '/' .$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.jpg';
				elseif(file_exists(KU_BOARDSDIR.$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.png'))
					$post['nonstandard_file'] = KU_WEBPATH . '/' .$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.png';
				elseif(file_exists(KU_BOARDSDIR.$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.webp'))
					$post['nonstandard_file'] = KU_WEBPATH . '/' .$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.webp';
				elseif(file_exists(KU_BOARDSDIR.$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.gif'))
					$post['nonstandard_file'] = KU_WEBPATH . '/' .$this->board['name'].'/' . $thumbdir . '/'.$post['file'].'s.gif';
				else {
					$post['thumb_w'] = $filetype_info[$post['file_type']][1];
					$post['thumb_h'] = $filetype_info[$post['file_type']][2];
				}
			}
			else {
				$post['thumb_w'] = $filetype_info[$post['file_type']][1];
				$post['thumb_h'] = $filetype_info[$post['file_type']][2];
			}
		}
		
		if ($_COOKIE["nodolls"] === "1")
			$post['nodolls'] = 1;

		if($post['pic_spoiler']&&($post['file_type']=='jpg'||$post['file_type']=='gif'||$post['file_type']=='webp'||$post['file_type']=='png'))
		{
			$post['nonstandard_file'] = KU_WEBPATH . '/images/spoiler.'.$post['file_type'];
			$post['thumb_w'] = 200;
			$post['thumb_h'] = 200;
		}
		
		// Server answers map by Smilefag
		global $tc_db;
		$post['repliesmap'] = array();

		if ($post['id'] != '?????') // Preview
		{
			if ($pregen_replies_q != false)
			{
				$replies_q = array();
				foreach($pregen_replies_q as $suspect)
				{
					if ($suspect['to_id'] == $post['id']) array_push($replies_q, $suspect);
				}
			}
			else
			{
				$replies_q = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."answers` WHERE `to_boardid` = '" . $this->board['id'] . "' AND `to_id` = " . $post['id']);
			}
			
			if (count($replies_q) > 0)
			{
				$post['do_repliesmap'] = true;
				
				foreach ($replies_q as $reply_q)
				{
					// (You) indicator in reply map
					$etalon_md5 = md5(KU_REMOTE_ADDR);
					$board_name = $reply_q['from_boardname'];
					$board_id = $this->boardids[$board_name];
					$ref_id = $reply_q['from_id'];
					$comparing_md5 = $tc_db->GetOne("SELECT `ipmd5` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($board_id) . " AND `id` = " . $tc_db->qstr($ref_id));
					$you = ($etalon_md5 == $comparing_md5);
					
					array_push($post['repliesmap'], array
					(
						'start' => ', ',
						'boardname' => $reply_q['from_boardname'],
						'id' => $reply_q['from_id'],
						'parentid' => ($reply_q['from_parentid'] == 0) ? $reply_q['from_id'] : $reply_q['from_parentid'],
						'you' => $you
					));
				}

				// As sorting using SQL instructions is very slow... do it by hand
				usort($post['repliesmap'], "replies_cmp");
				
				// Delete duplicates
				$i = 0;
				while ($i < count($post['repliesmap']) - 1)
				{
					if (($post['repliesmap'][$i]['id'] == $post['repliesmap'][$i+1]['id']) && ($post['repliesmap'][$i]['boardname'] == $post['repliesmap'][$i+1]['boardname']))
					{
						array_splice($post['repliesmap'], $i, 1);
					}
					else $i++;
				}
				
				$post['repliesmap'][0]['start'] = 'Ответы: ';
			}
			else
			{
				$post['do_repliesmap'] = false;
			}
		}

		return $post;
	}
	
	/**
	 * Build the page header
	 *
	 * @param integer $replythread The ID of the thread the header is being build for.  0 if it is for a board page, -1 if it is search page.
	 * @param integer $liststart The number which the thread list starts on (text boards only)
	 * @param integer $liststooutput The number of list pages which will be generated (text boards only)
	 * @return string The built header
	 */
	function PageHeader($replythread = '0', $liststart = '0', $liststooutput = '-1', $overridetitle = '') {
		global $tc_db, $CURRENTLOCALE;

		$faq_enabled = false;
		$special_threads = $tc_db->GetOne("SELECT `value` FROM `" . KU_DBPREFIX . "kurisaba_ext_data` WHERE `name` = 'special_threads'");
		$special_threads = preg_replace('/ +/', ' ', $special_threads);
		$special_threads = explode("\n", $special_threads);
		$current_board='';
		foreach ($special_threads as $special_thread)
		{
			$special_thread = explode(' ', trim($special_thread), 4);
			if($special_thread[0] == 'BOARD')
			{
				$current_board=$special_thread[1];
			}
			else if($special_thread[0] == 'THREAD' || $special_thread[0] == 'HIDDEN')
			{
				if($special_thread[2] == '/faq/') $faq_enabled = true;
			}
		}


		$tpl = Array();

		$tpl['htmloptions'] = ((KU_LOCALE == 'he' && empty($this->board['locale'])) || $this->board['locale'] == 'he') ? ' dir="rtl"' : '' ;

		$tpl['title'] = '';

		if ($replythread <= '0')
		{
			if (KU_DIRTITLE)
			{
				$tpl['title'] .= '/' . $this->board['name'] . '/ - ';
			}
			$tpl['title'] .= $this->board['desc'];
		}
		else
		{
			if (KU_DIRTITLE)
			{
				if ($replythread != 0)
				{
					$results = $tc_db->GetAll("SELECT subject FROM `".KU_DBPREFIX."posts` WHERE `boardid` = '" . $this->board['id'] . "' AND `id` = ".$tc_db->qstr($replythread)." LIMIT 1");
					if (count($results) == 0)
						exitWithErrorPage(_gettext('Invalid post ID.'));
					$title = $results[0]['subject'];
					if (($title != '') && (is_string($title))) $tpl['title'] .= $title . ' - ';
				}
				$tpl['title'] .= '/' . $this->board['name'] . '/' . $replythread;
			}
		}

		if ($overridetitle != '') $tpl['title'] = $overridetitle;
		
		$ad_top = 185;
		$ad_right = 25;
		if ($this->board['type']==1) {
			$ad_top -= 50;
		} else {
			if ($replythread!=0) {
				$ad_top += 50;
			}
		}
		if ($this->board['type']==2) {
			$ad_top += 40;
		}
		$this->dwoo_data->assign('title', $tpl['title']);
		$this->dwoo_data->assign('htmloptions', $tpl['htmloptions']);
		$this->dwoo_data->assign('locale', $CURRENTLOCALE);
		$this->dwoo_data->assign('ad_top', $ad_top);
		$this->dwoo_data->assign('ad_right', $ad_right);
		$this->dwoo_data->assign('board', $this->board);
		$this->dwoo_data->assign('replythread', $replythread);
		$this->dwoo_data->assign('faq_enabled', $faq_enabled);
		if ($this->board['type'] != 1) {
			$styles =  explode(':', KU_STYLES);
			$defaultstyle = $this->board['defaultstyle'];
			if(empty($defaultstyle)) $defaultstyle = KU_DEFAULTSTYLE;
			$this->dwoo_data->assign('ku_styles', $styles);
			$this->dwoo_data->assign('ku_defaultstyle', $defaultstyle);
		} else {
			$this->dwoo_data->assign('ku_styles', explode(':', KU_TXTSTYLES));
			$this->dwoo_data->assign('ku_defaultstyle', (!empty($this->board['defaultstyle']) ? ($this->board['defaultstyle']) : (KU_DEFAULTTXTSTYLE)));
		}
		$this->dwoo_data->assign('boardlist', $this->board['boardlist']);
		$this->dwoo_data->assign('maxfilesize', $this->board['maximagesize']);

		$global_header = $this->dwoo->get(KU_TEMPLATEDIR . '/global_board_header.tpl', $this->dwoo_data);

		$phrases=explode('#', KU_SEARCH_PHRASES);
		$this->dwoo_data->assign('search_phrases',implode("','",$phrases));
		$this->dwoo_data->assign('search_phrase',$phrases[array_rand($phrases)]);

		if ($this->board['type'] != 1) {
			$header = $this->dwoo->get(KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_header.tpl', $this->dwoo_data);
		} else {
			if ($liststooutput == -1) {
				$this->dwoo_data->assign('isindex', true);
			} else {
				$this->dwoo_data->assign('isindex', false);
			}
			if ($replythread != 0) $this->dwoo_data->assign('isthread', true);
			$header = $this->dwoo->get(KU_TEMPLATEDIR . '/txt_header.tpl', $this->dwoo_data);

			if ($replythread == 0) {
				$startrecord = ($liststooutput >= 0 || $this->board['compactlist']) ? 40 : KU_THREADSTXT ;
				$threads = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($this->board['id']) . " AND `parentid` = 0 AND `IS_DELETED` = 0 ORDER BY `stickied` DESC, `bumped` DESC LIMIT " . $startrecord . " OFFSET " . $liststart);
				foreach($threads AS $key=>$thread) {
					$replycount = $tc_db->GetOne("SELECT COUNT(`id`) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($this->board['id']) . " AND `parentid` = " . $thread['id']);
					$threads[$key]['replies'] = $replycount;
				}
				$this->dwoo_data->assign('threads', $threads);
				$header .= $this->dwoo->get(KU_TEMPLATEDIR . '/txt_threadlist.tpl', $this->dwoo_data);
			}
		}

		return $global_header.$header;
	}

	/**
	 * Build the page header for an oekaki posting
	 *
	 * @param integer $replyto The ID of the thread being replied to.  0 for a new thread
	 */
	function OekakiHeader($replyto, $postoek) {
		$executiontime_start = microtime_float();
		$this->InitializeDwoo();

		$page = $this->PageHeader();
		$this->dwoo_data->assign('replythread', $replyto);
		$page .= $this->Postbox($replyto);

		$executiontime_stop = microtime_float();

		$page .= $this->Footer(false, ($executiontime_stop - $executiontime_start));

		$this->PrintPage('', $page, true);
	}


	function ProduceSmileImages($add = '')
	{
		require KU_ROOTDIR . 'images/smilies/smilies_list.php';
		
		$string='';
		
		foreach ($smilies_replace as $smilies_groupkey => $smilies_group)
		{
			$string .= '<input type="radio" name="smilies_group' . $add . '" value="' . $smilies_groupkey . '" onclick="showsmilebox' . $add . '(this)" ' . /*(first ? "checked" : "") .*/ '> ' . $smilies_groupkey;
		}
				
		$string .= '<div style="width: 525px; overflow-x: scroll;"><table>';
		
		foreach ($smilies_replace as $smilies_groupkey => $smilies_group)
		{
			$string .= '<tr class="smilies_group' . $add . '" id="smilies_group' . $add . '_' . $smilies_groupkey . '" style="display: none;"><script>smilies_array' . $add . '[\'' . $smilies_groupkey . '\'] = \'';
			
			foreach($smilies_group as $key => $value)
			{
				$string .= '<td valign="middle" onclick="insert(\\\'::'.$key.'::\\\')"><img src="/images/smilies/'.$value.'"></td>';
			}
			$string .= '\';</script></tr>';
		}
		
		$string .= '</table></div>';
		
		return $string;
	}
	
	/**
	 * Generate the postbox area
	 *
	 * @param integer $replythread The ID of the thread being replied to.  0 if not replying
	 * @param string $postboxnotice The postbox notice
	 * @return string The generated postbox
	 */
	function Postbox($replythread = 0) {
		global $tc_db;
		$postbox = '';

		if ($this->board['type'] == 2 && $replythread > 0) {
			$oekposts = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX."posts` WHERE `boardid` = " . $this->board['id']." AND (`id` = ".$replythread." OR `parentid` = ".$replythread.") AND `file` != '' AND `file` != 'removed' AND `file_type` IN ('jpg', 'gif', 'png', 'webp') AND `IS_DELETED` = 0 ORDER BY `parentid` ASC, `timestamp` ASC");
			$this->dwoo_data->assign('oekposts', $oekposts);
		}
		
		$this->dwoo_data->assign('captchaid', rand() / getrandmax());
		$this->dwoo_data->assign('smile_images', $this->ProduceSmileImages());
		
		if(($this->board['type'] == 1 && $replythread == 0) || $this->board['type'] != 1) {
			$pb_tpl = KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_post_box.tpl';
			if ($_COOKIE["kudev"] === "1") {
				$pb_tpl = KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_post_box-dev.tpl';
				echo "kudev mode on: " . $_COOKIE["kudev"];
			}

			$postbox = $this->dwoo->get($pb_tpl, $this->dwoo_data);
		}
		return $postbox;
	}

	/**
	 * Display the user-defined list of boards found in boards.html
	 * * Snivy added section description for better header
	 * @param boolean $is_textboard If the board this is being displayed for is a text board
	 * @return string The board list
	 */
	function DisplayBoardList($is_textboard = false) {
		if (KU_GENERATEBOARDLIST) {
			global $tc_db;
			$output = '';
			$results = $tc_db->GetAll("SELECT `id`,`name`,`abbreviation` FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
			$boards = array();
			foreach($results AS $line) {
				$boards[$line['id']]['nick'] = htmlspecialchars($line['name']);
				$boards[$line['id']]['abbreviation'] = htmlspecialchars($line['abbreviation']);
				$results2 = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "boards` WHERE `section` = '" . $line['id'] . "' ORDER BY `order` ASC, `name` ASC");
				foreach($results2 AS $line2) {
					$boards[$line['id']][$line2['id']]['name'] = htmlspecialchars($line2['name']);
					$boards[$line['id']][$line2['id']]['desc'] = htmlspecialchars($line2['desc']);
				}
			}
		} else {
			$boards = KU_ROOTDIR . 'boards.html';
		}

		return $boards;
	}

	/**
	 * Display the page footer
	 *
	 * @param boolean $noboardlist Force the board list to not be displayed
	 * @param string $executiontime The time it took the page to be created
	 * @param boolean $hide_extra Hide extra footer information, and display the manage link
	 * @return string The generated footer
	 */
	function Footer($noboardlist = false, $executiontime = '', $hide_extra = false) {
		global $tc_db, $dwoo, $dwoo_data;
		$footer = '';
		if ($hide_extra || $noboardlist) $this->dwoo_data->assign('boardlist', '');
		if ($executiontime != '') $this->dwoo_data->assign('executiontime', round($executiontime, 2));
		$footer = $this->dwoo->get(KU_TEMPLATEDIR . '/' . $this->board['text_readable'] . '_footer.tpl', $this->dwoo_data);
		$footer .= $this->dwoo->get(KU_TEMPLATEDIR . '/global_board_footer.tpl', $this->dwoo_data);
		return $footer;
	}

	/**
	 * Finalize the page and print it to the specified filename
	 *
	 * @param string $filename File to print the page to
	 * @param string $contents Page contents
	 * @param string $board Board which the file is being generated for
	 * @return string The page contents, if requested
	 */
	function PrintPage($filename, $contents, $board) {

		if ($board !== true) {
			print_page($filename, $contents, $board);
		} else {
			echo $contents;
		}
	}

	/**
	 * Initialize the instance of smary which will be used for generating pages
	 */
	function InitializeDwoo() {

		require_once KU_ROOTDIR . 'lib/dwoo.php';
		$this->dwoo = new Dwoo();
		$this->dwoo_data = new Dwoo_Data();

		$this->dwoo_data->assign('cwebpath', KU_WEBPATH . '/');
		$this->dwoo_data->assign('boardpath', KU_BOARDSPATH . '/');
	}
}

/**
 * Post class
 *
 * Used for post insertion, deletion, and reporting.
 *
 * @package kusaba
 */
class Post extends Board {
	// Declare the public variables
	var $post = Array();

	function __construct($postid, $board, $boardid, $is_inserting = false) {
		global $tc_db;

		$results = $tc_db->GetAll("SELECT * FROM `".KU_DBPREFIX."posts` WHERE `boardid` = '" . $boardid . "' AND `id` = ".$tc_db->qstr($postid)." LIMIT 1");
		if (count($results)==0&&!$is_inserting) {
			exitWithErrorPage('Invalid post ID.');
		} elseif ($is_inserting) {
			parent::__construct($board, false);
		} else {
			foreach ($results[0] as $key=>$line) {
				if (!is_numeric($key)) $this->post[$key] = $line;
			}
			$results = $tc_db->GetAll("SELECT `cleared` FROM `".KU_DBPREFIX."reports` WHERE `postid` = ".$tc_db->qstr($this->post['id'])." LIMIT 1");
			if (count($results)>0) {
				foreach($results AS $line) {
					$this->post['isreported'] = ($line['cleared'] == 0) ? true : 'cleared';
				}
			} else {
				$this->post['isreported'] = false;
			}
			$this->post['isthread'] = ($this->post['parentid'] == 0) ? true : false;
			if (empty($this->board) || $this->board['name'] != $board) {
				parent::__construct($board, false);
			}
		}
	}

	function Delete($save_picture = false)
	{
		global $tc_db;

		if ($this->post['isthread'] == true)
		{
			AnswerMapDelete($this->post['id'], $this->board['id']);
			AnswerMapDelete(0,                 $this->board['id'], $this->post['id']);
			@unlink(KU_BOARDSDIR.$this->board['name'].'/res/'.$this->post['id'].'.html');
			$this->DeleteFile(false, true, $save_picture);
			@unlink(KU_BOARDSDIR.$this->board['name'].'/res/'.$this->post['id'].'-100.html');
			@unlink(KU_BOARDSDIR.$this->board['name'].'/res/'.$this->post['id'].'+50.html');

			$results = $tc_db->GetAll("SELECT `id`, `file`, `file_type` FROM `".KU_DBPREFIX."posts` WHERE `boardid` = '" . $this->board['id'] . "' AND `IS_DELETED` = 0 AND `parentid` = ".$tc_db->qstr($this->post['id']));
			foreach($results AS $line)
			{
				clearPostCache($line['id'], $this->board['name']);
			}
			$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `IS_DELETED` = 1 , `deleted_timestamp` = '" . (time() + KU_ADDTIME) . "' WHERE `boardid` = '" . $this->board['id'] . "' AND (`parentid` = ".$tc_db->qstr($this->post['id'])." OR `id` = ".$tc_db->qstr($this->post['id']).")");
			clearPostCache($this->post['id'], $this->board['name']);
			return true;
		}
		else
		{
			$this->DeleteFile(false, false, $save_picture);
			$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `IS_DELETED` = 1 , `deleted_timestamp` = '" . (time() + KU_ADDTIME) . "' WHERE `boardid` = '" . $this->board['id'] . "' AND `id` = ".$tc_db->qstr($this->post['id']));

			// Unbump thread after deletion
			$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . " AND `parentid` = " . $tc_db->qstr($this->post['parentid']) . " AND `IS_DELETED` = 0 ORDER BY `stickied` DESC, `bumped` DESC LIMIT 1");
			if(count($results) == 0)
			{
				$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $this->board['id'] . " AND `id` = " . $tc_db->qstr($this->post['parentid']) . " AND `IS_DELETED` = 0 ORDER BY `stickied` DESC, `bumped` DESC LIMIT 1");
			}
			foreach($results AS $line)
			{
				$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `bumped` = '" . $line['timestamp'] . "' WHERE `boardid` = '" . $this->board['id'] . "' AND `id` = ". $tc_db->qstr($this->post['parentid']));
			}

			clearPostCache($this->post['id'], $this->board['name']);
			AnswerMapDelete($this->post['id'], $this->board['id']);
			return true;
		}
	}

	function DeleteFile($update_to_removed = true, $whole_thread = false, $save_picture = false) {
		global $tc_db;
		if ($whole_thread && $this->post['isthread'])
		{
			// $save_picture does not work on deletion of the whole thread.
			$results = $tc_db->GetAll("SELECT `id`, `file`, `file_type` FROM `".KU_DBPREFIX."posts` WHERE `boardid` = " . $this->board['id'] . " AND `IS_DELETED` = 0 AND `parentid` = ".$tc_db->qstr($this->post['id']));
			if (count($results)>0)
			{
				foreach($results AS $line)
				{
					if ($line['file'] != '' && $line['file'] != 'removed')
					{
						@unlink(KU_BOARDSDIR.$this->board['name'].'/src/'.$line['file'].'.'.$line['file_type']);
						@unlink(KU_BOARDSDIR.$this->board['name'].'/src/'.$line['file'].'.pch');
						@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'s.'.$line['file_type']);
						@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'a.'.$line['file_type']);
						@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'c.'.$line['file_type']);
						if ($line['file_type'] == 'mp3' || $line['file_type'] == 'ogg' || $line['file_type'] == 'm4a')
						{
							@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'s.jpg');
							@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'s.webp');
							@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'s.png');
							@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$line['file'].'s.gif');
						}
						if ($update_to_removed)
						{
							$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `file` = 'removed', `file_md5` = '' WHERE `boardid` = '" . $this->board['id'] . "' AND `id` = ".$line['id']);
							clearPostCache($line['id'], $this->board['name']);
						}
					}
				}
			}
			$this->DeleteFile($update_to_removed, false, $save_picture);
		} else {
			if ($this->post['file']!=''&&$this->post['file']!='removed')
			{
				if ($save_picture)
				{
					@copy(KU_BOARDSDIR.$this->board['name'].'/src/'.$this->post['file'].'.'.$this->post['file_type'],
					      KU_BOARDSDIR.$this->board['name'].'/tmp/saved'.$this->post['file'].'.'.$this->post['file_type']);
				}
				@unlink(KU_BOARDSDIR.$this->board['name'].'/src/'.$this->post['file'].'.'.$this->post['file_type']);
				@unlink(KU_BOARDSDIR.$this->board['name'].'/src/'.$this->post['file'].'.pch');
				@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'s.'.$this->post['file_type']);
				@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'a.'.$this->post['file_type']);
				@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'c.'.$this->post['file_type']);
				if ($this->post['file_type'] == 'mp3' || $this->post['file_type'] == 'ogg' || $this->post['file_type'] == 'm4a')
				{
					@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'s.jpg');
					@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'s.webp');
					@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'s.png');
					@unlink(KU_BOARDSDIR.$this->board['name'].'/thumb/'.$this->post['file'].'s.gif');

				}
				if ($update_to_removed)
				{
					$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `file` = 'removed', `file_md5` = '' WHERE `boardid` = '" . $this->board['id'] . "' AND `id` = ".$tc_db->qstr($this->post['id']));
					clearPostCache($this->post['id'], $this->board['name']);
				}
			}
		}
	}

	function Insert($parentid, $name, $tripcode, $email, $subject, $message, $message_source, $filename, $file_original, $filetype, $file_md5, $image_md5, $image_w, $image_h, $filesize, $thumb_w, $thumb_h, $password, $timestamp, $bumped, $ip, $posterauthority, $stickied, $locked, $boardid, $country, $pic_spoiler, $pic_animated)
	{
		// Why do we need to transfer $boardid while we have $this->board['id']?
		global $tc_db;
		$quoted_message = $tc_db->qstr($message);
		if (strlen($quoted_message) > 60000) return -1;
		$tc_db->Execute("SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
		$tc_db->Execute("BEGIN TRANSACTION");
		$query = "INSERT INTO `".KU_DBPREFIX."posts` ("
		."`id`,"
		."`parentid`,"
		."`boardid`,"
		."`name`,"
		."`tripcode`,"
		."`email`,"
		."`subject`,"
		."`message`,"
		."`message_source`,"
		."`file`,"
		."`file_original`,"
		."`file_type`,"
		."`file_md5`,"
		."`banimage_md5`,"
		."`image_w`,"
		."`image_h`,"
		."`file_size`,"
		."`file_size_formatted`,"
		."`thumb_w`,"
		."`thumb_h`,"
		."`password`,"
		."`timestamp`,"
		."`bumped`,"
		."`ip`,"
		."`ipmd5`,"
		."`posterauthority`,"
		."`stickied`,"
		."`locked`,"
		."`country`,"
		."`pic_spoiler`,"
		."`pic_animated`"
		.") VALUES ("
		."(SELECT * FROM (SELECT (COALESCE(MAX(id), 0) + 1) FROM `".KU_DBPREFIX."posts` WHERE `boardid` = ".$tc_db->qstr($boardid).") AS musthavename),"
		.$tc_db->qstr($parentid).","
		.$tc_db->qstr($boardid).","
		.$tc_db->qstr($name).","
		.$tc_db->qstr($tripcode).","
		.$tc_db->qstr($email).","
		.$tc_db->qstr($subject).","
		.$quoted_message.","
		.$tc_db->qstr($message_source).","
		.$tc_db->qstr($filename).","
		.$tc_db->qstr($file_original).","
		.$tc_db->qstr($filetype).","
		.$tc_db->qstr($file_md5).","
		.$tc_db->qstr($image_md5).","
		.$tc_db->qstr(intval($image_w)).","
		.$tc_db->qstr(intval($image_h)).","
		.$tc_db->qstr($filesize).","
		.$tc_db->qstr(ConvertBytes($filesize)).","
		.$tc_db->qstr($thumb_w).","
		.$tc_db->qstr($thumb_h).","
		.$tc_db->qstr($password).","
		.$tc_db->qstr($timestamp).","
		.$tc_db->qstr($bumped).","
		.$tc_db->qstr(md5_encrypt($ip, KU_RANDOMSEED)).","
		."'".md5($ip)."',"
		.$tc_db->qstr($posterauthority).","
		.$tc_db->qstr($stickied).","
		.$tc_db->qstr($locked).","
		.$tc_db->qstr($country).","
		.$tc_db->qstr($pic_spoiler).","
		.$tc_db->qstr($pic_animated)
		.")";

		$tc_db->Execute($query);
		$tc_db->Execute("COMMIT");
		$tc_db->Execute("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");

		$id = $tc_db->Insert_Id();
		if(!$id || KU_DBTYPE == 'sqlite') {
			// Non-mysql installs don't return the insert ID after insertion, we need to manually get it.
			$id = $tc_db->GetOne("SELECT `id` FROM `".KU_DBPREFIX."posts` WHERE `boardid` = ".$tc_db->qstr($boardid)." AND timestamp = ".$tc_db->qstr($timestamp)." AND `ipmd5` = '".md5($ip)."' LIMIT 1");
		}

		if ($id == 1 && $this->board['start'] > 1) {
			$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `id` = '".$this->board['start']."' WHERE `boardid` = ".$boardid);
			$id = $this->board['start'];
		}

		// Add answer map
		$ans_req = array();
		array_push ($ans_req, array('id' => $id, 'boardid' => $boardid, 'boardname' => $this->board['name'], 'parentid' => $parentid, 'message' => $message));
		$altered_threads = AnswerMapAdd($ans_req, $this->boardids);
		
		// Return id of added post
		return $id;
	}

	function Report() {
		global $tc_db;

		return $tc_db->Execute("INSERT INTO `".KU_DBPREFIX."reports` ( `board` , `postid` , `when` , `ip`, `reason` ) VALUES ( " . $tc_db->qstr($this->board['name']) . " , " . $tc_db->qstr($this->post['id']) . " , ".(time() + KU_ADDTIME)." , '" . md5_encrypt(KU_REMOTE_ADDR, KU_RANDOMSEED) . "', " . $tc_db->qstr($_POST['reportreason']) . " )");
	}
}

?>
