<?php
// This script is a gateway to pleer.net that allows to embed http pages to https.
// It ensures that id contains only accepted characters.
// Yes, it may be exploited as security breach, but we have to deal with it until pleer.net start to accept https.
if (preg_match('/^[A-Za-z0-9]+$/', $_GET['id']) > 0)
{
	print("<script>document.location.href='http://embedpleer.net/normal/track?id=" . $_GET['id'] . "';</script>");
}
print ("<html><head><meta http-equiv='Content-Type' content='text/html;charset=UTF-8'></head><body bgcolor='#cccccc'><center>Неправильный ID ссылки: " . $_GET['id'] . ".</center></body></html>")
?>