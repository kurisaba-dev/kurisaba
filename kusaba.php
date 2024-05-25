<?php
if (file_exists("install.php")) {
	die('You are seeing this message because either you haven\'t ran the install file yet, and can do so <a href="install.php">here</a>, or already have, and <strong>must delete it</strong>.');
}
if (!isset($_GET['info'])) {
	$preconfig_db_unnecessary = true;
}
require 'config.php';
$menufile = 'menu.php';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title><?php echo KU_NAME; ?></title>
	<link rel="shortcut icon" href="<?php echo KU_WEBPATH; ?>/favicon.ico" />
	<style type="text/css">
		body, html {
			width: 100%;
			height: 100%;
			margin: 0;
			padding: 0;
			overflow: auto;
		}
		#menu {
			margin: 0;
			padding: 0;
			border: 0px;
			height: 100%;
			width: 100%;
		}
		#main {
			border: 0px;
			height: 100%;
			width: 100%;
		}
	</style>
</head>
<?php
if (isset($_GET['info'])) {
	require KU_ROOTDIR . 'inc/functions.php';
	echo '<body>';
	echo '<h1>General info:</h1><ul>';
	echo '<li>Version: Kurisaba ' . KU_VERSION . '</li>';
	$bans = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."banlist`");
	echo '<li>Active bans: ' . $bans . '</li>';
	$wordfilters = $tc_db->GetOne("SELECT COUNT(*) FROM `".KU_DBPREFIX."wordfilter`");
	echo '<li>Wordfilters: ' . $wordfilters . '</li>';
	echo '<li>Modules loaded: ';
	$modules = modules_list();
	if (count($modules) > 0) {
		$moduleslist = '';
		foreach ($modules as $module) {
			$moduleslist .= $module . ', ';
		}
		echo substr($moduleslist, 0, -2);
	} else {
		echo 'none';
	}
	echo '</li>';
	echo '</ul>';
	echo '</body></html>';
	die();
}
?>
<body style="overflow: hidden;">
	<table style="width: 100%; height: 100%; border-collapse: collapse;"><tr><td style="width: 15%; min-width: 240px; padding: 0px;">
	<iframe src="<?php echo $menufile; ?>" name="menu" id="menu">
		<a href="<?php echo KU_WEBPATH . '/' . $menufile; ?>"><?php echo KU_NAME; ?></a>
	</iframe>
	</td><td style="padding: 0px; height: 100%;">
	<iframe src="/<?php echo KU_DEFAULTBOARD;?>/" name="main" id="main">
		<a href="<?php echo KU_WEBPATH;?>/<?php echo KU_DEFAULTBOARD;?>/"><?php echo KU_NAME; ?><?php echo KU_DEFAULTBOARD;?></a>
	</iframe>
	</td></tr></table>
</body>
</html>
