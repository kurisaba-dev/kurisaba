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
 * +------------------------------------------------------------------------------+
 * Manage Class
 * +------------------------------------------------------------------------------+
 * Manage functions, along with the pages available
 * +------------------------------------------------------------------------------+
 */
class Manage {

	/* Show the header of the manage page */
	function Header() {
		global $dwoo_data, $tpl_page;
		$tpl_includeheader = '';
		$dwoo_data->assign('includeheader', $tpl_includeheader);
	}

	/* Show the footer of the manage page */
	function Footer() {
		global $dwoo_data, $dwoo, $tpl_page;

		$dwoo_data->assign('page', $tpl_page);

		$board_class = new Board('');

		$dwoo->output(KU_TEMPLATEDIR . '/manage.tpl', $dwoo_data);
	}

	// Creates a salt to be used for passwords
	function CreateSalt() {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$salt = '';

		for ($i = 0; $i < 3; ++$i) {
			$salt .= $chars[mt_rand(0, strlen($chars) - 1)];
		}
		return $salt;
	}

	/* Validate the current session */
	function ValidateSession($is_menu = false) {
		global $tc_db, $tpl_page;

		if (isset($_SESSION['manageusername']) && isset($_SESSION['managepassword'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . " AND `password` = " . $tc_db->qstr($_SESSION['managepassword']) . " LIMIT 1");
			if (count($results) == 0) {
				session_destroy();
				exitWithErrorPage(_gettext('Invalid session.'), '<a href="manage_page.php">'. _gettext('Log in again.') . '</a>');
			}

			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `lastactive` = " . ((time() + KU_ADDTIME) + KU_ADDTIME) . " WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']));

			return true;
		} else {
			if (!$is_menu) {
				$this->LoginForm();
				die($tpl_page);
			} else {
				return false;
			}
		}
	}

	/* Show the login form and halt execution */
	function LoginForm() {
		global $tc_db, $tpl_page;

		if (file_exists(KU_ROOTDIR . 'inc/pages/manage_login.html')) {
			$tpl_page .= file_get_contents(KU_ROOTDIR . 'inc/pages/manage_login.html');
		}
	}

	/* Check login names and create session if user/pass is correct */
	function CheckLogin() {
		global $tc_db, $action;

		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `timestamp` < '" . (time() + KU_ADDTIME - 1200) . "'");
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `ip` FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` = '" . KU_REMOTE_ADDR . "' LIMIT 6");
		if (count($results) > 5) {
			exitWithErrorPage(_gettext('System lockout'), _gettext('Sorry, because of your numerous failed logins, you have been locked out from logging in for 20 minutes. Please wait and then try again.'));
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `username`, `password`, `salt` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_POST['username']) . " LIMIT 1");
			if (count($results) > 0) {
				if (empty($results[0]['salt'])) {
					if (md5($_POST['password']) == $results[0]['password']) {
						$salt = $this->CreateSalt();
						$tc_db->Execute("UPDATE `" .KU_DBPREFIX. "staff` SET salt = '" .$salt. "' WHERE username = " .$tc_db->qstr($_POST['username']));
						$newpass = md5($_POST['password'] . $salt);
						$tc_db->Execute("UPDATE `" .KU_DBPREFIX. "staff` SET password = '" .$newpass. "' WHERE username = " .$tc_db->qstr($_POST['username']));
						$_SESSION['manageusername'] = $_POST['username'];
						$_SESSION['managepassword'] = $newpass;
            			$_SESSION['token'] = md5($_SESSION['manageusername'] . $_SESSION['managepassword'] . rand(0,100));
						$this->SetModerationCookies();
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "loginattempts` WHERE `ip` < '" . KU_REMOTE_ADDR . "'");
						$action = 'posting_rates';
						management_addlogentry(_gettext('Logged in'), 1);
						die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
					} else {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . KU_REMOTE_ADDR . "' , '" . (time() + KU_ADDTIME) . "' )");
						exitWithErrorPage(_gettext('Incorrect username/password.'));
					}
				} else {
					if (md5($_POST['password'] . $results[0]['salt']) == $results[0]['password']) {
						$_SESSION['manageusername'] = $_POST['username'];
						$_SESSION['managepassword'] = md5($_POST['password'] . $results[0]['salt']);
            $_SESSION['token'] = md5($_SESSION['manageusername'] . $_SESSION['managepassword'] . rand(0,100));
						$this->SetModerationCookies();
						$action = 'posting_rates';
						management_addlogentry(_gettext('Logged in'), 1);
						die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
					} else {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . KU_REMOTE_ADDR . "' , '" . (time() + KU_ADDTIME) . "' )");
						exitWithErrorPage(_gettext('Incorrect username/password.'));
					}
				}
			} else {
				$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "loginattempts` ( `username` , `ip` , `timestamp` ) VALUES ( " . $tc_db->qstr($_POST['username']) . " , '" . KU_REMOTE_ADDR . "' , '" . (time() + KU_ADDTIME) . "' )");
				exitWithErrorPage(_gettext('Incorrect username/password.'));
			}
		}
	}

	/* Set mod cookies for boards */
	function SetModerationCookies() {
		global $tc_db, $tpl_page;
		if (isset($_SESSION['manageusername'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . " LIMIT 1");
			if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
				setcookie_strict("kumod", "allboards", time() + KU_ADDTIME + 3600, KU_BOARDSFOLDER, KU_DOMAIN);
			} else {
				if ($results[0][0] != '') {
					setcookie_strict("kumod", $results[0][0], time() + KU_ADDTIME + 3600, KU_BOARDSFOLDER, KU_DOMAIN);
				}
			}
		}
	}

  function CheckToken($posttoken) {
    if ($posttoken != $_SESSION['token']) {
      // Something is strange
      session_destroy();
      exitWithErrorPage(_gettext('Invalid Token'));
    }
  }

	/* Log current user out */
	function Logout() {
		global $tc_db, $tpl_page;

		setcookie_strict('kumod', '', 1000000, KU_BOARDSFOLDER, KU_DOMAIN);

		session_destroy();
		unset($_SESSION['manageusername']);
		unset($_SESSION['managepassword']);
    	unset($_SESSION['token']);
		die('<script type="text/javascript">top.location.href = \''. KU_CGIPATH .'/manage.php\';</script>');
	}

		/* If the user logged in isn't an admin, kill the script */
	function AdministratorsOnly() {
		global $tc_db, $tpl_page;

		if (!$this->CurrentUserIsAdministrator()) {
			exitWithErrorPage('That page is for admins only.');
		}
	}

	/* If the user logged in isn't an moderator or higher, kill the script */
	function ModeratorsOnly() {
		global $tc_db, $tpl_page;

		if ($this->CurrentUserIsAdministrator()) {
			return true;
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
			foreach ($results as $line) {
				if ($line['type'] != 2 && $line['type'] != 3) {
					exitWithErrorPage(_gettext('That page is for moderators and administrators only.'));
				}
			}
		}
	}

	function BoardOwnersOnly() {
		global $tc_db, $tpl_page;

		if ($this->CurrentUserIsAdministrator()) {
			return true;
		} else {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
			foreach ($results as $line) {
				if ($line['type'] != 3) {
					exitWithErrorPage(_gettext('That page is for custom board owners only.'));
				}
			}
		}
	}

	/* See if the user logged in is an admin */
	function CurrentUserIsAdministrator() {
		global $tc_db, $tpl_page;

		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '' || $_SESSION['token'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
      		$_SESSION['token'] = '';
			return false;
		}

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 1) {
				return true;
			} else {
				return false;
			}
		}

		/* If the function reaches this point, something is fishy. Kill their session */
		session_destroy();
		exitWithErrorPage(_gettext('Invalid session, please log in again.'));
	}

	/* See if the user logged in is a moderator */
	function CurrentUserIsModerator() {
		global $tc_db, $tpl_page;

		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '' || $_SESSION['token'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
      		$_SESSION['token'] = '';
			return false;
		}

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 2) {
				return true;
			} else {
				return false;
			}
		}

		/* If the function reaches this point, something is fishy. Kill their session */
		session_destroy();
		exitWithErrorPage(_gettext('Invalid session, please log in again.'));
	}

	function CurrentUserIsBoardOwner() {
		global $tc_db, $tpl_page;

		if ($_SESSION['manageusername'] == '' || $_SESSION['managepassword'] == '' || $_SESSION['token'] == '') {
			$_SESSION['manageusername'] = '';
			$_SESSION['managepassword'] = '';
      		$_SESSION['token'] = '';
			return false;
		}

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "' AND `password` = '" . $_SESSION['managepassword'] . "' LIMIT 1");
		foreach ($results as $line) {
			if ($line['type'] == 3) {
				return true;
			} else {
				return false;
			}
		}

		/* If the function reaches this point, something is fishy. Kill their session */
		session_destroy();
		exitWithErrorPage(_gettext('Invalid session, please log in again.'));
	}

	/* See if the user logged in is a moderator of a specified board */
	function CurrentUserIsModeratorOfBoard($board, $username) {
		global $tc_db, $tpl_page;

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `type`, `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if (count($results) > 0) {
			foreach ($results as $line) {
				if ($line['boards'] == 'allboards') {
					return true;
				} else {
					if ($line['type'] == '1') {
						return true;
					} else {
						$array_boards = explode('|', $line['boards']);
						if (in_array($board, $array_boards)) {
							return true;
						} else {
							return false;
						}
					}
				}
			}
		} else {
			return false;
		}
	}

	function CountOkarins($seconds = 3600)
	{
		global $tc_db;
		$result = $tc_db->GetAll("SELECT DISTINCT `ipmd5` FROM `".KU_DBPREFIX."posts` WHERE `timestamp` > " . (time() + KU_ADDTIME - $seconds));
		return count($result);
	}

	function CountAgents($seconds = 3600)
	{
		$count = 0;
		foreach(glob(session_save_path()."/sess*") as $filename)
		{
			if (filectime($filename) > time() - $seconds) $count++;
		}
		return $count;
	}

	/*
	* +------------------------------------------------------------------------------+
	* Manage pages
	* +------------------------------------------------------------------------------+
	*/


	/*
	* +------------------------------------------------------------------------------+
	* Home Pages
	* +------------------------------------------------------------------------------+
	*/

	function welcome() {
		global $tpl_page;
		$tpl_page .= '<h1><center>Добро пожаловать в админку!</center></h1>'. "\n";
	}

	function apachelog() {
		global $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h1><center>Лог Apache:</center></h1><br><table width="100%" border=1><tr><td><pre>';
		$tpl_page .= shell_exec("get_error_log");
		/*
		    The executable (not script!) 'get_error_log' should:
		    - execute command like 'cat /var/log/apache2/error.log';
		    - have suid bit;
		    - be executable as www-data user.
		    For example, it may be achieved with following file, say, get_error_log.c:
				#include <stdlib.h>
				int main() {return system("cat /var/log/apache2/error.log");}
			Then you should compile it with 'gcc get_error_log.c -o get_error_log' and then do 'chmod u+s get_error_log'.
		*/
		$tpl_page .= '</td></tr></table></pre>';
	}

	function posting_rates() {
		global $tc_db, $tpl_page;

		$tpl_page .= '<h2>'. _gettext('Posting rates (past hour)') . '</h2><br />';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" cellspacing="2" cellpadding="2" width="100%"><tr><th>'. _gettext('Board') . '</th><th>'. _gettext('Threads') . '</th><th>'. _gettext('Replies') . '</th><th>'. _gettext('Posts') . '</th></tr>';
			foreach ($results as $line) {
				$rows_threads = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `parentid` = 0 AND `timestamp` >= " . (time() + KU_ADDTIME - 3600));
				$rows_replies = $tc_db->GetOne("SELECT HIGH_PRIORITY count(id) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `parentid` != 0 AND `timestamp` >= " . (time() + KU_ADDTIME - 3600));
				$rows_posts = $rows_threads + $rows_replies;
				$threads_perminute = $rows_threads;
				$replies_perminute = $rows_replies;
				$posts_perminute = $rows_posts;
				$tpl_page .= '<tr><td><strong><a href="'. KU_WEBFOLDER . $line['name'] . '">'. $line['name'] . '</a></strong></td><td>'. $threads_perminute . '</td><td>'. $replies_perminute . '</td><td>'. $posts_perminute . '</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('No boards');
		}
	}

	function threadlimit()
	{
		global $tc_db, $tpl_page, $CURRENTLOCALE;
		$this->AdministratorsOnly();

		$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "kurisaba_ext_data` SET `value` = " . $tc_db->qstr(time() + KU_ADDTIME) . " WHERE `name` = ". $tc_db->qstr("threadlimit_timestamp"));
		$results = $tc_db->GetOne("SELECT `value` FROM `" . KU_DBPREFIX . "kurisaba_ext_data` WHERE `name` = " . $tc_db->qstr("threadlimit_timestamp"));
		$timestamp_from_db = formatDate($results, 'post', $CURRENTLOCALE);

		$tpl_page .= '<h2>Момент отсчёта лимита тредов установлен на: ' . $timestamp_from_db . ' </h2><br />Теперь в течение 24 часов с этого момента на борде можно создать '.KU_MAXTHREADSADAY.' тредов.';
	}

	function verbosestats()
	{
		global $tc_db, $tpl_page, $CURRENTLOCALE;

		$tpl_page .= '<h2>Количество постов, созданных в конкретный день:</h2><table><tr><td>Date</td><td>/sg/</td><td>/vg/</td></tr>';

		$timestamp = $tc_db->GetOne("SELECT `timestamp` FROM `" . KU_DBPREFIX . "posts` WHERE `id` = '1' AND `boardid` = '9'"); // First post
		$timestamp = floor($timestamp / 86400) * 86400;
		$vgposts = $tc_db->GetAll("SELECT `timestamp` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '5'");
		$sgposts = $tc_db->GetAll("SELECT `timestamp` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '9'");


		while ($timestamp < time() + KU_ADDTIME + 86400)
		{
			$date = date('j.m.Y', $timestamp);

			$sgposts_today = 0;
			foreach ($sgposts as $sgpost) { if ($sgpost['timestamp'] >= $timestamp && $sgpost['timestamp'] < $timestamp + 86400) ++$sgposts_today;}

			$vgposts_today = 0;
			foreach ($vgposts as $vgpost) { if ($vgpost['timestamp'] >= $timestamp && $vgpost['timestamp'] < $timestamp + 86400) ++$vgposts_today;}

			$tpl_page .= '<tr><td>'.$date.'</td><td>'.$sgposts_today.'</td><td>'.$vgposts_today.'</td></tr>';
			$timestamp += 86400;
		}

		$tpl_page .= '</table>';
	}

	function statistics() {
		global $tc_db, $tpl_page;

		$tpl_page .= '<h2>'. _gettext('Statistics') .'</h2><br />';
		$tpl_page .= '<img src="manage_page.php?graph&type=day" />
		<img src="manage_page.php?graph&type=week" />
		<img src="manage_page.php?graph&type=postnum" />
		<img src="manage_page.php?graph&type=unique" />
		<img src="manage_page.php?graph&type=posttime" />';
	}

	function changepwd() {
		global $tc_db, $tpl_page;

		$tpl_page .= '<h2>'. _gettext('Change account password') . '</h2><br />';
		if (isset($_POST['oldpwd']) && isset($_POST['newpwd']) && isset($_POST['newpwd2'])) {
      $this->CheckToken($_POST['token']);
			if ($_POST['oldpwd'] != '' && $_POST['newpwd'] != '' && $_POST['newpwd2'] != '') {
				if ($_POST['newpwd'] == $_POST['newpwd2']) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . "");
					foreach ($results as $line) {
						$staff_passwordenc = $line['password'];
						$staff_salt = $line['salt'];
					}
					if (md5($_POST['oldpwd'].$staff_salt) == $staff_passwordenc) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `password` = '" . md5($_POST['newpwd'].$staff_salt) . "' WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']) . "");
						$_SESSION['managepassword'] = md5($_POST['newpwd'].$staff_salt);
						$tpl_page .= _gettext('Password successfully changed.');
					} else {
						$tpl_page .= _gettext('The old password you provided did not match the current one.');
					}
				} else {
					$tpl_page .= _gettext('The second password did not match the first.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr />';
		}
		$tpl_page .= '<form action="manage_page.php?action=changepwd" method="post">
    <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<label for="oldpwd">'. _gettext('Old password') . ':</label>
		<input type="password" name="oldpwd" /><br />

		<label for="newpwd">'. _gettext('New password') . ':</label>
		<input type="password" name="newpwd" /><br />

		<label for="newpwd2">'. _gettext('New password again') . ':</label>
		<input type="password" name="newpwd2" /><br />

		<input type="submit" value="' ._gettext('Change account password') . '" />

		</form>';
	}

	/*
	* +------------------------------------------------------------------------------+
	* Site Administration Pages
	* +------------------------------------------------------------------------------+
	*/

	/* Edit Dwoo templates */
	function templates() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$files = array();

		$tpl_page .= '<h2>'. _gettext('Template editor') .'</h2><br />';
		if ($dh = opendir(KU_TEMPLATEDIR)) {
			while (($file = readdir($dh)) !== false) {
				if($file != '.' && $file != '..')
				$files[] = $file;
			}
			closedir($dh);
		}
		sort($files);

		if(isset($_POST['templatedata']) && isset($_POST['template'])) {
      $this->CheckToken($_POST['token']);
			$file = basename($_POST['template']);
			if (in_array($file, $files)) {
				if(file_exists(KU_TEMPLATEDIR . '/'. $file)) {
					file_put_contents(KU_TEMPLATEDIR . '/'. $file, $_POST['templatedata']);
					$tpl_page .= '<hr /><h3>'. _gettext('Template edited') .'</h3><hr />';
					unset($_POST['template']);
					unset($_POST['templatedata']);
				}
			}
		}

		if(!isset($_POST['templatedata']) && !isset($_POST['template'])) {
			$tpl_page .= '<form method="post" action="?action=templates">
			<label for="template">' ._gettext('Template'). ':</label>
			<select name="template" id="template">';
			foreach($files as $template) {
				$tpl_page .='<option name="'. $template .'">'. $template . '</option>';
			}
			$tpl_page .= '</select>';
		}

			if(!isset($_POST['templatedata']) && isset($_POST['template'])) {
			$file = basename($_POST['template']);
			if (in_array($file, $files)) {
				if(file_exists(KU_TEMPLATEDIR . '/'. $file)) {
								$tpl_page .= '<form method="post" action="?action=templates">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<input type="hidden" name="template" value="'. $file .'" />
					<textarea wrap=off rows=40 cols=100 name="templatedata">'. htmlspecialchars(file_get_contents(KU_TEMPLATEDIR . '/'. $file)) . '</textarea>
					<label for="rebuild">'. _gettext('Rebuild HTML after edit?') .'</label>
					<input type="checkbox" name="rebuild" /><br /><br />
					<div class="desc">'. _gettext('Visit <a href="http://wiki.dwoo.org/">http://wiki.dwoo.org/</a> for syntax information.') . '</div>
					<div class="desc">'. sprintf(_gettext('To access Kusaba variables, use {%%KU_VARNAME}, for example {%%KU_BOARDSPATH} would be replaced with %s'), KU_BOARDSPATH) . '</div>
					<div class="desc">'. _gettext('Enclose text in {t}{/t} blocks to allow them to be translated for different languages.') . '</div><br /><br />';
				}
			}
				}

		$tpl_page .= '<input type="submit" value="' ._gettext('Edit') . '" /></form>';
	}

	/* Display disk space used per board, and finally total in a large table */
	function spaceused() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Disk space used') . '</h2><br />';
		$spaceused_res = 0;
		$spaceused_src = 0;
		$spaceused_thumb = 0;
		$spaceused_total = 0;
		$files_res = 0;
		$files_src = 0;
		$files_thumb = 0;
		$files_total = 0;
		$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('Board') .'</th><th>'. _gettext('Area') .'</th><th>'. _gettext('Files') .'</th><th>'. _gettext('Space Used') .'</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results as $line) {
			list($spaceused_board_res, $files_board_res) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/res');
			list($spaceused_board_src, $files_board_src) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/src');
			list($spaceused_board_thumb, $files_board_thumb) = recursive_directory_size(KU_BOARDSDIR . $line['name'] . '/thumb');

			$spaceused_board_total = $spaceused_board_res + $spaceused_board_src + $spaceused_board_thumb;
			$files_board_total = $files_board_res + $files_board_src + $files_board_thumb;

			$spaceused_res += $spaceused_board_res;
			$files_res += $files_board_res;

			$spaceused_src += $spaceused_board_src;
			$files_src += $files_board_src;

			$spaceused_thumb += $spaceused_board_thumb;
			$files_thumb += $files_board_thumb;

			$spaceused_total += $spaceused_board_total;
			$files_total += $files_board_total;

			$tpl_page .= '<tr><td rowspan="4">/'.$line['name'].'/</td><td>res/</td><td>'. number_format($files_board_res) . '</td><td>'. ConvertBytes($spaceused_board_res) . '</td></tr>';
			$tpl_page .= '<tr><td>src/</td><td>'. number_format($files_board_src) . '</td><td>'. ConvertBytes($spaceused_board_src) . '</td></tr>';
			$tpl_page .= '<tr><td>thumb/</td><td>'. number_format($files_board_thumb) . '</td><td>'. ConvertBytes($spaceused_board_thumb) . '</td></tr>';
			$tpl_page .= '<tr><td><strong>'. _gettext('Total') .'</strong></td><td>'. number_format($files_board_total) . '</td><td>'. ConvertBytes($spaceused_board_total) . '</td></tr>';
		}
		$tpl_page .= '<tr><td rowspan="4"><strong>'. _gettext('All boards') .'</strong></td><td>res/</td><td>'. number_format($files_res) . '</td><td>'. ConvertBytes($spaceused_res) . '</td></tr>';
		$tpl_page .= '<tr><td>src/</td><td>'. number_format($files_src) . '</td><td>'. ConvertBytes($spaceused_src) . '</td></tr>';
		$tpl_page .= '<tr><td>thumb/</td><td>'. number_format($files_thumb) . '</td><td>'. ConvertBytes($spaceused_thumb) . '</td></tr>';
		$tpl_page .= '<tr><td><strong>'. _gettext('Total') .'</strong></td><td>'. number_format($files_total) . '</td><td>'. ConvertBytes($spaceused_total) . '</td></tr>';
		$tpl_page .= '</table>';
		management_addlogentry(_gettext('Viewed disk space used'), 0);
	}

	function staff() { //183 lines
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>' ._gettext('Staff'). '</h2><br />';
		if (isset($_GET['add']) && !empty($_POST['username']) && !empty($_POST['password'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" .KU_DBPREFIX. "staff` WHERE `username` = " .$tc_db->qstr($_POST['username']));
			if (count($results) == 0) {
				if ($_POST['type'] <= 9 && $_POST['type'] >= 0) {
          			$this->CheckToken($_POST['token']);
					$salt = $this->CreateSalt();
					$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" .KU_DBPREFIX. "staff` ( `username` , `password` , `salt` , `type` , `addedon` ) VALUES (" .$tc_db->qstr($_POST['username']). " , '" .md5($_POST['password'] . $salt). "' , '" .$salt. "' , '" .$_POST['type']. "' , '" .(time() + KU_ADDTIME). "' )");
					$log = _gettext('Added'). ' ';
					switch ($_POST['type']) {
						case 9:
							$log .= _gettext('claire_user');
							break;
						case 1:
							$log .= _gettext('Administrator');
							break;
						case 2:
							$log .= _gettext('Moderator');
							break;
						case 3:
							$log .= _gettext('Board Owner');
							break;
					}
					$log .= ' '. $_POST['username'];
					management_addlogentry($log, 6);
					$tpl_page .= _gettext('Staff member successfully added.');
				} else {
					exitWithErrorPage('Invalid type');
				}
			} else {
				$tpl_page .= _gettext('A staff member with that ID already exists.');
			}
		} elseif (isset($_GET['del']) && $_GET['del'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['del']) . "");
			if (count($results) > 0) {
				$username = $results[0]['username'];
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['del']) . "");
				$tpl_page .= _gettext('Staff successfully deleted') . '<hr />';
				management_addlogentry(_gettext('Deleted staff member') . ': '. $username, 6);
			} else {
				$tpl_page .= _gettext('Invalid staff ID.');
			}
		} elseif (isset($_GET['edit']) && $_GET['edit'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = " . $tc_db->qstr($_GET['edit']) . "");
			if (count($results) > 0) {
				if (isset($_POST['submitting'])) {
          			$this->CheckToken($_POST['token']);
					$username = $results[0]['username'];
					$type	= $results[0]['type'];
					$boards	= array();
					if (isset($_POST['modsallboards'])) {
						$newboards = array('allboards');
					} else {
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY name FROM `" . KU_DBPREFIX . "boards`");
						foreach ($results as $line) {
							$boards = array_merge($boards, array($line['name']));
						}
						$changed_boards = array();
						$newboards = array();
						while (list($postkey, $postvalue) = each($_POST)) {
							if (substr($postkey, 0, 8) == "moderate") {
								$changed_boards = array_merge($changed_boards, array(substr($postkey, 8)));
							}
						}
						while (list(, $thisboard_name) = each($boards)) {
							if (in_array($thisboard_name, $changed_boards)) {
								$newboards = array_merge($newboards, array($thisboard_name));
							}
						}
					}
					$logentry = _gettext('Updated staff member') . ' - ';
					if ($_POST['type'] == '1') {
						$logentry .= _gettext('Administrator');
					} elseif ($_POST['type'] == '2') {
						$logentry .= _gettext('Moderator');
					} elseif ($_POST['type'] == '9') {
						$logentry .= _gettext('claire_user');
					} elseif ($_POST['type'] == '3') {
						$logentry .= _gettext('Board Owner');
					} else {
						exitWithErrorPage('Something went wrong.');
					}
					$logentry .= ': '. $username;
					if ($_POST['type'] != '1') {
						$logentry .= ' - '. _gettext('Moderates') . ': ';
						if (isset($_POST['modsallboards'])) {
							$logentry .= strtolower(_gettext('All boards'));
						} else {
							$logentry .= '/'. implode('/, /', $newboards) . '/';
						}
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = " . $tc_db->qstr(implode('|', $newboards)) . " , `type` = " .$tc_db->qstr($_POST['type']). " WHERE `id` = " . $tc_db->qstr($_GET['edit']) . "");
					management_addlogentry($logentry, 6);
					$tpl_page .= _gettext('Staff successfully updated') . '<hr />';
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `id` = '" . $_GET['edit'] . "'");
				$username = $results[0]['username'];
				$type	= $results[0]['type'];
				$boards	= explode('|', $results[0]['boards']);

				$tpl_page .= '<form action="manage_page.php?action=staff&edit=' .$_GET['edit']. '" method="post">
              <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
							<label for="username">' ._gettext('Username'). ':</label>
							<input type="text" id="username" name="username" value="' .$username. '" disabled="disabled" /><br />
							<label for="type">' ._gettext('Type'). ':</label>
							<select id="type" name="type">';
				$tpl_page .= ($type==1) ? '<option value="1" selected="selected">' ._gettext('Administrator'). '</option>' : '<option value="1">' ._gettext('Administrator'). '</option>';
				$tpl_page .= ($type==2) ? '<option value="2" selected="selected">' ._gettext('Moderator'). '</option>' : '<option value="2">' ._gettext('Moderator'). '</option>';
				$tpl_page .= ($type==0) ? '<option value="9" selected="selected">' ._gettext('claire_user'). '</option>' : '<option value="0">' ._gettext('Janitor'). '</option>';
				$tpl_page .= ($type==3) ? '<option value="3" selected="selected">' ._gettext('Board Owner'). '</option>' : '<option value="3">' ._gettext('Board Owner'). '</option>';
				$tpl_page .= '</select><br /><br />';

				$tpl_page .= _gettext('Moderates') . '<br />
							<label for="modsallboards"><strong>' ._gettext('All boards'). '</strong></label>'. "\n";
				$tpl_page .= ($boards==array('allboards')) ? '<input type="checkbox" name="modsallboards" checked="checked" />' : '<input type="checkbox" name="modsallboards" />';
				$tpl_page .= '<br />' ._gettext('or'). '<br />';
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
				foreach ($results as $line) {
					$tpl_page .= '<label for="moderate'. $line['name'] . '">'. $line['name'] . '</label><input type="checkbox" name="moderate'. $line['name'] . '" ';
					if (in_array($line['name'], $boards)) {
						$tpl_page .= 'checked="checked" ';
					}
					$tpl_page .= '/><br />';
				}
				$tpl_page .= '<input type="submit" value="'. _gettext('Modify staff member') . '" name="submitting" />
							</form><br />';
			}
		}

		$tpl_page .= '<form action="manage_page.php?action=staff&add" method="post">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<label for="username">' ._gettext('Username'). ':</label>
					<input type="text" id="username" name="username" /><br />
					<label for="password">' ._gettext('Password'). ':</label>
					<input type="text" id="password" name="password" /><br />
					<label for="type">' ._gettext('Type'). ':</label>
					<select id="type" name="type">
						<option value="1">' ._gettext('Administrator'). '</option>
						<option value="2">' ._gettext('Moderator'). '</option>
						<option value="9">' ._gettext('claire_user'). '</option>
						<option value="3">' ._gettext('Board Owner'). '</option>
					</select><br />

					<input type="submit" value="' ._gettext('Add staff member'). '" />
					</form>
					<hr /><br />';

		$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('Username') . '</th><th>'. _gettext('Added on') . '</th><th>'. _gettext('Last active') . '</th><th>'. _gettext('Moderating boards') . '</th><th>&nbsp;</th></tr>'. "\n";
		$i = 1;
		while($i <= 4) {
			if ($i == 1) {
				$stafftype = 'Administrator';
				$numtype = 1;
			} elseif ($i == 2) {
				$stafftype = 'Moderator';
				$numtype = 2;
			} elseif ($i == 4) {
				$stafftype = 'claire_user';
				$numtype = 9;
			} elseif ($i == 3) {
				$stafftype = 'Board Owner';
				$numtype = 3;
			}
			$tpl_page .= '<tr><td align="center" colspan="5"><font size="+1"><strong>'. _gettext($stafftype) . '</strong></font></td></tr>'. "\n";
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "staff` WHERE `type` = '" .$numtype. "' ORDER BY `username` ASC");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<tr><td>' .$line['username']. '</td><td>' .date("y/m/d(D)H:i", $line['addedon']). '</td><td>';
					if ($line['lastactive'] == 0) {
						$tpl_page .= _gettext('Never');
					} elseif (((time() + KU_ADDTIME) - $line['lastactive']) > 300) {
						$tpl_page .= timeDiff($line['lastactive'], false);
					} else {
						$tpl_page .= _gettext('Online now');
					}
					$tpl_page .= '</td><td>';
					if ($line['boards'] != '' || $line['type'] == 1) {
						if ($line['boards'] == 'allboards' || $line['type'] == 1) {
							$tpl_page .=  _gettext('All boards') ;
						} else {
							$tpl_page .= '<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>';
						}
					} else {
						$tpl_page .= _gettext('No boards');
					}
					$tpl_page .= '</td><td>[<a href="?action=staff&edit='. $line['id'] . '">'. _gettext('Edit') . '</a>] [<a href="?action=staff&del='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>'. "\n";
				}
			} else {
				$tpl_page .= '<tr><td colspan="5">'. _gettext('None') . '</td></tr>'. "\n";
			}
			$i++;
		}
		$tpl_page .= '</table>';
	}

	/* Display moderators and administrators actions which were logged */
	function modlog() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "modlog` WHERE `timestamp` < '" . ((time() + KU_ADDTIME) - KU_MODLOGDAYS * 86400) . "'");

		$tpl_page .= '<h2>'. ('ModLog') . '</h2><br />
		<table cellspacing="2" cellpadding="1" border="1" width="100%"><tr><th>'. _gettext('Time') .'</th><th>'. _gettext('User') .'</th><th width="100%">'. _gettext('Action') .'</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "modlog` ORDER BY `timestamp` DESC");
		foreach ($results as $line) {
			$tpl_page .= "<tr><td>" . date("y/m/d(D)H:i:s", $line['timestamp']) . "</td><td>" . $line['user'] . "</td><td>" . $line['entry'] . "</td></tr>";
		}
		$tpl_page .= '</table>';
	}

	function proxyban() {
		global $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Ban proxy list') . '</h2><br />';
		if (isset($_FILES['imagefile'])) {
			$bans_class = new Bans;
			$ips = 0;
			$successful = 0;
			$proxies = file($_FILES['imagefile']['tmp_name']);
			foreach($proxies as $proxy) {
				if (preg_match('/.[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+.*/', $proxy)) {
					$proxy = trim($proxy);
					$ips++;
					if ($bans_class->BanUser(preg_replace('/:.*/', '', $proxy), 'SERVER', 1, 0, '', 'IP from proxylist automatically banned', '', 0, 0, 1, true)) {
						$successful++;
					}
				}
			}
			management_addlogentry(sprintf(_gettext('Banned %d IP addresses using an IP address list.'), $successful), 8);
			$tpl_page .= $successful . ' of '. $ips . ' IP addresses banned.';
		} else {
			$tpl_page .= '<form id="postform" action="'. KU_CGIPATH . '/manage_page.php?action=proxyban" method="post" enctype="multipart/form-data"> '. _gettext('Proxy list') .'<input type="file" name="imagefile" size="35" accesskey="f" /><br />
			<input type="submit" value="'. _gettext('Submit') .'" />
			<br />'. _gettext('The proxy list is assumed to be in plaintext *.*.*.*:port or *.*.*.* format, one IP per line.') .'<br /><br /><hr />';
		}
	}

	function sql() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('SQL query') . '</h2><br />';
		if (isset($_POST['query'])) {
      $this->CheckToken($_POST['token']);
			$tpl_page .= '<hr />';
			$result = $tc_db->Execute($_POST['query']);
			if ($result) {
				$tpl_page .= _gettext('Query executed successfully');
				$tpl_page .= '<br>Результат:<pre>'."\n";
				$tpl_page .= print_r($result, true);
				$tpl_page .= '</pre>';
			} else {
				$tpl_page .= 'Error: '. $tc_db->ErrorMsg();
			}
			$tpl_page .= '<hr />';
			management_addlogentry(_gettext('Inserted SQL'), 0);
		}
		$tpl_page .= '<form method="post" action="?action=sql">
    <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<textarea name="query" rows="20" cols="60"></textarea>
		<br /><br />
		<input type="submit" value="'. _gettext('Inject') . '" />

		</form>';
	}

	function cleanup() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>'. _gettext('Cleanup') . '</h2><br />';

		if (isset($_POST['run'])) {
			$tpl_page .= '<hr />'. _gettext('Deleting non-deleted replies which belong to deleted threads.') .'<hr />';
			$this->delorphanreplies(true);
			$tpl_page .= '<hr />'. _gettext('Deleting unused images.') .'<hr />';
			$this->delunusedimages(true);
			$tpl_page .= '<hr />'. _gettext('Removing posts deleted more than one week ago from the database.') .'<hr />';
			$results = $tc_db->GetAll("SELECT `name`, `type`, `id` FROM `" . KU_DBPREFIX . "boards`");
			foreach ($results AS $line) {
				if ($line['type'] != 1) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line['id'] . " AND `IS_DELETED` = 1 AND `deleted_timestamp` < " . ((time() + KU_ADDTIME) - 604800) . "");
				}
			}
			$tpl_page .= _gettext('Optimizing all tables in database.') .'<hr />';
			if (KU_DBTYPE == 'mysql' || KU_DBTYPE == 'mysqli') {
				$results = $tc_db->GetAll("SHOW TABLES");
							foreach ($results AS $line) {
									$tc_db->Execute("OPTIMIZE TABLE `" . $line[0] . "`");
							}
			}
			if (KU_DBTYPE == 'postgres7' || KU_DBTYPE == 'postgres8' || KU_DBTYPE == 'postgres') {
								$results = $tc_db->GetAll("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE'");
								foreach ($results AS $line) {
										$tc_db->Execute("VACUUM ANALYZE `" . $line[0] . "`");
								}
			}
			$tpl_page .= _gettext('Cleanup finished.');
			management_addlogentry(_gettext('Ran cleanup'), 2);
		} else {
			$tpl_page .= '<form action="manage_page.php?action=cleanup" method="post">'. "\n" .
						'	<input name="run" id="run" type="submit" value="'. _gettext('Run Cleanup') . '" />'. "\n" .
						'</form>';
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Boards Administration Pages
	* +------------------------------------------------------------------------------+
	*/

	function adddelboard() {
		global $tc_db, $tpl_page, $board_class;
		$this->AdministratorsOnly();

		if (isset($_POST['directory'])) {
      $this->CheckToken($_POST['token']);
			if (isset($_POST['add'])) {
				$tpl_page .= $this->addBoard($_POST['directory'], $_POST['desc']);
			} elseif (isset($_POST['del'])) {
				if (isset($_POST['confirmation'])) {
					$tpl_page .= $this->delBoard($_POST['directory'], $_POST['confirmation']);
				} else {
					$tpl_page .= $this->delBoard($_POST['directory']);
				}
			}
		}
		$tpl_page .= '<h2>'. _gettext('Add board') . '</h2><br />
		<form action="manage_page.php?action=adddelboard" method="post">
    	<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<input type="hidden" name="add" id="add" value="add" />
		<label for="directory">'. _gettext('Directory') . ':</label>
		<input type="text" name="directory" id="directory" />
		<div class="desc">'. _gettext('The directory of the board.') . ' <strong>'. _gettext('Only put in the letter(s) of the board directory, no slashes!') . '</strong></div><br />

		<label for="desc">'. _gettext('Description') . ':</label>
		<input type="text" name="desc" id="desc" />
		<div class="desc">'. _gettext('The name of the board.') . '</div><br />

		<label for="firstpostid">'. _gettext('First Post ID') . ':</label>
		<input type="text" name="firstpostid" id="firstpostid" value="1" />
		<div class="desc">'. _gettext('The first post of this board will recieve this ID.') . '</div><br />

		<input type="submit" value="'. _gettext('Add Board') .'" />

		</form><br /><hr />

		<h2>'. _gettext('Delete board') .'</h2><br />

		<form action="manage_page.php?action=adddelboard" method="post">
    <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<input type="hidden" name="del" id="del" value="del" />
		<label for="directory">'. _gettext('Directory') .':</label>' .
		$this->MakeBoardListDropdown('directory', $this->BoardList($_SESSION['manageusername'])) .
		'<br />

		<input type="submit" value="'. _gettext('Delete board') .'" />

		</form>';
	}

	function adddelboard_mod() {
		global $tc_db, $tpl_page, $board_class;
		$this->BoardOwnersOnly();

		if (isset($_POST['directory'])) {
      		$this->CheckToken($_POST['token']);
			if (isset($_POST['add'])) {
				$tpl_page .= $this->addBoard_mod($_POST['directory'], $_POST['desc']);
			} elseif (isset($_POST['del'])) {
				if (isset($_POST['confirmation'])) {
					$tpl_page .= $this->delBoard_mod($_POST['directory'], $_POST['confirmation']);
				} else {
					$tpl_page .= $this->delBoard_mod($_POST['directory']);
				}
			}
		}

		$tpl_page .= '<h2>'. _gettext('Add board') . '</h2><br />
		<form action="manage_page.php?action=adddelboard_mod" method="post">
    	<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<input type="hidden" name="add" id="add" value="add" />
		<label for="directory">'. _gettext('Directory') . ': /_</label>
		<input type="text" name="directory" id="directory" />
		<div class="desc">'. _gettext('The directory of the board.') . ' <strong>'. _gettext('Only put in the letter(s) of the board directory, no slashes!') . '</strong></div><br />

		<label for="desc">'. _gettext('Description') . ':</label>
		<input type="text" name="desc" id="desc" />
		<div class="desc">'. _gettext('The name of the board.') . '</div><br />

		<input type="submit" value="'. _gettext('Add Board') .'" />

		</form><br /><hr />

		<h2>'. _gettext('Delete board') .'</h2><br />

		<form action="manage_page.php?action=adddelboard_mod" method="post">
    	<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
		<input type="hidden" name="del" id="del" value="del" />
		<label for="directory">'. _gettext('Directory') .':</label>' .
		$this->MakeBoardListDropdown('directory', $this->BoardList($_SESSION['manageusername'])) .
		'<br />

		<input type="submit" value="'. _gettext('Delete board') .'" />

		</form>';
	}

	function addBoard($dir, $desc) {
		global $tc_db;
		$this->AdministratorsOnly();

		$desc = htmlspecialchars($desc);

		$output = '';
		$output .= '<h2>'. _gettext('Add Results') .'</h2><br />';
		$dir = cleanBoardName($dir);
		if ($dir != '' && $desc != '') {
			if (strtolower($dir) != 'allboards') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
				if (count($results) == 0) {
					if (mkdir(KU_BOARDSDIR . $dir, 0777) && mkdir(KU_BOARDSDIR . $dir . '/res', 0777) && mkdir(KU_BOARDSDIR . $dir . '/src', 0777) && mkdir(KU_BOARDSDIR . $dir . '/thumb', 0777) && mkdir(KU_BOARDSDIR . $dir . '/tmp', 0777) && mkdir(KU_BOARDSDIR . $dir . '/tmp/thumb', 0777)) {
						file_put_contents(KU_BOARDSDIR . $dir . '/.htaccess', 'DirectoryIndex '. 'board.html' . '');
						file_put_contents(KU_BOARDSDIR . $dir . '/src/.htaccess', 'AddType text/plain .ASM .C .CPP .CSS .JAVA .JS .LSP .PHP .PL .PY .RAR .SCM .TXT'. "\n" . 'SetHandler default-handler');
						if ($_POST['firstpostid'] < 1) {
							$_POST['firstpostid'] = 1;
						}
						$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "boards` ( `name` , `desc` , `createdon`, `start`, `image`, `includeheader` ) VALUES ( " . $tc_db->qstr($dir) . " , " . $tc_db->qstr($desc) . " , '" . (time() + KU_ADDTIME) . "', " . $_POST['firstpostid'] . ", '', '' )");
						$boardid = $tc_db->Insert_Id();
						$filetypes = $tc_db->GetAll("SELECT " . KU_DBPREFIX . "filetypes.id FROM " . KU_DBPREFIX . "filetypes WHERE " . KU_DBPREFIX . "filetypes.filetype = 'JPG' OR " . KU_DBPREFIX . "filetypes.filetype = 'GIF' OR " . KU_DBPREFIX . "filetypes.filetype = 'PNG';");
						foreach ($filetypes AS $filetype) {
							$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid` , `typeid` ) VALUES ( " . $boardid . " , " . $filetype['id'] . " );");
						}
						$output .= _gettext('Board successfully added.') . '<br /><br /><a href="'. KU_BOARDSPATH . '/'. $dir . '/">/'. $dir . '/</a>!<br />';
						$output .= '<form action="?action=boardopts" method="post"><input type="hidden" name="board" value="'. $dir . '" /><input type="submit" style="border: 1px solid; background: none; text-align: center;" value="'. _gettext('Click to edit board options') .'" /><br /><hr /></form>';
						management_addlogentry(_gettext('Added board') . ': /'. $dir . '/', 3);
					} else {
						$output .= '<br />'. _gettext('Unable to create directories.');
					}
				} else {
					$output .= _gettext('A board with that name already exists.');
				}
			} else {
				$output .= _gettext('That name is for internal use. Please pick another.');
			}
		} else {
			$output .= _gettext('Please fill in all required fields.');
		}
		return $output;
	}

	function addBoard_mod($dir, $desc) {
		global $tc_db;
		$this->BoardOwnersOnly();

		$desc = htmlspecialchars($desc);

		$output = '';
		$output .= '<h2>'. _gettext('Add Results') .'</h2><br />';
		$dir = '_'.cleanBoardName($dir);
		if ($dir != '' && $desc != '') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
			if (count($results) == 0) {
				$moderating = array_filter(explode('|', $tc_db->GetOne("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $_SESSION['manageusername'] . "'")));
				$existingboards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` from `" . KU_DBPREFIX . "boards`");
				foreach($existingboards as &$exb) {
					$xba[] = $exb['name'];
				}
				$moderating = array_intersect($moderating, $xba);
				unset($exb);
				if (mkdir(KU_BOARDSDIR . $dir, 0777) && mkdir(KU_BOARDSDIR . $dir . '/res', 0777) && mkdir(KU_BOARDSDIR . $dir . '/src', 0777) && mkdir(KU_BOARDSDIR . $dir . '/thumb', 0777) && mkdir(KU_BOARDSDIR . $dir . '/tmp', 0777) && mkdir(KU_BOARDSDIR . $dir . '/tmp/thumb', 0777)) {
					/* Add mod rights */
					$moderating[] = $dir;
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = " . $tc_db->qstr(implode('|',$moderating)) . " WHERE `username` = '" . $_SESSION['manageusername'] . "'");
					file_put_contents(KU_BOARDSDIR . $dir . '/.htaccess', 'DirectoryIndex '. 'board.html' . '');
					file_put_contents(KU_BOARDSDIR . $dir . '/src/.htaccess', 'AddType text/plain .ASM .C .CPP .CSS .JAVA .JS .LSP .PHP .PL .PY .RAR .SCM .TXT'. "\n" . 'SetHandler default-handler');
					$_POST['firstpostid'] = 1;
					$sect20 = $tc_db->GetOne('SELECT `id` FROM `'. KU_DBPREFIX .'sections` WHERE `abbreviation`="20"');
					$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "boards` ( `name` , `desc` , `createdon`, `start`, `image`, `includeheader`,`section` ) VALUES ( " . $tc_db->qstr($dir) . " , " . $tc_db->qstr($desc) . " , '" . (time() + KU_ADDTIME) . "', " . $_POST['firstpostid'] . ", '', '', ".$sect20." )");
					$boardid = $tc_db->Insert_Id();
					$filetypes = $tc_db->GetAll("SELECT " . KU_DBPREFIX . "filetypes.id FROM " . KU_DBPREFIX . "filetypes WHERE " . KU_DBPREFIX . "filetypes.filetype = 'JPG' OR " . KU_DBPREFIX . "filetypes.filetype = 'GIF' OR " . KU_DBPREFIX . "filetypes.filetype = 'PNG';");
					foreach ($filetypes AS $filetype) {
						$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid` , `typeid` ) VALUES ( " . $boardid . " , " . $filetype['id'] . " );");
					}

					$output .= _gettext('Board successfully added.') . '<br /><br /><a href="'. KU_BOARDSPATH . '/'. $dir . '/">/'. $dir . '/</a>!<br />';
					$output .= '<form action="?action=boardopts_mod" method="post"><input type="hidden" name="board" value="'. $dir . '" /><input type="submit" style="border: 1px solid; background: none; text-align: center;" value="'. _gettext('Click to edit board options') .'" /><br /><hr /></form>';
					management_addlogentry(_gettext('Added board') . ': /'. $dir . '/', 3);
				}
				else {
					$output .= '<br />'. _gettext('Unable to create directories.');
				}
			} else {
				$output .= _gettext('A board with that name already exists.');
			}
		} else {
			$output .= _gettext('Please fill in all required fields.');
		}
		return $output;
	}

	function delboard($dir, $confirm = '') {
		global $tc_db;
		$this->AdministratorsOnly();

		$output = '';
		$output .= '<h2>'. _gettext('Delete Results') .'</h2><br />';
		if (!empty($dir)) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
			foreach ($results as $line) {
				$board_id = $line['id'];
				$board_dir = $line['name'];
			}
			if (count($results) > 0) {
				if (!empty($confirm)) {
					if (removeBoard($board_dir)) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "boards` WHERE `id` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $board_id . "'");

						/*     Remove rights for all mods     */
						$staffresults = $tc_db->GetAll("SELECT `id`, `boards` FROM `" .KU_DBPREFIX. "staff` WHERE `boards` != 'allboards'");
						foreach($staffresults as $moder) {
							$set = explode('|', $moder['boards']);
							$moderating = array_diff(array_filter($set), array($dir));
							if(count($set) != count($moderating))
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = " . $tc_db->qstr(implode('|',$moderating)) . " WHERE `id` = '" . $moder['id'] . "'");
						}
						/*     Remove board entries from wordfilter     */
						$wfresults = $tc_db->GetAll("SELECT `id`, `boards` FROM `" .KU_DBPREFIX. "wordfilter`");
						if(count($wfresults) > 0) {
							foreach($wfresults as $line) {
								$set = explode('|', $line['boards']);
								$filtered = array_diff(array_filter($set), array($dir));
								if(count($set) != count($filtered))
									$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "wordfilter` SET `boards` = " . $tc_db->qstr(implode('|',$filtered)) . " WHERE `id` = '" . $line['id'] . "'");
							}
						}

						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$output .= _gettext('Board successfully deleted.');
						management_addlogentry(_gettext('Deleted board') .': /'. $dir . '/', 3);
					} else {
						// Error
						$output .= _gettext('Unable to delete board.');
					}
				} else {
					$output .= sprintf(_gettext('Are you absolutely sure you want to delete %s?'),'/'. $board_dir . '/') .
					'<br />
					<form action="manage_page.php?action=adddelboard" method="post">
          			<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<input type="hidden" name="del" id="del" value="del" />
					<input type="hidden" name="directory" id="directory" value="'. $dir . '" />
					<input type="hidden" name="confirmation" id="confirmation" value="yes" />

					<input type="submit" value="'. _gettext('Continue') .'" />

					</form><br />';
				}
			} else {
				$output .= _gettext('A board with that name does not exist.');
			}
		}
		$output .= '<br />';

		return $output;
	}

	function delboard_mod($dir, $confirm = '') {
		global $tc_db, $tpl_page;
		$this->BoardOwnersOnly();

		$output = '';
		$output .= '<h2>'. _gettext('Delete Results') .'</h2><br />';
		if (!empty($dir)) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($dir) . "");
			foreach ($results as $line) {
				$board_id = $line['id'];
				$board_dir = $line['name'];
			}
			if (count($results) > 0) {
				if (!empty($confirm)) {
					if (!$this->CurrentUserIsModeratorOfBoard($dir, $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					if (removeBoard($board_dir)) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "boards` WHERE `id` = '" . $board_id . "'");
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $board_id . "'");

						/*     Remove rights for all mods     */
						$staffresults = $tc_db->GetAll("SELECT `id`, `boards` FROM `" .KU_DBPREFIX. "staff` WHERE `boards` != 'allboards'");
						foreach($staffresults as $moder) {
							$set = explode('|', $moder['boards']);
							$moderating = array_diff(array_filter($set), array($dir));
							if(count($set) != count($moderating))
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "staff` SET `boards` = " . $tc_db->qstr(implode('|',$moderating)) . " WHERE `id` = '" . $moder['id'] . "'");
						}
						/*     Remove board entries from wordfilter     */
						$wfresults = $tc_db->GetAll("SELECT `id`, `boards` FROM `" .KU_DBPREFIX. "wordfilter`");
						if(count($wfresults) > 0) {
							foreach($wfresults as $line) {
								$set = explode('|', $line['boards']);
								$filtered = array_diff(array_filter($set), array($dir));
								if(count($set) != count($filtered))
									$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "wordfilter` SET `boards` = " . $tc_db->qstr(implode('|',$filtered)) . " WHERE `id` = '" . $line['id'] . "'");
							}
						}

						unset($moder);
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$output .= _gettext('Board successfully deleted.');
						management_addlogentry(_gettext('Deleted board') .': /'. $dir . '/', 3);
					} else {
						// Error
						$output .= _gettext('Unable to delete board.');
					}
				} else {
					$output .= sprintf(_gettext('Are you absolutely sure you want to delete %s?'),'/'. $board_dir . '/') .
					'<br />
					<form action="manage_page.php?action=adddelboard_mod" method="post">
          			<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<input type="hidden" name="del" id="del" value="del" />
					<input type="hidden" name="directory" id="directory" value="'. $dir . '" />
					<input type="hidden" name="confirmation" id="confirmation" value="yes" />

					<input type="submit" value="'. _gettext('Continue') .'" />

					</form><br />';
				}
			} else {
				$output .= _gettext('A board with that name does not exist.');
			}
		}
		$output .= '<br />';

		return $output;
	}

	/* Replace words in posts with something else */
	function wordfilter() {
		global $tc_db, $tpl_page;
		$this->BoardOwnersOnly();

		$tpl_page .= '<h2>'. _gettext('Wordfilter') . '</h2><br />';

		$boardlist = $this->BoardList($_SESSION['manageusername']);
		// $bl_redux = [];
			foreach($boardlist as $brd) {
				$bl_redux[]= $brd['name'];
			}

		if (isset($_POST['word'])) {
      		$this->CheckToken($_POST['token']);
			if ($_POST['word'] != '' && $_POST['replacedby'] != '' && is_array($_POST['wordfilter'])) {
				// $boards_toinsert = [];
				foreach ($_POST['wordfilter'] as $board) {
					if(in_array($board, $bl_redux)) $boards_toinsert[] = $board;
				}
				if(count($boards_toinsert) > 0) {
					$wf_all = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter`");
					// $wf_mywords = [];
					foreach($wf_all as $line) {
						$belong = true;
						foreach(explode('|', $line['boards']) as $lb) {
							if(!in_array($lb, $bl_redux)) $belong = false;
						}
						if($belong) $wf_mywords[]= $line['word'];
					}
					if(!in_array($_POST['word'], $wf_mywords)) {
						$is_regex = (isset($_POST['regex'])) ? '1' : '0';
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "wordfilter` ( `word` , `replacedby` , `boards` , `time` , `regex` ) VALUES ( " . $tc_db->qstr($_POST['word']) . " , " . $tc_db->qstr($_POST['replacedby']) . " , " . $tc_db->qstr(implode('|', $boards_toinsert)) . " , '" . (time() + KU_ADDTIME) . "' , '" . $is_regex . "' )");
						$tpl_page .= _gettext('Word successfully added.');
					}
					else {
						$tpl_page .= _gettext('That word already exists.');
					}
				}
				else {
					$tpl_page .= _gettext('No boards belong to you.');
				}
			} else {
				$tpl_page .= _gettext('Please fill in all required fields.');
			}
			$tpl_page .= '<hr />';
		} elseif (isset($_GET['delword'])) {
			if ($_GET['delword'] > 0) {
				$brds = $tc_db->GetOne("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['delword']) . "");
				$brds = explode("|", $results);
				if(count($results) > 0) {
					if(array_in_array($brds, $bl_redux)) {
						$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['delword']) . "");
						$tpl_page .= _gettext('Word successfully removed.');
						management_addlogentry(_gettext('Removed word from wordfilter') . ': '. $del_word, 11);
					}
					else {

					}

				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr />';
			}
		} elseif (isset($_GET['editword'])) {
			if ($_GET['editword'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");
				if (count($results) > 0) {
					if (!isset($_POST['replacedby'])) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="manage_page.php?action=wordfilter&editword='.$_GET['editword'].'" method="post">
              				<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
							<label for="word">'. _gettext('Word') .':</label>
							<input type="text" name="word" value="'.$line['word'].'" disabled /><br />

							<label for="replacedby">'. _gettext('Replace by') .':</label>
							<input type="text" name="replacedby" value="'.$line['replacedby'].'" /><br />

							<label for="regex">'. _gettext('Regular expression') .':</label>
							<input type="checkbox" name="regex"';
							if ($line['regex'] == '1') {
								$tpl_page .= ' checked';
							}
							$tpl_page .= ' /><br />

							<label>'. _gettext('Boards') .':</label><br />';

							$array_boards = array();
							$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");
							foreach ($resultsboard as $lineboard) {
								$array_boards = array_merge($array_boards, array($lineboard['name']));
							}
							foreach ($array_boards as $this_board_name) {
								$tpl_page .= '<label for="wordfilter[]">'. $this_board_name . '</label><input type="checkbox" name="wordfilter[]" value="'.$this_board_name.'"';
								if (in_array($this_board_name, explode("|", $line['boards'])) && explode("|", $line['boards']) != '') {
									$tpl_page .= 'checked ';
								}
								$tpl_page .= ' /><br />';
							}
							$tpl_page .= '<br />

							<input type="submit" value="'. _gettext('Edit word') .'" />

							</form>';
						}
					} else {
            $this->CheckToken($_POST['token']);
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter` WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");
						if (count($results) > 0) {
							foreach ($results as $line) {
								$wordfilter_word = $line['word'];
							}
							$wordfilter_boards = array();
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards`");

							if (isset($_POST['wordfilter'])){
								foreach ($_POST['wordfilter'] as $board) {
									$check = $tc_db->GetOne("SELECT `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));
									if (!empty($check)) {
										$wordfilter_boards[] = $board;
									}
								}
							}

							$is_regex = (isset($_POST['regex'])) ? '1' : '0';

							$tc_db->Execute("UPDATE `". KU_DBPREFIX ."wordfilter` SET `replacedby` = " . $tc_db->qstr($_POST['replacedby']) . " , `boards` = " . $tc_db->qstr(implode('|', $wordfilter_boards)) . " , `regex` = '" . $is_regex . "' WHERE `id` = " . $tc_db->qstr($_GET['editword']) . "");

							$tpl_page .= _gettext('Word successfully updated.');
							management_addlogentry(_gettext('Updated word on wordfilter') . ': '. $wordfilter_word, 11);
						} else {
							$tpl_page .= _gettext('Unable to locate that word.');
						}
					}
				} else {
					$tpl_page .= _gettext('That ID does not exist.');
				}
				$tpl_page .= '<hr />';
			}
		} else {
			$tpl_page .= '<form action="manage_page.php?action=wordfilter" method="post">
      		<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<label for="word">'. _gettext('Word') .':</label>
			<input type="text" name="word" /><br />

			<label for="replacedby">'. _gettext('Replace by') .':</label>
			<input type="text" name="replacedby" /><br />

			<label for="regex">'. _gettext('Regular expression') .':</label>
			<input type="checkbox" name="regex" /><br />

			<label>'. _gettext('Boards') .':</label><br />';

			$array_boards = array();
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY name FROM `" . KU_DBPREFIX . "boards`");
			$array_boards = array_merge($array_boards, $resultsboard);
			$tpl_page .= $this->MakeBoardListCheckboxes('wordfilter', $this->BoardList($_SESSION['manageusername'])) .
			'<br />
			<input type="submit" value="'. _gettext('Add word') .'" />
			</form>
			<hr />';
		}
		$tpl_page .= '<br />';

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "wordfilter`");
		if ($results > 0 && count($boardlist) > 0) {

			$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('Word') . '</th><th>'. _gettext('Replacement') . '</th><th>'. _gettext('Boards') . '</th><th>&nbsp;</th></tr>'. "\n";
			foreach($results as $line) {
				$belong = true;
				foreach(explode('|', $line['boards']) as $lb) {
					if(!in_array($lb, $bl_redux)) $belong = false;
				}
				if($belong) {
					$tpl_page .= '<tr><td>'. $line['word'] . '</td><td>'. $line['replacedby'] . '</td><td>'.
						'<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>&nbsp;'.
						'</td><td>[<a href="manage_page.php?action=wordfilter&editword='. $line['id'] . '">'. _gettext('Edit') . '</a>] [<a href="manage_page.php?action=wordfilter&delword='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>'. "\n";
				}
			}
			$tpl_page .= '</table>';
		}
	}

	/* Add or delete Embed Entries */
	function embeds() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		$disptable = true; $formval = 'add'; $title = _gettext('Embed Management');
		if(isset($_GET['act'])) {
			if ($_GET['act'] == 'edit') {
				if (isset($_POST['embeds'])) {
          $this->CheckToken($_POST['token']);
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "embeds` SET `filetype` = " . $tc_db->qstr(trim($_POST['filetype'])) . ", `videourl` = " . $tc_db->qstr(trim($_POST['videourl'])) . ", `name` = " . $tc_db->qstr(trim($_POST['name'])) . ", `width` = " . $tc_db->qstr(trim($_POST['width'])) . ", `height` = " . $tc_db->qstr(trim($_POST['height'])) . ", `code` = " . $tc_db->qstr(trim($_POST['embeds'])) . " WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
					$tpl_page .= '<hr /><h3>'. _gettext('Embed Edited') .'</h3><hr />';
					management_addlogentry(_gettext('Edited an embed'), 9);
				}
				$formval = 'edit&amp;id='. $_GET['id']; $title .= ' - Edit';
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "embeds` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$values = $results[0];
				$disptable = false;
			} elseif ($_GET['act'] == 'del') {
				$results = $tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "embeds` WHERE `id` = " . $tc_db->qstr($_GET['id']) . "");
				$tpl_page .= '<hr /><h3>'. _gettext('Embed deleted') .'</h3><hr />';
				management_addlogentry(_gettext('Deleted an Embed'), 9);
			} elseif ($_GET['act'] == 'add') {
				if (isset($_POST['embeds']) && isset($_POST['name']) && isset($_POST['filetype']) && isset($_POST['videourl'])) {
					if ($_POST['embeds'] != '' && $_POST['width'] != '' && $_POST['height'] != '') {
						$this->CheckToken($_POST['token']);
						$width = $_POST['width'];
						$height = $_POST['height'];

						$tpl_page .= '<hr />';
						if ($_POST['embeds'] != '') {
							$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "embeds` ( `name` , `filetype` , `videourl` , `width` , `height` , `code` ) VALUES ( " . $tc_db->qstr(trim($_POST['name'])) . " , " . $tc_db->qstr(trim($_POST['filetype'])) . " , " . $tc_db->qstr(trim($_POST['videourl'])) . " , " . $tc_db->qstr(trim($width)) . " , " . $tc_db->qstr(trim($height)) . " , " . $tc_db->qstr(trim($_POST['embeds'])) . " )");
							$tpl_page .= '<h3>'. _gettext('Embed successfully added.') . '</h3>';
							management_addlogentry(_gettext('Added an Embed'), 9);
						} else {
							$tpl_page .= _gettext('You must enter code.');
						}
						$tpl_page .= '<hr />';
					}
				}
			}
		}
		$tpl_page .= '<h2>'. $title . '</h2><br />
			<form method="post" action="?action=embeds&amp;act='. $formval . '">
      <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<label for="name">'. _gettext('Site Name') . ':</label>
			<input type="text" id="name" name="name" value="'. (isset($values['name']) ? $values['name'] : ''). '" />
			<div class="desc">'. _gettext('Can not be left blank.') . '</div><br />

			<label for="filetype">'. _gettext('Filetype') . ':</label>
			<input type="text" id="filetype" name="filetype" maxlength="3" value="'. (isset($values['filetype']) ? $values['filetype'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank, or longer than 3 characters') . '</div><br />

			<label for="videourl">'. _gettext('Video URL Start') . ':</label>
			<input type="text" id="videourl" name="videourl" value="'. (isset($values['videourl']) ? $values['videourl'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank. This is what comes before the embed ID. Example: \'http://www.youtube.com/watch?v=\'') . '</div><br />

			<label for="embeds">'. _gettext('Code') . ':</label>
			<textarea id="embeds" name="embeds" rows="25" cols="80">' . (isset($values['code']) ? htmlspecialchars($values['code']) : '') . '</textarea><br />

			<label for="width">'. _gettext('Width') . ':</label>
			<input type="text" id="width" name="width" value="'. (isset($values['width']) ? $values['width'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank.') . '</div><br />

			<label for="height">'. _gettext('Height') . ':</label>
			<input type="text" id="height" name="height" value="'. (isset($values['height']) ? $values['height'] : '') . '" />
			<div class="desc">'. _gettext('Can not be left blank.') . '</div><br />

			<input type="submit" value="'. _gettext('Edit') .'" />
			</form>';
		if ($disptable) {
			$tpl_page .= '<br /><hr /><h1>'. _gettext('Edit/Delete Embeds') .'</h1>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "embeds` ORDER BY `id` ASC");
			if (count($results) > 0) {
				$find = array('<', '>');
				$replace = array ('&lt;', '&gt;');
				$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('Site Name') .'</th><th>'. _gettext('Filetype') .'</th><th>'. _gettext('Video URL Start') .'</th><th>'. _gettext('Width') .'</th><th>'. _gettext('Height') .'</th><th>'. _gettext('Code') .'</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td>'. $line['name'] . '</td><td>'. $line['filetype'] . '</td><td>'. $line['videourl'] . '</td><td>'. $line['width'] . '</td><td>'. $line['height'] . '</td><td>'. str_replace($find, $replace, $line['code']) . '</td><td>[<a href="?action=embeds&amp;act=edit&amp;id='. $line['id'] . '">'. _gettext('Edit') .'</a>] [<a href="?action=embeds&amp;act=del&amp;id='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
				}
				$tpl_page .= '</table>';
			} else {
				$tpl_page .= _gettext('No Embeds yet.');
		}
		}
	}


	function movethread() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Move thread') . '</h2><br />';

		if (isset($_POST['id']) && isset($_POST['board_from']) && isset($_POST['board_to'])) {
      $this->CheckToken($_POST['token']);
			// Get the IDs for the from and to boards
			$board_from_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board_from']) . "");
			$board_to_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board_to']) . "");
			$board_from = $_POST['board_from'];
			$board_to = $_POST['board_to'];
			$id = $tc_db->qstr($_POST['id']);

			if (isset($_POST['mf'])) {
				$image		= $tc_db->GetOne("SELECT `file` FROM " .KU_DBPREFIX. "posts WHERE `boardid` = " .$board_from_id. " AND `id` = " .$id);
				$filetype	= $tc_db->GetOne("SELECT `file_type` FROM " .KU_DBPREFIX. "posts WHERE `boardid` = " .$board_from_id. " AND `id` = " .$id);
				$from_pic	= KU_BOARDSDIR . $board_from . '/src/'. $image . '.'. $filetype;
				$from_thumb	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 's'. '.'. $filetype;
				$from_cat	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 'c'. '.'. $filetype;
				$to_pic	= KU_BOARDSDIR . $board_to . '/src/'. $image . '.'. $filetype;
				$to_thumb	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 's'. '.'. $filetype;
				$to_cat	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 'c'. '.'. $filetype;
				@rename($from_pic, $to_pic);
				@rename($from_thumb, $to_thumb);
				@rename($from_cat, $to_cat);
				@unlink($from_pic);
				@unlink($from_thumb);
				@unlink($from_cat);
			}

			$from_html = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '.html';
			$from_html_50 = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '+50.html';
			$from_html_100 = KU_BOARDSDIR . $board_from . '/res/'. $_POST['id'] . '-100.html';
			@unlink($from_html);
			@unlink($from_html_50);
			@unlink($from_html_100);

			$tc_db->Execute("START TRANSACTION");
			$new_id = $tc_db->GetOne("SELECT COALESCE(MAX(id),0) + 1 FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_to_id);
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `id` = " . $new_id . ", `boardid` = " .$board_to_id. " WHERE `boardid` = " .$board_from_id. " AND `id` = " . $id);
			processPost($new_id, $new_id, $id, $board_from, $board_to, $board_to_id);

			$results = $tc_db->GetAll("SELECT `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " .$board_from_id. " AND `parentid` = " . $id . " ORDER BY `id` ASC");
			foreach ($results as $line) {
				if (isset($_POST['mf'])) {
					$image		= $tc_db->GetOne("SELECT `file` FROM `" .KU_DBPREFIX. "posts` WHERE `boardid` = " .$board_from_id. " AND `id` = " .$line['id']);
					$filetype	= $tc_db->GetOne("SELECT `file_type` FROM `" .KU_DBPREFIX. "posts` WHERE `boardid` = " .$board_from_id. " AND `id` = " .$line['id']);
					$from_pic	= KU_BOARDSDIR . $board_from . '/src/'. $image . '.'. $filetype;
					$from_thumb	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 's'. '.'. $filetype;
					$from_cat	= KU_BOARDSDIR . $board_from . '/thumb/'. $image . 'c'. '.'. $filetype;
					$to_pic	= KU_BOARDSDIR . $board_to . '/src/'. $image . '.'. $filetype;
					$to_thumb	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 's'. '.'. $filetype;
					$to_cat	= KU_BOARDSDIR . $board_to . '/thumb/'. $image . 'c'. '.'. $filetype;
					@rename($from_pic, $to_pic);
					@rename($from_thumb, $to_thumb);
					@rename($from_cat, $to_cat);
					@unlink($from_pic);
					@unlink($from_thumb);
					@unlink($from_cat);
				}
				$insert_id = $tc_db->GetOne("SELECT COALESCE(MAX(id),0) + 1 FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_to_id);
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `id` = " . $insert_id . ", `boardid` = " .$board_to_id. " WHERE `boardid` = " .$board_from_id. " AND `id` = " . $line['id']);
				processPost($insert_id, $new_id, $id, $board_from, $board_to, $board_to_id);
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `parentid` = " . $new_id . " WHERE `boardid` = " . $board_to_id . " AND `id` = " . $insert_id);
			}

			$tc_db->Execute("COMMIT");

			$tpl_page .= _gettext('Move complete.'); // . '<br><h2>Не забудь пересобрать все доски!</h2><br><hr />';
		}

		$tpl_page .= '<form action="?action=movethread" method="post">
    	<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />

		<label for="id">'. _gettext('ID') . ':</label>
		<input type="text" name="id" />
		<br />

		<label for="board_from">'. _gettext('From') . ':</label>' .
		$this->MakeBoardListDropdown('board_from', $this->BoardList($_SESSION['manageusername'])) .
		'<br />

		<label for="board_to">' ._gettext('To') . ':</label>' .
		$this->MakeBoardListDropdown('board_to', $this->BoardList($_SESSION['manageusername'])) .
		'<br />
		<label for="mf">'. _gettext('Move Files') .':</label>
		<input type="checkbox" id="mf" name="mf" /><br />
		<input type="submit" value="'. _gettext('Move thread') . '" />';
	}

	/* Search for posts by IP */
	function ipsearch() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('IP Address Search') .'</h2><br />'. "\n";

		if (isset($_GET['ip']) && !empty($_GET['board'])) {
			if ($_GET['board'] == 'all') {
				$queryextra = "";
			} else {
				$queryextra = "`boardid` IN (" . $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "") . ") AND";
			}

			$results = $tc_db->GetAll("SELECT `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`ip` AS ip, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "posts`.`file` AS file, `" . KU_DBPREFIX . "posts`.`file_type` AS file_type, `" . KU_DBPREFIX . "boards`.`name` AS boardname FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX . "boards` WHERE ".$queryextra." `ipmd5` = '" . md5($_GET['ip']) . "' AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `boardid`");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$tpl_page .= '<table border="1" width="100%">'. "\n" .
					'<tr><th width="10%">'. _gettext('Post Number') .'</th><th width="10%">'. _gettext('File') .'</th><th width="70%">'. _gettext('Message') .'</th><th width=10%">'. _gettext('IP Address') .'</th></tr>'. "\n";

					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];

					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td>'. (($line['file_type'] == 'jpg' || $line['file_type'] == 'gif' || $line['file_type'] == 'webp' || $line['file_type'] == 'png') ? ('<a href="'. KU_WEBPATH .'/'. $line['boardname'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '"><img border=0 src="'. KU_WEBPATH .'/'. $line['boardname'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '"></a>') : ('')) . '</td><td>'. $line['message'] . '</td><td>'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</tr>';
				}
				$tpl_page .= '</table>'. "\n";
			} else {
			$tpl_page .= _gettext('No results found for') .' '. $_GET['ip'] . '<br />'. "\n";
			}
		} else {
			$tpl_page .= '<form action="?" method="get">'. "\n" .
						'<input type="hidden" name="action" value="ipsearch" />'. "\n" .
						'<label for="board">'. _gettext('Board') . ':</label>'. "\n" .
						$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername']), true) . '<br />'. "\n" .
						'<label for="ip">'. _gettext('IP') . ':</label>'. "\n" .
						'<input type="text" name="ip" value="'. (isset($_GET['ip']) ? $_GET['ip'] : ''). '" /><br />'. "\n" .
						'<input type="submit" value="'. _gettext('IP Search') . '" />'. "\n";
		}
	}

	function remove_spaces($str) {
		return str_replace(" ", "", $str);
	}

	/* Search for posts by IP */
	function specialthreads() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Короткие ссылки на треды') .'</h2><br />'. "\n";

		if (isset($_GET['special_threads']))
		{
			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "kurisaba_ext_data` SET `value` = " . $tc_db->qstr($_GET['special_threads']) . " WHERE `name` = 'special_threads'");
			$tpl_page .= _gettext('Данные обновлены.<br />'). "\n";
		}
		else
		{
			$special_threads = $tc_db->GetOne("SELECT `value` FROM `" . KU_DBPREFIX . "kurisaba_ext_data` WHERE `name` = 'special_threads'");
			$tpl_page .= '<form action="?" method="get">'. "\n" .
						'<input type="hidden" name="action" value="specialthreads" />'. "\n" .
						'<label for="special_threads">Список тредов для меню (см. README.md):</label><br>'. "\n" .
						'<textarea name="special_threads" cols="80" rows="35">'. $special_threads . '</textarea><br /><br />' . "\n" .
						'<input type="submit" value="'. _gettext('Обновить данные') . '" />'. "\n";
		}
	}

	/* Search for text in posts */
	function search() {
		global $tc_db, $tpl_page;

		if (isset($_GET['query'])) {
			$search_query = $_GET['query'];
			if (isset($_GET['s'])) {
				$s = $_GET['s'];
			} else {
				$s = 0;
			}
			$deleted_on = 0;
			if (isset($_GET['deleted'])) {
				if ( $_GET['deleted'] === "1" )
					$deleted_on = 1;
			}
			$search_query_array = explode('KUSABA_AND', $search_query);
			$trimmed = trim($search_query);
			$limit = 10;
			if ($trimmed == '') {
				$tpl_page .= _gettext('Please enter a search query.');
				exit;
			}
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			$likequery = '';
			foreach ($search_query_array as $search_split) {
				$likequery .= "`message` LIKE " . $tc_db->qstr(str_replace('_', '\_', $search_split)) . " AND ";
			}
			$likequery = substr($likequery, 0, -4);
			$query = '';

			if ($deleted_on === 1) {
				$query .= "SELECT `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "boards`.`name` AS boardname FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX . "boards` WHERE " . $likequery . " AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC";
			} else {
				$query .= "SELECT `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "boards`.`name` AS boardname FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX . "boards` WHERE `IS_DELETED` = 0 AND " . $likequery . " AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC";
			}
			$numresults = $tc_db->GetAll($query);
			$numrows = count($numresults);
			if ($numrows == 0) {
				$tpl_page .= '<h4>'. _gettext('Results') . '</h4>';
				$tpl_page .= '<p>'. _gettext('Sorry, your search returned zero results.') . '</p>';
			} else {
				$query .= " LIMIT $limit OFFSET $s";
				$results = $tc_db->GetAll($query);
				$tpl_page .= '<p style="font-size: 1.5em;">'. _gettext('Results for') .': <strong>'. $search_query . '</strong></p>';
				$count = 1 + $s;
				foreach ($results as $line) {
					$tpl_page .= '<span style="font-size: 1.5em;">'. $count . '.</span> <span style="font-size: 1.3em;">'. _gettext('Board') .': /'. $line['boardname'] . '/, <a href="'.KU_BOARDSPATH . '/'. $line['boardname'] . '/res/';
					if ($line['parentid'] == 0) {
						$tpl_page .= $line['id'] . '.html">';
					} else {
						$tpl_page .= $line['parentid'] . '.html#'. $line['id'] . '">';
					}

					if ($line['parentid'] == 0) {
						$tpl_page .= _gettext('Thread') .' #'. $line['id'];
					} else {
						$tpl_page .= _gettext('Thread') .' #'. $line['parentid'] . ', Post #'. $line['id'];
					}
					$tpl_page .= '</a></span>';

					$regexp = '/(';
					foreach ($search_query_array as $search_word) {
						$regexp .= preg_quote($search_word) . '|';
					}
					$regexp = substr($regexp, 0, -1) . ')/';
					//$line['message'] = preg_replace_callback($regexp, array(&$this, 'search_callback'), stripslashes($line['message']));
					$line['message'] = stripslashes($line['message']);
					$tpl_page .= '<fieldset>'. $line['message'] . '</fieldset><br />';
					$count++;
				}
				$currPage = (($s / $limit) + 1);
				$tpl_page .= '<br />';
				if ($s >= 1) {
					$prevs = ($s - $limit);
					$tpl_page .= "&nbsp;<a href=\"?action=search&deleted=$deleted_on&s=$prevs&query=" . urlencode($search_query) . "\">&lt;&lt; "._gettext('Prev')." 10</a>&nbsp&nbsp;";
				}
				$pages = intval($numrows / $limit);
				if ($numrows % $limit) {
					$pages++;
				}
				if (!((($s + $limit) / $limit) == $pages) && $pages != 1) {
					$news = $s + $limit;
					$tpl_page .= "&nbsp;<a href=\"?action=search&deleted=$deleted_on&s=$news&query=" . urlencode($search_query) . "\">"._gettext('Next')." 10 &gt;&gt;</a>";
				}

				$a = $s + ($limit);
				if ($a > $numrows) {
					$a = $numrows;
				}
				$b = $s + 1;

				$tpl_page .= $this->search_results_display($a, $b, $numrows);
			}
		}

		$tpl_page .= '<form action="?" method="get">
		<input type="hidden" name="action" value="search" />
		<input type="hidden" name="s" value="0" />

		<strong>'. _gettext('Query') .'</strong>:<br /><input type="text" name="query" ';
		if (isset($_GET['query'])) {
			$tpl_page .= 'value="'. $_GET['query'] . '" ';
		}
		$tpl_page .= 'size="52" /><br />

		<input type="submit" value="'. _gettext('Search') .'" />

		<label><input type="checkbox" value="1" name="deleted" /> ' . _gettext('Search deleted posts too') .'</label><br /><br />

		<h1>'. _gettext('Search Help') .'</h1>

		'. _gettext('Separate search terms with the word <strong>KUSABA_AND</strong>') .' <br /><br />

		'. _gettext('To find a single phrase anywhere in a post\'s message, use:') .'<br />
		%'. _gettext('some phrase here') .'%<br /><br />

		'. _gettext('To find a phrase at the beginning of a post\'s message:') .'<br />
		'. _gettext('some phrase here') .'%<br /><br />

		'. _gettext('To find a phrase at the end of a post\'s message:') .'<br />
		%'. _gettext('some phrase here') .'<br /><br />

		'. _gettext('To find two phrases anywhere in a post\'s message, use:') .'<br />
		%'. _gettext('first phrase here') .'%KUSABA_AND%'. _gettext('second phrase here') .'%<br /><br />

		</form>';
	}
	function search_callback($matches) {
		print_r($matches);
		return '<strong>'. $matches[0] . '</strong>';
	}

	function search_results_display($a, $b, $numrows) {
		return '<p>'. _gettext('Results') . ' <strong>'. $b . '</strong> to <strong>'. $a . '</strong> of <strong>'. $numrows . '</strong></p>'. "\n" .
		'<hr />';
	}

	function restorethread() {
		global $tc_db, $tpl_page;
		$tpl_page .= '<h2>Восстановить тред</h2>';
		$board = isset($_GET['board']) ? $_GET['board'] : '';
		$thread = isset($_GET['thread']) ? $_GET['thread'] : '';

		$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));


		$lines = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($boardid) . " AND `IS_DELETED` = 1 AND `id` = " . $tc_db->qstr($thread));
		if (count($lines) == 0)
		{
			$tpl_page .= '<p>Тред ' . $board . '/' . $thread . ' не существует или не удалён.</p>';
		}
		else
		{
			$line = $lines[0];
			$timestamp = $line['deleted_timestamp'];
			$deleted_at = date(r, $timestamp);
			$deleted_timestamp_limit = $timestamp - 30;
			$restore_to = date(r, $deleted_timestamp_limit);

			$tpl_page .= '<p>Тред ' . $board . '/' . $thread . ' удалён ' . $deleted_at . '(' . $timestamp . '); будут восстановлены все посты на ' . $restore_to . ' (' . $deleted_timestamp_limit . ').</p>';

			$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `IS_DELETED` = 0, `deleted_timestamp` = 0 WHERE (`id` = " . $tc_db->qstr($thread) . " OR `parentid` = " . $tc_db->qstr($thread) . ") AND `boardid` = " . $tc_db->qstr($boardid) . " AND `deleted_timestamp` > " . $deleted_timestamp_limit);

			$tpl_page .= '<p>Тред ' . $board . '/' . $thread . ' восстановлен на момент за 30 секунд до удаления.</p>';

			management_addlogentry('Восстановлен тред' . ' #<a href="?action=viewthread&thread='. $thread . '&board='. $board . '#'. $thread . '">'. $thread . '</a> - /'. $board . '/', 7);
		}
	}

	// Credits to Eman for this code
	function viewthread() {
		global $tc_db, $tpl_page;
		$tpl_page .= '<h2>'. _gettext('View Threads (including deleted)') .'</h2>';
		$board = isset($_GET['board']) ? $_GET['board'] : '';
		$thread = isset($_GET['thread']) ? $_GET['thread'] : '';
		if (!$thread ) {
			$thread = "0";
		}
		$tpl_page .= "
			<style type=\"text/css\">
			input {
				display: inline !important;
				width: auto !important;
				float: none !important;
				margin-bottom: 0px !important;
			}
			th,td {
				font-size: 14px !important;
			}
			</style>";
		if (!$board) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `id` FROM `". KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			$tpl_page .= '<form method="get" action=""><input type="hidden" name="action" value="viewthread" />'. _gettext('Select Board') .': <select name="board">';
			foreach ($results as $line) {
				$name = $line['name'];
				$id =	$line['id'];
				$tpl_page .= "<option value=\"$name\">/$name/</option>";
			}
			$tpl_page .= '</select>&nbsp;<input type=submit value="'. _gettext('Go') .'" />';
			return;
		}

		$board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `". KU_DBPREFIX . "boards` WHERE `name` = ".$tc_db->qstr($board));
		$tpl_page .= "
			<form method=\"get\" action=\"\">
			<input type=\"hidden\" name=\"action\" value=\"viewthread\" />
			<input type=\"hidden\" name=\"board\" value=\"$board\" />";
		if ($thread == "0" ) {
			$tpl_page .= "<h2>". sprintf(_gettext('All threads on /%s/'), $board) ."</h2>";
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = $board_id AND (`id` = ".$tc_db->qstr($thread)." OR `parentid` = ".$tc_db->qstr($thread).") ORDER BY `id` DESC");
		} else {
			$tpl_page .= "<h2>". sprintf(_gettext('Thread %s on /%s/'), $thread, $board) ."</h2>";
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = $board_id AND (`id` = ".$tc_db->qstr($thread)." OR `parentid` = ".$tc_db->qstr($thread).") ORDER BY `id` ASC");
		}
		$time = round(microtime(), 5);
		$first = "1";
		foreach ($results as $line) {
			$bans = "";
			$id = $line['id'];
			$ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
			$filename = $line['file'];
			$file_original = $line['file_original'];
			$filetype = $line['file_type'];
			$filesize_formatted = $line['file_size_formatted'];
			$image_w = $line['image_w'];
			$image_h = $line['image_h'];
			$thumb_w = $line['thumb_w'];
			$thumb_h = $line['thumb_h'];
			$message = $line['message'];
			$name = $line['name'];
			$tripcode = $line['tripcode'];
			$timestamp = date(r, $line['timestamp']);
			$subject = $line['subject'];
			$posterauthority = $line['posterauthority'];
			$deleted = isset($line['IS_DELETED']) ? $line['IS_DELETED'] : $line['is_deleted'];
			$deleted = $deleted == "1" ? true : false;
			if ($thread == "0") {
				$view = "<a href=\"?action=viewthread&board=$board&thread=$id\">". _gettext('View') ."</a>";
				if ($deleted)
				{
					$view .= "|<a href=\"?action=restorethread&board=$board&thread=$id\">". _gettext('Undelete') ."</a>";
				}
				$view = '['.$view.']';
			} else {
				$view = "";
			}
			if ($name == "") {
				$name = _gettext('Anonymous');
			} else {
				$name = "<font color=\"blue\">$name</font>";
			}
			if ($tripcode != "") {
				$tripcode = "<font color=\"green\">!$tripcode</font>";
			}
			if ($subject != "") {
				$subject = "<font color=\"red\">$subject</font> - ";
			}
			if ($posterauthority == "1") {
				$posterauthority = "<font color=\"purple\"><strong>##Admin##</strong></font>";
			} elseif ($posterauthority == "2") {
				$posterauthority = "<font color=\"red\"><strong>##Mod##</strong></font>";
			} else {
				$posterauthority = "";
			}
			if ($deleted) {
				$deleted = "<font color=green><blink><strong>". _gettext('DELETED') ."</strong></blink></font> - ";
			} else {
				$deleted = "";
				$bans = "</td><td width=80px style=\"text-align: right; vertical-align: top;\">[<a href=\"manage_page.php?action=delposts&boarddir=$board&delpostid=$id&noreturn=true\">D</a> <a href=\"manage_page.php?action=delposts&boarddir=$board&delpostid=$id&do_ban=true\">&amp;</a> <a href=\"manage_page.php?action=bans&banboard=$board&banpost=$id\">B</a>]";
			}


			if ($bans == "") {
			$bans = "</td><td>&nbsp;</td>";
			}
			$tpl_page .= "
				<table style=\"text-align: left; width: 100%;\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\">
					<tbody>
						<tr>
							<td style=\"vertical-align: top;\">$deleted$subject$name$tripcode $posterauthority $timestamp ". _gettext('No.') ." $id ". _gettext('IP') .": $ip $view $bans
							</td>
						</tr>
			";
			if ($filename != "") {
				$tpl_page .= "
					<tr>
						<td colspan=\"2\" style=\"vertical-align: top;\">". _gettext('File') .": <a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=_new>$filename.$filetype</a> -( $filesize_formatted, {$image_w}x{$image_h}, $file_original.$filetype )</td>
					</tr>
				";
			}
			$tpl_page .= "
			</tbody></table>
							<table style=\"text-align: left; width: 100%;\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\">
					<tbody>
			<tr>";


			if ($filename != "" && !$deleted) {
				$tpl_page .= "
					<td style=\"vertical-align: top; width: 300px;\"><center><a href=\"". KU_WEBPATH ."/$board/src/$filename.$filetype\" target=\"_new\"><img src=\"". KU_WEBPATH ."/$board/thumb/{$filename}s.$filetype\" height=\"$thumb_h\" width=\"$thumb_w\" border=\"0\"></a></center></td>";
			}
			if ($message == "") {
				$message = "&nbsp;";
			}
			$tpl_page .= "<td style=\"vertical-align: top; height: 100%;\">$message</td></tr></tbody></table><br />";
			$first = "0";
		}
		$time2 = round(microtime(), 5);
		$generation = $time2 - $time;
		$generation = abs($generation);
		$tpl_page .= '
		'. _gettext('Render Time') .':'. $generation .' '._gettext('Seconds').'
		<!--<h2>'. _gettext('Ban') .'</h2>
		'. _gettext('Reason') .': <input type="text" name="banreason" value="'. _gettext('You Are Banned') .'" />&nbsp;&nbsp;
		'. _gettext('Duration') .': <input type="text" name="banduration" value="0" />&nbsp;&nbsp;
		'. _gettext('Appeal') .': <input type="text" name="banappeal" value="0" />&nbsp;&nbsp;
		<input type="submit" value="'. _gettext('Submit') .'" />-->
		</form>';
	}

	/* View a thread marked as deleted */
	function viewdeletedthread() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('View deleted thread') . '</h2><br />'. "\n";

		if (isset($_GET['board']) && isset($_GET['threadid']) && $_GET['threadid'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
			foreach ($results as $line) {
				$board_id = $line['id'];
				$board_dir = $line['name'];
			}
			if (count($results) > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `id` = " . $tc_db->qstr($_GET['threadid']) . "");
				foreach ($results as $line) {
					$thread_isdeleted = $line['IS_DELETED'];
					$thread_parentid = $line['parentid'];
				}
				if ($thread_isdeleted == 1 && $thread_parentid == 0) {
					foreach ($results as $line) {
						if ($line['name'] != '') {
							$name = $line['name'];
						} else {
							$name =  _gettext('Anonymous') .' ';
						}
						$tpl_page .= '<div style="width: 75%; border: 1px solid #CCC; padding: 5px;">'. "\n";
						$tpl_page .= $name . $line['tripcode'] . formatDate($line['postedat']) . ' | '. _gettext('No.') .' '. $line['id'] . ' | '. _gettext('IP') .': '. md5_decrypt($line['ip'], KU_RANDOMSEED) . '<br />'. "\n";
						if (isset($line['filename'])) {
							$tpl_page .= '<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank">'. $line['filename'] . '.'. $line['filetype'] . '</a><br />'. "\n" .
										'<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank"><img src="'. KU_WEBPATH . '/'. $_GET['board'] . '/thumb/'. $line['filename'] . 's.'. $line['filetype'] . '" border="0" alt="'. $line['filename'] . '.'. $line['filetype'] . '" /></a>'. "\n";
						}
						$tpl_page .= $line['message'];
						$tpl_page .= '</div><br />';
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts_" . $board_dir . "` WHERE `parentid` = " . $tc_db->qstr($_GET['threadid']) . "");
					foreach ($results as $line) {
						if ($line['name'] != '') {
							$name = $line['name'] . ' ';
						} else {
							$name =  _gettext('Anonymous') .' ';
						}
						$tpl_page .= '<div style="width: 75%; border: 1px solid #CCC; padding: 5px;">'. "\n";
						$tpl_page .= $name . $line['tripcode'] . formatDate($line['postedat']) . ' | No. '. $line['id'] . ' | IP: '. md5_decrypt($line['ip'], KU_RANDOMSEED) . '<br />'. "\n";
						if ($line['filename'] != '') {
							$tpl_page .= '<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank">'. $line['filename'] . '.'. $line['filetype'] . '</a><br />'. "\n" .
										'<a href="'. KU_WEBPATH . '/'. $_GET['board'] . '/src/'. $line['filename'] . '.'. $line['filetype'] . '" target="_blank"><img src="'. KU_WEBPATH . '/'. $_GET['board'] . '/thumb/'. $line['filename'] . 's.'. $line['filetype'] . '" border="0" alt="'. $line['filename'] . '.'. $line['filetype'] . '" /></a>'. "\n";
						}
						$tpl_page .= $line['message'];
						$tpl_page .= '</div><br />';
					}
				} else {
					$tpl_page .=  _gettext('That\'s either not a thread, or it\'s not deleted.') ;
				}
			}
		} else {

		$tpl_page .= '<form method="get" action="?">'. "\n" .
					'<input type="hidden" name="action" value="viewthread" />'. "\n" .
					'<label for="board">'. _gettext('Board') . ':</label>'. "\n" .
					$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) . "\n" .
					'<br />'. "\n" .
					'<label for="threadid">'. _gettext('Thread') . ':</label>'. "\n" .
					'<input type="text" name="threadid" /><br />'. "\n" .
					'<input type="submit" value="'. _gettext('View deleted thread') . '" />'. "\n" .
					'</form>';
		}
	}

	/* Add, view, and delete filetypes */
	function editfiletypes() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Edit filetypes') . '</h2><br />';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addfiletype') {
				if (isset($_POST['filetype']) || isset($_POST['image'])) {
          $this->CheckToken($_POST['token']);
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "filetypes` ( `filetype` , `mime` , `image` , `image_w` , `image_h` ) VALUES ( " . $tc_db->qstr($_POST['filetype']) . " , " . $tc_db->qstr($_POST['mime']) . " , " . $tc_db->qstr($_POST['image']) . " , " . $tc_db->qstr($_POST['image_w']) . " , " . $tc_db->qstr($_POST['image_h']) . " )");
						$tpl_page .= _gettext('Filetype added.');
					}
				} else {
					$tpl_page .= '<form action="?action=editfiletypes&do=addfiletype" method="post">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<label for="filetype">'. _gettext('Filetype') .':</label>
					<input type="text" name="filetype" />
					<div class="desc">'. _gettext('The extension this will be applied to. <strong>Must be lowercase</strong>') .'</div><br />

					<label for="mime">'. _gettext('MIME type(s)') .':</label>
					<input type="text" name="mime" />
					<div class="desc">'. _gettext('The MIME type which must be present with an image uploaded in this type. Leave blank to disable. If many allowed, use semicolon to separate.') .'</div><br />

					<label for="image">Image:</label>
					<input type="text" name="image" value="generic.png" />
					<div class="desc">'. _gettext('The image which will be used, found in inc/filetypes.') .'</div><br />

					<label for="image_w">'. _gettext('Image width') .':</label>
					<input type="text" name="image_w" value="48" />
					<div class="desc">'. _gettext('The width of the image. Needs to be set to prevent the page from jumping around while images load.') .'</div><br />

					<label for="image_h">'. _gettext('Image height') .':</label>
					<input type="text" name="image_h" value="48" />
					<div class="desc">'. _gettext('The height of the image. Needs to be set to prevent the page from jumping around while images load.') .'.</div><br />

					<input type="submit" value="'. _gettext('Add') .'" />

					</form>';
				}
				$tpl_page .= '<br /><hr />';
			}
			if ($_GET['do'] == 'editfiletype' && $_GET['filetypeid'] > 0) {
				if (isset($_POST['filetype'])) {
					if ($_POST['filetype'] != '' && $_POST['image'] != '') {
            $this->CheckToken($_POST['token']);
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "filetypes` SET `filetype` = " . $tc_db->qstr($_POST['filetype']) . " , `mime` = " . $tc_db->qstr($_POST['mime']) . " , `image` = " . $tc_db->qstr($_POST['image']) . " , `image_w` = " . $tc_db->qstr($_POST['image_w']) . " , `image_h` = " . $tc_db->qstr($_POST['image_h']) . " WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
						if (KU_APC) {
							apc_delete('filetype|'. $_POST['filetype']);
						}
						$tpl_page .= _gettext('Filetype updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editfiletypes&do=editfiletype&filetypeid='. $_GET['filetypeid'] . '" method="post">
              <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
							<label for="filetype">'. _gettext('Filetype') .':</label>
							<input type="text" name="filetype" value="'. $line['filetype'] . '" />
							<div class="desc">'. _gettext('The extension this will be applied to. <strong>Must be lowercase</strong>') .'</div><br />

							<label for="mime">'. _gettext('MIME type(s)') .':</label>
							<input type="text" name="mime" value="'. $line['mime'] . '" />
							<div class="desc">'. _gettext('The MIME type which must be present with an image uploaded in this type. Leave blank to disable. If many allowed, use semicolon to separate.') .'</div><br />

							<label for="image">'. _gettext('Image') .':</label>
							<input type="text" name="image" value="'. $line['image'] . '" />
							<div class="desc">'. _gettext('The image which will be used, found in inc/filetypes.') .'</div><br />

							<label for="image_w">'. _gettext('Image width') .':</label>
							<input type="text" name="image_w" value="'. $line['image_w'] . '" />
							<div class="desc">'. _gettext('The width of the image. Needs to be set to prevent the page from jumping around while images load.') .'</div><br />

							<label for="image_h">'. _gettext('Image height') .':</label>
							<input type="text" name="image_h" value="'. $line['image_h'] . '" />
							<div class="desc">'. _gettext('The height of the image. Needs to be set to prevent the page from jumping around while images load.') .'.</div><br />

							<input type="submit" value="'. _gettext('Edit') .'" />

							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a filetype with that ID.');
					}
				}
				$tpl_page .= '<br /><hr />';
			}
			if ($_GET['do'] == 'deletefiletype' && $_GET['filetypeid'] > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "filetypes` WHERE `id` = " . $tc_db->qstr($_GET['filetypeid']) . "");
				$tpl_page .= _gettext('Filetype deleted.');
				$tpl_page .= '<br /><hr />';
			}
		}
		$tpl_page .= '<a href="?action=editfiletypes&do=addfiletype">'. _gettext('Add filetype') .'</a><br /><br />';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>'. _gettext('ID') .'</th><th>'. _gettext('Filetype') .'</th><th>'. _gettext('Image') .'</th><th>'. _gettext('Edit/Delete') .'</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>'. $line['id'] . '</td><td>'. $line['filetype'] . '</td><td>'. $line['image'] . '</td><td>[<a href="?action=editfiletypes&do=editfiletype&filetypeid='. $line['id'] . '">'. _gettext('Edit') .'</a>] [<a href="?action=editfiletypes&do=deletefiletype&filetypeid='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no filetypes.');
		}
	}

	/* Add, view, and delete sections */
	function editsections() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Edit sections') . '</h2><br />';
		if (isset($_GET['do'])) {
			if ($_GET['do'] == 'addsection') {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
            $this->CheckToken($_POST['token']);
						$tc_db->Execute("INSERT HIGH_PRIORITY INTO `" . KU_DBPREFIX . "sections` ( `name` , `abbreviation` , `order` , `hidden` ) VALUES ( " . $tc_db->qstr($_POST['name']) . " , " . $tc_db->qstr($_POST['abbreviation']) . " , " . $tc_db->qstr($_POST['order']) . " , '" . (isset($_POST['hidden']) ? '1' : '0') . "' )");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section added.');
					}
				} else {
					$tpl_page .= '<form action="?action=editsections&do=addsection" method="post">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
					<label for="name">'. _gettext('Name') .':</label><input type="text" name="name" /><div class="desc">'. _gettext('The name of the section') .'</div><br />
					<label for="abbreviation">'. _gettext('Abbreviation') .':</label><input type="text" name="abbreviation" /><div class="desc">'. _gettext('Abbreviation (less then 10 characters)') .'</div><br />
					<label for="order">'. _gettext('Order') .':</label><input type="text" name="order" /><div class="desc">'. _gettext('Order to show this section with others, in ascending order') .'</div><br />
					<label for="hidden">'. _gettext('Hidden') .':</label><input type="checkbox" name="hidden" /><div class="desc">'. _gettext('If checked, this section will be collapsed by default when a user visits the site.') .'</div><br />
					<input type="submit" value="'. _gettext('Add') .'" />
					</form>';
				}
				$tpl_page .= '<br /><hr />';
			}
			if ($_GET['do'] == 'editsection' && $_GET['sectionid'] > 0) {
				if (isset($_POST['name'])) {
					if ($_POST['name'] != '' && $_POST['abbreviation'] != '') {
            $this->CheckToken($_POST['token']);
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "sections` SET `name` = " . $tc_db->qstr($_POST['name']) . " , `abbreviation` = " . $tc_db->qstr($_POST['abbreviation']) . " , `order` = " . $tc_db->qstr($_POST['order']) . " , `hidden` = '" . (isset($_POST['hidden']) ? '1' : '0') . "' WHERE `id` = '" . $_GET['sectionid'] . "'");
						require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
						$menu_class = new Menu();
						$menu_class->Generate();
						$tpl_page .= _gettext('Section updated.');
					}
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` WHERE `id` = " . $tc_db->qstr($_GET['sectionid']) . "");
					if (count($results) > 0) {
						foreach ($results as $line) {
							$tpl_page .= '<form action="?action=editsections&do=editsection&sectionid='. $_GET['sectionid'] . '" method="post">
              <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
							<input type="hidden" name="id" value="'. $_GET['sectionid'] . '" />

							<label for="name">'. _gettext('Name') .':</label>
							<input type="text" name="name" value="'. $line['name'] . '" />
							<div class="desc">'. _gettext('The name of the section') .'</div><br />

							<label for="abbreviation">'. _gettext('Abbreviation') .':</label>
							<input type="text" name="abbreviation" value="'. $line['abbreviation'] . '" />
							<div class="desc">'. _gettext('Abbreviation (less then 10 characters)') .'</div><br />

							<label for="order">'. _gettext('Order') .':</label>
							<input type="text" name="order" value="'. $line['order'] . '" />
							<div class="desc">'. _gettext('Order to show this section with others, in ascending order') .'</div><br />

							<label for="hidden">'. _gettext('Hidden') .':</label>
							<input type="checkbox" name="hidden" '. ($line['hidden'] == 0 ? '' : 'checked') . ' />
							<div class="desc">'. _gettext('If checked, this section will be collapsed by default when a user visits the site.') .'</div><br />

							<input type="submit" value="'. _gettext('Edit') .'" />

							</form>';
						}
					} else {
						$tpl_page .= _gettext('Unable to locate a section with that ID.');
					}
				}
				$tpl_page .= '<br /><hr />';
			}
			if ($_GET['do'] == 'deletesection' && isset($_GET['sectionid'])) {
				if ($_GET['sectionid'] > 0) {
					$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "sections` WHERE `id` = " . $tc_db->qstr($_GET['sectionid']) . "");
					require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
					$menu_class = new Menu();
					$menu_class->Generate();
					$tpl_page .= _gettext('Section deleted.') . '<br /><hr />';
				}
			}
		}
		$tpl_page .= '<a href="?action=editsections&do=addsection">'. _gettext('Add section') .'</a><br /><br />';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if (count($results) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>'.('ID') .'</th><th>'.('Order') .'</th><th>'. _gettext('Abbreviation') .'</th><th>'. _gettext('Name') .'</th><th>'. _gettext('Edit/Delete') .'</th></tr>';
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>'. $line['id'] . '</td><td>'. $line['order'] . '</td><td>'. $line['abbreviation'] . '</td><td>'. $line['name'] . '</td><td>[<a href="?action=editsections&do=editsection&sectionid='. $line['id'] . '">'. _gettext('Edit') .'</a>] [<a href="?action=editsections&do=deletesection&sectionid='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= _gettext('There are currently no sections.');
		}
	}

	function rebuildanswerslist()
	{
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();
		$tpl_page .= '<h2>Перегенерация карты ответов</h2><br />'. "\n";
		management_addlogentry('Перегенерация карты ответов запущена');
		$starttime = time();

		// Create list of boards
		$boardnames = array();
		$boardids = array();
		$boards_list = $tc_db->GetAll("SELECT `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($boards_list as $line)
		{
			$boardnames[$line['id']] = $line['name'];
			$boardids[$line['name']] = $line['id'];
		}

		$tc_db->Execute("TRUNCATE TABLE `" . KU_DBPREFIX . "answers`");

		$nposts = 0;
        for ($step = 0;;$step = $step + 10000)
		{
            $results = $tc_db->GetAll("SELECT `id`, `boardid`, `parentid`, `message` FROM `" . KU_DBPREFIX . "posts` " .
				"WHERE `IS_DELETED` = 0 AND `id` >= " . $step . " AND `id` < " . ($step + 10000));
            if (count($results) == 0)
                break;

            foreach ($results as $key=>$value)
            {
                $results[$key]['boardname'] = $boardnames[$value['boardid']];
            }
            AnswerMapAdd($results, $boardids);
			$nposts = $nposts + count($results);
        }

		$nrecords = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "answers`");
		$ntime = time() - $starttime;
		management_addlogentry('Перегенерация карты ответов закончена. '.$nrecords.' ответов для '.$nposts.' постов, '.$ntime.' секунд.');
		$tpl_page .= 'Готово. Записано '.$nrecords.' ответов для '.$nposts.' постов. Затрачено '.$ntime.' секунд.';
	}

	/*
	* +------------------------------------------------------------------------------+
	* Boards Pages
	* +------------------------------------------------------------------------------+
	*/

	function gentimestamp() {
		return preg_replace('/[.\ ]/', '', microtime());
	}

	function boardopts() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$_POST['desc'] = htmlspecialchars($_POST['desc']);

		$tpl_page .= '<h2>'. _gettext('Board options') . '</h2><br />';
		if (isset($_GET['updateboard']) && isset($_POST['order']) && isset($_POST['maxpages']) && isset($_POST['maxage']) && isset($_POST['messagelength'])) {
      		$this->CheckToken($_POST['token']);
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . " LIMIT 1");
			if ($boardid != '') {
				$available_styles = explode(':', KU_STYLES);
				if ($_POST['order'] >= 0 && $_POST['maxpages'] >= 0 && $_POST['markpage'] >= 0 && $_POST['maxage'] >= 0 && $_POST['messagelength'] >= 0 && ($_POST['defaultstyle'] == '' || in_array($_POST['defaultstyle'], $available_styles))) {
					$filetypes = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 9) == 'filetype_') {
							$filetypes[] = substr($postkey, 9);
						}
					}
					$updateboard_enablecatalog = isset($_POST['enablecatalog']) ? '1' : '0';
					$updateboard_enablenofile = isset($_POST['enablenofile']) ? '1' : '0';
					$updateboard_redirecttothread = isset($_POST['redirecttothread']) ? '1' : '0';
					$updateboard_enablereporting = isset($_POST['enablereporting']) ? '1' : '0';
					$updateboard_enablecaptcha = isset($_POST['enablecaptcha']) ? '1' : '0';
					$updateboard_forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
					$updateboard_trial = isset($_POST['trial']) ? '1' : '0';
					$updateboard_popular = isset($_POST['popular']) ? '1' : '0';
					$updateboard_showid = isset($_POST['showid']) ? '1' : '0';
					$updateboard_compactlist = isset($_POST['compactlist']) ? '1' : '0';
					$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
					$updateboard_balls = isset($_POST['balls']) ? '1' : '0';
					$updateboard_dice = isset($_POST['dice']) ? '1' : '0';
					$updateboard_useragent = isset($_POST['useragent']) ? '1' : '0';

					if (($_POST['type'] == '0' || $_POST['type'] == '1' || $_POST['type'] == '2' || $_POST['type'] == '3') && ($_POST['uploadtype'] == '0' || $_POST['uploadtype'] == '1' || $_POST['uploadtype'] == '2')) {
						if (!($_POST['uploadtype'] != '0' && $_POST['type'] == '3')) {
							if(in_array($_POST['locale'], explode('|', KU_SUPPORTED_LOCALES))) {
								if(count($_POST['allowedembeds']) > 0) {
									$updateboard_allowedembeds = '';
									$results = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
									foreach ($results as $line) {
										if(in_array($line['filetype'], $_POST['allowedembeds'])) {
											$updateboard_allowedembeds .= $line['filetype'].',';
										}
									}
									if ($updateboard_allowedembeds != '') {
										$updateboard_allowedembeds = substr($updateboard_allowedembeds, 0, -1);
									}
								}
								$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `type` = " . $tc_db->qstr($_POST['type']) . " , `uploadtype` = " . $tc_db->qstr($_POST['uploadtype']) . " , `order` = " . $tc_db->qstr(intval($_POST['order'])) . " , `section` = " . $tc_db->qstr(intval($_POST['section'])) . " , `desc` = " . $tc_db->qstr($_POST['desc']) . " , `locale` = " . $tc_db->qstr($_POST['locale']) . " , `showid` = '" . $updateboard_showid . "' , `compactlist` = '" . $updateboard_compactlist . "' , `locked` = '" . $updateboard_locked . "' , `balls` = '" . $updateboard_balls . "' , `dice` = '" . $updateboard_dice . "' , `useragent` = '" . $updateboard_useragent . "' , `maximagesize` = " . $tc_db->qstr($_POST['maximagesize']) . " , `messagelength` = " . $tc_db->qstr($_POST['messagelength']) . " , `maxpages` = " . $tc_db->qstr($_POST['maxpages']) . " , `maxage` = " . $tc_db->qstr($_POST['maxage']) . " , `markpage` = " . $tc_db->qstr($_POST['markpage']) . " , `maxreplies` = " . $tc_db->qstr($_POST['maxreplies']) . " , `image` = " . $tc_db->qstr($_POST['image']) . " , `includeheader` = " . $tc_db->qstr($_POST['includeheader']) . " , `redirecttothread` = '" . $updateboard_redirecttothread . "' , `anonymous` = " . $tc_db->qstr($_POST['anonymous']) . " , `forcedanon` = '" . $updateboard_forcedanon . "' , `embeds_allowed` = " . $tc_db->qstr($updateboard_allowedembeds) . " , `trial` = '" . $updateboard_trial . "' , `popular` = '" . $updateboard_popular . "' , `defaultstyle` = " . $tc_db->qstr($_POST['defaultstyle']) . " , `enablereporting` = '" . $updateboard_enablereporting . "', `enablecaptcha` = '" . $updateboard_enablecaptcha . "' , `enablenofile` = '" . $updateboard_enablenofile . "', `enablecatalog` = '" . $updateboard_enablecatalog . "' , `hiddenthreads` = " . $tc_db->qstr($_POST['hiddenthreads']) . " WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . "");
								$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $boardid . "'");
								foreach ($filetypes as $filetype) {
									$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid`, `typeid` ) VALUES ( '" . $boardid . "', " . $tc_db->qstr($filetype) . " )");
								}
								require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
								$menu_class = new Menu();
								$menu_class->Generate();
								$tpl_page .= _gettext('Update successful.');
								management_addlogentry(_gettext('Updated board configuration') . " - /" . $_GET['updateboard'] . "/", 4);
							}
							else $tpl_page .= _gettext('Sorry, a generic error has occurred.');

						} else {
							$tpl_page .= _gettext('Sorry, embed may only be enabled on normal imageboards.');
						}
					} else {
						$tpl_page .= _gettext('Sorry, a generic error has occurred.');
					}
				} else {
					$tpl_page .= _gettext('Integer values must be entered correctly.');
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_GET['updateboard'] . '</strong>.';
			}
		} elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">
					<form action="?action=boardopts&updateboard='.urlencode($_POST['board']).'" method="post">
          			<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />';
					/* Directory */
					$tpl_page .= '<label for="board">'. _gettext('Directory') .':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled />
					<div class="desc">'. _gettext('The directory of the board.') .'</div><br />';

					/* Description */
					$tpl_page .= '<label for="desc">'. _gettext('Description') .':</label>
					<input type="text" name="desc" value="'.$lineboard['desc'].'" />
					<div class="desc">'. _gettext('The name of the board.') .'</div><br />';

					/* Board type */
					$tpl_page .= '<label for="type">'. _gettext('Board type') .':</label>
					<select name="type">
					<option value="0"';
					if ($lineboard['type'] == '0') { $tpl_page .= ' selected="selected"'; }
					$tpl_page .= '>'. _gettext('Normal imageboard') .'</option>
					<option value="1"';
					if ($lineboard['type'] == '1') { $tpl_page .= ' selected="selected"'; }
					$tpl_page .= '>'. _gettext('Text board') .'</option><option value="2"';
					if ($lineboard['type'] == '2') { $tpl_page .= ' selected="selected"'; }
					$tpl_page .= '>'. _gettext('Oekaki imageboard') .'</option><option value="3"';
					if ($lineboard['type'] == '3') { $tpl_page .= ' selected="selected"'; }
					$tpl_page .= '>'. _gettext('Upload imageboard') .'</option>
					</select>
					<div class="desc">'. _gettext('The type of posts which will be accepted on this board. A normal imageboard will feature image and extended format posts, a text board will have no images, an Oekaki board will allow users to draw images and use them in their posts, and an Upload imageboard will be styled more towards file uploads.') .' '. _gettext('Default') .': <strong>Normal Imageboard</strong></div><br />';

					/* Upload type */
					$tpl_page .= '<label for="uploadtype">'. _gettext('Upload type') .':</label>
					<select name="uploadtype">
					<option value="0"';
					if ($lineboard['uploadtype'] == '0') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('No embedding') .'</option>
					<option value="1"';
					if ($lineboard['uploadtype'] == '1') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('Images and embedding') .'</option>
					<option value="2"';
					if ($lineboard['uploadtype'] == '2') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('Embedding only') .'</option>
					</select>
					<div class="desc">'. _gettext('Whether or not to allow embedding of videos.') .' '. _gettext('Default') .'.: <strong>'. _gettext('No Embedding') .'</strong></div><br />';

					/* Order */
					$tpl_page .= '<label for="order">'. _gettext('Order') .':</label>
					<input type="text" name="order" value="'.$lineboard['order'].'" />
					<div class="desc">'. _gettext('Order to show board in menu list, in ascending order.') .' '. _gettext('Default') .': <strong>0</strong></div><br />';

					/* Section */
					$tpl_page .= '<label for="section">'. _gettext('Section') .':</label>' .
					$this->MakeSectionListDropdown('section', $lineboard['section']) .
					'<div class="desc">'. _gettext('The section the board is in. This is used for displaying the list of boards on the top and bottom of pages.') .'<br />'. _gettext('If this is set to <em>Select a Board</em>, <strong>it will not be shown in the menu</strong>.') .'</div><br />';

					/* Hidden thread list */
					$tpl_page .= '<label for="hiddenthreads">Спрятанные треды:</label>
					<input type="text" name="hiddenthreads" value="'.$lineboard['hiddenthreads'].'" />
					<div class="desc">Список тредов, которые не видны на странице борды, разделённые запятыми. К примеру: 12345,8675,987</div><br />';

					/* Allowed filetypes */
					$tpl_page .= '<label>'. _gettext('Allowed filetypes') .':</label>
					<div class="desc">'. _gettext('What filetypes users are allowed to upload.') .'</div><br />';
					$filetypes = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype` FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
					foreach ($filetypes as $filetype) {
						$tpl_page .= '<label for="filetype_'. $filetype['id'] . '">'. strtoupper($filetype['filetype']) . '</label><input type="checkbox" name="filetype_'. $filetype['id'] . '"';
						$filetype_isenabled = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $lineboard['id'] . "' AND `typeid` = '" . $filetype['id'] . "' LIMIT 1");
						if ($filetype_isenabled == 1) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= ' /><br />';
					}

					/* Allowed embeds */
					$tpl_page .= '<label>'. _gettext('Allowed embeds') .':</label>
					<div class="desc">'. _gettext('What embed sites are allowed on this board. Only useful on board with embedding enabled.') .'</div><br />';
					$embeds = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype`, `name` FROM `" . KU_DBPREFIX . "embeds` ORDER BY `filetype` ASC");
					foreach ($embeds as $embed) {
						$tpl_page .= '<label for="allowedembeds[]">'. $embed['name'] . '</label><input type="checkbox" name="allowedembeds[]" value="'. $embed['filetype'] . '"';
						if (in_array($embed['filetype'], explode(',', $lineboard['embeds_allowed']))) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= ' /><br />';
					}

					/* Maximum image size */
					$tpl_page .= '<label for="maximagesize">'. _gettext('Maximum image size') .':</label>
					<input type="text" name="maximagesize" value="'.$lineboard['maximagesize'].'" />
					<div class="desc">'. _gettext('Maxmimum size of uploaded images, in <strong>bytes</strong>.') . ' '. _gettext('Default') .': <strong>1024000</strong></div><br />';

					$imagesize = from_human_readable(ini_get("upload_max_filesize"));
					if ($imagesize < $lineboard['maximagesize'])
						$tpl_page .= '<div class="desc" style="color: red">'. _gettext("Value is greater than upload_max_filesize in php.ini: ") . $imagesize . '</div><br />';
					else
						$tpl_page .= '<div class="desc">'. _gettext("Should not be greater than upload_max_filesize in php.ini: ") . $imagesize . '</div><br />';

					/* Maximum message length */
					$tpl_page .= '<label for="messagelength">'. _gettext('Maximum message length') .':</label>
					<input type="text" name="messagelength" value="'.$lineboard['messagelength'].'" />
					<div class="desc">'. _gettext('Default') .': <strong>8192</strong></div><br />';

					/* Maximum board pages */
					$tpl_page .= '<label for="maxpages">'. _gettext('Maximum board pages') .':</label>
					<input type="text" name="maxpages" value="'.$lineboard['maxpages'].'" />
					<div class="desc">'. _gettext('Default') .': <strong>11</strong></div><br />';

					/* Maximum thread age */
					$tpl_page .= '<label for="maxage">'. _gettext('Maximum thread age (Hours)') .':</label>
					<input type="text" name="maxage" value="'.$lineboard['maxage'].'" />
					<div class="desc">'. _gettext('Default') .': <strong>0</strong></div><br />';

					/* Mark page */
					$tpl_page .= '<label for="maxage">'. _gettext('Mark page') .':</label>
					<input type="text" name="markpage" value="'.$lineboard['markpage'].'" />
					<div class="desc">'. _gettext('Threads which reach this page or further will be marked to be deleted in two hours.') .' '. _gettext('Default') .': <strong>9</strong></div><br />';

					/* Maximum thread replies */
					$tpl_page .= '<label for="maxreplies">'. _gettext('Maximum thread replies') .':</label>
					<input type="text" name="maxreplies" value="'.$lineboard['maxreplies'].'" />
					<div class="desc">'. _gettext('The number of replies a thread can have before autosaging to the back of the board.') . ' '. _gettext('Default') .': <strong>200</strong></div><br />';

					/* Header image */
					$tpl_page .= '<label for="image">'. _gettext('Header image') .':</label>
					<input type="text" name="image" value="'.$lineboard['image'].'" />
					<div class="desc">'. _gettext('Overrides the header set in the config file. Leave blank to use configured global header image. Needs to be a full url including http://. Set to none to show no header image.') .'</div><br />';

					/* Include header */
					$tpl_page .= '<label for="includeheader">'. _gettext('Include header') .':</label>
					<textarea name="includeheader" rows="12" cols="80">'.htmlspecialchars($lineboard['includeheader']).'</textarea>
					<div class="desc">'. _gettext('Raw HTML which will be inserted at the top of each page of the board.') .'</div><br />';

					/* Anonymous */
					$tpl_page .= '<label for="anonymous">'. _gettext('Anonymous') .':</label>
					<input type="text" name="anonymous" value="'. $lineboard['anonymous'] . '" />
					<div class="desc">'. _gettext('Name to display when a name is not attached to a post.') . ' '. _gettext('Default') .': <strong>'. _gettext('Anonymous') .'</strong></div><br />';

					/* Locked */
					$tpl_page .= '<label for="locked">'. _gettext('Locked') .':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('Only moderators of the board and admins can make new posts/replies') .'</div><br />';

					/* Show ID */
					$tpl_page .= '<label for="showid">'. _gettext('Show ID') .':</label>
					<input type="checkbox" name="showid" ';
					if ($lineboard['showid'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If enabled, each post will display the poster\'s ID, which is a representation of their IP address.') .'</div><br />';

					/* Show ID */
					$tpl_page .= '<label for="compactlist">'. _gettext('Compact list') .':</label>
					<input type="checkbox" name="compactlist" ';
					if ($lineboard['compactlist'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('Text boards only. If enabled, the list of threads displayed on the front page will be formatted differently to be compact.') . '</div><br />';

					/* Enable reporting */
					$tpl_page .= '<label for="enablereporting">'. _gettext('Enable reporting') .':</label>
					<input type="checkbox" name="enablereporting"';
					if ($lineboard['enablereporting'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Reporting allows users to report posts, adding the post to the report list.') .' '. _gettext('Default') .': <strong>'. _gettext('Yes') .'</strong></div><br />';

					/* Locale */;
					$locales = explode('|', KU_SUPPORTED_LOCALES);
					$tpl_page .= '<label for="locale">'. _gettext('Locale') .':</label><select name="locale">';
					foreach($locales as $loc) {
						$tpl_page .= '<option value="'.$loc.'"';
						if($loc == $lineboard['locale']) $tpl_page .= ' selected="selected"';
						$tpl_page .= '>'.$loc.'</option>';
					}
					$tpl_page .= '</select><br />';

					/* Countryballs */
					$tpl_page .= '<label for="balls">'. _gettext('Countryballs') .':</label>
					<input type="checkbox" name="balls"';
					if ($lineboard['balls'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add countryballs to posts corresponding to poster\'s country (provided by Cloudflare).') .'</div><br />';

					/* Dice */
					$tpl_page .= '<label for="dice">'. _gettext('Dice') .':</label>
					<input type="checkbox" name="dice"';
					if ($lineboard['dice'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add ##dice## feature.') .'</div><br />';

					/* Useragent */
					$tpl_page .= '<label for="useragent">'. _gettext('Useragent') .':</label>
					<input type="checkbox" name="useragent"';
					if ($lineboard['useragent'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add ##Useragent## feature') .'</div><br />';

					/* Enable captcha */
					$tpl_page .= '<label for="enablecaptcha">'. _gettext('Enable captcha') .':</label>
					<input type="checkbox" name="enablecaptcha"';
					if ($lineboard['enablecaptcha'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('Enable/disable captcha system for this board. If captcha is enabled, in order for a user to post, they must first correctly enter the text on an image.') .' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Enable catalog */
					$tpl_page .= '<label for="enablecatalog">'. _gettext('Enable catalog') .':</label>
					<input type="checkbox" name="enablecatalog"';
					if ($lineboard['enablecatalog'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, a catalog.html file will be built with the other files, displaying the original picture of every thread in a box. This will only work on normal/oekaki imageboards.') .' '. _gettext('Default') .': <strong>'. _gettext('Yes') .'</strong></div><br />';

					/* Enable "no file" posting */
					$tpl_page .= '<label for="enablenofile">'. _gettext('Enable \'no file\' posting') .':</label>
					<input type="checkbox" name="enablenofile"';
					if ($lineboard['enablenofile'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, new threads will not require an image to be posted.') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Redirect to thread */
					$tpl_page .= '<label for="redirecttothread">'. _gettext('Redirect to thread') .':</label>
					<input type="checkbox" name="redirecttothread"';
					if ($lineboard['redirecttothread'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, users will be redirected to the thread they replied to/posted after posting. If set to no, users will be redirected to the first page of the board.') . ' '. _gettext('Default') .': <strong>'.('No') .'</strong></div><br />';

					/* Forced anonymous */
					$tpl_page .= '<label for="forcedanon">'. _gettext('Forced anonymous') .':</label>
					<input type="checkbox" name="forcedanon"';
					if ($lineboard['forcedanon'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, users will not be allowed to enter a name, making everyone appear as Anonymous') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Trial */
					$tpl_page .= '<label for="trial">'. _gettext('Trial') .':</label>
					<input type="checkbox" name="trial"';
					if ($lineboard['trial'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, this board will appear in italics in the menu') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Popular */
					$tpl_page .= '<label for="popular">'. _gettext('Popular') .':</label>
					<input type="checkbox" name="popular"';
					if ($lineboard['popular'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, this board will appear in bold in the menu') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Default style */
					$tpl_page .= '<label for="defaultstyle">'. _gettext('Default style') .':</label>
					<select name="defaultstyle">

					<option value=""';
					$tpl_page .= ($lineboard['defaultstyle'] == '') ? ' selected="selected"' : '';
					$tpl_page .= '>'._gettext('Use Default').'</option>';

					$styles = explode(':', KU_STYLES);
					$tpl_page .= '<optgroup label="'._gettext('Default Styles').'">';
					foreach ($styles as $stylesheet) {
						$tpl_page .= '<option value="'. $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected="selected"' : '';
						$tpl_page .= '>'. ucfirst($stylesheet) . '</option>';
					}
					$tpl_page .= '</optgroup>';

					$tpl_page .= '</select>
					<div class="desc">'. _gettext('The style which will be set when the user first visits the board.') .' '. _gettext('Default') .': <strong>'. _gettext('Use Default') .'</strong></div><br />';

					/* Submit form */
					$tpl_page .= '<input type="submit" value="Обновить доску" />
					</form>
					</div><br />';
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_POST['board'] . '</strong>.';
			}
		} else {
			$tpl_page .= '<form action="?action=boardopts" method="post">
			<label for="board">'. _gettext('Board') .':</label>' .
			$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
			'<input type="submit" value="'. _gettext('Go') .'" />
			</form>';
		}
	}

	function boardopts_mod() {
		global $tc_db, $tpl_page;

		$_POST['desc'] = htmlspecialchars($_POST['desc']);

		$this->BoardOwnersOnly();

		$tpl_page .= '<h2>'. _gettext('Board options') . '</h2><br />';
		if (isset($_GET['updateboard'])) {
      	    $this->CheckToken($_POST['token']);
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . " LIMIT 1");
			if ($boardid != '') {
				$available_styles = explode(':', KU_STYLES);
				if($_POST['defaultstyle'] == '' || in_array($_POST['defaultstyle'], $available_styles)) {
					$filetypes = array();
					while (list($postkey, $postvalue) = each($_POST)) {
						if (substr($postkey, 0, 9) == 'filetype_') {
							$filetypes[] = substr($postkey, 9);
						}
					}
					$updateboard_enablenofile = isset($_POST['enablenofile']) ? '1' : '0';
					$updateboard_forcedanon = isset($_POST['forcedanon']) ? '1' : '0';
					$updateboard_showid = isset($_POST['showid']) ? '1' : '0';
					$updateboard_locked = isset($_POST['locked']) ? '1' : '0';
					$updateboard_balls = isset($_POST['balls']) ? '1' : '0';
					$updateboard_dice = isset($_POST['dice']) ? '1' : '0';
					$updateboard_useragent = isset($_POST['useragent']) ? '1' : '0';

					if ($_POST['uploadtype'] == '0' || $_POST['uploadtype'] == '1' || $_POST['uploadtype'] == '2') {
						if(in_array($_POST['locale'], explode('|', KU_SUPPORTED_LOCALES))) {
							if(count($_POST['allowedembeds']) > 0) {
								$updateboard_allowedembeds = '';

								$results = $tc_db->GetAll("SELECT `filetype` FROM `" . KU_DBPREFIX . "embeds`");
								foreach ($results as $line) {
									if(in_array($line['filetype'], $_POST['allowedembeds'])) {
										$updateboard_allowedembeds .= $line['filetype'].',';
									}
								}
								if ($updateboard_allowedembeds != '') {
									$updateboard_allowedembeds = substr($updateboard_allowedembeds, 0, -1);
								}
							}
							$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "boards` SET `uploadtype` = " . $tc_db->qstr($_POST['uploadtype']) . " , `desc` = " . $tc_db->qstr($_POST['desc']) . " , `showid` = '" . $updateboard_showid . "' , `locked` = '" . $updateboard_locked . "' , `anonymous` = " . $tc_db->qstr($_POST['anonymous']) . " , `balls` = '" . $updateboard_balls . "' , `dice` = '" . $updateboard_dice . "' , `useragent` = '" . $updateboard_useragent . "' , `forcedanon` = '" . $updateboard_forcedanon . "' , `embeds_allowed` = " . $tc_db->qstr($updateboard_allowedembeds) . " , `locale` = " . $tc_db->qstr($_POST['locale']) . " , `defaultstyle` = " . $tc_db->qstr($_POST['defaultstyle']) . " , `enablenofile` = '" . $updateboard_enablenofile . "' WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . "");
							$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $boardid . "'");
							foreach ($filetypes as $filetype) {
								$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "board_filetypes` ( `boardid`, `typeid` ) VALUES ( '" . $boardid . "', " . $tc_db->qstr($filetype) . " )");
							}
							require_once KU_ROOTDIR . 'inc/classes/menu.class.php';
							$menu_class = new Menu();
							$menu_class->Generate();
							$tpl_page .= _gettext('Update successful.');
							//$tpl_page .= '<br><h2>Не забудь пересобрать все доски!</h2><br>';

							management_addlogentry(_gettext('Updated board configuration') . " - /" . $_GET['updateboard'] . "/", 4);
						}
						else $tpl_page .= _gettext('Sorry, a generic error has occurred.');
					}
					else {
						$tpl_page .= _gettext('Sorry, a generic error has occurred.');
					}
				}
				else {
					$tpl_page .= _gettext('Integer values must be entered correctly.');
				}
			}
			else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_GET['updateboard'] . '</strong>.';
			}
		}
		elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (count($resultsboard) > 0) {
				foreach ($resultsboard as $lineboard) {
					$tpl_page .= '<div class="container">
					<form action="?action=boardopts_mod&updateboard='.urlencode($_POST['board']).'" method="post">
          <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />';
					/* Directory */
					$tpl_page .= '<label for="board">'. _gettext('Directory') .':</label>
					<input type="text" name="board" value="'.$_POST['board'].'" disabled />
					<div class="desc">'. _gettext('The directory of the board.') .'</div><br />';

					/* Description */
					$tpl_page .= '<label for="desc">'. _gettext('Description') .':</label>
					<input type="text" name="desc" value="'.$lineboard['desc'].'" />
					<div class="desc">'. _gettext('The name of the board.') .'</div><br />';

					/* Upload type */
					$tpl_page .= '<label for="uploadtype">'. _gettext('Upload type') .':</label>
					<select name="uploadtype">
					<option value="0"';
					if ($lineboard['uploadtype'] == '0') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('No embedding') .'</option>
					<option value="1"';
					if ($lineboard['uploadtype'] == '1') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('Images and embedding') .'</option>
					<option value="2"';
					if ($lineboard['uploadtype'] == '2') {
						$tpl_page .= ' selected="selected"';
					}
					$tpl_page .= '>'. _gettext('Embedding only') .'</option>
					</select>
					<div class="desc">'. _gettext('Whether or not to allow embedding of videos.') .' '. _gettext('Default') .'.: <strong>'. _gettext('No Embedding') .'</strong></div><br />';

					/* Allowed filetypes */
					$tpl_page .= '<label>'. _gettext('Allowed filetypes') .':</label>
					<div class="desc">'. _gettext('What filetypes users are allowed to upload.') .'</div><br />';
					$filetypes = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype` FROM `" . KU_DBPREFIX . "filetypes` ORDER BY `filetype` ASC");
					foreach ($filetypes as $filetype) {
						$tpl_page .= '<label for="filetype_'. $filetype['id'] . '">'. strtoupper($filetype['filetype']) . '</label><input type="checkbox" name="filetype_'. $filetype['id'] . '"';
						$filetype_isenabled = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "board_filetypes` WHERE `boardid` = '" . $lineboard['id'] . "' AND `typeid` = '" . $filetype['id'] . "' LIMIT 1");
						if ($filetype_isenabled == 1) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= ' /><br />';
					}

					/* Allowed embeds */
					$tpl_page .= '<label>'. _gettext('Allowed embeds') .':</label>
					<div class="desc">'. _gettext('What embed sites are allowed on this board. Only useful on board with embedding enabled.') .'</div><br />';
					$embeds = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `filetype`, `name` FROM `" . KU_DBPREFIX . "embeds` ORDER BY `filetype` ASC");
					foreach ($embeds as $embed) {
						$tpl_page .= '<label for="allowedembeds[]">'. $embed['name'] . '</label><input type="checkbox" name="allowedembeds[]" value="'. $embed['filetype'] . '"';
						if (in_array($embed['filetype'], explode(',', $lineboard['embeds_allowed']))) {
							$tpl_page .= ' checked';
						}
						$tpl_page .= ' /><br />';
					}

					/* Anonymous */
					$tpl_page .= '<label for="anonymous">'. _gettext('Anonymous') .':</label>
					<input type="text" name="anonymous" value="'. $lineboard['anonymous'] . '" />
					<div class="desc">'. _gettext('Name to display when a name is not attached to a post.') . ' '. _gettext('Default') .': <strong>'. _gettext('Anonymous') .'</strong></div><br />';

					/* Locked */
					$tpl_page .= '<label for="locked">'. _gettext('Locked') .':</label>
					<input type="checkbox" name="locked" ';
					if ($lineboard['locked'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('Only moderators of the board and admins can make new posts/replies') .'</div><br />';

					/* Show ID */
					$tpl_page .= '<label for="showid">'. _gettext('Show ID') .':</label>
					<input type="checkbox" name="showid" ';
					if ($lineboard['showid'] == '1') {
						$tpl_page .= 'checked ';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If enabled, each post will display the poster\'s ID, which is a representation of their IP address.') .'</div><br />';

					/* Enable "no file" posting */
					$tpl_page .= '<label for="enablenofile">'. _gettext('Enable \'no file\' posting') .':</label>
					<input type="checkbox" name="enablenofile"';
					if ($lineboard['enablenofile'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, new threads will not require an image to be posted.') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Locale */;
					$locales = explode('|', KU_SUPPORTED_LOCALES);
					$tpl_page .= '<label for="locale">'. _gettext('Locale') .':</label><select name="locale">';
					foreach($locales as $loc) {
						$tpl_page .= '<option value="'.$loc.'"';
						if($loc == $lineboard['locale']) $tpl_page .= ' selected="selected"';
						$tpl_page .= '>'.$loc.'</option>';
					}
					$tpl_page .= '</select><br />';

					/* Countryballs */
					$tpl_page .= '<label for="balls">'. _gettext('Countryballs') .':</label>
					<input type="checkbox" name="balls"';
					if ($lineboard['balls'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add countryballs to posts corresponding to poster\'s country (provided by Cloudflare).') .'</div><br />';

					/* Dice */
					$tpl_page .= '<label for="dice">'. _gettext('Dice') .':</label>
					<input type="checkbox" name="dice"';
					if ($lineboard['dice'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add ##dice## feature.') .'</div><br />';

					/* Useragent */
					$tpl_page .= '<label for="useragent">'. _gettext('Useragent') .':</label>
					<input type="checkbox" name="useragent"';
					if ($lineboard['useragent'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />'. "\n" .
					'<div class="desc">'. _gettext('Add ##Useragent## feature') .'</div><br />';

					/* Forced anonymous */
					$tpl_page .= '<label for="forcedanon">'. _gettext('Forced anonymous') .':</label>
					<input type="checkbox" name="forcedanon"';
					if ($lineboard['forcedanon'] == '1') {
						$tpl_page .= ' checked';
					}
					$tpl_page .= ' />
					<div class="desc">'. _gettext('If set to yes, users will not be allowed to enter a name, making everyone appear as Anonymous') . ' '. _gettext('Default') .': <strong>'. _gettext('No') .'</strong></div><br />';

					/* Default style */
					$tpl_page .= '<label for="defaultstyle">'. _gettext('Default style') .':</label>
					<select name="defaultstyle">

					<option value=""';
					$tpl_page .= ($lineboard['defaultstyle'] == '') ? ' selected="selected"' : '';
					$tpl_page .= '>'._gettext('Use Default').'</option>';

					$styles = explode(':', KU_STYLES);
					$tpl_page .= '<optgroup label="'._gettext('Default Styles').'">';
					foreach ($styles as $stylesheet) {
						$tpl_page .= '<option value="'. $stylesheet . '"';
						$tpl_page .= ($lineboard['defaultstyle'] == $stylesheet) ? ' selected="selected"' : '';
						$tpl_page .= '>'. ucfirst($stylesheet) . '</option>';
					}
					$tpl_page .= '</optgroup>';

					$tpl_page .= '</select><div class="desc">'. _gettext('The style which will be set when the user first visits the board.').'<br>'._gettext('For custom styles, the custom style will be set until user choose otherwise.').'<br />';

					/* Submit form */
					$tpl_page .= '<br><input type="submit" value="'. _gettext('Update') .'" />

					</form>
					</div><br />';
				}
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_POST['board'] . '</strong>.';
			}
		} else {
			$tpl_page .= '<form action="?action=boardopts_mod" method="post">
			<label for="board">'. _gettext('Board') .':</label>' .
			$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
			'<input type="submit" value="'. _gettext('Go') .'" />
			</form>';
		}
	}

	//Checks if file is 100x300 gif png or jpg
	function CheckValidBanner($file) {
		//first we check the file extension
		if($file['error'] === UPLOAD_ERR_OK) {
			$path_parts = pathinfo($file["name"]);
			$extension = strtolower($path_parts['extension']);
			if(in_array($extension, array('jpg', 'jpeg', 'gif', 'png', 'webp'))) {
				//then we check actual type
				$size = getimagesize($file['tmp_name']);
				if($size[0] == 300 && $size[1] == 100) {
					if(($size[2]==IMAGETYPE_PNG && $extension == 'png') || ($size[2]==IMAGETYPE_WEBP && $extension == 'webp') || ($size[2]==IMAGETYPE_GIF && $extension == 'gif') || ($size[2]==IMAGETYPE_JPEG && ($extension == 'jpg' || $extension == 'jpeg'))) {
						return array('status'=>'success', 'extension'=>'.'.$extension);
					}
					else return array('status'=>'error', 'msg'=>_gettext('A common cause for this is an incorrect extension when the file is actually of a different type.'));
				}
				else return array('status'=>'error', 'msg'=>_gettext('Wrong file dimensions. Must be ').'300 x 100');
			}
			else return array('status'=>'error', 'msg'=>_gettext('Wrong file type. Allowed file types').': JPG, PNG, GIF.');
		}
		else return array('status'=>'error', 'msg'=>_gettext('Internal error.'));
	}

	function validurl($url) {
		return preg_match('#([^:]|^)(http://|https://|ftp://)([^(\s<|\[)]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#u',$url);
	}

	function unstickypost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Manage stickies') . '</h2><br />';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
						$sticky_board_id = $line['id'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $sticky_board_id ." AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `stickied` = '0' WHERE `boardid` = " . $sticky_board_id ." AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$tpl_page .= _gettext('Thread successfully un-stickied');

						management_addlogentry(_gettext('Unstickied thread') . ' #' . intval($_GET['postid']) . ' - /' . $sticky_board_name . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID. This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr />';
			}
		}
		$tpl_page .= $this->stickyforms();
	}

	function ctype_alnum_($string) {
		return ctype_alnum(str_replace('_', '', $string));
	}

	function stickypost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Manage stickies') . '</h2><br />';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$sticky_board_name = $line['name'];
						$sticky_board_id = $line['id'];
					}
					$result = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $sticky_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if ($result > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `stickied` = '1' WHERE `boardid` = " . $sticky_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$tpl_page .= _gettext('Thread successfully stickied.');

						management_addlogentry(_gettext('Stickied thread') . ' #' . intval($_GET['postid']) . ' - /' . $sticky_board_name . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID. This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr />';
			}
		}
		$tpl_page .= $this->stickyforms();
	}

	/* Create forms for stickying a post */
	function stickyforms() {
		global $tc_db;

		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>'. _gettext('Sticky') . '</h1></td><td width="50%"><h1>'. _gettext('Unsticky') . '</h1></td></tr>
		<tr><td style="vertical-align:top;"><br />

		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="stickypost" />
		<label for="board">'. _gettext('Board') .':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br />

		<label for="postid">'. _gettext('Thread') .':</label>
		<input type="text" name="postid" /><br />

		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'. _gettext('Sticky') .'" />
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `name`, `id` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/'. $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line_board['id'] . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `stickied` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unstickypost&board='. $line_board['name'] . '&postid='. $line['id'] . '">#'. $line['id'] . '</a><br />';
				}
			} else {
				$output .= 'No stickied threads.<br />';
			}
		}
		$output .= '</td></tr></table>';

		return $output;
	}

	function lockpost() {
		global $tc_db, $tpl_page, $board_class;
		$this->ModeratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Manage locked threads') . '</h2><br />';
		if (isset($_GET['postid']) && isset($_GET['board'])) {
			if ($_GET['postid'] > 0 && $_GET['board'] != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
				if (count($results) > 0) {
					if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
						exitWithErrorPage(_gettext('You are not a moderator of this board.'));
					}
					foreach ($results as $line) {
						$lock_board_name = $line['name'];
						$lock_board_id = $line['id'];
					}
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lock_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					if (count($results) > 0) {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `locked` = '1' WHERE `boardid` = " . $lock_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
						$tpl_page .= _gettext('Thread successfully locked.');

						management_addlogentry(_gettext('Locked thread') . ' #'. intval($_GET['postid']) . ' - /'. intval($_GET['board']) . '/', 5);
					} else {
						$tpl_page .= _gettext('Invalid thread ID. This may have been caused by the thread recently being deleted.');
					}
				} else {
					$tpl_page .= _gettext('Invalid board directory.');
				}
				$tpl_page .= '<hr />';
			}
		}
		$tpl_page .= $this->lockforms();
	}

	function unlockpost() {
		global $tc_db, $tpl_page, $board_class;

		$tpl_page .= '<h2>'. _gettext('Manage locked threads') . '</h2><br />';
		if ($_GET['postid'] > 0 && $_GET['board'] != '') {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['board']) . "");
			if (count($results) > 0) {
				if (!$this->CurrentUserIsModeratorOfBoard($_GET['board'], $_SESSION['manageusername'])) {
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line) {
					$lock_board_name = $line['name'];
					$lock_board_id = $line['id'];
				}
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lock_board_id . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
				if (count($results) > 0) {
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "posts` SET `locked` = '0' WHERE `boardid` = " . $lock_board_id . " AND `parentid` = '0' AND `id` = " . $tc_db->qstr($_GET['postid']) . "");
					management_addlogentry(_gettext('Unlocked thread') . ' #'. intval($_GET['postid']) . ' - /'. intval($_GET['board']) . '/', 5);
				} else {
					$tpl_page .= _gettext('Invalid thread ID. This may have been caused by the thread recently being deleted.');
				}
			} else {
				$tpl_page .= _gettext('Invalid board directory.');
			}
			$tpl_page .= '<hr />';
		}
		$tpl_page .= $this->lockforms();
	}

	function lockforms() {
		global $tc_db;

		$output = '<table width="100%" border="0">
		<tr><td width="50%"><h1>'. _gettext('Lock') . '</h1></td><td width="50%"><h1>'. _gettext('Unlock') . '</h1></td></tr>
		<tr><td><br />

		<form action="manage_page.php" method="get"><input type="hidden" name="action" value="lockpost" />
		<label for="board">'. _gettext('Board') .':</label>' .
		$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
		'<br />

		<label for="postid">'. _gettext('Thread') .':</label>
		<input type="text" name="postid" /><br />

		<label for="submit">&nbsp;</label>
		<input name="submit" type="submit" value="'. _gettext('Lock') .'" />
		</form>
		</td><td>';
		$results_boards = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		foreach ($results_boards as $line_board) {
			$output .= '<h2>/'. $line_board['name'] . '/</h2>';
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $line_board['id'] . " AND `IS_DELETED` = '0' AND `parentid` = '0' AND `locked` = '1'");
			if (count($results) > 0) {
				foreach ($results as $line) {
					$output .= '<a href="?action=unlockpost&board='. $line_board['name'] . '&postid='. $line['id'] . '">#'. $line['id'] . '</a><br />';
				}
			} else {
				$output .= 'No locked threads.<br />';
			}
		}
		$output .= '</td></tr></table>';

		return $output;
	}

	/* Delete a post, or multiple posts */
	function delposts($multidel=false)
	{
		global $tc_db, $tpl_page, $board_class;

		$isquickdel = false;
		if (isset($_POST['boarddir']) || isset($_GET['boarddir']))
		{
			if (!isset($_GET['boarddir']) && isset($_POST['boarddir']))
			{
				$this->CheckToken($_POST['token']);
			}
			if (isset($_GET['boarddir']))
			{
				$isquickdel = true;
				$_POST['boarddir'] = $_GET['boarddir'];
				if (isset($_GET['delthreadid']))
				{
					$_POST['delthreadid'] = $_GET['delthreadid'];
				}
				if (isset($_GET['delpostid']))
				{
					$_POST['delpostid'] = $_GET['delpostid'];
				}
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['boarddir']) . "");
			if (count($results) > 0)
			{
				if (!$this->CurrentUserIsModeratorOfBoard($_POST['boarddir'], $_SESSION['manageusername']))
				{
					exitWithErrorPage(_gettext('You are not a moderator of this board.'));
				}
				foreach ($results as $line)
				{
					$board_id = $line['id'];
					$board_dir = $line['name'];
				}
				if (isset($_GET['cp']))
				{
					$cp = '&amp;cp=y&amp;instant=y';
				}

				if (isset($_POST['delpostid']))
				{
					if ($_POST['delpostid'] > 0)
					{
						$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_id . " AND `IS_DELETED` = '0' AND `id` = " . $tc_db->qstr($_POST['delpostid']) . "");
						if (count($results) > 0)
						{
							if (isset($_POST['fileonly']))
							{
								foreach ($results as $line)
								{
									if (!empty($line['file']))
									{
										$del = @unlink(KU_ROOTDIR . $_POST['boarddir'] . '/src/'. $line['file'] . '.'. $line['file_type']);
										if ($del)
										{
											@unlink(KU_ROOTDIR . $_POST['boarddir'] . '/thumb/'. $line['file'] . 's.'. $line['file_type']);
											$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `file` = 'removed', `file_md5` = '' WHERE `boardid` = " . $board_id . " AND `id` = ".$_POST['delpostid']." LIMIT 1");
											$tpl_page .= '<hr />File successfully deleted<hr />';
										}
										else
										{
											$tpl_page .= '<hr />That file has already been deleted.<hr />';
										}
									}
									else
									{
										$tpl_page .= '<hr />Error: That thread doesn\'t have a file associated with it.<hr />';
									}
								}
							}
							else
							{
								foreach ($results as $line)
								{
									$delpost_id = $line['id'];
									$delpost_parentid = $line['parentid'];
								}
								$post_class = new Post($delpost_id, $board_dir, $board_id);
								$result = $post_class->Delete();
								unset($post_class);
								if ($result === true)
								{
									if ($delpost_parentid == 0)
									{
										$tpl_page .= 'Тред '.$delpost_id.' успешно удалён.';
									}
									else
									{
										$tpl_page .= 'Пост '.$delpost_id.' успешно удалён.';
									}
								}
								else
								{
									$tpl_page = 'Невозможно удалить пост '.$delpost_id.': ' . $result;
								}

								if ($delpost_parentid == 0)
								{
									management_addlogentry('Удалён тред' . ' #<a href="?action=viewthread&thread='. $delpost_parentid . '&board='. $_POST['boarddir'] . '#'. $delpost_id . '">'. $delpost_id . '</a> - /'. $board_dir . '/', 7);
								}
								else
								{
									management_addlogentry('Удалён пост' . ' #<a href="?action=viewthread&thread='. $delpost_parentid . '&board='. $_POST['boarddir'] . '#'. $delpost_id . '">'. $delpost_id . '</a> - /'. $board_dir . '/', 7);
								}

								if (isset($_GET['do_ban']))
								{
									$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $delpost_id .'"><a href="'. KU_CGIPATH . '/manage_page.php?action=bans&banboard='. $_GET['boarddir'] . '&banpost='. $delpost_id . '">'. _gettext('Redirecting') . '</a> to ban page...';
								}
								elseif ($isquickdel && !isset($_GET['noreturn']))
								{
									if ($delpost_parentid > 0)
									{
										$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/res/'. $delpost_parentid . '.html"><a href="'. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '/res/'. $delpost_parentid . '.html">'. _gettext('Redirecting') . '</a> back to thread...';
									}
									else
									{
										$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '"><a href="'. KU_BOARDSPATH . '/'. $_GET['boarddir'] . '">'. _gettext('Redirecting') . '</a> back to board...';
									}
								}
							}
						}
						else
						{
							$tpl_page .= _gettext('Invalid thread ID '.$delpost_id.'. This may have been caused by the thread recently being deleted.');
						}
					}
				}
			}
			else
			{
				$tpl_page .= _gettext('Invalid board directory.');
			}
		}
		$tpl_page .= '<h2>'. _gettext('Delete thread/post') . '</h2><br />';
		if (!$multidel)
		{
			$tpl_page .= '<form action="manage_page.php?action=delposts" method="post">
			<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<label for="boarddir">'. _gettext('Board') .':</label>' .
			$this->MakeBoardListDropdown('boarddir', $this->BoardList($_SESSION['manageusername'])) .
			'<br />

			<label for="delpostid">'. 'Тред или пост' .':</label>
			<input type="text" name="delpostid" /><br />'.

			'<label for="fileonly">'. _gettext('File Only') .':</label>
			<input type="checkbox" id="fileonly" name="fileonly" /><br />

			<input type="submit" value="'. _gettext('Delete post') .'" />

			</form>';
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Moderation Pages
	* +------------------------------------------------------------------------------+
	*/

		/* View and delete reports */
	function reports() {
		global $tc_db, $tpl_page;
		$this->ModeratorsOnly();

		$tpl_page .= '<h2>'. _gettext('Reports') . '</h2><br />';
		if (isset($_GET['clear'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "reports` WHERE `id` = " . $tc_db->qstr($_GET['clear']) . " LIMIT 1");
			if (count($results) > 0) {
				$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = '1' WHERE `id` = " . $tc_db->qstr($_GET['clear']));
				$tpl_page .= 'Report successfully cleared.<hr />';
			}
		}
		$query = "SELECT * FROM `" . KU_DBPREFIX . "reports` WHERE `cleared` = 0";
		if (!$this->CurrentUserIsAdministrator()) {
			$boardlist = $this->BoardList($_SESSION['manageusername']);
			if (!empty($boardlist)) {
				$query .= ' AND (';
				foreach ($boardlist as $board) {
					$query .= ' `board` = \''. $board['name'] .'\' OR';
				}
				$query = substr($query, 0, -3) . ')';
			} else {
				$tpl_page .= _gettext('You do not moderate any boards.');
			}
		}
		$resultsreport = $tc_db->GetAll($query);
		if (count($resultsreport) > 0) {
			$tpl_page .= '<table border="1" width="100%"><tr><th>Board</th><th>Post</th><th>File</th><th>Message</th><th>Reason</th><th>Reporter IP</th><th>Action</th></tr>';
			foreach ($resultsreport as $linereport) {
				$reportboardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($linereport['board']) . "");
				$results = $tc_db->GetAll("SELECT * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $reportboardid . " AND `id` = " . $tc_db->qstr($linereport['postid']) . "");
				foreach ($results as $line) {
					if ($line['IS_DELETED'] == 0) {
						$tpl_page .= '<tr><td>/'. $linereport['board'] . '/</td><td><a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/res/';
						if ($line['parentid'] == '0') {
							$tpl_page .= $linereport['postid'];
							$post_threadorpost = 'thread';
						} else {
							$tpl_page .= $line['parentid'];
							$post_threadorpost = 'post';
						}
						$tpl_page .= '.html#'. $linereport['postid'] . '">'. $line['id'] . '</a></td><td>';
						if ($line['file'] == 'removed') {
							$tpl_page .= 'removed';
						} elseif ($line['file'] == '') {
							$tpl_page .= 'none';
						} elseif ($line['file_type'] == 'jpg' || $line['file_type'] == 'gif' || $line['file_type'] == 'webp' || $line['file_type'] == 'png') {
							$tpl_page .= '<a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '"><img src="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '" border="0"></a>';
						} else {
							$tpl_page .= '<a href="'. KU_BOARDSPATH . '/'. $linereport['board'] . '/src/'. $line['file'] . '.'. $line['file_type'] . '">File</a>';
						}
						$tpl_page .= '</td><td>';
						if ($line['message'] != '') {
							$tpl_page .= stripslashes($line['message']);
						} else {
							$tpl_page .= '&nbsp;';
						}
						$tpl_page .= '</td><td>';
						if ($linereport['reason'] != '') {
							$tpl_page .= htmlspecialchars(stripslashes($linereport['reason']));
						} else {
							$tpl_page .= '&nbsp;';
						}
						$tpl_page .= '</td><td>'. md5_decrypt($linereport['ip'], KU_RANDOMSEED) . '</td><td><a href="?action=reports&clear='. $linereport['id'] . '">Clear</a>&nbsp;&#91;<a href="?action=delposts&boarddir='. $linereport['board'] . '&del'. $post_threadorpost . 'id='. $line['id'] . '" title="Delete" onclick="return confirm(\'Are you sure you want to delete this thread/post?\');">D</a>&nbsp;<a href="'. KU_CGIPATH . '/manage_page.php?action=delposts&boarddir='. $linereport['board'] . '&del'. $post_threadorpost . 'id='. $line['id'] . '&postid='. $line['id'] . '" title="Delete &amp; Ban" onclick="return confirm(\'Are you sure you want to delete and ban this poster?\');">&amp;</a>&nbsp;<a href="?action=bans&banboard='. $linereport['board'] . '&banpost='. $line['id'] . '" title="Ban">B</a>&#93;</td></tr>';
					} else {
						$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "reports` SET `cleared` = 1 WHERE id = " . $linereport['id'] . "");
					}
				}
			}
			$tpl_page .= '</table>';
		} else {
			$tpl_page .= 'No reports to show.';
		}
	}

	/* Addition, modification, deletion, and viewing of bans */
	function bans() {
		global $tc_db, $tpl_page, $bans_class;

		$this->ModeratorsOnly();
		$reason = KU_BANREASON;
		$ban_ip = ''; $ban_hash = ''; $ban_parentid = 0; $multiban = Array();
		if (isset($_POST['modban']) && is_array($_POST['post']) && $_POST['board']) {
			$ban_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (!empty($ban_board_id)) {
				foreach ( $_POST['post'] as $post ) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $ban_board_id . "' AND `id` = " . intval($post) . "");
					if (count($results) > 0) {
						$multiban[] = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
						$ban_hash = $results[0]['banimage_md5'];
					    if ($ban_hash == '') $ban_hash = $results[0]['file_md5'];
						$multiban_hash[] = $ban_hash;
						$multiban_parentid[] = $results[0]['parentid'];
					}
				}
			}
		}
		if (isset($_GET['banboard']) && isset($_GET['banpost'])) {
			$ban_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['banboard']) . "");
			$ban_board = $_GET['banboard'];
			$ban_post_id = $_GET['banpost'];
			if (!empty($ban_board_id)) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = '" . $ban_board_id . "' AND `id` = " . $tc_db->qstr($_GET['banpost']) . "");
				if (count($results) > 0) {
					if (isset($_GET['ban_img_by_post'])) {
						$ban_hash = $results[0]['banimage_md5'];
						if ($ban_hash == '') $ban_hash = $results[0]['file_md5'];
					}
					$ban_ip = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
					$ban_parentid = $results[0]['parentid'];
				} else {
					$tpl_page.= _gettext('A post with that ID does not exist.') . '<hr />';
				}
			}
		}
		$instantban = false;
		if ((isset($_GET['instant']) || isset($_GET['cp'])) && $ban_ip) {
			if (isset($_GET['cp'])) {
				$ban_reason = "You have been banned for posting Child Pornography. Your IP has been logged, and the proper authorities will be notified.";
			} else {
				if($_GET['reason']) {
					$ban_reason = urldecode($_GET['reason']);
				} else {
					$ban_Reason = KU_BANREASON;
				}
			}
			$instantban = true;
		}
		$tpl_page .= '<h2>'. _gettext('Bans') . '</h2><br />';
		if (((isset($_POST['ip']) || isset($_POST['multiban'])) && isset($_POST['seconds']) && (!empty($_POST['ip']) || (empty($_POST['ip']) && !empty($_POST['multiban'])))) || $instantban) {
			if ($_POST['seconds'] >= 0 || $instantban) {
				$banning_boards = array();
				$ban_boards = '';
				if (isset($_POST['banfromall']) || $instantban) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `name` FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						if (!$this->CurrentUserIsModeratorOfBoard($line['name'], $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $line['name'] . '/: '. _gettext('You can only make bans applying to boards you moderate.'));
						}
					}
				} else {
					if (empty($_POST['bannedfrom'])) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
					if(isset($_POST['deleteposts'])) {
						$_POST['deletefrom'] = $_POST['bannedfrom'];
					}
					foreach($_POST['bannedfrom'] as $board) {
						if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $board . '/: '. _gettext('You can only make bans applying to boards you moderate.'));
						}
					}
					$ban_boards = implode('|', $_POST['bannedfrom']);
				}
				if(!$this->CurrentUserIsAdministrator() && (isset($_POST['banfromall']) || isset($_POST['allowread']) || isset($_POST['type'])) )
					exitWithErrorPage(_gettext('Illegal action.'));
				else {
					if($this->CurrentUserIsAdministrator()) {
						$ban_globalban = (isset($_POST['banfromall']) || $instantban) ? 1 : 0;
						$ban_allowread = ($_POST['allowread'] == 0 || $instantban) ? 0 : 1;
						$ban_type = ($_POST['type'] <= 2 && $_POST['type'] >= 0) ? $_POST['type'] : 0;
					}
					else {
						$ban_globalban = 0;
						$ban_allowread = 1;
						$ban_type = 0;
					}
				}
				if (isset($_POST['quickbanboardid'])) {
					$ban_board_id = $_POST['quickbanboardid'];
				}
				if(isset($_POST['quickbanboard'])) {
					$ban_board = $_POST['quickbanboard'];
				}
				if(isset($_POST['quickbanpostid'])) {
					$ban_post_id = $_POST['quickbanpostid'];
				}
				$ban_ip = ($instantban) ? $ban_ip : $_POST['ip'];
				$ban_duration = ($_POST['seconds'] == 0 || $instantban) ? 0 : $_POST['seconds'];

				$ban_reason = ($instantban) ? $ban_reason : $_POST['reason'];
				$ban_note = ($instantban) ? '' : $_POST['staffnote'];
				$ban_appealat = 0;
				if (KU_APPEAL != '' && !$instantban) {
					$ban_appealat = intval($_POST['appealdays'] * 86400);
					if ($ban_appealat > 0) $ban_appealat += (time() + KU_ADDTIME);
				}
				if (isset($_POST['multiban']))
					$ban_ips = unserialize($_POST['multiban']);
				else
					$ban_ips = Array($ban_ip);
				$i = 0;
				foreach ($ban_ips as $ban_ip) {
					$ban_msg = '';
					$whitelist = $tc_db->GetAll("SELECT `ipmd5` FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = 2");
					if (in_array(md5($ban_ip), $whitelist)) {
						exitWithErrorPage(_gettext('That IP is on the whitelist'));
					}
					if ($bans_class->BanUser($ban_ip, $_SESSION['manageusername'], $ban_globalban, $ban_duration, $ban_boards, $ban_reason, $ban_note, $ban_appealat, $ban_type, $ban_allowread)) {
						$regenerated = array();
						if (((KU_BANMSG != '' || $_POST['banmsg'] != '') && isset($_POST['addbanmsg']) && (isset($_POST['quickbanpostid']) || isset($_POST['quickmultibanpostid']))) || $instantban ) {
							$ban_msg = ((KU_BANMSG == $_POST['banmsg']) || empty($_POST['banmsg'])) ? KU_BANMSG : $_POST['banmsg'];
							if (isset($ban_post_id))
								$postids = Array($ban_post_id);
							else
								$postids = unserialize($_POST['quickmultibanpostid']);
							$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `parentid`, `message` FROM `".KU_DBPREFIX."posts` WHERE `boardid` = " . $tc_db->qstr($ban_board_id) . " AND `id` = ".$tc_db->qstr($postids[$i])." LIMIT 1");

							foreach($results AS $line) {
								$tc_db->Execute("UPDATE `".KU_DBPREFIX."posts` SET `message` = ".$tc_db->qstr($line['message'] . $ban_msg)." WHERE `boardid` = " . $tc_db->qstr($ban_board_id) . " AND `id` = ".$tc_db->qstr($postids[$i]));
								clearPostCache($postids[$i], $ban_board_id);
								if ($line['parentid']==0) {
									if (!in_array($postids, $regenerated)) {
										$regenerated[] = $postids[$i];
									}
								} else {
									if (!in_array($line['parentid'], $regenerated)) {
										$regenerated[] = $line['parentid'];
									}
								}
							}
						}
						$tpl_page .= _gettext('Ban successfully placed.')."<br />";
					} else {
						exitWithErrorPage(_gettext('Sorry, a generic error has occurred.'));
					}

					$logentry = _gettext('Banned') . ' '. $ban_ip;
					$logentry .= ($ban_duration == 0) ? ' '. _gettext('without expiration') : ' '. _gettext('until') . ' '. date('F j, Y, g:i a', (time() + KU_ADDTIME) + $ban_duration);
					$logentry .= ' - '. _gettext('Reason') . ': '. $ban_reason . (($ban_note) ? (" (".$ban_note.")") : ("")). ' - '. _gettext('Banned from') . ': ';
					$logentry .= ($ban_globalban == 1) ? _gettext('All boards') . ' ' : '/'. implode('/, /', explode('|', $ban_boards)) . '/ ';
					management_addlogentry($logentry, 8);
					$ban_ip = '';
					$i++;
				}

				if(isset($_POST['deleteposts'])) {
					$tpl_page .= '<br />';
					$this->deletepostsbyip(true);
				}

				if ((isset($_GET['instant']) && !isset($_GET['cp']))) {
					die("success");
				}

				if (isset($_POST['banhashtime']) && $_POST['banhashtime'] !== '' && ($_POST['hash'] !== '' || isset($_POST['multibanhashes'])) && $_POST['banhashtime'] >= 0) {
					if (isset($_POST['multibanhashes']))
						$banhashes = unserialize($_POST['multibanhashes']);
					else
						$banhashes = Array($_POST['hash']);
					foreach ($banhashes as $banhash){
						$results = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `".KU_DBPREFIX."bannedhashes` WHERE `md5` = ".$tc_db->qstr($banhash)." LIMIT 1");
						if ($results == 0) {
							$tc_db->Execute("INSERT INTO `".KU_DBPREFIX."bannedhashes` ( `md5` , `bantime` , `description` ) VALUES ( ".$tc_db->qstr($banhash)." , ".$tc_db->qstr($_POST['banhashtime'])." , ".$tc_db->qstr($_POST['banhashdesc'])." )");
							management_addlogentry('Banned md5 hash '. $banhash . ' with a description of '. $_POST['banhashdesc'], 8);
						}
					}
				}
				if (!empty($_POST['quickbanboard']) && !empty($_POST['quickbanthreadid'])) {
					$tpl_page .= '<br /><br /><meta http-equiv="refresh" content="1;url='. KU_BOARDSPATH . '/'. $_POST['quickbanboard'] . '/';
					if ($_POST['quickbanthreadid'] != '0') $tpl_page .= 'res/'. $_POST['quickbanthreadid'] . '.html';
					$tpl_page .= '"><a href="'. KU_BOARDSPATH . '/'. $_POST['quickbanboard'] . '/';
					if ($_POST['quickbanthreadid'] != '0') $tpl_page .= 'res/'. $_POST['quickbanthreadid'] . '.html';
					$tpl_page .= '">'. _gettext('Redirecting') . '</a>...';
				}
			} else {
				$tpl_page .= _gettext('Please enter a positive amount of seconds, or zero for a permanent ban.');
			}
			$tpl_page .= '<hr />';
		} elseif (isset($_GET['delban']) && $_GET['delban'] > 0) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['delban']) . "");
			if (count($results) > 0) {
				$unban_ip = md5_decrypt($results[0]['ip'], KU_RANDOMSEED);
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['delban']) . "");
				$bans_class->UpdateHtaccess();
				$tpl_page .= _gettext('Ban successfully removed.');
				management_addlogentry(_gettext('Unbanned') . ' '. $unban_ip, 8);
			} else {
				$tpl_page .= _gettext('Invalid ban ID');
			}
			$tpl_page .= '<br /><hr />';
		} elseif (isset($_GET['delhashid'])) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = " . $tc_db->qstr($_GET['delhashid']) . "");
			if (count($results) > 0) {
				$tc_db->Execute("DELETE FROM `" . KU_DBPREFIX . "bannedhashes` WHERE `id` = " . $tc_db->qstr($_GET['delhashid']) . "");
				$tpl_page .= _gettext('Hash removed from ban list.') . '<br /><hr />';
			}
		}

		flush();

		$isquickban = false;

		$tpl_page .= '<form action="manage_page.php?action=bans" method="post" name="banform">';

		if ((!empty($ban_ip) && isset($_GET['banboard']) && isset($_GET['banpost'])) || (!empty($multiban) && isset($_POST['board']) && isset($_POST['post'])))  {
			$isquickban = true;
			$tpl_page .= '<input type="hidden" name="quickbanboard" value="'. (isset($_GET['banboard']) ? $_GET['banboard'] : $_POST['board']) . '" />';
			if(!empty($multiban)) {
				$tpl_page .= '<input type="hidden" name="quickbanboardid" value="'. $ban_board_id . '" /><input type="hidden" name="quickmultibanthreadid" value="'. htmlspecialchars(serialize($multiban_parentid)) . '" /><input type="hidden" name="quickmultibanpostid" value="'. htmlspecialchars(serialize($_POST['post'])) . '" />';
			} else {
				$tpl_page .= '<input type="hidden" name="quickbanboardid" value="'. $ban_board_id . '" /><input type="hidden" name="quickbanthreadid" value="'. $ban_parentid . '" /><input type="hidden" name="quickbanpostid" value="'. $_GET['banpost'] . '" />';
			}
		} elseif (isset($_GET['ip'])) {
			$ban_ip = $_GET['ip'];
		}

		$tpl_page .= '<fieldset>
		<legend>'. _gettext('IP address and ban type') . '</legend>
		<label for="ip">'. _gettext('IP') . ':</label>';
		if (!$multiban) {
			$tpl_page .= '<input type="text" name="ip" id="ip" value="'. $ban_ip . '" />
			<br /><label for="deleteposts">'. _gettext('Delete all posts by this IP') . ':</label>
			<input type="checkbox" name="deleteposts" id="deleteposts" />';
		}
		else {
			$tpl_page .= '<input type="hidden" name="multiban" value="'.htmlspecialchars(serialize($multiban)).'">
			<input type="hidden" name="multibanhashes" value="'.htmlspecialchars(serialize($multiban_hash)).'">	Multiple IPs
			<br /><label for="deleteposts">'. _gettext('Delete all posts by these IPs') . ':</label>
			<input type="checkbox" name="deleteposts" id="deleteposts" />';
		}
		if($this->CurrentUserIsAdministrator()) {
			$tpl_page .= '<br />
			<label for="allowread">'. _gettext('Allow read') . ':</label>
			<select name="allowread" id="allowread"><option value="1">'._gettext('Yes').'</option><option value="0">'._gettext('No').'</option></select>
			<div class="desc">'. _gettext('Whether or not the user(s) affected by this ban will be allowed to read the boards.') . '<br /><strong>'. _gettext('Warning') . ':</strong> '. _gettext('Selecting "No" will prevent any reading of any page on the level of the boards on the server. It will also act as a global ban.') . '</div><br />

			<label for="type">'. _gettext('Type') . ':</label>
			<select name="type" id="type"><option value="0">'. _gettext('Single IP') . '</option><option value="1">'. _gettext('IP Range') . '</option><option value="2">'. _gettext('Whitelist') . '</option></select>
			<div class="desc">'. _gettext('The type of ban. A single IP can be banned by providing the full address. A whitelist ban prevents that IP from being banned. An IP range can be banned by providing the IP range you would like to ban, in this format: 123.123.12') . '</div><br />';
		}

		if ($isquickban && KU_BANMSG != '') {
			$tpl_page .= '<label for="addbanmsg">'. _gettext('Add ban message') . ':</label>
			<input type="checkbox" name="addbanmsg" id="addbanmsg" checked="checked" />
			<div class="desc">'. _gettext('If checked, the configured ban message will be added to the end of the post.') . '</div><br />
			<label for="banmsg">'. _gettext('Ban message') . ':</label>
			<input type="text" name="banmsg" id="banmsg" value="'. htmlspecialchars(KU_BANMSG) . '" size='. strlen(KU_BANMSG) . '" />';
		}

		$tpl_page .='</fieldset>
		<fieldset>
		<legend> '. _gettext('Ban from') . '</legend>';
		if($this->CurrentUserIsAdministrator()) {
			$tpl_page .= '<label for="banfromall"><strong>'. _gettext('All boards') . '</strong></label>
		<input type="checkbox" name="banfromall" id="banfromall" /><br /><hr /><br />';
		}
		$tpl_page .= $this->MakeBoardListCheckboxes('bannedfrom', $this->BoardList($_SESSION['manageusername'])) .
		'</fieldset>';

		if ($ban_hash != "") {
			$tpl_page .= '<fieldset>
			<legend>'. _gettext('Ban file') . '</legend>
			<input type="hidden" name="hash" value="'. $ban_hash . '" />

			<label for="banhashtime">'. _gettext('Ban file hash duration (in seconds)') . ':</label>
			<input type="text" name="banhashtime" id="banhashtime" value="0" />
			<div class="desc">'. _gettext('The amount of time to ban the hash of the image which was posted under this ID. Leave blank to not ban the image, 0 for an infinite global ban, or any number of seconds for that duration of a global ban.') . '</div>
			<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'3600\';return false;">1hr</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'86400\';return false;">1d</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'259200\';return false;">3d</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'604800\';return false;">1w</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'1209600\';return false;">2w</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'2592000\';return false;">30d</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'31536000\';return false;">1yr</a>&nbsp;<a href="#" onclick="document.banform.banhashtime.value=\'0\';return false;">'. _gettext('never') .'</a></div><br />

			<label for="banhashdesc">'. _gettext('Ban file hash description') . ':</label>
			<input type="text" name="banhashdesc" id="banhashdesc" value="' . (isset($ban_post_id) ? ("post " . $ban_post_id) : "") . '" />
			<div class=desc">'. _gettext('The description of the image being banned.') . '</div>
			</fieldset>';
		}

		$tpl_page .= '<fieldset>
		<legend>'. _gettext('Ban duration, reason, and appeal information') . '</legend>
		<label for="seconds">'. _gettext('Seconds') . ':</label>
		<input type="text" name="seconds" id="seconds" />
		<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.seconds.value=\'3600\';return false;">1hr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'86400\';return false;">1d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'259200\';return false;">3d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'604800\';return false;">1w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'1209600\';return false;">2w</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'2592000\';return false;">30d</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'31536000\';return false;">1yr</a>&nbsp;<a href="#" onclick="document.banform.seconds.value=\'0\';return false;">'. _gettext('never') .'</a></div><br />

		<label for="reason">'. _gettext('Reason') . ':</label>
		<input type="text" name="reason" id="reason" value="'. $reason . '" />
		<div class="desc">'. _gettext('Presets') .':&nbsp;<a href="#" onclick="document.banform.reason.value=\''. _gettext('Child Pornography') .'\';return false;">CP</a>&nbsp;<a href="#" onclick="document.banform.reason.value=\''. _gettext('Proxy') .'\';return false;">'. _gettext('Proxy') .'</a></div><br />

		<label for="staffnote">'. _gettext('Staff Note') . '</label>
		<input type="text" name="staffnote" id="staffnote" />
		<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.staffnote.value=\''. _gettext('Child Pornography') .'\';return false;">CP</a> || '. _gettext('This message will be shown only on this page and only to staff, not to the user.') .'</div><br />';

		if (KU_APPEAL != '') {
			$tpl_page .= '<label for="appealdays">'. _gettext('Appeal (days)') . ':</label>
			<input type="text" name="appealdays" id="appealdays" value="5" />
			<div class="desc">'. _gettext('Presets') . ':&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'0\';return false;">'. _gettext('No Appeal') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'5\';return false;">5 '. _gettext('days') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'10\';return false;">10 '. _gettext('days') .'</a>&nbsp;<a href="#" onclick="document.banform.appealdays.value=\'30\';return false;">30 '. _gettext('days') .'</a></div><br />';
		}

		$tpl_page .= '</fieldset><input type="submit" value="'. _gettext('Add ban') . '" /></form><hr /><br />';

		for ($i = 2; $i >= 0; $i--) {
			switch ($i) {
				case 2:
					$tpl_page .= '<strong>'. _gettext('Whitelisted IPs') . ':</strong><br />';
					break;
				case 1:
					$tpl_page .= '<br /><strong>'. _gettext('IP Range Bans') . ':</strong><br />';
					break;
				case 0:
					if (!empty($ban_ip))
						$tpl_page .= '<br /><strong>'. _gettext('Previous bans on this IP') . ':</strong><br />';
					else
						$tpl_page .= '<br /><strong>'. _gettext('Single IP Bans') . ':</strong><br />';
					break;
			}
			if (isset($_GET['allbans'])) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' ORDER BY `id` DESC");
				$hiddenbans = 0;
			} elseif (isset($_GET['limit'])) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' ORDER BY `id` DESC LIMIT ".intval($_GET['limit']));
				$hiddenbans = 0;
			} else {
				if (!empty($ban_ip) && $i == 0) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($ban_ip) . "' AND `type` = '" . $i . "' AND `by` != 'SERVER' ORDER BY `id` DESC");
				} else {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' AND `by` != 'SERVER' ORDER BY `id` DESC LIMIT 15");
					// Get the number of bans in the database of this type
					$hiddenbans = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "'");
					// Subtract 15 from the count, since we only want the number not shown
					$hiddenbans = $hiddenbans[0][0] - 15;
				}
			}
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>';
				$tpl_page .= ($i == 1) ? _gettext('IP Range') : _gettext('IP Address');
				$tpl_page .= '</th><th>'. _gettext('Boards') . '</th><th>'. _gettext('Reason') . '</th><th>'. _gettext('Staff Note') . '</th><th>'. _gettext('Date added') . '</th><th>'. _gettext('Expires/Expired') . '</th><th>'. _gettext('Added By') . '</th><th>&nbsp;</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr><td><a href="?action=bans&ip='. md5_decrypt($line['ip'], KU_RANDOMSEED) . '">'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
					if ($line['globalban'] == 1) {
						$tpl_page .= '<strong>'. _gettext('All boards') . '</strong>';
					} elseif (!empty($line['boards'])) {
						$tpl_page .= '<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>&nbsp;';
					}
					$tpl_page .= '</td><td>';
					$tpl_page .= (!empty($line['reason'])) ? htmlentities(stripslashes($line['reason'])) : '&nbsp;';
					$tpl_page .= '</td><td>';
					$tpl_page .= (!empty($line['staffnote'])) ? htmlentities(stripslashes($line['staffnote'])) : '&nbsp;';
					$tpl_page .= '</td><td>'. date("F j, Y, g:i a", $line['at']) . '</td><td>';
					$tpl_page .= ($line['until'] == 0) ? '<strong>'. _gettext('Does not expire') . '</strong>' : date("F j, Y, g:i a", $line['until']);
					$tpl_page .= '</td><td>'. $line['by'] . '</td><td>[<a href="manage_page.php?action=bans&delban='. $line['id'] . '">'. _gettext('Delete') .'</a>]</td></tr>';
				}
				$tpl_page .= '</table>';
				if ($hiddenbans > 0) {
					$tpl_page .= sprintf(_gettext('%s bans not shown.'), $hiddenbans) .
					' <a href="?action=bans&allbans=1">'. _gettext('View all bans') . '</a>'.' <a href="?action=bans&limit=100">View last 100 bans</a>';
				}
			} else {
				$tpl_page .= _gettext('There are currently no bans');
			}
		}
		$tpl_page .= '<br /><br /><strong>'. _gettext('File hash bans') . ':</strong><br /><table border="1" width="100%"><tr><th>'. _gettext('Hash') . '</th><th>'. _gettext('Description') . '</th><th>'. _gettext('Ban time') . '</th><th>&nbsp;</th></tr>';
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `".KU_DBPREFIX."bannedhashes` ". ((!isset($_GET['allbans'])) ? ("LIMIT 500") : ("")));
		if (count($results) == 0) {
			$tpl_page .= '<tr><td colspan="4">'. _gettext('None') . '</td></tr>';
		} else {
			foreach ($results as $line) {
				$tpl_page .= '<tr><td>'. $line['md5'] . '</td><td>'. $line['description'] . '</td><td>';
				$tpl_page .= ($line['bantime'] == 0) ? '<strong>'. _gettext('Does not expire') . '</strong>' : $line['bantime'] . ' seconds';
				$tpl_page .= '</td><td>[<a href="?action=bans&delhashid='. $line['id'] . '">x</a>]</td></tr>';
			}
		}
		$tpl_page .= '</table>';
	}

	function appeals() {
		global $tc_db, $tpl_page, $bans_class;
		$this->ModeratorsOnly();
		$tpl_page .= '<h2>'. _gettext('Appeals') . '</h2><br />';

		$ban_ip = '';
		if (isset($_GET['accept'])) {
			if ($_GET['accept'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['accept']) . "");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "banlist` SET `expired` = 1, `appealat` = -4 WHERE `id` = " . $tc_db->qstr($_GET['accept']) . "");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Ban successfully removed.');
					management_addlogentry('Accepted appeal #'.$_GET['accept'].' from: '. $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ID');
				}
				$tpl_page .= '<hr />';
			}
		} elseif (isset($_GET['deny'])) {
			if ($_GET['deny'] > 0) {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `id` = " . $tc_db->qstr($_GET['deny']) . "");
				if (count($results) > 0) {
					foreach ($results as $line) {
						$unban_ip = md5_decrypt($line['ip'], KU_RANDOMSEED);
					}
					$tc_db->Execute("UPDATE `" . KU_DBPREFIX . "banlist` SET `appealat` = -2 WHERE `id` = " . $tc_db->qstr($_GET['deny']) . "");
					$bans_class->UpdateHtaccess();
					$tpl_page .= _gettext('Appeal successfully denied.');
					management_addlogentry(_gettext('Denied the ban appeal for') . ' '. $unban_ip, 8);
				} else {
					$tpl_page .= _gettext('Invalid ID');
				}
				$tpl_page .= '<hr />';
			}
		}
		flush();

		for ($i = 1; $i >= 0; $i--) {
			if ($i == 1) {
				$tpl_page .= '<strong>'. _gettext('IP Range bans') . ':</strong><br />';
			} else {
				$tpl_page .= '<br /><strong>'. _gettext('Single IP Bans') . ':</strong><br />';
			}

			if ($ban_ip != '') {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `ipmd5` = '" . md5($ban_ip) . "' AND `type` = '" . $i . "' AND `expired` = 0 ORDER BY `id` DESC");
			} else {
				$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "banlist` WHERE `type` = '" . $i . "' AND `appealat` = -1 AND `expired` = 0 ORDER BY `id` DESC");
			}
			if (count($results) > 0) {
				$tpl_page .= '<table border="1" width="100%"><tr><th>';
				if ($i == 1) {
					$tpl_page .= 'IP Range';
				} else {
					$tpl_page .= 'IP Address';
				}
				$tpl_page .= '</th><th>Boards</th><th>Reason</th><th>Staff Note</th><th>Date Added</th><th>Expires</th><th>Added By</th><th>Appeal Message</th><th>Deny</th><th>Accept</th></tr>';
				foreach ($results as $line) {
					$tpl_page .= '<tr>';
					$tpl_page .= '<td><a href="?action=bans&ip='. md5_decrypt($line['ip'], KU_RANDOMSEED) . '">'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</a></td><td>';
					if ($line['globalban'] == '1') {
						$tpl_page .= '<strong>'. _gettext('All boards') . '</strong>';
					} else {
						if ($line['boards'] != '') {
							$tpl_page .= '<strong>/'. implode('/</strong>, <strong>/', explode('|', $line['boards'])) . '/</strong>&nbsp;';
						}
					}
					$tpl_page .= '</td><td>';
					if ($line['reason'] != '') {
						$tpl_page .= htmlentities(stripslashes($line['reason']));
					} else {
						$tpl_page .= '&nbsp;';
					}
					$tpl_page .= '</td><td>';
					if ($line['staffnote'] != '') {
						$tpl_page .= htmlentities(stripslashes($line['staffnote']));
					} else {
						$tpl_page .= '&nbsp;';
					}
					$tpl_page .= '</td><td>'. date("F j, Y, g:i a", $line['at']) . '</td><td>';
					if ($line['until'] == '0') {
						$tpl_page .= '<strong>'. _gettext('Does not expire') . '</strong>';
					} else {
						$tpl_page .= date("F j, Y, g:i a", $line['until']);
					}
					$tpl_page .= '</td><td>'. $line['by'] . '</td>
					<td>'.$line['appeal'].'</td>
					<td><a href="manage_page.php?action=appeals&deny='. $line['id'] . '">:(</a></td>
					<td><a href="manage_page.php?action=appeals&accept='. $line['id'] . '">:)</a></td>';
					$tpl_page .= '</tr>';
				}
				$tpl_page .= '</table>';
				if ($hiddenbans>0) {
					$tpl_page .= sprintf(_gettext('%s bans not shown.'), $hiddenbans) .
					' <a href="?action=bans&allbans=1">'. _gettext('View all bans') . '</a>'.' <a href="?action=bans&limit=100">View last 100 bans</a>';
				}
			} else {
				$tpl_page .= _gettext('There are currently no bans.');
			}
		}
	}

	/* Search for all posts by a selected IP address and delete them */
	function deletepostsbyip($from_ban = false) {
		global $tc_db, $tpl_page, $board_class;
		$this->AdministratorsOnly();
		if (!$from_ban) {
			$tpl_page .= '<h2>'. _gettext('Delete all posts by IP') . '</h2><br />';
		}
		if (isset($_POST['ip']) || isset($_POST['multiban'])) {
			if ($_POST['ip'] != '' || !empty($_POST['multiban'])) {
        if (!$from_ban) {
          $this->CheckToken($_POST['token']);
        }
				$deletion_boards = array();
				$deletion_new_boards = array();
				$board_ids = '';
				if (isset($_POST['banfromall'])) {
					$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
					foreach ($results as $line) {
						if (!$this->CurrentUserIsModeratorOfBoard($line['name'], $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $line['name'] . '/: '. _gettext('You can only delete posts from boards you moderate.'));
						}
						$delete_boards[$line['id']] = $line['name'];
						$board_ids .= $line['id'] . ',';
					}
				} else {
					if (empty($_POST['deletefrom'])) {
						exitWithErrorPage(_gettext('Please select a board.'));
					}
					foreach($_POST['deletefrom'] as $board) {
						if (!$this->CurrentUserIsModeratorOfBoard($board, $_SESSION['manageusername'])) {
							exitWithErrorPage('/'. $board . '/: '. _gettext('You can only delete posts from boards you moderate.'));
						}
						$id = $tc_db->GetOne("SELECT `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($board));
						$board_ids .= $tc_db->qstr($id) . ',';
						$delete_boards[$id] = $board;
					}
				}
				$board_ids = substr($board_ids, 0, -1);

				$i = 0;
				if (isset($_POST['multiban']))
					$ips = unserialize($_POST['multiban']);
				else
					$ips = Array($_POST['ip']);
				foreach  ($ips as $ip) {
					$i = 0;
					$post_list = $tc_db->GetAll("SELECT `id`, `boardid` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` IN (" . $board_ids . ") AND `IS_DELETED` = '0' AND `ipmd5` = '" . md5($ip) . "'");
					if (count($post_list) > 0) {
						foreach ($post_list as $post) {
							$i++;
							$post_class = new Post($post['id'], $delete_boards[$post['boardid']], $post['boardid']);
							$post_class->Delete();
							$boards_deleted[$post['boardid']] = $delete_boards[$post['boardid']];
							unset($post_class);
						}

						$tpl_page .= _gettext('All threads/posts by that IP in selected boards successfully deleted.') . '<br /><strong>'. $i . '</strong> posts were removed.<br />';
						management_addlogentry(_gettext('Deleted posts by ip') . ' '. $ip, 7);
					}
					else {
						$tpl_page .= _gettext('No posts for that IP found');
					}
				}
				$tpl_page .= '<hr />';
			}
		}
		if (!$from_ban) {
			$tpl_page .= '<form action="?action=deletepostsbyip" method="post">
      <input type="hidden" name="token" value="' . $_SESSION['token'] . '" />
			<fieldset><legend>IP</legend>
			<label for="ip">'. _gettext('IP') .':</label>
			<input type="text" id="ip" name="ip"';
			if (isset($_GET['ip'])) {
				$tpl_page .= ' value="'. $_GET['ip'] . '"';
			}
			$tpl_page .= ' /></fieldset><br /><fieldset>
			<legend>'. _gettext('Boards') .'</legend>

			<label for="banfromall"><strong>'. _gettext('All boards') .'</strong></label>
			<input type="checkbox" id="banfromall" name="banfromall" /><br /><hr /><br />' .
			$this->MakeBoardListCheckboxes('deletefrom', $this->BoardList($_SESSION['manageusername'])) .
			'<br /></fieldset>

			<input type="submit" value="'. _gettext('Delete posts') .'" />

			</form>';
		}
	}

	/* View recently uploaded images */
	function recentimages() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		if (!isset($_SESSION['imagesperpage'])) {
			$_SESSION['imagesperpage'] = 50;
		}

		if (isset($_GET['show'])) {
			if ($_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['imagesperpage'] = $_GET['show'];
			}
		}

		$tpl_page .= '<h2>'. _gettext('Recently uploaded images') . '</h2><br />
		'._gettext('Number of images to show per page').': <a href="?action=recentimages&show=25">25</a>, <a href="?action=recentimages&show=50">50</a>, <a href="?action=recentimages&show=75">75</a>, <a href="?action=recentimages&show=100">100</a> '._gettext('(note that this is a rough limit, more may be shown)').'<br />';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);

					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= _gettext('Successfully marked previous images as reviewed.').'<hr />';
				}
			}
		}

		$dayago = ((time() + KU_ADDTIME) - 86400);
		$imagesshown = 0;
		$reviewsql_array = array();

		if ($imagesshown <= $_SESSION['imagesperpage']) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `" . KU_DBPREFIX . "boards`.`name` AS `boardname`, `" . KU_DBPREFIX . "posts`.`boardid` AS boardid, `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`file` AS file, `" . KU_DBPREFIX . "posts`.`file_type` AS file_type, `" . KU_DBPREFIX . "posts`.`thumb_w` AS thumb_w, `" . KU_DBPREFIX . "posts`.`thumb_h` AS thumb_h FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX ."boards` WHERE (`file_type` = 'jpg' OR `file_type` = 'gif' OR `file_type` = 'webp' OR `file_type` = 'png') AND `reviewed` = 0 AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC LIMIT " . intval($_SESSION['imagesperpage']));
			if (count($results) > 0) {
				$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts` SET `reviewed` = 1 WHERE ";
				$tpl_page .= '<table border="1">'. "\n";
				foreach ($results as $line) {
					$reviewsql .= '(`boardid` = '.$line['boardid'] .' AND `id` = '. $line['id'] . ') OR ';
					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '"><img src="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/thumb/'. $line['file'] . 's.'. $line['file_type'] . '" width="'. $line['thumb_w'] . '" height="'. $line['thumb_h'] . '" border="0"></a></td></tr>';
				}
				$tpl_page .= '</table>';

				$reviewsql = substr($reviewsql, 0, -3);
				$reviewsql_array[] = $reviewsql;
				$imagesshown += count($results);
			}
		}

		if ($imagesshown > 0) {
			$tpl_page .= '<br /><br />'. sprintf(_gettext('%s images shown.'), $imagesshown). '<br />';
			$tpl_page .= '<form action="?action=recentimages" method="post">
			<input type="hidden" name="clear" value="'. md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '" />
			<input type="submit" value="'. _gettext('Clear All On Page As Reviewed') .'" />
			</form><br />';
		} else {
			$tpl_page .= '<br /><br />'. _gettext('No recent images currently need review.') ;
		}
	}

	/* View recently posted posts */
	function recentposts() {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		if (!isset($_SESSION['postsperpage'])) {
			$_SESSION['postsperpage'] = 50;
		}

		if (isset($_GET['show'])) {
			if ($_GET['show'] == '25' || $_GET['show'] == '50' || $_GET['show'] == '75' || $_GET['show'] == '100') {
				$_SESSION['postsperpage'] = $_GET['show'];
			}
		}

		$tpl_page .= '<h2>'. _gettext('Recent posts') . '</h2><br />
		'._gettext('Number of posts to show per page').': <a href="?action=recentposts&show=25">25</a>, <a href="?action=recentposts&show=50">50</a>, <a href="?action=recentposts&show=75">75</a>, <a href="?action=recentposts&show=100">100</a> '._gettext('(note that this is a rough limit, more may be shown)').'<br />';
		if (isset($_POST['clear'])) {
			if ($_POST['clear'] != '') {
				$clear_decrypted = md5_decrypt($_POST['clear'], KU_RANDOMSEED);
				if ($clear_decrypted != '') {
					$clear_unserialized = unserialize($clear_decrypted);

					foreach ($clear_unserialized as $clear_sql) {
						$tc_db->Execute($clear_sql);
					}
					$tpl_page .= _gettext('Successfully marked previous posts as reviewed.').'<hr />';
				}
			}
		}

		$dayago = ((time() + KU_ADDTIME) - 86400);
		$postsshown = 0;
		$reviewsql_array = array();

		$boardlist = $this->BoardList($_SESSION['manageusername']);
		if ($postsshown <= $_SESSION['postsperpage']) {
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `" . KU_DBPREFIX . "boards`.`name` AS `boardname`, `" . KU_DBPREFIX . "posts`.`boardid` AS boardid, `" . KU_DBPREFIX . "posts`.`id` AS id, `" . KU_DBPREFIX . "posts`.`parentid` AS parentid, `" . KU_DBPREFIX . "posts`.`message` AS message, `" . KU_DBPREFIX . "posts`.`ip` AS ip FROM `" . KU_DBPREFIX . "posts`, `" . KU_DBPREFIX ."boards` WHERE `" . KU_DBPREFIX . "posts`.`timestamp` > " . $dayago . " AND `reviewed` = 0 AND `IS_DELETED` = 0 AND `" . KU_DBPREFIX . "boards`.`id` = `" . KU_DBPREFIX . "posts`.`boardid` ORDER BY `timestamp` DESC LIMIT " . intval($_SESSION['postsperpage']));
			if (count($results) > 0) {
				$reviewsql = "UPDATE `" . KU_DBPREFIX . "posts` SET `reviewed` = 1 WHERE ";
				$tpl_page .= '<table border="1" width="1005%">'. "\n";
				$tpl_page .= '<tr><th width="75px">'._gettext('Post Number').'</th><th>'._gettext('Post Message').'</th><th width="100px">'._gettext('Poster IP').'</th></tr>'. "\n";
				foreach ($results as $line) {
					$reviewsql .= '(`boardid` = '.$line['boardid'] .' AND `id` = '. $line['id'] . ') OR ';
					$real_parentid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
					$tpl_page .= '<tr><td><a href="'. KU_BOARDSPATH . '/'. $line['boardname'] . '/res/'. $real_parentid . '.html#'. $line['id'] . '">/'. $line['boardname'] . '/'. $line['id'] . '</td><td>'. stripslashes($line['message']) . '</td><td>'. md5_decrypt($line['ip'], KU_RANDOMSEED) . '</tr>';
				}
				$tpl_page .= '</table>';

				$reviewsql = substr($reviewsql, 0, -3) . ' LIMIT '. count($results);
				$reviewsql_array[] = $reviewsql;
				$postsshown += count($results);
			}
		}

		if ($postsshown > 0) {
			$tpl_page .= '<br /><br />'. sprintf(_gettext('%s posts shown.'), $postsshown) .'<br />
			<form action="?action=recentposts" method="post">
			<input type="hidden" name="clear" value="'. md5_encrypt(serialize($reviewsql_array), KU_RANDOMSEED) . '" />
			<input type="submit" value="'. _gettext('Clear All On Page As Reviewed') .'" />
			</form><br />';
		} else {
			$tpl_page .= '<br /><br />'. _gettext('No recent posts currently need review.') ;
		}
	}

	/*
	* +------------------------------------------------------------------------------+
	* Misc Functions
	* +------------------------------------------------------------------------------+
	*/

	/* Show APC info */
	function apc() {
		global $tpl_page;

		if (KU_APC) {
			$apc_info_system = apc_cache_info();
			$apc_info_user = apc_cache_info('user');
			//print_r($apc_info_user);
			$tpl_page .= '<h2>APC</h2><h3>'. _gettext('System (File cache)') .'</h3><ul>';
			$tpl_page .= '<li>Start time: <strong>'. date("y/m/d(D)H:i", $apc_info_system['start_time']) . '</strong></li>';
			$tpl_page .= '<li>Hits: <strong>'. $apc_info_system['num_hits'] . '</strong></li>';
			$tpl_page .= '<li>Misses: <strong>'. $apc_info_system['num_misses'] . '</strong></li>';
			$tpl_page .= '<li>Entries: <strong>'. $apc_info_system['num_entries'] . '</strong></li>';
			$tpl_page .= '</ul><br /><h3>User (kusaba)</h3><ul>';
			$tpl_page .= '<li>Start time: <strong>'. date("y/m/d(D)H:i", $apc_info_user['start_time']) . '</strong></li>';
			$tpl_page .= '<li>Hits: <strong>'. $apc_info_user['num_hits'] . '</strong></li>';
			$tpl_page .= '<li>Misses: <strong>'. $apc_info_user['num_misses'] . '</strong></li>';
			$tpl_page .= '<li>Entries: <strong>'. $apc_info_user['num_entries'] . '</strong></li>';
			$tpl_page .= '</ul><br /><br /><a href="?action=clearcache">Clear APC cache</a>';
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}

	/* Clear the APC cache */
	function clearcache() {
		global $tpl_page;

		if (KU_APC) {
			apc_clear_cache();
			apc_clear_cache('user');
			$tpl_page .= 'APC cache cleared.';
			management_addlogentry(_gettext('Cleared APC cache'), 0);
		} else {
			$tpl_page .= 'APC isn\'t enabled!';
		}
	}

	/* Generate a list of boards a moderator controls */
	function BoardList($username) {
		global $tc_db, $tpl_page;

		$staff_boardsmoderated = array();
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `boards` FROM `" . KU_DBPREFIX . "staff` WHERE `username` = '" . $username . "' LIMIT 1");
		if ($this->CurrentUserIsAdministrator() || $results[0][0] == 'allboards') {
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
			foreach ($resultsboard as $lineboard) {
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array(array( 'name' => $lineboard['name'], 'id' => $lineboard['id'])));
			}
		} else {
			if ($results[0][0] != '') {
				foreach ($results as $line) {
					$array_boards = explode('|', $line['boards']);
				}
				foreach ($array_boards as $this_board_name) {
					$this_board_id = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($this_board_name) . "");
					$staff_boardsmoderated = array_merge($staff_boardsmoderated, array(array('name' => $this_board_name, 'id' => $this_board_id)));
				}
			}
		}

		return $staff_boardsmoderated;
	}

	/* Generate a list of boards in query format */
	function sqlboardlist() {
		global $tc_db, $tpl_page;

		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` ORDER BY `name` ASC");
		$sqlboards = '';
		foreach ($results as $line) {
			$sqlboards .= 'posts_'. $line['name'] . ', ';
		}

		return substr($sqlboards, 0, -2);
	}

	/* Generate a dropdown box from a supplied array of boards */
	function MakeBoardListDropdown($name, $boards, $all = false) {
		$output = '<select name="'. $name . '"><option value="">'. _gettext('Select a Board') .'</option>';
		if (!empty($boards)) {
			if ($all) {
				$output .= '<option value="all">'. _gettext('All Boards') .'</option>';
			}
			foreach ($boards as $board) {
				$output .= '<option value="'. $board['name'] . '">/'. $board['name'] . '/</option>';
			}
		}
		$output .= '</select>';

		return $output;
	}

	/* Generate a series of checkboxes from a supplied array of boards */
	function MakeBoardListCheckboxes($boxname, $boards) {
		$output = '';

		if (!empty($boards)) {
			foreach ($boards as $board) {
				$output .= '<label for="'. $boxname .'" >'. $board['name'] . '</label><input type="checkbox" name="'. $boxname . '[]" value="'. $board['name'] . '" /> '."<br>";
			}
		}

		return $output;
	}

	/* Generate a dropdown box for all sections */
	function MakeSectionListDropDown($name, $selected) {
		global $tc_db;

		$output = '<select name="'. $name . '"><option value="">'. _gettext('Select a Section') .'</option>'. "\n";
		$results = $tc_db->GetAll("SELECT `id`, `name` FROM `" . KU_DBPREFIX . "sections` ORDER BY `order` ASC");
		if(count($results) > 0) {
			foreach ($results as $section) {
				if ($section['id'] == $selected) {
					$select = ' selected="selected"';
				} else {
					$select = '';
				}
				$output .= '<option value="'. $section['id'] . '"'. $select . '>'. $section['name'] . '</option>'. "\n";
			}
		}
		$output .= '</select><br />'. "\n";

		return $output;
	}

	/* Delete files without their md5 stored in the database */
	function delunusedimages($verbose = false) {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<strong>'. _gettext('Looking for unused images in') .' /'. $lineboard['name'] . '/</strong><br />';
			}
			$file_md5list = array();
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `file_md5` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `IS_DELETED` = 0 AND `file` != '' AND `file` != 'removed' AND `file_md5` != ''");
			foreach ($results as $line) {
				$file_md5list[] = $line['file_md5'];
			}
			$dir = './'. $lineboard['name'] . '/src';
			$files = glob("$dir/{*.jpg, *.png, *.gif, *.webp, *.swf}", GLOB_BRACE);
			if (is_array($files)) {
				foreach ($files as $file) {
					if (in_array(md5_file(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file)), $file_md5list) == false) {
						if ((time() + KU_ADDTIME) - filemtime(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file)) > 120) {
							if ($verbose == true) {
								$tpl_page .= sprintf(_gettext('A live record for %s was not found; the file has been removed.'), $file).'<br />';
							}
							unlink(KU_BOARDSDIR . $lineboard['name'] . '/src/'. basename($file));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/'. substr(basename($file), 0, -4) . 's'. substr(basename($file), strlen(basename($file)) - 4));
							@unlink(KU_BOARDSDIR . $lineboard['name'] . '/thumb/'. substr(basename($file), 0, -4) . 'c'. substr(basename($file), strlen(basename($file)) - 4));
						}
					}
				}
			}
		}

		return true;
	}

	/* Delete replies currently not marked as deleted who belong to a thread which is marked as deleted */
	function delorphanreplies($verbose = false) {
		global $tc_db, $tpl_page;
		$this->AdministratorsOnly();

		$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `name` FROM `" . KU_DBPREFIX . "boards`");
		foreach ($resultsboard as $lineboard) {
			if ($verbose) {
				$tpl_page .= '<strong>'. _gettext('Looking for orphans in') .' /'. $lineboard['name'] . '/</strong><br />';
			}
			$results = $tc_db->GetAll("SELECT HIGH_PRIORITY `id`, `parentid` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `parentid` != '0' AND `IS_DELETED` = 0");
			foreach ($results as $line) {
				$exists_rows = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $lineboard['id'] . " AND `id` = '" . $line['parentid'] . "' AND `IS_DELETED` = 0", 1);
				if ($exists_rows[0] == 0) {
					$post_class = new Post($line['id'], $lineboard['name'], $lineboard['id']);
					$post_class->Delete;
					unset($post_class);

					if ($verbose) {
						$tpl_page .= sprintf(_gettext('Reply #%1$s\'s thread (#%2$s) does not exist! It has been deleted.'),$line['id'],$line['parentid']).'<br />';
					}
				}
			}
		}

		return true;
	}

	function spam() {
		$this->BoardOwnersOnly();
		global $tc_db, $tpl_page;
		$tpl_page .= '<h2>'. _gettext('Spam.txt Management') .'</h2> <br />';

		if (isset($_GET['updateboard'])) {
      	    $this->CheckToken($_POST['token']);
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['updateboard'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$boardid = $tc_db->GetOne("SELECT HIGH_PRIORITY `id` FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['updateboard']) . " LIMIT 1");
			if ($boardid != '') {
				file_put_contents(KU_ROOTDIR . $_GET['updateboard']. '/spam.txt', $_POST['spam']);
				management_addlogentry(_gettext('Updated spam list') . " - /" . $_GET['updateboard'] . "/", 4);
				$tpl_page .= '<hr />'. _gettext('Spam.txt successfully edited.') .'<hr />';
			}
			else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_GET['updateboard'] . '</strong>.';
			}
		}
		elseif (isset($_POST['board'])) {
			if (!$this->CurrentUserIsModeratorOfBoard($_POST['board'], $_SESSION['manageusername'])) {
				exitWithErrorPage(_gettext('You are not a moderator of this board.'));
			}
			$resultsboard = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_POST['board']) . "");
			if (count($resultsboard) > 0) {
				$spamfile = KU_ROOTDIR . $_POST['board']. '/spam.txt';
				$content = file_exists($spamfile) ? htmlspecialchars(file_get_contents($spamfile)) : '';
				$tpl_page .= '<p>Доска: '. $_POST['board'] . '</p>' . "\n" .
					'<p><b>PROTIP: </b>'._gettext("You don't have to include all variations of the word since it's done autamatically.").'</p>'.
					'<form action="?action=spam&updateboard=' . $_POST['board'] . '" method="post">'. "\n" .
          			'<input type="hidden" name="token" value="' . $_SESSION['token'] . '" />' . "\n" .
          			'<input type="hidden" name="updateboard" value="' . $_POST['board'] . '" />' . "\n" .
					'<textarea name="spam" rows="25" cols="80">' . $content . '</textarea><br />' . "\n" .
					'<input type="submit" value="'. _gettext('Submit') .'" />'. "\n" .
					'</form>'. "\n";
			} else {
				$tpl_page .= _gettext('Unable to locate a board named') . ' <strong>'. $_POST['board'] . '</strong>.';
			}
		} else {
			$tpl_page .= '<p>'._gettext('You can create separate spam filters for any of your boards.').'</p>'
				.'<form action="?action=spam" method="post">
			<label for="board">'. _gettext('Board') .':</label>' .
			$this->MakeBoardListDropdown('board', $this->BoardList($_SESSION['manageusername'])) .
			'<input type="submit" value="'. _gettext('Go') .'" />
			</form>';
		}
	}

	/* Gets the IP address of a post */
	function getip() {
		global $tc_db, $smarty, $tpl_page;
		if(!$this->CurrentUserIsModerator() && !$this->CurrentUserIsAdministrator()) {
			die();
		}
		$results = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` WHERE `name` = " . $tc_db->qstr($_GET['boarddir']));
		if (count($results) > 0) {
			if (!$this->CurrentUserIsModeratorOfBoard($_GET['boarddir'], $_SESSION['manageusername'])) {
				die();
			}
			$ip = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $tc_db->qstr($results[0]['id']) . " AND `id` = " . $tc_db->qstr($_GET['id']));
			die("dnb-".$_GET['boarddir']."-".$_GET['id']."-".(($ip[0]['parentid'] == 0) ? ("y") : ("n"))."=".md5_decrypt($ip[0]['ip'], KU_RANDOMSEED));
		}
		die();
	}
}
?>
