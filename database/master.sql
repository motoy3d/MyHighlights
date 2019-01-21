-- カテゴリー
insert into categories values(null, 100, '練習連絡', 1, 0, now(), 0, now()),
(null, 100, '試合連絡', 2, 0, now(), 0, now()),
(null, 100, '試合結果 報告', 3, 0, now(), 0, now()),
(null, 100, '事務連絡', 4, 0, now(), 0, now()),
(null, 100, 'イベント', 5, 0, now(), 0, now()),
(null, 100, '学校利用の注意事項', 6, 0, now(), 0, now()),
(null, 100, 'はじめての方へ', 7, 0, now(), 0, now()),
(null, 100, 'その他', 8, 0, now(), 0, now());

insert into categories values(null,39,'練習連絡',1,0,now(),0,now()),
(null,39,'試合連絡',2,0,now(),0,now()),
(null,39,'事務連絡',3,0,now(),0,now()),
(null,39,'はじめに',4,0,now(),0,now()),
(null,39,'その他',5,0,now(),0,now());

insert into categories values(null,37,'練習連絡',1,0,now(),0,now()),
(null,37,'試合連絡',2,0,now(),0,now()),
(null,37,'試合結果',3,0,now(),0,now()),
(null,37,'事務連絡',4,0,now(),0,now()),
(null,37,'はじめに',5,0,now(),0,now()),
(null,37,'その他',6,0,now(),0,now()),
(null,37,'2016年度(1年生)',7,0,now(),0,now());

insert into categories values(null,38,'事務連絡',1,0,now(),0,now()),
(null,38,'試合結果',2,0,now(),0,now()),
(null,38,'試合連絡',3,0,now(),0,now()),
(null,38,'練習連絡',4,0,now(),0,now()),
(null,38,'はじめに',5,0,now(),0,now()),
(null,38,'その他',6,0,now(),0,now());


-- すべて既読に更新
insert ignore into post_responses
select null, u.user_id, p.id post_id,1,0,0,0,now(),0,now() from posts p join user_teams u on p.team_id=u.team_id
where p.team_id=100 and u.team_id = 100 order by p.id


-- 一旦投稿コメントと投稿を削除(データ移行時用)
delete from post_responses where post_id in (select id from posts where team_id=39)
delete from post_comments where post_id in (select id from posts where team_id=39)
delete from posts where team_id=39
