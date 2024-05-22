<?php
/*
 * This file is part of kusaba.
 *
 * kusaba is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * kusaba is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * kusaba; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 * +------------------------------------------------------------------------------+
 * Upload class
 * +------------------------------------------------------------------------------+
 * Used for image/misc file upload through the post form on board/thread pages
 * +------------------------------------------------------------------------------+
 */

class Upload {
	var $file_name			= '';
	var $original_file_name	= '';
	var $file_type			= '';
	var $file_md5			= '';
	var $image_md5			= '';
	var $file_location		= '';
	var $file_thumb_location = '';
	var $file_is_special	= false;
	var $imgWidth			= 0;
	var $imgHeight			= 0;
	var $file_size			= 0;
	var $imgWidth_thumb		= 0;
	var $imgHeight_thumb	= 0;
	var $isreply			= false;
	var $animated			= false;

	function HandleUpload($post_message, $temporary = false)
	{
		global $tc_db, $board_class, $is_oekaki, $oekaki;
		$thumb_result = 0;

		if ($temporary)
		{
			$srcdir = 'tmp';
			$thumbdir = 'tmp/thumb';
		}
		else
		{
			$srcdir = 'src';
			$thumbdir = 'thumb';
		}
		
		// Delete temporary files older than 10 min.
		$dir1 = KU_BOARDSDIR . $board_class->board['name'] . '/tmp';
		$dir2 = KU_BOARDSDIR . $board_class->board['name'] . '/tmp/thumb';
		$dir3 = KU_BOARDSDIR . 'tmp/xhrupload';
		if (is_dir($dir1))
		{
			$files1 = scandir($dir1);
			foreach($files1 as $key => $value) $files1[$key] = $dir1 . '/' . $value;
		}		
		if (is_dir($dir2))
		{
			$files2 = scandir($dir2);
			foreach($files2 as $key => $value) $files2[$key] = $dir2 . '/' . $value;
		}
		if (is_dir($dir3))
		{
			$files3 = scandir($dir3);
			foreach($files3 as $key => $value) $files3[$key] = $dir3 . '/' . $value;
		}
		$files = array_merge($files1, $files2, $files3);
		foreach($files as $file)
		{
			if ((time() - filemtime($file) > KU_TEMPFILESCLEAN) && is_file($file)) unlink($file);
		}

		// Try to embed something!
		if (!$is_oekaki)
		{
			if ($board_class->board['type'] == 0 || $board_class->board['type'] == 2 || $board_class->board['type'] == 3)
			{
				// Emulate old behavoir: file or embed, file has higher priority
				if (!isset($_POST['attach_type']) || $_POST['attach_type'] == '')
				{
					$_POST['attach_type'] = (isset($_FILES['imagefile'])) ? 'file' : 'embed';
				}
				
				// Embed a video.
				if ($_POST['attach_type'] == 'embed')
				{
					if (isset($_POST['embed']))
					{
						if ($_POST['embed'] != '')
						{
							$_POST['embed'] = strip_tags($_POST['embed']);
							$video_id = $_POST['embed'];
							$this->file_name = $video_id;

							if ($video_id != '' && strpos($video_id, '@') == false && strpos($video_id, '?') == false) {

								$embeds = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . KU_DBPREFIX . "embeds`");
								$worked = false;

								foreach ($embeds as $line)
								{
									if ((strtolower($_POST['embedtype']) == strtolower($line['name'])) && in_array($line['filetype'], explode(',', $board_class->board['embeds_allowed'])))
									{
										$worked = true;
										$videourl_start = $line['videourl'];
										$this->file_type = '.' . strtolower($line['filetype']);
									}
								}

								if (!$worked)
								{
									return _gettext('Invalid video type.');
								}

								$results = $tc_db->GetOne("SELECT COUNT(*) FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `file` = " . $tc_db->qstr($video_id) . " AND `IS_DELETED` = 0");
								if ($results[0] == 0)
								{
									$video_check = check_link($videourl_start . $video_id);
									switch ($video_check[1])
									{
										case 404:
											return _gettext('Unable to connect to') .': '. $videourl_start . $video_id;
											break;
										case 303:
											return _gettext('Invalid video ID.');
											break;
										case 302:
											// Continue
											break;
										case 301:
											// Continue
											break;
										case 200:
											// Continue
											break;
									}
								}
								else
								{
									$results = $tc_db->GetAll("SELECT `id`,`parentid` FROM `" . KU_DBPREFIX . "posts` WHERE `boardid` = " . $board_class->board['id'] . " AND `file` = " . $tc_db->qstr($video_id) . " AND `IS_DELETED` = 0 LIMIT 1");
									foreach ($results as $line)
									{
										$real_threadid = ($line['parentid'] == 0) ? $line['id'] : $line['parentid'];
										return sprintf(_gettext('That video ID has already been posted %shere%s.'),'<a href="' . KU_BOARDSPATH . '/' . $board_class->board['name'] . '/res/' . $real_threadid . '.html#' . $line['id'] . '">','</a>');
									}
								}
							}
							else
							{
								return _gettext('Invalid ID');
							}
						}
					}
				}
				
				// Upload an image
				else
				{
					// Detect source of file.
					$imagefile_name = '';
					if ($_POST['attach_type'] == 'file')
					{
						if (isset($_FILES['imagefile']))
						{
							$file = $_FILES['imagefile'];
							$imagefile_name = $file['name'];
						}
					}
					else if ($_POST['attach_type'] == 'drop')
					{
						if ($_POST['drop_file_name'] != '')
						{
							if(!file_exists($_POST['drop_file_name']))
							{
								return 'Время хранения файла вышло. Перезалей его ещё раз.';
							}
							$file = array();
							$file['size'] = filesize($_POST['drop_file_name']);
							$file['error'] = UPLOAD_ERR_OK;
							$file['name'] = basename($_POST['drop_file_name']);
							$file['tmp_name'] = $_POST['drop_file_name'];
							$imagefile_name = $file['name'];
						}
					}
					else /* if ($_POST['attach_type'] == 'link') */
					{
						if ($_POST['embedlink'] != '')
						{
							if (substr($_POST['embedlink'], 0, 2) == '>>')
							{
								$file = array();
								$file['name'] = substr($_POST['embedlink'], 2);
								if($temporary)
								{
									$file['tmp_name'] = KU_BOARDSDIR . $board_class->board['name'] . '/src/' . $file['name'];
								}
								else
								{
									$file['tmp_name'] = KU_BOARDSDIR . $board_class->board['name'] . '/tmp/saved' . $file['name'];
									if(!file_exists($file['tmp_name']))
									{
										return 'Время хранения файла вышло. Перезалей его ещё раз.';
									}
								}
								$file['size'] = filesize($file['tmp_name']);
								$file['error'] = UPLOAD_ERR_OK;
								$imagefile_name = $file['name'];
							}
							else
							{
								$dir3 = KU_BOARDSDIR . 'tmp/xhrupload';

								// At first, delete old files.
								if (is_dir($dir3))
								{
									$files3 = scandir($dir3);
									foreach($files3 as $key => $value) $files3[$key] = $dir3 . '/' . $value;
								}
								foreach($files3 as $file)
								{
									if ((time() - filemtime($file) > KU_TEMPFILESCLEAN) && is_file($file)) unlink($file);
								}

								// At second, count remaining files.
								if (count(scandir($dir3)) >= KU_XHRLOADLIMIT)
								{
									return 'Уже загружено много файлов. Подожди несколько минут.';
								}
								
								// Download the file (maximum length = limit + 1 byte to determine that we over that limit, if so)
								$data = file_get_contents_remote($_POST['embedlink'], false, NULL, -1, $board_class->board['maximagesize'] + 1);
								if ($data === false)
								{
									return 'Невозможно скачать файл.';
								}

								$file = array();
								
								// Create filename.
								$file['name'] = basename(explode('?',$_POST['embedlink'],2)[0]);
								$file['tmp_name'] = $dir3 . '/' . $file['name'];
								$file['size'] = file_put_contents($file['tmp_name'], $data);
								if ($file['size'] === false)
								{
									return 'Невозможно сохранить файл.';
								}

								$file['error'] = UPLOAD_ERR_OK;
								$imagefile_name = $file['name'];
							}
						}
					}

					if ($imagefile_name != '')
					{
						if ($file['size'] > $board_class->board['maximagesize'])
						{
							return sprintf(_gettext('Please make sure your file is smaller than %dB'), $board_class->board['maximagesize']);
						}

						switch ($file['error'])
						{
							case UPLOAD_ERR_OK:
								break;
							case UPLOAD_ERR_INI_SIZE:
								return sprintf(_gettext('The uploaded file exceeds the upload_max_filesize directive (%s) in php.ini.'), ini_get('upload_max_filesize'));
								break;
							case UPLOAD_ERR_FORM_SIZE:
								return sprintf(_gettext('Please make sure your file is smaller than %dB'), $board_class->board['maximagesize']);
								break;
							case UPLOAD_ERR_PARTIAL:
								return _gettext('The uploaded file was only partially uploaded.');
								break;
							case UPLOAD_ERR_NO_FILE:
								return _gettext('No file was uploaded.');
								break;
							case UPLOAD_ERR_NO_TMP_DIR:
								return _gettext('Missing a temporary folder.');
								break;
							case UPLOAD_ERR_CANT_WRITE:
								return _gettext('Failed to write file to disk');
								break;
							default:
								return _gettext('Unknown File Error');
						}

						$this->file_type = strtolower(preg_replace('/.*(\..+)/','\1',$file['name']));

						// Try to determine file type in the case of no extension is given
						if (strpos($this->file_type,'.') === false)
						{
							/* Check if the MIMEs don't match up */
							$finfo = finfo_open( FILEINFO_MIME_TYPE );
							$this->file_type = mime_to_extension(finfo_file($finfo, $file['tmp_name']));
							finfo_close($finfo);							
						}
						
						if ($this->file_type == '.jpeg') {
							/* Fix for the rarely used 4-char format */
							$this->file_type = '.jpg';
						}

						$pass = true;
						if (!is_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
							$pass = false;
						} else {
							if(in_array($this->file_type, array('.jpg', '.gif', '.webp', '.png'))) {
								if (!@getimagesize($file['tmp_name'])) {
									$pass = false;
								}
							}
						}
						if (!$pass) {
							return _gettext('File transfer failure. Please go back and try again.');
						}

						$this->file_name = substr(htmlspecialchars(preg_replace('/(.*)\..+/','\1',$file['name']), ENT_QUOTES), 0, 50);
						$this->file_name = str_replace('.','_',$this->file_name);
						$this->original_file_name = $this->file_name;
						$this->file_md5  = md5_file($file['tmp_name']);

						$exists_thread = checkMd5($this->file_md5, $board_class->board['name'], $board_class->board['id']);
						if (is_array($exists_thread)) {
							return sprintf(_gettext('Duplicate file entry detected.').' '._gettext('Already posted %shere%s.'),'<a href="' . KU_BOARDSPATH . '/' . $board_class->board['name'] . '/res/' . $exists_thread[0] . '.html#' . $exists_thread[1] . '">','</a>');
						}

						if ($this->file_type == 'svg') {
							require_once 'svg.class.php';
							$svg = new Svg($file['tmp_name']);
							$this->imgWidth = $svg->width;
							$this->imgHeight = $svg->height;
						} 
						elseif($this->file_type == '.webm' || $this->file_type == '.mp4' ) {
							// I honestly have no idea what the hay is going on there. Probably a dirty hack, should remove sometimes I guess.
							$webminfo = $pass;
							$this->imgWidth = $webminfo['width'];
							$this->imgHeight = $webminfo['height'];
						}
						else {
							$imageDim = getimagesize($file['tmp_name']);
							$this->imgWidth = $imageDim[0];
							$this->imgHeight = $imageDim[1];
							$this->image_md5 = md5_image($file['tmp_name']);
						}
						
						if ($this->imgWidth > KU_WIDTHHEIGHTLIMIT || $this->imgHeight > KU_WIDTHHEIGHTLIMIT)
						{
							return _gettext('Картинка имеет слишком большую ширину или высоту (максимум - '.KU_WIDTHHEIGHTLIMIT.'x'.KU_WIDTHHEIGHTLIMIT.').');
						}
						
						$this->file_size = $file['size'];

						$filetype_forcethumb = $tc_db->GetOne("SELECT " . KU_DBPREFIX . "filetypes.force_thumb FROM " . KU_DBPREFIX . "boards, " . KU_DBPREFIX . "filetypes, " . KU_DBPREFIX . "board_filetypes WHERE " . KU_DBPREFIX . "boards.id = " . KU_DBPREFIX . "board_filetypes.boardid AND " . KU_DBPREFIX . "filetypes.id = " . KU_DBPREFIX . "board_filetypes.typeid AND " . KU_DBPREFIX . "boards.name = '" . $board_class->board['name'] . "' and " . KU_DBPREFIX . "filetypes.filetype = '" . substr($this->file_type, 1) . "';");
						if ($filetype_forcethumb != '') {
							if ($filetype_forcethumb == 0) {
								$this->file_name = (time() + KU_ADDTIME) . mt_rand(1, 99);

								$this->file_location = KU_BOARDSDIR . $board_class->board['name'] . '/' . $srcdir . '/' . $this->file_name . $this->file_type;

								if($this->file_type != '.webm' && $this->file_type != '.mp4' ) {
									$this->file_thumb_location = KU_BOARDSDIR . $board_class->board['name'] . '/' . $thumbdir . '/' . $this->file_name . 's' . $this->file_type;
									$this->file_thumb_cat_location = $this->file_thumb_location;

									// Move file to its final place
									if (is_uploaded_file($file['tmp_name']))

									{
										if (!move_uploaded_file($file['tmp_name'], $this->file_location))
										{
											return _gettext('Could not copy uploaded image.');
										}
									}
									else
									{
										if (!copy($file['tmp_name'], $this->file_location))
										{
											unlink($file['tmp_name']);
											return _gettext('Could not copy uploaded image.');
										}
										if (($_POST['attach_type'] != 'drop') || !isset($_POST['preview_mode']))
										{
											unlink($file['tmp_name']); // Don't unlink on dropped files preview!
										}
									}

									chmod($this->file_location, 0644);

									if ($file['size'] == filesize($this->file_location))
									{
										if (isset($_POST['picspoiler']))
										{
											$this->imgWidth_thumb  = 200;
											$this->imgHeight_thumb = 200;
										}
										else
										{
											if ((!$this->isreply && ($this->imgWidth > KU_THUMBWIDTH || $this->imgHeight > KU_THUMBHEIGHT)) || ($this->isreply && ($this->imgWidth > KU_REPLYTHUMBWIDTH || $this->imgHeight > KU_REPLYTHUMBHEIGHT))) {
												if (!$this->isreply)
												{
													if (($thumb_result = createThumbnail($this->file_location, $this->file_thumb_location, KU_THUMBWIDTH, KU_THUMBHEIGHT)) > 0)
													{
														return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
													}
												}
												else
												{
													if (($thumb_result = createThumbnail($this->file_location, $this->file_thumb_location, KU_REPLYTHUMBWIDTH, KU_REPLYTHUMBHEIGHT)) > 0)
													{
														return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
													}
												}
											}
											else
											{
												if (($thumb_result = createThumbnail($this->file_location, $this->file_thumb_location, $this->imgWidth, $this->imgHeight)) > 0)
												{
													return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
												}
											}
											if ($thumb_result == -1) $this->animated = true;
											$imageDim_thumb = getimagesize($this->file_thumb_location);
											$this->imgWidth_thumb = $imageDim_thumb[0];
											$this->imgHeight_thumb = $imageDim_thumb[1];
										}
									}
									else
									{
										return _gettext('File was not fully uploaded. Please go back and try again.');
									}
								}
							}
							else
							{
								/* Fetch the mime requirement for this special filetype */
								$filetype_required_mime = $tc_db->GetOne("SELECT `mime` FROM `" . KU_DBPREFIX . "filetypes` WHERE `filetype` = " . $tc_db->qstr(substr($this->file_type, 1)));
								$this->file_name = (time() + KU_ADDTIME) . mt_rand(1, 99);
								$this->file_location = KU_BOARDSDIR . $board_class->board['name'] . '/' . $srcdir . '/' . $this->file_name . $this->file_type;
								
								if (file_exists($this->file_location))
								{
									return _gettext('A file by that name already exists');
								}
								if($this->file_type == '.mp3' || $this->file_type == '.ogg' || $this->file_type == '.m4a')
								{
									require_once(KU_ROOTDIR . 'lib/getid3/getid3.php');

									$getID3 = new getID3;
									$getID3->analyze($file['tmp_name']);
									if (isset($getID3->info['comments']['picture'][0]['data']) && isset($getID3->info['comments']['picture'][0]['image_mime'])) {
										$source_data = $getID3->info['comments']['picture'][0]['data'];
										$mime = $getID3->info['comments']['picture'][0]['image_mime'];
									}

									if($source_data) {
										$im = imagecreatefromstring($source_data);
										if (preg_match("/png/", $mime)) {
											$ext = ".png";
											imagepng($im,$this->file_location.".tmp",0,PNG_ALL_FILTERS);
										} else if (preg_match("/jpg|jpeg/", $mime)) {
											$ext = ".jpg";
											imagejpeg($im, $this->file_location.".tmp");
										} else if (preg_match("/gif/", $mime)) {
											$ext = ".gif";
											imagegif($im, $this->file_location.".tmp");
										} else if (preg_match("/webp/", $mime)) {
											$ext = ".webp";
											imagewebp($im, $this->file_location.".tmp");
										}
										$this->file_thumb_location = KU_BOARDSDIR . $board_class->board['name'] . '/' . $thumbdir . '/' . $this->file_name .'s'. $ext;
										if (!$this->isreply)
										{
											if (($thumb_result = createThumbnail($this->file_location.".tmp", $this->file_thumb_location, KU_THUMBWIDTH, KU_THUMBHEIGHT)) > 0)
											{
												return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
											}
										}
										else
										{
											if (($thumb_result = createThumbnail($this->file_location.".tmp", $this->file_thumb_location, KU_REPLYTHUMBWIDTH, KU_REPLYTHUMBHEIGHT)) > 0)
											{
												return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
											}
										}
										if ($thumb_result == -1) $this->animated = true;
										$imageDim_thumb = getimagesize($this->file_thumb_location);
										$this->imgWidth_thumb = $imageDim_thumb[0];
										$this->imgHeight_thumb = $imageDim_thumb[1];
										$imageused = true;
										unlink($this->file_location.".tmp");
									}


								}

								// Move file to its final place
								if (is_uploaded_file($file['tmp_name']))
								{
									if (!move_uploaded_file($file['tmp_name'], $this->file_location))
									{
										return _gettext('Could not copy uploaded image.');
									}
								}
								else
								{
									if (!copy($file['tmp_name'], $this->file_location))
									{
										unlink($file['tmp_name']);
										return _gettext('Could not copy uploaded image.');
									}
									if (($_POST['attach_type'] != 'drop') || !isset($_POST['preview_mode']))
									{
										unlink($file['tmp_name']); // Don't unlink on dropped files preview!
									}
								}

								/* Check if the filetype provided comes with a MIME restriction */
								if ($filetype_required_mime != '')
								{
									/* Check if the MIMEs don't match up */
									$finfo = finfo_open( FILEINFO_MIME_TYPE );
									if (!$finfo || !in_array(finfo_file($finfo, $this->file_location), explode(';', $filetype_required_mime)))
									{
										/* Delete the file we just uploaded and kill the script */
										unlink($this->file_location);
										return _gettext('Invalid MIME type for this filetype.');
									}
									if ($finfo) finfo_close($finfo);
								}

								/* Make sure the entire file was uploaded */
								if ($file['size'] == filesize($this->file_location))
								{
									$imageused = true;
								}
								else
								{
									unlink($this->file_location);
									return _gettext('File transfer failure. Please go back and try again.');
								}

								/* Flag that the file used isn't an internally supported type */
								$this->file_is_special = true;
							}
						}
						else
						{
							return _gettext('Sorry, that filetype is not allowed on this board.').'<br>Расширение файла: '.substr($this->file_type, 1);
						}
					}
				}
			}
		}
		
