SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

INSERT INTO `user` (`id`, `name`, `surname`, `mail`, `role`) VALUES
	(0,	'Franta',	'Deleted',	'deleted@vzs-jablonec.cz',	NULL),
	(1,	'Tonda',	'User',			'user@vzs-jablonec.cz',			0),
	(2,	'Jirka',	'Member',		'member@vzs-jablonec.cz',		1),
	(3,	'Pepa',		'Board',		'board@vzs-jablonec.cz',		2),
	(4,	'Míra',		'Admin',		'admin@vzs-jablonec.cz',		3);

INSERT INTO `akce` (`id`, `name`, `perex`, `description`, `date_start`, `date_end`, `date_deatline`, `date_add`, `date_update`, `akce_for_id`, `place`, `created_by`, `modified_by`, `confirm`) VALUES
	(1, 'Minulá akce', 'Akce', 'Akce odehrávající se v minulosti', NOW() - INTERVAL 48 HOUR, NOW() - INTERVAL 47 HOUR, NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 12 HOUR, 1, 'VD Mšeno', 2, 2, 1),
	(2, 'Budoucí akce', 'Akce', 'Akce odehrávající se v budoucnosti', NOW() + INTERVAL 48 HOUR, NOW() + INTERVAL 49 HOUR, NOW() + INTERVAL 24 HOUR, NOW(), NOW(), 1, 'VD Mšeno', 2, 2, 1),
	(3, 'Minulá neschválená akce', 'Akce', 'Akce odehrávající se v minulosti, která není schválená', NOW() - INTERVAL 48 HOUR, NOW() - INTERVAL 47 HOUR, NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 12 HOUR, 1, 'VD Mšeno', 2, 2, 0),
	(4, 'Budoucí nechválená akce', 'Akce', 'Akce odehrávající se v budoucnosti, která není schválená', NOW() + INTERVAL 48 HOUR, NOW() + INTERVAL 49 HOUR, NOW() + INTERVAL 24 HOUR, NOW(), NOW(), 1, 'VD Mšeno', 2, 2, 0),
	(5, 'Minulá smazaná akce', 'Akce', 'Akce odehrávající se v minulosti, která je smazaná', NOW() - INTERVAL 48 HOUR, NOW() - INTERVAL 47 HOUR, NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 12 HOUR, NOW() - INTERVAL 12 HOUR, 1, 'VD Mšeno', 2, 2, 0),
	(6, 'Budoucí smazaná akce', 'Akce', 'Akce odehrávající se v budoucnosti, která je smazaná', NOW() + INTERVAL 48 HOUR, NOW() + INTERVAL 49 HOUR, NOW() + INTERVAL 24 HOUR, NOW(), NOW(), 1, 'VD Mšeno', 2, 2, 0);

INSERT INTO `akce_member` (`id`, `akce_id`, `user_id`, `organizator`, `created_by`, `date_add`, `deleted_by`,	`date_deleted`) VALUES
	(1, 1, 2, 1, 2, NOW(), 2, NOW()),
	(2, 1, 1, 0, 2, NOW(), 2, NOW()),
	(3, 1, 2, 1, 2, NOW(), NULL, NULL),
	(4, 1, 1, 0, 2, NOW(), NULL, NULL),
	(5, 2, 2, 1, 2, NOW(), NULL, NULL),
	(6, 2, 1, 0, 2, NOW(), NULL, NULL),
	(7, 4, 2, 1, 3, NOW(), NULL, NULL),
	(8, 4, 3, 0, 3, NOW(), NULL, NULL);

INSERT INTO `forum_post` (`id`, `forum_topic_id`, `forum_id`, `user_id`, `row_number`, `date_add`, `date_update`, `title`, `text`, `locked`, `hidden`) VALUES
	(1, 1, 1, 1, 1, NOW(), NOW(), 'Obecné téma', 'Obecně', 0, 0),
	(2, 2, 1, 2, 1, NOW(), NOW(), 'Obecné zamčené téma', 'Zamčeno', 1, 0),
	(3, 3, 1, 3, 1, NOW(), NOW(), 'Obecné Board téma', 'Hmm', 0, 0),
	(4, 4, 1, 4, 0, NOW(), NOW(), 'Obecné Admin smazané téma', 'Smazáno', 0, 1),

	(5, 1, 1, 2, 2, NOW(), NOW(), NULL, 'Příspěvěk', 0, 0),
	(6, 2, 1, 2, 2, NOW(), NOW(), NULL, 'Příspěvěk', 0, 0),
	(7, 3, 1, 3, 0, NOW(), NOW(), NULL, 'Smazáno', 0, 1),
	(8, 4, 1, 2, 2, NOW(), NOW(), NULL, 'Příspěvěk', 0, 0);

