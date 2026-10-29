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
 * Manage menu
 *
 * Loaded when a user visits manage.php
 *
 * @package kusaba
 */

session_start(['cookie_samesite' => 'Strict']);

require 'settings_manager.php';
require KU_ENGINEDIR . 'lib/dwoo.php';
require KU_ENGINEDIR . 'lib/functions.php';
require KU_ENGINEDIR . 'lib/classes/manage.class.php';

$manage_class = new Manage();
$dwoo_data->assign('styles', explode(':', KU_STYLES));

$tpl_links = '';
if (!$manage_class->ValidateSession(true)) {
    $tpl_links .= '<li><a href="' . KU_WEBPATH . '" target="_top">' . _gettext('Home') . '</a></li>' . "\n";
    $tpl_links .= '<li><a href="manage_page.php">' . ucfirst(_gettext('Log in')) . '</a></li>';
} else {
    $manage_postpassword = md5_encrypt($_SESSION['manageusername'], KU_RANDOMSEED);
    $tpl_links .= _gettext('Welcome') . ', <strong>' . $_SESSION['manageusername'] . '</strong>';
    if ($_SESSION['manageusername'] == 'admin') {
        $salt = $tc_db->GetOne("SELECT `salt` FROM " . KU_DBPREFIX . "staff WHERE `username` = " . $tc_db->qstr($_SESSION['manageusername']));
        if ($_SESSION['managepassword'] == md5('admin'.$salt)) {
            $tpl_links .= '<br /><strong><font color="red">' . _gettext('NOTICE: You are using the default administrator account password. Change password as soon as possible!') . '</font></strong>';
        }
    }

    // Header
    $tpl_links .= '<br />' . _gettext('Staff rights') . ': <strong>';
    if ($manage_class->CurrentUserIsAdministrator()) {
        $tpl_links .= _gettext('Administrator');
    } elseif ($manage_class->CurrentUserIsModerator()) {
        $tpl_links .= _gettext('Moderator');
    } else {
        $tpl_links .= _gettext('Janitor');
    }
    $tpl_links .= "</strong>";
    $tpl_links .= '<li><a href="' . KU_WEBPATH . '" target="_top">' . _gettext('Home') . '</a></li>' . "\n";
    $tpl_links .= '<li><a href="manage_page.php?action=changepwd">' . _gettext('Change account password') . '</a></li>';
    $tpl_links .= '<li><a href="manage_page.php?action=logout">'._gettext('Log out').'</a></li>';

    // Statistics
    $tpl_links .= section_html(_gettext('Statistics'), 'home') . '<ul>';
    if (KU_OPEN_MODLOG || $manage_class->CurrentUserIsAdministrator()) {
        $tpl_links .= '<li><a style="color:red" href="manage_page.php?action=modlog">' . _gettext('ModLog') . '</a></li>';
    }
    $tpl_links .= '<li><a href="manage_page.php?action=posting_rates">' . _gettext('Posting rates') . '</a></li>';
    if ($manage_class->CurrentUserIsAdministrator()) {
        $tpl_links .= '<li><a href="manage_page.php?action=statistics">' . _gettext('Statistics') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=verbosestats">' . _gettext('Verbose statistics') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=captchastats">' . _gettext('Captcha statistics') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=spaceused">' . _gettext('Disk space used') . '</a></li>';
    }
    $tpl_links .= '<li>Окаринов на борде (за последний час): ' . $manage_class->CountOkarins(3600) . '</li>';
    $agents = $manage_class->CountAgents(3600);
    if($agents !== false) {
        $tpl_links .= '<li>Агентов Организации на борде: ' . $agents . '</li>';
    }
    $tpl_links .= '</ul></div>';

    // Administration
    if ($manage_class->CurrentUserIsAdministrator()) {
        $tpl_links .= section_html(_gettext('Site Administration'), 'siteadministration') . '<ul>';
        if (is_dir(KU_ENGINEDIR . ".git")) {
            $tpl_links .= '<li><a style="color:green" href="manage_page.php?action=updateengine">' . _gettext('Update engine') . '</a></li>';
        }
        $tpl_links .= '<li><a href="manage_page.php?action=enginesettings">' . _gettext('Engine settings') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=staff">' . _gettext('Staff') . '</a></li>';
        $tpl_links .= '<li><a style="color:blue" href="manage_page.php?action=sql">' . _gettext('SQL query') . '</a></li>';
        $tpl_links .= '<li><a style="color:blue" href="manage_page.php?action=phpinfo">' . _gettext('PHP Info') . '</a></li>';
        if(KU_DEBUGREQUESTS) {
            $tpl_links .= '<li><a style="color:red" href="manage_page.php?action=reqlog">' . _gettext('Request log') . '</a></li>';
        }
        $tpl_links .= '<li><a href="manage_page.php?action=rebuildanswerslist">' . _gettext('Rebuild static reply map') . '</a></li>';
        $tpl_links .= '</ul></div>';
        $tpl_links .= section_html(_gettext('Boards Administration'), 'boardsadministration') . '<ul>';
        $tpl_links .= '<li class="magic-link" data-help="adddelboard"><a href="manage_page.php?action=adddelboard">' . _gettext('Add/Delete boards') . '</a></li>';
        $tpl_links .= '<li class="magic-link" data-help="boardopts"><a href="manage_page.php?action=boardopts">' . _gettext('Board options') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=embeds">' . _gettext('Manage embeds') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=movethread">' . _gettext('Move thread') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=proxyban">' . _gettext('Ban proxy list') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=ipsearch">' . _gettext('IP Search') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=search">' . _gettext('Search posts') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=editfiletypes">' . _gettext('Edit filetypes') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=editsections">' . _gettext('Edit sections') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=specialthreads">' . _gettext('Special threads') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=edittrips">' . _gettext('Edit special tripcodes') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=threadlimit">' . _gettext('Flush limit of threads') . '</a></li>';
        $tpl_links .= '</ul></div>';
    }

    // Moderation
    if ($manage_class->CurrentUserIsAdministrator() || $manage_class->CurrentUserIsModerator()) {
        $open_reports = $tc_db->GetAll("SELECT HIGH_PRIORITY COUNT(*) FROM `" . KU_DBPREFIX . "reports` WHERE `cleared` = '0'");
        $tpl_links .= section_html(_gettext('Moderation'), 'moderation') . '<ul>';
        $tpl_links .= '<li><a href="manage_page.php?action=viewthread">'._gettext('View thread (including deleted)').'</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=viewdeletedthread">'._gettext('View thread by number').'</a></li>';
        $tpl_links .= '<li class="magic-link" data-help="spam"><a href="manage_page.php?action=spam">' . _gettext('Spamfilter') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=wordfilter">' . _gettext('Wordfilter') . '</a></li>';
        $tpl_links .= '<li class="magic-link" data-help="stickypost"><a href="manage_page.php?action=stickypost">' . _gettext('Manage stickies') . '</a></li>';
        $tpl_links .= '<li class="magic-link" data-help="lockpost"><a href="manage_page.php?action=lockpost">' . _gettext('Manage locked threads') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=delposts">' . _gettext('Delete thread/post') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=deletepostsbyip">' . _gettext('Delete all posts by IP') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=reports">' . _gettext('View Reports') . ' [' . $open_reports[0][0] . ']</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=bans">' . _gettext('View/Add/Remove IP bans') . '</a></li>';
        $tpl_links .= '<li><a href="manage_page.php?action=bans&ishashban=1">' . _gettext('View/Add/Remove file bans') . '</a></li>';
        if (KU_APPEAL) {
            $tpl_links .= '<li><a href="manage_page.php?action=appeals">' . _gettext('View Appeals') . '</a></li>';
        }
        if ($manage_class->CurrentUserIsAdministrator()) {
            $tpl_links .= '<li><a href="manage_page.php?action=recentimages">' . _gettext('Recently uploaded images') . '</a></li>';
            $tpl_links .= '<li><a href="manage_page.php?action=recentposts">' . _gettext('Recent posts') . '</a></li>';
        }
        $tpl_links .= '</ul></div>';
    }

    // Boards list
    if ($manage_class->CurrentUserIsModerator()) {
        $tpl_links .= section_html(_gettext('Moderating Boards'), 'mboards', true) . '<ul>';
    } elseif ($manage_class->CurrentUserIsAdministrator()) {
        $tpl_links .= section_html(_gettext('All Boards'), 'mboards', true) . '<ul>';
    }
    $boards = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "boards` ORDER BY `name`");
    if(count($boards) == 0) {
        $tpl_links .= '<li>' . _gettext('No boards') . '</li></ul></div>';
    } else {
        $i = 0;
        foreach ($boards as $board) {
            if ($manage_class->CurrentUserIsAdministrator() || $manage_class->CurrentUserIsModeratorOfBoard($board['name'])) {
                $i++;
                $tpl_links .= '<li><a href="' . KU_BOARDSPATH . '/' . $board['name'] . '/"><strong>/' . $board['name'] . '/</strong></a></li>';
            }
        }
        $tpl_links .= '<li>(' . sprintf(_gettext('%d Boards'), $i) . ')</li></ul></div>';
    }
}

function section_html($section, $abbreviation, $show = true)
{
    return '<h2 class="yoba" title="' . _gettext("Click to show/hide") . '" onclick="toggle(this, \'' . $abbreviation . '\');">' . $section . '</h2><div id="' . $abbreviation . '" style="' . ($show ? '' : 'display:none') . '">';
}

$dwoo_data->assign('links', $tpl_links);
$dwoo->output(KU_TEMPLATEDIR . '/manage_menu.tpl', $dwoo_data);
