INSERT INTO `user` SET `id` = 0, `name` = 'Franta',	`surname` = 'Deleted',	`mail` = 'deleted@vzs-jablonec.cz', `role` = NULL;
INSERT INTO `user` SET `id` = 1, `name` = 'Tonda',	`surname` = 'User', 		`mail` = 'user@vzs-jablonec.cz', 		`role` = 0;
INSERT INTO `user` SET `id` = 2, `name` = 'Jirka',	`surname` = 'Member', 	`mail` = 'member@vzs-jablonec.cz', 	`role` = 1;
INSERT INTO `user` SET `id` = 3, `name` = 'Pepa',		`surname` = 'Board', 		`mail` = 'board@vzs-jablonec.cz', 	`role` = 2;
INSERT INTO `user` SET `id` = 4, `name` = 'Míra',		`surname` = 'Admin', 		`mail` = 'admib@vzs-jablonec.cz', 	`role` = 3;

INSERT INTO `akce` SET `id` = 1, `name` = 'Minulá akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v minulosti',
	`date_start` = NOW() - INTERVAL 48 HOUR, `date_end` = NOW() - INTERVAL 47 HOUR, `date_deatline` = NOW() - INTERVAL 24 HOUR, `date_add` = NOW() - INTERVAL 12 HOUR, `date_update` = NOW() - INTERVAL 12 HOUR,
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2, `confirm` = 1;

INSERT INTO `akce` SET `id` = 2, `name` = 'Budoucí akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v budoucnosti',
	`date_start` = NOW() + INTERVAL 48 HOUR, `date_end` = NOW() + INTERVAL 49 HOUR, `date_deatline` = NOW() + INTERVAL 24 HOUR, `date_add` = NOW(), `date_update` = NOW(),
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2, `confirm` = 1;

INSERT INTO `akce` SET `id` = 3, `name` = 'Minulá neschválená akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v minulosti, která není schválená',
	`date_start` = NOW() - INTERVAL 48 HOUR, `date_end` = NOW() - INTERVAL 47 HOUR, `date_deatline` = NOW() - INTERVAL 24 HOUR, `date_add` = NOW() - INTERVAL 12 HOUR, `date_update` = NOW() - INTERVAL 12 HOUR,
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2;

INSERT INTO `akce` SET `id` = 4, `name` = 'Budoucí nechválená akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v budoucnosti, která není schválená',
	`date_start` = NOW() + INTERVAL 48 HOUR, `date_end` = NOW() + INTERVAL 49 HOUR, `date_deatline` = NOW() + INTERVAL 24 HOUR, `date_add` = NOW(), `date_update` = NOW(),
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2;

INSERT INTO `akce` SET `id` = 5, `name` = 'Minulá smazaná akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v minulosti, která je smazaná',
	`date_start` = NOW() - INTERVAL 48 HOUR, `date_end` = NOW() - INTERVAL 47 HOUR, `date_deatline` = NOW() - INTERVAL 24 HOUR, `date_add` = NOW() - INTERVAL 12 HOUR, `date_update` = NOW() - INTERVAL 12 HOUR,
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2, `enable` = 0;

INSERT INTO `akce` SET `id` = 6, `name` = 'Budoucí smazaná akce', `perex` = 'Akce', `description` = 'Akce odehrávající se v budoucnosti, která je smazaná',
	`date_start` = NOW() + INTERVAL 48 HOUR, `date_end` = NOW() + INTERVAL 49 HOUR, `date_deatline` = NOW() + INTERVAL 24 HOUR, `date_add` = NOW(), `date_update` = NOW(),
	`akce_for_id` = 1, `place` = 'VD Mšeno', `created_by` = 2, `modified_by` = 2, `enable` = 0;

INSERT INTO `akce_member` SET `akce_id` = 1, `user_id` = 2, `organizator` = 1, `created_by` = 2, `date_add` = NOW();
INSERT INTO `akce_member` SET `akce_id` = 1, `user_id` = 1, `organizator` = 0, `created_by` = 2, `date_add` = NOW();
INSERT INTO `akce_member` SET `akce_id` = 2, `user_id` = 2, `organizator` = 1, `created_by` = 2, `date_add` = NOW();
INSERT INTO `akce_member` SET `akce_id` = 2, `user_id` = 1, `organizator` = 0, `created_by` = 2, `date_add` = NOW();

INSERT INTO `akce_member` SET `akce_id` = 4, `user_id` = 3, `organizator` = 1, `created_by` = 3, `date_add` = NOW();


INSERT INTO `album` SET `id` = 1, `name` = 'Viditelné album akce', `slug` = '1-viditelne-album-akce', `text` = 'Viditelný text', `private` = 'Soukromý text', `visible` = 1, `user_id` = 2, `date` = NOW(), `date_add` = NOW(), `date_update` = NOW(), `pubkey` = MD5(NOW());
INSERT INTO `album` SET `id` = 2, `name` = 'Neditelné album akce', `slug` = '2-neviditelne-album-akce', `text` = 'Viditelný text', `private` = 'Soukromý text', `visible` = 0, `user_id` = 2, `date` = NOW(), `date_add` = NOW(), `date_update` = NOW(), `pubkey` = MD5(NOW());
INSERT INTO `album` SET `id` = 3, `name` = 'Neditelné album akce', `slug` = '3-neviditelne-album-akce', `text` = 'Viditelný text', `private` = 'Soukromý text', `visible` = 0, `user_id` = 1, `date` = NOW(), `date_add` = NOW(), `date_update` = NOW(), `pubkey` = MD5(NOW());

INSERT INTO `album_photo` SET `id` = 11, `album_id` = 1, `filename` = 'Filename_1.jpg', `thumb` = 'filename-1.jpg', `order` = 1, `visible` = 1, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 12, `album_id` = 1, `filename` = 'Filename_2.jpg', `thumb` = 'filename-2.jpg', `order` = 2, `visible` = 0, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 13, `album_id` = 1, `filename` = 'Filename_3.jpg', `thumb` = 'filename-3.jpg', `order` = 3, `visible` = 1, `date_add` = NOW();

INSERT INTO `album_photo` SET `id` = 21, `album_id` = 2, `filename` = 'Filename_1.jpg', `thumb` = 'filename-1.jpg', `order` = 1, `visible` = 0, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 22, `album_id` = 2, `filename` = 'Filename_2.jpg', `thumb` = 'filename-2.jpg', `order` = 2, `visible` = 0, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 23, `album_id` = 2, `filename` = 'Filename_3.jpg', `thumb` = 'filename-3.jpg', `order` = 3, `visible` = 0, `date_add` = NOW();

INSERT INTO `album_photo` SET `id` = 31, `album_id` = 3, `filename` = 'Filename_1.jpg', `thumb` = 'filename-1.jpg', `order` = 1, `visible` = 0, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 32, `album_id` = 3, `filename` = 'Filename_2.jpg', `thumb` = 'filename-2.jpg', `order` = 2, `visible` = 0, `date_add` = NOW();
INSERT INTO `album_photo` SET `id` = 33, `album_id` = 3, `filename` = 'Filename_3.jpg', `thumb` = 'filename-3.jpg', `order` = 3, `visible` = 0, `date_add` = NOW();