INSERT INTO `anketa` (`id`, `title`, `text`, `locked`, `date_add`, `date_update`, `user_id`) VALUES
	(1, 'Testovací anketa', 'Testovací popis',	0, NOW(), NOW(), 2),
	(2, 'Zamčená anketa', 	'Zamčeno',					1, NOW(), NOW(), 2),
	(3, 'Prázdná anketa', 	'Nic', 							0, NOW(), NOW(), 3),
	(4, 'Nová anketa', 			'Teď', 							0, NOW(), NOW(), 4);

INSERT INTO `anketa_odpoved` (`id`, `anketa_id`, `text`) VALUES
	(1, 1, 'Odpoved 1'),
	(2, 1, 'Odpoved 2'),
	(3, 1, 'Odpoved 3'),
	(4, 2, 'Odpoved 1'),
	(5, 2, 'Odpoved 2'),
	(6, 2, 'Odpoved 3'),
	(7, 4, 'Odpoved 1'),
	(8, 4, 'Odpoved 2'),
	(9, 4, 'Odpoved 3');

INSERT INTO `anketa_member` (`user_id`, `anketa_id`, `anketa_odpoved_id`, `date_add`) VALUES
	(2 , 1, 1, NOW()),
	(3 , 1, 1, NOW()),
	(4 , 1, 2, NOW()),
	(2 , 2, 2, NOW()),
	(3 , 2, 1, NOW()),
	(4 , 2, 3, NOW());

INSERT INTO `message` SET `id` = 1, `message_type_id` = 1, `user_id` = 4, `date_add` = NOW(), `date_send` = NOW(), `subject` = 'Test', `text` = 'Testovací zpráva';

INSERT INTO `message_user` (`message_id`, `user_id`) VALUES
	(1, 0),
	(1, 1),
	(1, 2),
	(1, 3),
	(1, 4);

INSERT `album` (`id`, `name`, `slug`, `text`, `private`, `visible`, `user_id`, `date`, `date_add`, `date_update`, `pubkey`) VALUES
	(1, 'Viditelné album akce', '1-viditelne-album-akce',		'Viditelný text', 'Soukromý text', 1,  2,  NOW(),  NOW(),  NOW(), MD5(NOW())),
	(2, 'Neditelné album akce', '2-neviditelne-album-akce', 'Viditelný text', 'Soukromý text', 0,  2,  NOW(),  NOW(),  NOW(), MD5(NOW())),
	(3, 'Neditelné album akce', '3-neviditelne-album-akce', 'Viditelný text', 'Soukromý text', 0,  1,  NOW(),  NOW(),  NOW(), MD5(NOW()));

INSERT INTO `album_photo` (`id`, `album_id`, `filename`, `thumb`, `order`, `visible`, `date_add`) VALUES
	(11, 1, 'Filename_1.jpg', 'filename-1.jpg', 1, 1, NOW()),
	(12, 1, 'Filename_2.jpg', 'filename-2.jpg', 2, 0, NOW()),
	(13, 1, 'Filename_3.jpg', 'filename-3.jpg', 3, 1, NOW()),

	(21, 2, 'Filename_1.jpg', 'filename-1.jpg', 1, 0, NOW()),
	(22, 2, 'Filename_2.jpg', 'filename-2.jpg', 2, 0, NOW()),
	(23, 2, 'Filename_3.jpg', 'filename-3.jpg', 3, 0, NOW()),

	(31, 3, 'Filename_1.jpg', 'filename-1.jpg', 1, 0, NOW()),
	(32, 3, 'Filename_2.jpg', 'filename-2.jpg', 2, 0, NOW()),
	(33, 3, 'Filename_3.jpg', 'filename-3.jpg', 3, 0, NOW());
