<?php
session_start(['cookie_samesite' => 'Strict']);
require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/manage.class.php';

$manage_class = new Manage();
$manage_class->ValidateSession();
$manage_class->AdministratorsOnly();

/* Not sure if we really need to allow only POST requests:
   it does not help to avoid automated requests by DDoSers,
   but it makes stuff much harder to quickly do from the browser.
   Maybe we should incorporate this to manage menu, and then
   re-enable this check again? But for now, let's turn it off.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Use POST with a valid management token to update the engine.\n";
	exit;
} */

$manage_class->CheckToken(isset($_POST['token']) ? $_POST['token'] : '');

header('Content-Type: text/plain; charset=utf-8');
$command = 'git -C ' . escapeshellarg(KU_ROOTDIR) . ' pull --ff-only origin master 2>&1';
passthru($command, $exit_code);
exit($exit_code);
