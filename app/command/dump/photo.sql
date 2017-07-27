-- 2017-07-27T19:39:12+02:00 - mysql:host=localhost;dbname=photo

-- Table structure for table `album`

DROP TABLE IF EXISTS `album`;
CREATE TABLE `album` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `text` text COLLATE utf8_czech_ci,
  `member_id` smallint(5) unsigned NOT NULL,
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `date` date NOT NULL,
  `date_add` datetime NOT NULL,
  `date_update` datetime NOT NULL,
  `pubkey` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `member_log`

DROP TABLE IF EXISTS `member_log`;
CREATE TABLE `member_log` (
  `member_id` smallint(5) unsigned NOT NULL,
  `date_add` datetime NOT NULL,
  PRIMARY KEY (`member_id`,`date_add`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Table structure for table `photo`

DROP TABLE IF EXISTS `photo`;
CREATE TABLE `photo` (
  `id` int(30) unsigned NOT NULL AUTO_INCREMENT,
  `album_id` smallint(5) unsigned NOT NULL,
  `filename` varchar(50) COLLATE utf8_czech_ci NOT NULL,
  `text` varchar(50) COLLATE utf8_czech_ci DEFAULT NULL,
  `order` smallint(5) unsigned DEFAULT NULL,
  `visible` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `date_add` datetime NOT NULL,
  `date_taken` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `album_id` (`album_id`),
  CONSTRAINT `photo_ibfk_1` FOREIGN KEY (`album_id`) REFERENCES `album` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18128 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- Trigger structure `album_bi_pubkey`

DROP TRIGGER IF EXISTS `album_bi_pubkey`;
DELIMITER //
CREATE TRIGGER `album_bi_pubkey` BEFORE INSERT ON `album` FOR EACH ROW
SET NEW.pubkey = MD5(UUID())
//
DELIMITER ;

-- Completed on: 2017-07-27T19:39:12+02:00