		// Oekaki
		else
		{
			$this->file_name = (time() + KU_ADDTIME) . mt_rand(1, 99);
			$this->original_file_name = $this->file_name;
			$this->file_md5 = md5_file($oekaki);
			$this->image_md5 = ''; // Will not support ban by oekaki.
			$this->file_type = '.png';
			$this->file_size = filesize($oekaki);
			$imageDim = getimagesize($oekaki);
			$this->imgWidth = $imageDim[0];
			$this->imgHeight = $imageDim[1];

			if (!copy($oekaki, KU_BOARDSDIR . $board_class->board['name'] . '/' . $srcdir . '/' . $this->file_name . $this->file_type)) {
				return _gettext('Could not copy uploaded image.');
			}

			$oekaki_animation = substr($oekaki, 0, -4) . '.pch';
			if (file_exists($oekaki_animation)) {
				if (!copy($oekaki_animation, KU_BOARDSDIR . $board_class->board['name'] . '/' . $srcdir . '/' . $this->file_name . '.pch')) {
					return _gettext('Could not copy animation.');
				}
				unlink($oekaki_animation);
			}

			$thumbpath = KU_BOARDSDIR . $board_class->board['name'] . '/' . $thumbdir . '/' . $this->file_name . 's' . $this->file_type;
			if (
				(!$this->isreply && ($this->imgWidth > KU_THUMBWIDTH || $this->imgHeight > KU_THUMBHEIGHT)) ||
				($this->isreply && ($this->imgWidth > KU_REPLYTHUMBWIDTH || $this->imgHeight > KU_REPLYTHUMBHEIGHT))
			)
			{
				if (!$this->isreply)
				{
					if (($thumb_result = createThumbnail($oekaki, $thumbpath, KU_THUMBWIDTH, KU_THUMBHEIGHT)) > 0)
					{
						return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
					}
				}
				else
				{
					if (($thumb_result = createThumbnail($oekaki, $thumbpath, KU_REPLYTHUMBWIDTH, KU_REPLYTHUMBHEIGHT)) > 0)
					{
						return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
					}
				}
			}
			else
			{
				if (($thumb_result = createThumbnail($oekaki, $thumbpath, $this->imgWidth, $this->imgHeight)) > 0)
				{
					return ($thumb_result > 1) ? _gettext('Could not create thumbnail.') : _gettext('Unable to read uploaded file during thumbnailing.');
				}
			}

			if ($thumb_result == -1) $this->animated = true;
			$imgDim_thumb = getimagesize($thumbpath);
			$this->imgWidth_thumb = $imgDim_thumb[0];
			$this->imgHeight_thumb = $imgDim_thumb[1];
			unlink($oekaki);
		}
		
		// Success or no embed.
		return '';
	}
}
?>
