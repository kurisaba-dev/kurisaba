<?php
mb_internal_encoding("UTF-8");

$wovels = 'уеыаоэяию';
$wovelsnoy = 'уеаоэяию';
$h_wovels = 'уеаоэи';
$consonants = 'цкнгшщзхфвпрлджчсмтб';

$start = array( 'н' => "цзврд",
				'т' => "цнщфврлм",
				'ш' => "кнвпрлмт",
				'в' => "кшзврлдчсмб",
				'с' => "кнгшхфвпрлджчсмтб",
				'ц' => "нзхврлм",
				'к' => "ншзхфврлсмт",
				'г' => "нзврлдм",
				'з' => "нврлдм",
				'х' => "нврлмт",
				'л' => "гвлж",
				'д' => "нзврлжм",
				'ч' => "нхвлм",
				'м' => "янгщрлд",
				'ж' => "грлдмб",
				'б' => "рлдж",
				'п' => "шщрлст",
				'р' => "фвж",
				'ф' => "рлчмт",
				'щ' => "мт",
				'у' => "еаояию",
				'е' => "уао",
				'ы' => "",
				'а' => "уеоэяию",
				'о' => "уеяю",
				'э' => "уаяю",
				'я' => "уи",
				'и' => "уеаояию",
				'ю' => "уаоэи",
			);
$mid = array( 	'ц' => "цкншхфврлмт",
				'к' => "цкншщзхфврлдчсмт",
				'н' => "цкнгшщзхфвпрлджчсмтб",
				'ш' => "цкнхвпрлдсмт",
				'х' => "цншщвпрлсмт",
				'ф' => "цкнфрлст",
				'в' => "цкнгшзхврлдчсмтб",
				'п' => "цкншхфпрлчст",
				'р' => "цкнгшзхфвпрлджчсмтб",
				'л' => "цкнгшзхфвплджчсмтб",
				'д' => "цкнгзхфврлджчсмб",
				'с' => "цкнгшхфвпрлджчсмт",
				'т' => "ацкнщзхфврлжчсмт",
				'б' => "цнгшщзхфврлджчсмб",
				'щ' => "нт",
				'з' => "кнгзфврлджмтб",
				'ж' => "кнгзвпрлджсмб",
				'ч' => "кнхфвлсмт",
				'м' => "кнгщзхфвпрлджчсмтб",
				'г' => "нгшзврлдчсмб",
				'у' => "еаоэяию",
				'е' => "уеаояю",
				'ы' => "уаояю",
				'а' => "уеоэяию",
				'о' => "уеэяию",
				'э' => "уаояию",
				'я' => "туои",
				'и' => "уеаоэяю",
				'ю' => "еаэ",
			);
$end = array( 	'к' => "цкшхфлчст",
				'н' => "цкнгшзхджчст",
				'х' => "цлт",
				'в' => "цшзрлжс",
				'п' => "цшхфрст",
				'л' => "цкншзхфвплдчмтб",
				'д' => "цзхрлдж",
				'т' => "ацхрлст",
				'б' => "цшзрлжс",
				'ф' => "кфрлст",
				'р' => "кнгшщзхфвпрджчсмтб",
				'с' => "кшплст",
				'м' => "ншзфвпчсмтб",
				'г' => "зфлжс",
				'ц' => "л",
				'з' => "лдм",
				'ш' => "смт",
				'ч' => "т",
				'щ' => "с",
				'ж' => "рлд",
				'у' => "аоэяию",
				'е' => "уаояию",
				'ы' => "аояю",
				'а' => "уеоэяию",
				'о' => "уеаяию",
				'э' => "уояию",
				'я' => "уояию",
				'и' => "уеаояю",
				'ю' => "аоэ"
			);
$freqs = array( 'а' => "0.09582817",
				'о' => "0.089425857",
				'и' => "0.083643123",
				'е' => "0.08488228",
				'н' => "0.065572078",
				'р' => "0.065055762",
				'к' => "0.057104502",
				'т' => "0.056278397",
				'с' => "0.044609665",
				'л' => "0.043267245",
				'в' => "0.035832301",
				'п' => "0.034593143",
				'д' => "0.033973565",
				'м' => "0.029946303",
				'у' => "0.026435357",
				'я' => "0.022924411",
				'б' => "0.021788517",
				'г' => "0.019619992",
				'ы' => "0.01559273",
				'з' => "0.014560099",
				'ж' => "0.013114416",
				'ч' => "0.010636101",
				'х' => "0.009500207",
				'ш' => "0.008054523",
				'ю' => "0.005369682",
				'ц' => "0.005059893",
				'ф' => "0.004130525",
				'щ' => "0.001755473",
				'э' => "0.001445684" );

