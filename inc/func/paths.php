<?php
function getCWebPath() {
	if (KU_WEBCORAL != '') {
		return KU_WEBCORAL . '/';
	}

	return KU_WEBPATH . '/';
}

function getCLBoardPath($board = '', $loadbalanceurl = '') {
	global $board_class;
	if ($loadbalanceurl == '') {
		if (KU_BOARDSCORAL != '') {
			return KU_BOARDSCORAL . '/' . $board;
		} else {
			return KU_BOARDSPATH . '/' . $board;
		}
	} elseif ($board != '') {
		return $loadbalanceurl;
	}
}
?>