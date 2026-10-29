<?php

require_once 'settings_manager.php';

if (KU_MAINPAGE != "front.php") {
    header("Location: " . KU_WEBPATH . "/" . KU_MAINPAGE);
    die();
}

// Put your front page code here
header("Location: " . KU_WEBPATH . "/kusaba.php");