function generate_code($length = 7) {
	global $wovels, $wovelsnoy, $consonants, $start, $mid, $end, $freqs;

	for ($i = 0; $i < $length; $i++) {
		if($i == 0) {
			$chars = $wovelsnoy.$consonants;
		}
		elseif($i == 1) {
			$chars = wcdispatch($result[$i-1], $start);
		}
		elseif(in_array($result[$i-1], mbStringToArray($wovels)) && in_array($result[$i-2], mbStringToArray($wovels))) {
			$chars = $consonants;
		}
		elseif(in_array($result[$i-1], mbStringToArray($consonants)) && in_array($result[$i-2], mbStringToArray($consonants))) {
			$chars = checkharmony($result[$i-1]);
		}
		elseif($i == ($length-1)) {
			$chars = wcdispatch($result[$i-1], $end);
		}
		else {
			$chars = wcdispatch($result[$i-1], $mid);
		}
		$charset = mbStringToArray($chars);
		$sum = 0;
		foreach($charset as $char) {
			$sum += $freqs[$char];
		}
		$xfq = array();
		$bsum = 0;
		foreach($charset as $char) {
			$balanced = $freqs[$char] / $sum;
			$bsum += $balanced;
			$xfq[(string)$bsum] = $char;
		}
		$roll = frand();
		foreach($xfq as $freq => $letter) {
			if($roll < $freq) {
				$result[$i] = $letter;
				$roll = 2;
			}
		}
	}

	mt_srand(make_seed());

	$array_mix = preg_split('//',mb_strtoupper(implode("", $result)), -1, PREG_SPLIT_NO_EMPTY);
	return implode("", $array_mix);
}

function wcdispatch($prev, $position) {
	global $wovels, $consonants, $h_wovels;
	if(in_array($prev, mbStringToArray($wovels))) {
		$chars = $consonants.$position[$prev];
	}
	else {
		$chars = checkharmony($prev).$position[$prev];
	}
	return $chars;
}

function checkharmony($con) {
	global $wovels, $h_wovels;
	return (in_array($con, array('ж','ш','щ','ч','ц'))) ? $h_wovels : $wovels;
}

function make_seed() {
	list($usec, $sec) = explode(' ', microtime());
	return (float) $sec + ((float) $usec * 100000);
}

function frand() {
	return mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
}

function mbStringToArray($string) { 
    $strlen = mb_strlen($string); 
    while ($strlen) { 
        $array[] = mb_substr($string,0,1,"UTF-8"); 
        $string = mb_substr($string,1,$strlen,"UTF-8"); 
        $strlen = mb_strlen($string); 
    } 
    return $array; 
}

function english_word( $length = 6 ) {
       
        // consonant sounds
        $cons = array(
                // single consonants. Beware of Q, it's often awkward in words
                '1','2','3',
        );
       
        // consonant combinations that cannot start a word
        $cons_cant_start = array(
                '',
        );
       
        // wovels
        $vows = array(
                // single vowels
                '1','2','3',
        );
       
        // start by vowel or consonant ?
        $current = ( mt_rand( 0, 1 ) == '0' ? 'cons' : 'vows' );
       
        $word = '';
               
        while( strlen( $word ) < $length ) {
       
                // After first letter, use all consonant combos
                if( strlen( $word ) == 2 )
                        $cons = array_merge( $cons, $cons_cant_start );
 
                 // random sign from either $cons or $vows
                $rnd = ${$current}[ mt_rand( 0, count( ${$current} ) -1 ) ];
               
                // check if random sign fits in word length
                if( strlen( $word . $rnd ) <= $length ) {
                        $word .= $rnd;
                        // alternate sounds
                        $current = ( $current == 'cons' ? 'vows' : 'cons' );
                }
        }
       
        return strtoupper($word);
}
?>