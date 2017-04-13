<?php
// This script is a gateway to pleer.net that allows to embed http pages to https.
// It ensures that id contains only accepted characters.
if (preg_match('^[A-Za-z0-9]+$', $_GET['id']) > 0)
{
	header("Location: http://embedpleer.net/normal/track?id=" . $_GET['id']);
}
print ("<html><body bgcolor='#cccccc'><center>Неправильный ID ссылки: " . $_GET['id'] . ".</center></body></html>")
?>