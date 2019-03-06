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