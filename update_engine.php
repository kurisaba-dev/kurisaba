<?php
session_start(['cookie_samesite' => 'Strict']);
require 'config.php';
require KU_ROOTDIR . 'inc/functions.php';
require KU_ROOTDIR . 'inc/classes/manage.class.php';

header('Content-Type: text/plain; charset=utf-8');

$manage_class = new Manage();
$manage_class->ValidateSession();
$manage_class->AdministratorsOnly();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo "Use POST with a valid management token to update the engine.\n";
	exit;
}

$manage_class->CheckToken(isset($_POST['token']) ? $_POST['token'] : '');

$command = 'git -C ' . escapeshellarg(KU_ROOTDIR) . ' pull --ff-only origin master 2>&1';
passthru($command, $exit_code);
exit($exit_code);
