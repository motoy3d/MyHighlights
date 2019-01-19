-- カテゴリー
insert into categories values(null, '練習連絡', 36, 1, 0, now(), 0, now()),
(null, '試合連絡', 36, 2, 0, now(), 0, now()),
(null, '試合結果 報告', 36, 3, 0, now(), 0, now()),
(null, '事務連絡', 36, 4, 0, now(), 0, now()),
(null, 'イベント', 36, 5, 0, now(), 0, now()),
(null, '学校利用の注意事項', 36, 6, 0, now(), 0, now()),
(null, 'はじめての方へ', 36, 7, 0, now(), 0, now()),
(null, 'その他', 36, 8, 0, now(), 0, now());

insert into categories values(null,39,'練習連絡',1,0,now(),0,now()),
(null,39,'試合連絡',2,0,now(),0,now()),
(null,39,'事務連絡',3,0,now(),0,now()),
(null,39,'はじめに',4,0,now(),0,now()),
(null,39,'その他',5,0,now(),0,now())


-- 一旦投稿コメントと投稿を削除
delete from post_responses where post_id in (select id from posts where team_id=39)
delete from post_comments where post_id in (select id from posts where team_id=39)
delete from posts where team_id=39
