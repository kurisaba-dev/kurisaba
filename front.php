<?php
require_once 'config.php';
require_once KU_ROOTDIR . 'lib/dwoo.php';
require_once KU_ROOTDIR . 'inc/functions.php';
require_once KU_ROOTDIR . 'inc/classes/bans.class.php';

$dwoo = new Dwoo();

$dwoo_data = new Dwoo_Data();
$dwoo_data->assign('title', KU_NAME);

$prefix = KU_DBPREFIX;

$page = (isset($_GET['page']) ? $_GET['page'] : 'index');

$dwoo_data->assign('page', $page);


function isNewYearModeDate() {
	/* from 1 december to 14 february new year mode is enabled */
	if ( time() <= 500000000 ) return false;
	/* 500000000 is near 1985 year. this means server has wrong date */

	$dayOfYear = intval( date("z") ); /* from 0 to 365 */
	$monthOfYear = intval( date("n") ); /* from 1 to 12 */

	if ( $dayOfYear <= 44 ) return true; /* 44 day is 14 feb */
	if ( $monthOfYear === 12 ) return true; /* december */
    return false;
}

$sections = $tc_db->GetAll("SELECT * FROM {$prefix}sections ORDER BY `order` ASC");
$boards = $tc_db->GetAll("SELECT * from {$prefix}boards ORDER by `order` ASC, name ASC");
$sql = "SELECT 
			p.id,
			CASE p.parentid
				WHEN 0 THEN p.id
				ELSE p.parentid
			END AS parentid,
			b.name AS board,
			p.file,
			p.file_type
		FROM {$prefix}posts AS p
		JOIN {$prefix}boards AS b ON p.boardid = b.id
		JOIN {$prefix}sections AS s ON b.section = s.id
		WHERE file_type IN ('jpg' , 'gif', 'png') AND IS_DELETED = 0
		ORDER BY TIMESTAMP DESC
		LIMIT 10";

$last_images = $tc_db->GetAll($sql);
$sql = "SELECT * FROM `posts` WHERE `boardid`=9 ORDER BY `TIMESTAMP` desc limit 10";
$last_posts = $tc_db->GetAll($sql);
$diskfree  = disk_free_space('/') / 1048576;
$disktotal = disk_total_space('/') / 1048576;
$postcount  = $tc_db->GetAll("SELECT COUNT(*) AS postcount FROM posts WHERE boardid=9");
$postcountz = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `posts` WHERE `boardid`=9 AND `timestamp` > " . (time() + KU_ADDTIME - 86400) . "");
$postcounth = $tc_db->GetOne("SELECT HIGH_PRIORITY COUNT(*) FROM `posts` WHERE `boardid`=9 AND `timestamp` > " . (time() + KU_ADDTIME - 3600)  . "");

if (!isset($_GET['mode'])) {
	if ( isNewYearModeDate() )
		$_GET['mode'] = 'newyear';
	else
		$_GET['mode'] = 'default';
}
$mode = $_GET['mode'];
if ($mode === 'newyear')
{
	$template_file = '/front_index.tpl';
	$path = 'newyear';
	$cssfile = 'front-winter';
	$bgnames = array("christmas","akiba01","akiba02","akiba03","akiba04","akiba05","akiba06","akiba07","akiba08","akiba09","akiba10","akiba11");
	$skip = array("christmas" => true);
	$characters = array("tohsaka", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-2-new", "ny-3", "ny-4", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5", "ny-5");
}
else if ($mode === 'touhou')
{
	$template_file = '/front_touhou.tpl';
	$path = 'touhou';
	$cssfile = 'front';
	$bgnames = array('genso1','genso2','genso3','genso4','genso5','genso6','genso7');
	$skip = array();
	$characters = array('alice1','alice2','alice3','chen','cirno1','cirno2','cirno3','daiyousei','eirin1','eirin2','flandre','keine','lunasa','lyrica','marisa1','marisa2','meiling','merlin','patchouli1','patchouli2','patchouli3','ran1','ran2','reimu1','reimu2','reimu3','remilia1','remilia2','sakuya','suika','sumireko','suwako1','suwako2','tenshi','tewi','yamaxanadu1','yamaxanadu2','youmu1','youmu2','yuuka','yuyuko1','yuyuko2');
}
else
{
	$mode = 'default';
	$template_file = '/front_index.tpl';
	$path = 'default';
	$cssfile = 'front';
	$bgnames = array("bg1","bg2","bg3","bg4","bg5","bg6","bg7","bg8","bg9","bg10");
	$skip = array();
	$characters = array
	(
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"kurisu","daru","okabe","mayuri","ruko","moeka","suzuha","faris","kurisutina",
		"fremy", "hanako", "homura", "tohsaka", "kate", "mary", "rinka", "reimu", "ene", "cirno", "suigintou", "yui", "utsuho", "shinobu", "snusmu"
	);
}
$bgname  = $bgnames[array_rand($bgnames)];

$randvalue = array_rand($characters);
$charimg = '<img class="char" src="images/front/' . $path . '/front/' . $characters[$randvalue] . '.png">';
if (isset($skip[$bgname]) && $skip[$bgname] == true)
{
	$charimg = '';
}
$dwoo_data->assign('bgname',      $bgname);
$dwoo_data->assign('bgpath',      $path);
$dwoo_data->assign('charimg',     $charimg);
$dwoo_data->assign('cssfile',     $cssfile);
$dwoo_data->assign('front_mode',  $mode);

$dwoo_data->assign('sections',    $sections);
$dwoo_data->assign('boards',      $boards);
$dwoo_data->assign('last_images', $last_images);
$dwoo_data->assign('last_posts',  $last_posts);
$dwoo_data->assign('diskfree',    $diskfree);
$dwoo_data->assign('disktotal',   $disktotal);
$dwoo_data->assign('postcount',   $postcount[0]['postcount']);
$dwoo_data->assign('postcountz',  $postcountz);
$dwoo_data->assign('postcounth',  $postcounth);
$dwoo->output(KU_TEMPLATEDIR . $template_file, $dwoo_data);		
