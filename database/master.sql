-- カテゴリー
insert into categories values
(null, 35, 'はじめに', 1, 0, now(), 0, now()),
(null, 35, '試合連絡', 2, 0, now(), 0, now()),
(null, 35, '練習連絡', 3, 0, now(), 0, now()),
(null, 35, '事務連絡', 4, 0, now(), 0, now()),
(null, 35, 'その他', 5, 0, now(), 0, now()),
(null, 35, '2014年度', 6, 0, now(), 0, now()),
(null, 35, '2015年度', 7, 0, now(), 0, now()),
(null, 35, '2016年度', 8, 0, now(), 0, now()),
(null, 35, '2017年度', 9, 0, now(), 0, now()),
(null, 35, '2018年度', 10, 0, now(), 0, now()),

(null, 36, '練習連絡', 1, 0, now(), 0, now()),
(null, 36, '試合連絡', 2, 0, now(), 0, now()),
(null, 36, '試合結果 報告', 3, 0, now(), 0, now()),
(null, 36, '事務連絡', 4, 0, now(), 0, now()),
(null, 36, 'イベント', 5, 0, now(), 0, now()),
(null, 36, '学校利用の注意事項', 6, 0, now(), 0, now()),
(null, 36, 'はじめての方へ', 7, 0, now(), 0, now()),
(null, 36, 'その他', 8, 0, now(), 0, now()),

(null,37,'練習連絡',1,0,now(),0,now()),
(null,37,'試合連絡',2,0,now(),0,now()),
(null,37,'試合結果',3,0,now(),0,now()),
(null,37,'事務連絡',4,0,now(),0,now()),
(null,37,'はじめに',5,0,now(),0,now()),
(null,37,'その他',6,0,now(),0,now()),
(null,37,'2016年度(1年生)',7,0,now(),0,now()),

(null,38,'事務連絡',1,0,now(),0,now()),
(null,38,'試合結果',2,0,now(),0,now()),
(null,38,'試合連絡',3,0,now(),0,now()),
(null,38,'練習連絡',4,0,now(),0,now()),
(null,38,'はじめに',5,0,now(),0,now()),
(null,38,'その他',6,0,now(),0,now()),

(null,39,'練習連絡',1,0,now(),0,now()),
(null,39,'試合連絡',2,0,now(),0,now()),
(null,39,'事務連絡',3,0,now(),0,now()),
(null,39,'はじめに',4,0,now(),0,now()),
(null,39,'その他',5,0,now(),0,now()),

(null,40,'練習連絡',1,0,now(),0,now()),
(null,40,'試合連絡',2,0,now(),0,now()),
(null,40,'事務連絡',3,0,now(),0,now()),
(null,40,'はじめに',4,0,now(),0,now()),
(null,40,'その他',5,0,now(),0,now())
;

-- すべて既読に更新
insert ignore into post_responses
 select null, m.user_id, p.id post_id,1,0,0,0,now(),0,now()
 from posts p join members m on p.team_id=m.team_id
 where p.team_id=35 and m.team_id = 35 and m.user_id is not null order by p.id;

insert ignore into post_responses
 select null, m.user_id, p.id post_id,1,0,0,0,now(),0,now()
 from posts p join members m on p.team_id=m.team_id
 where p.team_id=36 and m.team_id = 36 and m.user_id is not null order by p.id;

insert ignore into post_responses
 select null, m.user_id, p.id post_id,1,0,0,0,now(),0,now()
 from posts p join members m on p.team_id=m.team_id
 where p.team_id=37 and m.team_id = 37 and m.user_id is not null order by p.id;

insert ignore into post_responses
 select null, m.user_id, p.id post_id,1,0,0,0,now(),0,now()
 from posts p join members m on p.team_id=m.team_id
 where p.team_id=38 and m.team_id = 38 and m.user_id is not null order by p.id;

insert ignore into post_responses
 select null, m.user_id, p.id post_id,1,0,0,0,now(),0,now()
 from posts p join members m on p.team_id=m.team_id
 where p.team_id=39 and m.team_id = 39 and m.user_id is not null order by p.id;

-- 管理者フラグ　カラム追加後 39th
update members set admin_flg=0;
update members set admin_flg=1 where
  name in ('片岡　瑛太'
  -- 36th
  ,'河島　礼央（父）'
  ,'小林　悠吏（父）'
  -- 37th
  ,'塩山　創祐(母)'
  ,'塩山　創祐(父)'
  -- 38th
  ,'有村　風音 (母)'
  ,'有村　風音（父）'
  -- 39th,40th
  ,'横溝　斗将・永斗（母）'
  ,'佐々木　太陽・大地(母)'
  ,'佐々木　太陽・大地(父)'
  ,'横溝　斗将，永斗(父)'
  ,'中村陽翔　(父)'
  ,'中村　陽翔(母)'
  ,'近藤　櫂(母)'
  ,'近藤　櫂（父）'
  ,'齊藤　誠晃母'
  ,'齊藤　誠晃・父'
  ,'池田　きり')

-- アイコン更新
update members set prof_img_filename='woman.png' where name in (
'阿部　時宗'
,'田井　柾翔'
,'小笠原　隼'
,'長坂　夏樹'
,'田村　悠翔'
,'川島　修'
,'吉田　隼斗'
,'平田　大輔'
)

−− schedulesの00:00:00をnullに更新
update schedules set time_from=null where time_from='00:00:00';
update schedules set time_to=null where time_to='00:00:00';

-- 一旦投稿コメントと投稿を削除(データ移行時用)
delete from post_responses where post_id in (select id from posts where team_id=39)
delete from post_comments where post_id in (select id from posts where team_id=39)
delete from posts where team_id=39

-- 色々truncate
truncate table posts;
truncate table post_attachments;
truncate table post_comments;
truncate table post_comment_attachments;
truncate table post_responses;
truncate table questionnaires;
truncate table questionnaire_answers;

truncate table schedules;
truncate table schedule_comments;
