-- 2018-05-25T15:38:07+02:00 - mysql:host=localhost;dbname=vzsjablonec

-- Table structure for table `akce`

DROP TABLE IF EXISTS `akce`;
CREATE TABLE `akce` (
  `id` smallint(6) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL,
  `perex` text CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL,
  `description` text CHARACTER SET utf8 COLLATE utf8_czech_ci NOT NULL,
  `message` text CHARACTER SET utf8 COLLATE utf8_czech_ci,
  `date_start` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `date_deatline` datetime NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `price` smallint(5) unsigned DEFAULT NULL,
  `akce_for_id` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `place` varchar(50) NOT NULL,
  `enable` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `confirm` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `login_mem` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `login_org` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `user_id` smallint(5) unsigned NOT NULL,
  `anketa_id` smallint(5) unsigned DEFAULT NULL,
  `forum_topic_id` smallint(5) unsigned DEFAULT NULL,
  `album_id` smallint(5) unsigned DEFAULT NULL,
  `calendarId` varchar(32) DEFAULT NULL,
  `report` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `bill` varchar(60) DEFAULT NULL,
  `file` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `date_start` (`date_start`),
  KEY `enable` (`enable`),
  KEY `confirm` (`confirm`),
  KEY `user_id` (`user_id`),
  KEY `akce_for_id` (`akce_for_id`),
  KEY `anketa_id` (`anketa_id`),
  KEY `album_id` (`album_id`),
  KEY `forum_topic_id` (`forum_topic_id`),
  CONSTRAINT `akce_ibfk_2` FOREIGN KEY (`akce_for_id`) REFERENCES `akce_for` (`id`),
  CONSTRAINT `akce_ibfk_4` FOREIGN KEY (`anketa_id`) REFERENCES `anketa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akce_ibfk_5` FOREIGN KEY (`album_id`) REFERENCES `album` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akce_ibfk_6` FOREIGN KEY (`forum_topic_id`) REFERENCES `forum_post` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akce_ibfk_7` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=619 DEFAULT CHARSET=utf8;

-- Table structure for table `akce_for`

DROP TABLE IF EXISTS `akce_for`;
CREATE TABLE `akce_for` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `text` varchar(60) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `akce_member`

DROP TABLE IF EXISTS `akce_member`;
CREATE TABLE `akce_member` (
  `akce_id` smallint(6) NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `organizator` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `created_by` smallint(5) unsigned NOT NULL,
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`akce_id`,`user_id`,`organizator`),
  KEY `akce_id` (`akce_id`),
  KEY `user_id` (`user_id`),
  KEY `organizator` (`organizator`),
  CONSTRAINT `akce_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `akce_user_ibfk_3` FOREIGN KEY (`akce_id`) REFERENCES `akce` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `akce_rating_member`

DROP TABLE IF EXISTS `akce_rating_member`;
CREATE TABLE `akce_rating_member` (
  `akce_id` smallint(6) NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `message` text COLLATE utf8_czech_ci,
  `anonymous` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `public` tinyint(1) unsigned NOT NULL DEFAULT '1',
  `date_add` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`akce_id`,`user_id`),
  KEY `akce_id` (`akce_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `akce_rating_user_ibfk_1` FOREIGN KEY (`akce_id`) REFERENCES `akce` (`id`) ON DELETE CASCADE,
  CONSTRAINT `akce_rating_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `album`

DROP TABLE IF EXISTS `album`;
CREATE TABLE `album` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci,
  `private` text COLLATE utf8_czech_ci,
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `user_id` smallint(5) unsigned NOT NULL,
  `date` date NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `pubkey` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `album_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `album_photo`

DROP TABLE IF EXISTS `album_photo`;
CREATE TABLE `album_photo` (
  `id` int(30) unsigned NOT NULL AUTO_INCREMENT,
  `album_id` smallint(5) unsigned NOT NULL,
  `filename` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `thumb` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `order` smallint(5) unsigned DEFAULT NULL,
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_taken` datetime DEFAULT NULL,
  `user_id` smallint(5) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `album_photo_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `album_photo_ibfk_2` FOREIGN KEY (`album_id`) REFERENCES `album` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20486 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `anketa`

DROP TABLE IF EXISTS `anketa`;
CREATE TABLE `anketa` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  `locked` tinyint(1) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `anketa_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `anketa_member`

DROP TABLE IF EXISTS `anketa_member`;
CREATE TABLE `anketa_member` (
  `user_id` smallint(5) unsigned NOT NULL,
  `anketa_id` smallint(5) unsigned NOT NULL,
  `anketa_odpoved_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`anketa_id`),
  KEY `anketa_id` (`anketa_id`),
  KEY `anketa_odpoved_id` (`anketa_odpoved_id`),
  CONSTRAINT `anketa_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anketa_user_ibfk_2` FOREIGN KEY (`anketa_id`) REFERENCES `anketa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `anketa_user_ibfk_3` FOREIGN KEY (`anketa_odpoved_id`) REFERENCES `anketa_odpoved` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `anketa_odpoved`

DROP TABLE IF EXISTS `anketa_odpoved`;
CREATE TABLE `anketa_odpoved` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `anketa_id` smallint(5) unsigned NOT NULL,
  `text` varchar(200) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `anketa_id` (`anketa_id`),
  CONSTRAINT `anketa_odpoved_ibfk_1` FOREIGN KEY (`anketa_id`) REFERENCES `anketa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `dokumenty`

DROP TABLE IF EXISTS `dokumenty`;
CREATE TABLE `dokumenty` (
  `id` varchar(44) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `description` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `directory` varchar(44) COLLATE utf8_czech_ci DEFAULT NULL,
  `modifiedTime` datetime NOT NULL,
  `mimeType` text COLLATE utf8_czech_ci NOT NULL,
  `webContentLink` text COLLATE utf8_czech_ci,
  `webViewLink` text COLLATE utf8_czech_ci NOT NULL,
  `iconLink` text COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumenty_category_id` (`directory`),
  CONSTRAINT `dokumenty_ibfk_2` FOREIGN KEY (`directory`) REFERENCES `dokumenty_directories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `dokumenty_directories`

DROP TABLE IF EXISTS `dokumenty_directories`;
CREATE TABLE `dokumenty_directories` (
  `id` varchar(44) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `parent` varchar(44) COLLATE utf8_czech_ci DEFAULT NULL,
  `webViewLink` text COLLATE utf8_czech_ci NOT NULL,
  `level` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent`),
  CONSTRAINT `dokumenty_directories_ibfk_3` FOREIGN KEY (`parent`) REFERENCES `dokumenty_directories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `forum`

DROP TABLE IF EXISTS `forum`;
CREATE TABLE `forum` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `ord` smallint(5) unsigned NOT NULL,
  `title` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `forum_post`

DROP TABLE IF EXISTS `forum_post`;
CREATE TABLE `forum_post` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `forum_id` tinyint(3) unsigned NOT NULL,
  `forum_topic_id` smallint(5) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `row_number` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `title` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  `locked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `hidden` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `forum_id` (`forum_id`),
  KEY `forum_topic_id` (`forum_topic_id`),
  KEY `row_number` (`row_number`),
  KEY `user_id` (`user_id`),
  FULLTEXT KEY `title_text` (`title`,`text`),
  CONSTRAINT `forum_post_ibfk_4` FOREIGN KEY (`forum_id`) REFERENCES `forum` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_post_ibfk_5` FOREIGN KEY (`forum_topic_id`) REFERENCES `forum_post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `forum_post_ibfk_6` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4153 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `hlasovani`

DROP TABLE IF EXISTS `hlasovani`;
CREATE TABLE `hlasovani` (
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(80) COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  `locked` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `date_deatline` date NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `hlasovani_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `hlasovani_member`

DROP TABLE IF EXISTS `hlasovani_member`;
CREATE TABLE `hlasovani_member` (
  `user_id` smallint(5) unsigned NOT NULL,
  `hlasovani_id` tinyint(4) unsigned NOT NULL,
  `hlasovani_odpoved_id` tinyint(4) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`hlasovani_id`),
  KEY `hlasovani_id` (`hlasovani_id`),
  KEY `hlasovani_odpoved_id` (`hlasovani_odpoved_id`),
  CONSTRAINT `hlasovani_user_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `hlasovani_user_ibfk_4` FOREIGN KEY (`hlasovani_id`) REFERENCES `hlasovani` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `hlasovani_user_ibfk_6` FOREIGN KEY (`hlasovani_odpoved_id`) REFERENCES `hlasovani_odpoved` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table structure for table `hlasovani_odpoved`

DROP TABLE IF EXISTS `hlasovani_odpoved`;
CREATE TABLE `hlasovani_odpoved` (
  `id` tinyint(4) unsigned NOT NULL AUTO_INCREMENT,
  `hlasovani_id` tinyint(4) unsigned NOT NULL,
  `text` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hlasovani_id` (`hlasovani_id`),
  CONSTRAINT `hlasovani_odpoved_ibfk_2` FOREIGN KEY (`hlasovani_id`) REFERENCES `hlasovani` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8;

-- Table structure for table `message`

DROP TABLE IF EXISTS `message`;
CREATE TABLE `message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_type_id` int(11) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `date_send` datetime DEFAULT NULL,
  `subject` text COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci NOT NULL,
  `param` text COLLATE utf8_czech_ci,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `message_type_id` (`message_type_id`),
  CONSTRAINT `message_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_ibfk_2` FOREIGN KEY (`message_type_id`) REFERENCES `message_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `message_type`

DROP TABLE IF EXISTS `message_type`;
CREATE TABLE `message_type` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `message_user`

DROP TABLE IF EXISTS `message_user`;
CREATE TABLE `message_user` (
  `message_id` int(10) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`message_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `message_user_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `message` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `module_log`

DROP TABLE IF EXISTS `module_log`;
CREATE TABLE `module_log` (
  `user_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `module_id` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`date_add`),
  CONSTRAINT `module_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `password_attempt`

DROP TABLE IF EXISTS `password_attempt`;
CREATE TABLE `password_attempt` (
  `user_id` smallint(5) unsigned NOT NULL,
  `count` tinyint(3) unsigned NOT NULL,
  `date_end` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `password_attempt_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `password_session`

DROP TABLE IF EXISTS `password_session`;
CREATE TABLE `password_session` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `pubkey` varchar(32) DEFAULT NULL,
  `date_end` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_session_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Table structure for table `report`

DROP TABLE IF EXISTS `report`;
CREATE TABLE `report` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8_czech_ci NOT NULL,
  `report_type_id` tinyint(3) unsigned DEFAULT NULL,
  `type_text` varchar(30) COLLATE utf8_czech_ci DEFAULT NULL,
  `place` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  `date_start` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `pocasi` text COLLATE utf8_czech_ci NOT NULL,
  `osetreni_drobne` tinyint(3) unsigned DEFAULT NULL,
  `osetreni_vetsi` tinyint(3) unsigned DEFAULT NULL,
  `osetreni_odvoz` tinyint(3) unsigned DEFAULT NULL,
  `akce_breh` tinyint(3) unsigned DEFAULT NULL,
  `akce_voda` tinyint(3) unsigned DEFAULT NULL,
  `akce_majetek` tinyint(3) unsigned DEFAULT NULL,
  `popis` text COLLATE utf8_czech_ci NOT NULL,
  `public` text COLLATE utf8_czech_ci,
  `zos_id` smallint(5) unsigned NOT NULL,
  `material_ms` text COLLATE utf8_czech_ci,
  `material_cizi` text COLLATE utf8_czech_ci,
  `material_ztraty` text COLLATE utf8_czech_ci,
  `doprava` text COLLATE utf8_czech_ci,
  `member_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `report_type_id` (`report_type_id`),
  KEY `zos_id` (`zos_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `report_ibfk_1` FOREIGN KEY (`report_type_id`) REFERENCES `report_type` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_ibfk_2` FOREIGN KEY (`zos_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_ibfk_3` FOREIGN KEY (`member_id`) REFERENCES `user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=554 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `report_member`

DROP TABLE IF EXISTS `report_member`;
CREATE TABLE `report_member` (
  `report_id` smallint(5) unsigned NOT NULL,
  `user_id` smallint(5) unsigned NOT NULL,
  `date_start` datetime NOT NULL,
  `date_end` datetime NOT NULL,
  `hodiny` float unsigned NOT NULL,
  `placeno` float unsigned NOT NULL,
  PRIMARY KEY (`report_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `report_user_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `report` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `report_type`

DROP TABLE IF EXISTS `report_type`;
CREATE TABLE `report_type` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(30) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `rights`

DROP TABLE IF EXISTS `rights`;
CREATE TABLE `rights` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `roles`

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `user`

DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(40) COLLATE utf8_czech_ci NOT NULL,
  `surname` varchar(40) COLLATE utf8_czech_ci NOT NULL,
  `hash` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL,
  `date_born` date DEFAULT NULL,
  `zamestnani` varchar(30) COLLATE utf8_czech_ci DEFAULT NULL,
  `mesto` varchar(30) COLLATE utf8_czech_ci DEFAULT NULL,
  `ulice` varchar(30) COLLATE utf8_czech_ci DEFAULT NULL,
  `mail` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `mail2` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `send_to_second` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `telefon` char(9) COLLATE utf8_czech_ci DEFAULT NULL,
  `telefon2` char(9) COLLATE utf8_czech_ci DEFAULT NULL,
  `text` text COLLATE utf8_czech_ci,
  `role` tinyint(4) DEFAULT '0',
  `date_add` date DEFAULT NULL,
  `date_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `mail2` (`mail2`),
  UNIQUE KEY `mail_mail2` (`mail`, `mail2`),
  KEY `role` (`role`),
  CONSTRAINT `user_ibfk_1` FOREIGN KEY (`role`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `user_log`

DROP TABLE IF EXISTS `user_log`;
CREATE TABLE `user_log` (
  `user_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  `method_id` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`user_id`,`date_add`),
  CONSTRAINT `user_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `user_rights`

DROP TABLE IF EXISTS `user_rights`;
CREATE TABLE `user_rights` (
  `user_id` smallint(5) unsigned NOT NULL,
  `rights_id` tinyint(4) NOT NULL,
  PRIMARY KEY (`user_id`,`rights_id`),
  KEY `rights_id` (`rights_id`),
  CONSTRAINT `user_rights_ibfk_1` FOREIGN KEY (`rights_id`) REFERENCES `rights` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_rights_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Trigger structure `password_session_bi`

DROP TRIGGER IF EXISTS `password_session_bi`;
DELIMITER //
CREATE TRIGGER `password_session_bi` BEFORE INSERT ON `password_session` FOR EACH ROW
SET NEW.pubkey = MD5(UUID())
//
DELIMITER ;

-- Completed on: 2018-05-25T15:38:07+02:00