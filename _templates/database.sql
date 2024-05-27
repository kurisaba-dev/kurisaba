CREATE TABLE IF NOT EXISTS `answers` (
  `from_id` int(10) unsigned NOT NULL,
  `from_boardid` smallint(5) unsigned NOT NULL,
  `from_parentid` int(10) NOT NULL,
  `from_boardname` varchar(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `to_id` int(10) unsigned NOT NULL,
  `to_boardid` smallint(5) unsigned NOT NULL,
  `to_parentid` int(10) NOT NULL,
  `to_boardname` varchar(75) NOT NULL,
  `IS_ARCHIVED` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=cp1251;

CREATE TABLE IF NOT EXISTS `banlist` (
`id` mediumint(8) unsigned NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `expired` tinyint(1) NOT NULL DEFAULT '0',
  `allowread` tinyint(1) NOT NULL DEFAULT '1',
  `ip` varchar(50) NOT NULL,
  `ipmd5` char(32) NOT NULL,
  `globalban` tinyint(1) NOT NULL DEFAULT '0',
  `boards` varchar(255) NOT NULL,
  `by` varchar(75) NOT NULL,
  `at` int(20) NOT NULL,
  `until` int(20) NOT NULL,
  `reason` text NOT NULL,
  `staffnote` text NOT NULL,
  `appeal` text,
  `appealat` int(20) NOT NULL DEFAULT '0'
) ENGINE=MyISAM AUTO_INCREMENT=230 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `bannedhashes` (
`id` int(10) NOT NULL,
  `md5` varchar(255) NOT NULL,
  `bantime` int(10) NOT NULL DEFAULT '0',
  `description` text NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=63 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `boards` (
`id` smallint(5) unsigned NOT NULL,
  `order` tinyint(5) NOT NULL DEFAULT '0',
  `name` varchar(75) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `start` int(10) unsigned NOT NULL DEFAULT '1',
  `uploadtype` tinyint(1) NOT NULL DEFAULT '1',
  `desc` varchar(75) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `image` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `section` tinyint(2) NOT NULL DEFAULT '0',
  `maximagesize` int(20) NOT NULL DEFAULT '4096000',
  `maxpages` int(20) NOT NULL DEFAULT '11',
  `maxage` int(20) NOT NULL DEFAULT '0',
  `markpage` tinyint(4) NOT NULL DEFAULT '99',
  `maxreplies` int(5) NOT NULL DEFAULT '500',
  `messagelength` int(10) NOT NULL DEFAULT '8192',
  `createdon` int(20) NOT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `includeheader` text COLLATE utf8_unicode_ci NOT NULL,
  `redirecttothread` tinyint(1) NOT NULL DEFAULT '0',
  `anonymous` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Аноним',
  `forcedanon` tinyint(1) NOT NULL DEFAULT '0',
  `embeds_allowed` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'you,vim,cob',
  `trial` tinyint(1) NOT NULL DEFAULT '0',
  `popular` tinyint(1) NOT NULL DEFAULT '0',
  `defaultstyle` varchar(50) CHARACTER SET latin1 NOT NULL DEFAULT '',
  `locale` varchar(30) CHARACTER SET latin1 NOT NULL DEFAULT 'ru',
  `showid` tinyint(1) NOT NULL DEFAULT '0',
  `compactlist` tinyint(1) NOT NULL DEFAULT '0',
  `enablereporting` tinyint(1) NOT NULL DEFAULT '1',
  `enablecaptcha` tinyint(1) NOT NULL DEFAULT '1',
  `enablenofile` tinyint(1) NOT NULL DEFAULT '0',
  `enablecatalog` tinyint(1) NOT NULL DEFAULT '1',
  `balls` tinyint(1) NOT NULL DEFAULT '0',
  `dice` tinyint(1) NOT NULL DEFAULT '0',
  `useragent` tinyint(1) NOT NULL DEFAULT '0',
  `hiddenthreads` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `board_filetypes` (
  `boardid` tinyint(5) NOT NULL DEFAULT '0',
  `typeid` mediumint(5) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `embeds` (
`id` tinyint(5) unsigned NOT NULL,
  `filetype` varchar(3) NOT NULL,
  `name` varchar(255) NOT NULL,
  `videourl` varchar(510) NOT NULL,
  `width` tinyint(3) unsigned NOT NULL,
  `height` tinyint(3) unsigned NOT NULL,
  `code` text NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

INSERT INTO `embeds` (`id`, `filetype`, `name`, `videourl`, `width`, `height`, `code`) VALUES
(1, 'you', 'Youtube', 'http://www.youtube.com/watch?v=', 255, 255, '<div class="thumb youtube embed wrapper" style="margin: 0px 20px 0px 0px; background-image:url(https://i.ytimg.com/vi/EMBED_ID_SHORT/0.jpg)" data-id="EMBED_ID" data-site="youtube" ONCLICK></div>'),
(2, 'vim', 'Vimeo', 'http://vimeo.com/', 200, 164, '<div class="thumb vimeo embed wrapper" style="margin: 0px;" data-id="EMBED_ID" data-site="vimeo" ONCLICK></div>'),
(3, 'cob', 'Coub', 'http://coub.com/view/', 200, 164, '<div class="thumb coub embed wrapper" style="margin: 0px;" data-id="EMBED_ID" data-site="coub" ONCLICK></div>');

CREATE TABLE IF NOT EXISTS `filetypes` (
`id` smallint(5) unsigned NOT NULL,
  `filetype` varchar(255) NOT NULL,
  `mime` varchar(255) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT '',
  `image_w` int(7) NOT NULL DEFAULT '0',
  `image_h` int(7) NOT NULL DEFAULT '0',
  `force_thumb` int(1) NOT NULL DEFAULT '1'
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

INSERT INTO `filetypes` (`id`, `filetype`, `mime`, `image`, `image_w`, `image_h`, `force_thumb`) VALUES
(1,  'jpg',  'image/jpeg', '', 0, 0, 0),
(2,  'gif',  'image/gif', '', 0, 0, 0),
(3,  'png',  'image/png', '', 0, 0, 0),
(4,  'mp3',  'audio/mpeg', 'mp3.png', 36, 48, 1),
(5,  'ogg',  'audio/ogg', 'ogg.png', 36, 48, 1),
(7,  'swf',  'application/x-shockwave-flash', 'flash.png', 36, 48, 1),
(8,  'webm', 'video/webm', 'generic.png', 255, 255, 1),
(9,  'webp', 'image/webp', '', 0, 0, 0),
(10, 'm4a',  'audio/x-m4a;audio/x-hx-aac-adts', 'm4a.png', 36, 48, 1),
(11, 'mp4',  'video/mp4', 'generic.png', 255, 255, 1);

CREATE TABLE IF NOT EXISTS `kurisaba_ext_data` (
`id` int(11) NOT NULL,
  `name` text CHARACTER SET cp1251 NOT NULL,
  `value` text CHARACTER SET cp1251 NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO `kurisaba_ext_data` (`id`, `name`, `value`) VALUES
(1, 'threadlimit_timestamp', '0'),
(3, 'thread_random', '/b/1'),
(4, 'thread_cirno', '/a/9'),
(5, 'thread_faq', '/d/1'),
(6, 'thread_dev', '/d/2'),
(7, 'special_threads', 'BOARD b\r\nTHREAD 100 /some/ Some Thread\r\nBOARD d\r\nBOARD a\r\nTHREAD 1 /a/a/ General Anime\r\nHIDDEN 9 /9/ Cirno Thread\r\n');

CREATE TABLE IF NOT EXISTS `loginattempts` (
  `username` varchar(255) NOT NULL,
  `ip` varchar(20) NOT NULL,
  `timestamp` int(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `modlog` (
  `entry` text NOT NULL,
  `user` varchar(255) NOT NULL,
  `category` tinyint(2) NOT NULL DEFAULT '0',
  `timestamp` int(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `module_settings` (
  `module` varchar(255) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'string'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `posts` (
`id` int(10) unsigned NOT NULL,
  `boardid` smallint(5) unsigned NOT NULL,
  `parentid` int(10) unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tripcode` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_source` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_md5` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `banimage_md5` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_original` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int(20) NOT NULL DEFAULT '0',
  `file_size_formatted` varchar(75) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_w` smallint(5) NOT NULL DEFAULT '0',
  `image_h` smallint(5) NOT NULL DEFAULT '0',
  `thumb_w` smallint(5) unsigned NOT NULL DEFAULT '0',
  `thumb_h` smallint(5) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(75) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ipmd5` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `timestamp` int(20) unsigned NOT NULL,
  `stickied` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `posterauthority` tinyint(1) NOT NULL DEFAULT '0',
  `reviewed` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `deleted_timestamp` int(20) NOT NULL DEFAULT '0',
  `IS_DELETED` tinyint(1) NOT NULL DEFAULT '0',
  `bumped` int(20) unsigned NOT NULL DEFAULT '0',
  `country` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'xx',
  `pic_spoiler` tinyint(1) NOT NULL DEFAULT '0',
  `pic_animated` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
`id` smallint(5) unsigned NOT NULL,
  `cleared` tinyint(1) NOT NULL DEFAULT '0',
  `board` varchar(255) NOT NULL,
  `postid` int(20) NOT NULL,
  `when` int(20) NOT NULL,
  `ip` varchar(75) NOT NULL,
  `reason` varchar(255) NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=838 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sections` (
`id` smallint(5) unsigned NOT NULL,
  `order` tinyint(3) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '0',
  `abbreviation` varchar(10) NOT NULL
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

INSERT INTO `sections` (`id`, `order`, `hidden`, `name`, `abbreviation`) VALUES
(1, 1, 0, 'Борды', 'boards');

CREATE TABLE IF NOT EXISTS `staff` (
`id` smallint(5) unsigned NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `salt` varchar(3) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `boards` text,
  `addedon` int(20) NOT NULL,
  `lastactive` int(20) NOT NULL DEFAULT '0'
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wordfilter` (
`id` smallint(5) unsigned NOT NULL,
  `word` varchar(75) NOT NULL,
  `replacedby` varchar(75) NOT NULL,
  `boards` text NOT NULL,
  `time` int(20) NOT NULL,
  `regex` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

ALTER TABLE `answers`
 ADD KEY `destination` (`to_id`,`to_boardid`), ADD KEY `source` (`from_id`,`from_boardid`);

ALTER TABLE `banlist`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `bannedhashes`
 ADD UNIQUE KEY `id` (`id`);

ALTER TABLE `boards`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `embeds`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `filetypes`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `kurisaba_ext_data`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `posts`
 ADD PRIMARY KEY (`boardid`,`id`), ADD KEY `parentid` (`parentid`), ADD KEY `bumped` (`bumped`), ADD KEY `file_md5` (`file_md5`), ADD KEY `stickied` (`stickied`);

ALTER TABLE `reports`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `sections`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `staff`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `wordfilter`
 ADD PRIMARY KEY (`id`);

ALTER TABLE `banlist`
MODIFY `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=230;

ALTER TABLE `bannedhashes`
MODIFY `id` int(10) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=63;

ALTER TABLE `boards`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=52;

ALTER TABLE `embeds`
MODIFY `id` tinyint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=5;

ALTER TABLE `filetypes`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=9;

ALTER TABLE `kurisaba_ext_data`
MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8;

ALTER TABLE `posts`
MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT;

ALTER TABLE `reports`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=838;

ALTER TABLE `sections`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=8;

ALTER TABLE `staff`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=27;

ALTER TABLE `wordfilter`
MODIFY `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=2;
