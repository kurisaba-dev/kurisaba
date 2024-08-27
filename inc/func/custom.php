<?php
/* Place any functions you create here. Note that this is not meant for module functions, which should be placed in the module's php file. */
function nslookup($ip) {

	if(filter_var($ip, FILTER_VALIDATE_IP)) {
		$ptr= implode(".",array_reverse(explode(".",$ip))).".in-addr.arpa";
			$host = @dns_get_record($ptr,DNS_PTR);
			if ($host == null) return $ip;
			else return $host[0]['target'];
		}
		
		else {
			return false;
		}
        
	}
	
function geoloc($MyIP){
	
		$geokeys = array(
			'f509a7398da4da908560a1508b7772d5cd36409921eec414d2d1851a6dcd0014',
			'526651676b00bf33296ba4e997ed10742b61a445f20d651c3e46f03ec8f223e1',
			'0022c245867a7618ef9bfddcd4c417c0e6f7b54fa527a76c0516895f0dea56e2',
			'067087c3b3f85a0c1367fa3680db5018b14435deea42547f04a239db2da1095b'
		);
		
		$getkey = array_rand($geokeys);

		$ch = curl_init("http://api.ipinfodb.com/v3/ip-city/?key=".$geokeys[$getkey]."&ip=".$MyIP."&format=xml");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		$data = curl_exec($ch);
		curl_close($ch);
		$doc = new SimpleXmlElement($data, LIBXML_NOCDATA);

		if(isset($doc)){

			return array(
				'country' 	=> $doc->countryCode,
				'region'  	=> $doc->regionName,
				'city'		=> $doc->cityName,
				'zip'		=> $doc->zipCode,
			);

		}
	}

function client_country()
{
	if(isset($_SERVER["HTTP_CF_IPCOUNTRY"])) return $_SERVER["HTTP_CF_IPCOUNTRY"];
	if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])) return geoloc($_SERVER["HTTP_CF_CONNECTING_IP"])["country"];
	return geoloc($_SERVER["REMOTE_ADDR"])["country"];
}

 function image_create_alpha ($width, $height)
{
  // Create a normal image and apply required settings
  $img = imagecreatetruecolor($width, $height);
  imagealphablending($img, false);
  imagesavealpha($img, true);
  
  // Apply the transparent background
  $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
  for ($x = 0; $x < $width; $x++)
  {
    for ($y = 0; $y < $height; $y++)
    {
      imagesetpixel($img, $x, $y, $trans);
    }
  }
  
  return $img;
} 
 
function rainbow ($ip, $threadno)
{
  $size=16;
  $steps=2;
  $step=$size/$steps;
  
  $string = $ip . $threadno;
  
  $image = image_create_alpha($size, $size);
  
  $n = 0;
  $prev = 0;
  
  $len = strlen($string);
  $sum = 0;
  for ($i=0;$i<$len;$i++) $sum += ord($string[$i]);
  
  for ($i=0;$i<$steps;$i++) {
    for ($j=0;$j<$steps;$j++) {
      $letter = $string[$n++ % $len];
      
      $u = ($n % (ord($letter)+$sum)) + ($prev % (ord($letter)+$len)) + (($sum-1) % ord($letter));
      $color = imagecolorallocate($image, pow($u*$prev+$u+$prev+5,2)%256, pow($u*$prev+$u+$prev+3,2)%256, pow($u*$prev+$u+$prev+1,2)%256);
      if (($u%2)==0)
        imagefilledpolygon($image, array($i*$step, $j*$step, $i*$step+$step, $j*$step, $i*$step, $j*$step+$step), 3, $color);
      $prev = $u;
      
      $u = ($n % (ord($letter)+$len)) + ($prev % (ord($letter)+$sum)) + (($sum-1) % ord($letter));
      if (($u%2)==0)
        imagefilledpolygon($image, array($i*$step, $j*$step+$step, $i*$step+$step, $j*$step+$step, $i*$step+$step, $j*$step), 3, $color);
      $prev = $u;
    
    }
  }
  
  ob_start (); 

  imagepng ($image);
  $image_data = ob_get_contents (); 

	ob_end_clean (); 

return base64_encode ($image_data);

} 

function omitted_syntax($posts, $images) {
  $pd = declense($posts); $id = declense($images); 
  if($pd == 0) $pw = 'постов';
  elseif($pd == 1) $pw = 'пост';
  else $pw = 'поста';
  $s = $posts.' '.$pw;
  $omit = ' пропущено.';
  if($images) {
    if($id == 0) $iw = 'изображений';
    elseif($id == 1) $iw = 'изображение';
    else $iw = 'изображения';
    $s .= ' и '.$images.' '.$iw;
  }
  elseif($posts == 1) $omit = ' пропущен.';
  return $s.$omit;
}

function declense($num) {
  if($num >= 11 && $num <= 20) return 0;
  $lastnum = $num % 10;
  if($lastnum == 0 || $lastnum >= 5) return 0;
  elseif($lastnum == 1) return 1;
  else return 2;
}

?>