<?php 

require 'config.php';

$dir3 = KU_BOARDSDIR . 'tmp/xhrupload';

// At first, delete old files.
if (is_dir($dir3))
{
	$files3 = scandir($dir3);
	foreach($files3 as $key => $value) $files3[$key] = $dir3 . '/' . $value;
}
foreach($files3 as $file)
{
	if ((time() - filemtime($file) > KU_TEMPFILESCLEAN) && is_file($file)) unlink($file);
}

// At second, count remaining files.
if (count(scandir($dir3)) >= KU_XHRLOADLIMIT)
{
	header('HTTP/1.0 507 Insufficient Storage');
	header('Content-Type: text/html; charset=Windows-1251');
	die('уже загружено много файлов. Подожди несколько минут.');
}

// As basename works incorrectly with cyrillic names, we have to dismiss it. 
$file_name = $dir3 . '/' . (time() + KU_ADDTIME) . mt_rand(1, 99) . '_' . /*basename*/($_FILES['file']['name']);
move_uploaded_file($_FILES['file']['tmp_name'], $file_name);
echo $file_name;

?>
