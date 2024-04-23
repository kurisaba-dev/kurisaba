<?php
require 'config.php';
define ( 'DOCUMENT_ROOT', dirname ( __FILE__ ) );
include("inc/func/nrand.php");
$ltrs = rand(5, 7);
if(!isset($_COOKIE['captchalang'])) $_COOKIE['captchalang'] = 'ru';
if($_COOKIE['captchalang'] == 'en') $captcha = english_word(3);
elseif($_COOKIE['captchalang'] == 'num') {
	//$ltrs = rand(4, 7);
	for ($i=0; $i < $ltrs; $i++) { 
		$captcha .= rand(0, 9);
	}
}
else $captcha = generate_code($ltrs);
session_start(['cookie_samesite' => 'Strict']);
function img_code($code) {
	$scolor=style_to_captcha_color(isset($_COOKIE['kustyle']) ? $_COOKIE['kustyle'] : '');
	$linenum = 5; 
	$img_arr = array("1.png");
	$font_arr = array();
		$font_arr[0]["fname"] = "OpenSans-Light.ttf";
		$font_arr[0]["size"] = 18;
	$n = rand(0,sizeof($font_arr)-1);
	$img_fn = $img_arr[rand(0, sizeof($img_arr)-1)];
	$im=imagecreatefrompng(dirname(__FILE__)."/images/captcha_back.png");
			$color = imagecolorallocate($im, 100, 100, 100);				
	$x = -15;
	mb_internal_encoding("UTF-8");
	for($i = 0; $i < mb_strlen($code); $i++) {
		$y = 0;
		$z = 24;
		$x+=20;
		$letter=mb_substr($code, $i, 1);
		imagettftext ($im, $font_arr[$n]["size"], $y, $x, $z, $color, dirname(__FILE__).'/css/fonts/'.$font_arr[$n]["fname"], $letter);

		if (!isset($_GET['captchaid'])) $_GET['captchaid'] = '';
		
		$_SESSION['security_code'.$_GET['captchaid']] = $code;
		$_SESSION['security_code'] = $code; // To support old versions
	}
	for ($i=0; $i<$linenum; $i++) 
	{
		imageline($im, rand(0, 20), rand(0, 70), rand(120, 150), rand(0, 70), $color);
	}
	$im=opsmaz($im,$scolor);

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");                   
	header("Last-Modified: " . gmdate("D, d M Y H:i:s", 10000) . " GMT");
	header("Cache-Control: no-store, no-cache, must-revalidate");         
	header("Cache-Control: post-check=0, pre-check=0", false);           
	header("Pragma: no-cache");                                           
	header("Content-Type:image/png");		
	ImagePNG ($im);
	ImageDestroy ($im);
}
 	function getKeyString(){
		return $cap->keystring;
	}
function opsmaz($img,$ncolor){
     $foreground_color =array(254,254,254);
   $background_color =array(254,254,254);
   $width=imagesx($img);
   $height=imagesy($img);
   $center=$width/2;
   $img2=imagecreatetruecolor($width, $height);
   $foreground=imagecolorresolve($img2, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
   $background=imagecolorresolve($img2, $background_color[0], $background_color[1], $background_color[2]);
   imagefilledrectangle($img2, 0, 0, $width-1, $height-1, $background);		
   imagefilledrectangle($img2, 0, $height, $width-1, $height+12, $foreground);    
		$rand1=mt_rand(00000, 750000)/10000000;
		$rand2=mt_rand(0000000, 750000)/10000000;
		$rand3=mt_rand(000000, 750000)/10000000;
		$rand4=mt_rand(000000, 750000)/10000000;
		$rand5=mt_rand(0, 31415926)/1000000;
		$rand6=mt_rand(0, 31415926)/1000000;
		$rand7=mt_rand(0, 31415926)/1000000;
		$rand8=mt_rand(0, 31415926)/1000000;
		$rand9=mt_rand(300, 330)/110;
		$rand10=mt_rand(300, 330)/110;
		for($x=0;$x<$width;$x++){
			for($y=0;$y<$height;$y++){
				$sx=$x+(sin($x*$rand1+$rand5)+sin($y*$rand3+$rand6))*$rand9-$width/2+$center+1;
				$sy=$y+(sin($x*$rand2+$rand7)+sin($y*$rand4+$rand8))*$rand10;

				if($sx<0 || $sy<0 || $sx>=$width-1 || $sy>=$height-1){
					continue;
				}else{
					$color=imagecolorat($img, $sx, $sy) & 0xFF;
					$color_x=imagecolorat($img, $sx+1, $sy) & 0xFF;
					$color_y=imagecolorat($img, $sx, $sy+1) & 0xFF;
					$color_xy=imagecolorat($img, $sx+1, $sy+1) & 0xFF;
				}
				if($color==255 && $color_x==255 && $color_y==255 && $color_xy==255){
					continue;
				}else if($color==0 && $color_x==0 && $color_y==0 && $color_xy==0){
					$newred=$foreground_color[0];
					$newgreen=$foreground_color[1];
					$newblue=$foreground_color[2];
				}else{
					$newred=$ncolor[0];
					$newgreen=$ncolor[1];
					$newblue=$ncolor[2];
				}
				imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
				imagecolortransparent($img2, imagecolorallocate($img2, 254,254,254));
			}
		}
  return $img2;
}
$_SESSION['captchatime'] = time() + KU_ADDTIME;
img_code($captcha);
?>