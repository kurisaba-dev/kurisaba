<?php

require 'settings_manager.php';
define('DOCUMENT_ROOT', dirname(__FILE__));
include("lib/func/nrand.php");
$ltrs = rand(KU_CAPTCHAMINLENGTH, KU_CAPTCHAMAXLENGTH);
if (!isset($_COOKIE['captchalang']) || !in_array($_COOKIE['captchalang'], explode(',', KU_CAPTCHAENABLED))) {
    $_COOKIE['captchalang'] = explode(',', KU_CAPTCHAENABLED)[0];
}
if ($_COOKIE['captchalang'] == 'en') {
    $captcha = english_word($ltrs);
} elseif ($_COOKIE['captchalang'] == 'num') {
    for ($i = 0; $i < $ltrs; $i++) {
        $captcha .= rand(0, 9);
    }
} elseif ($_COOKIE['captchalang'] == 'jp') {
    $captcha = japanese_word($ltrs * KU_KANAMULTIPLIER);
} elseif ($_COOKIE['captchalang'] == 'ru') {
    $captcha = generate_code($ltrs);
} else {
    $captcha = ''; // We get here if captcha.php called when captcha of unsupported type is enabled, like MTCaptcha; so we just record generate call and leave.
}
session_start(['cookie_samesite' => 'Strict']);
function img_code($code)
{
    // Decide about font, number of lines, and image width
    $font_arr = array();
    if ($_COOKIE['captchalang'] == 'jp') {
        $linenum = 1;
        $font_arr[0]["fname"] = "NotoSansJP-VariableFont_wght.ttf";
    } else {
        $linenum = 5;
        $font_arr[0]["fname"] = "OpenSans-Light.ttf";
    }
    $font_arr[0]["size"] = 18;
    $n = rand(0, sizeof($font_arr) - 1);
    $img_width = 10 + 20 * mb_strlen($code);

    // Draw a text and lines on draft image (... x 30)
    $im = imagecreatetruecolor($img_width, 30);
    $color = imagecolorallocate($im, 255, 255, 255);
    $x = -15;
    mb_internal_encoding("UTF-8");
    for ($i = 0; $i < mb_strlen($code); $i++) {
        $y = 0;
        $z = 24;
        $x += 20;
        $letter = mb_substr($code, $i, 1);
        imagettftext($im, $font_arr[$n]["size"], $y, $x, $z, $color, dirname(__FILE__).'/static/fonts/'.$font_arr[$n]["fname"], $letter);

        if (!isset($_GET['captchaid'])) {
            $_GET['captchaid'] = '';
        }

        $_SESSION['security_code'.$_GET['captchaid']] = $code;
        $_SESSION['security_code'] = $code; // To support old versions
    }
    for ($i = 0; $i < $linenum; $i++) {
        imageline($im, rand(0, 20), rand(0, 30), rand($img_width - 30, $img_width), rand(0, 30), $color);
    }

    // Distort the original image
    $im_opsmaz = imagecreatetruecolor($img_width, 30);
    imagefilledrectangle($im_opsmaz, 0, 0, $img_width, 30, imagecolorallocate($im_opsmaz, 255, 255, 255));
    $rand1 = mt_rand(0, 750000) / 10000000;
    $rand2 = mt_rand(0, 750000) / 10000000;
    $rand3 = mt_rand(0, 750000) / 10000000;
    $rand4 = mt_rand(0, 750000) / 10000000;
    $rand5 = mt_rand(0, 31415926) / 1000000;
    $rand6 = mt_rand(0, 31415926) / 1000000;
    $rand7 = mt_rand(0, 31415926) / 1000000;
    $rand8 = mt_rand(0, 31415926) / 1000000;
    $rand9 = mt_rand(300, 330) / 110;
    $rand10 = mt_rand(300, 330) / 110;
    for ($x = 0; $x < $img_width; $x++) {
        for ($y = 0; $y < 30; $y++) {
            $sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 + 1;
            $sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;
            if ($sx >= 0 && $sy >= 0 && $sx < $img_width - 1 && $sy < 29) {
                $color = imagecolorat($im, $sx, $sy) & 0xFF;
                $color_x = imagecolorat($im, $sx + 1, $sy) & 0xFF;
                $color_y = imagecolorat($im, $sx, $sy + 1) & 0xFF;
                $color_xy = imagecolorat($im, $sx + 1, $sy + 1) & 0xFF;
                if (($color < 255 || $color_x < 255 || $color_y < 255 || $color_xy < 255) && ($color > 0 || $color_x > 0 || $color_y > 0 || $color_xy > 0)) {
                    imagesetpixel($im_opsmaz, $x, $y, imagecolorallocate($im_opsmaz, 0, 0, 0));
                }
            }
        }
    }
    imagedestroy($im);

    // Resize to final size (80 x 19)
    $im_80x19 = imagecreatetruecolor(80, 19);
    imagecopyresampled($im_80x19, $im_opsmaz, 0, 0, 0, 0, 80, 19, $img_width, 30);
    imagedestroy($im_opsmaz);

    // Colorize final image and add transparency
    $scolor = captcha_color(isset($_COOKIE['captchacolor']) ? $_COOKIE['captchacolor'] : '');
    $im_result = imagecreatetruecolor(80, 19);
    imagealphablending($im_result, false);
    imagesavealpha($im_result, true);
    for ($x = 0; $x < 80; $x++) {
        for ($y = 0; $y < 19; $y++) {
            $rgb = imagecolorat($im_80x19, $x, $y);
            $alpha = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) * 127 / 3 / 255;
            imagesetpixel($im_result, $x, $y, imagecolorallocatealpha($im_result, $scolor[0], $scolor[1], $scolor[2], $alpha));
        }
    }
    imagedestroy($im_80x19);

    // Spit out the resulting image
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s", 10000) . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Content-Type:image/png");
    imagepng($im_result);
    imagedestroy($im_result);
}

if($captcha) {
    $_SESSION['captchatime'] = time() + KU_ADDTIME;
    img_code($captcha);
}

$tc_db->Execute("CREATE TABLE IF NOT EXISTS `" . KU_DBPREFIX . "captcha_generate` (`lang` varchar(32), `etalon` varchar(32), `time` int(20) unsigned NOT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
$tc_db->Execute("INSERT INTO `" . KU_DBPREFIX . "captcha_generate` (`lang`, `etalon`, `time`) VALUES (" . $tc_db->qstr($_COOKIE['captchalang']) . ", " . $tc_db->qstr($captcha) . ", '" . (time() + KU_ADDTIME) . "')");
