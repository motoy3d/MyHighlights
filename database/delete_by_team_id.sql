-- team_id 指定で関連データを一括削除するSQL(MySQL想定)
-- 使い方:
--  1) @target_team_id を対象の team_id に変更
--  2) まず BEGIN〜COMMIT の代わりに ROLLBACK で試験実行
--  3) 件数が想定どおりなら COMMIT

SET @target_team_id = 40;

START TRANSACTION;

SELECT team_id,count(*),max(created_at) FROM posts GROUP BY team_id ORDER BY team_id;

-- 事前確認(削除対象件数)
SELECT 'posts' AS table_name, COUNT(*) AS cnt FROM posts WHERE team_id = @target_team_id
UNION ALL SELECT 'post_comments', COUNT(*) FROM post_comments WHERE post_id IN (SELECT id FROM posts WHERE team_id = @target_team_id)
UNION ALL SELECT 'post_comment_attachments', COUNT(*) FROM post_comment_attachments WHERE post_comment_id IN (
  SELECT pc.id FROM post_comments pc INNER JOIN posts p ON p.id = pc.post_id WHERE p.team_id = @target_team_id
)
UNION ALL SELECT 'post_comment_responses', COUNT(*) FROM post_comment_responses WHERE post_comment_id IN (
  SELECT pc.id FROM post_comments pc INNER JOIN posts p ON p.id = pc.post_id WHERE p.team_id = @target_team_id
)
UNION ALL SELECT 'post_attachments', COUNT(*) FROM post_attachments WHERE post_id IN (SELECT id FROM posts WHERE team_id = @target_team_id)
UNION ALL SELECT 'post_responses', COUNT(*) FROM post_responses WHERE post_id IN (SELECT id FROM posts WHERE team_id = @target_team_id)
UNION ALL SELECT 'questionnaire_answers', COUNT(*) FROM questionnaire_answers WHERE questionnaire_id IN (
  SELECT DISTINCT questionnaire_id FROM posts WHERE team_id = @target_team_id AND questionnaire_id IS NOT NULL
)
UNION ALL SELECT 'questionnaires', COUNT(*) FROM questionnaires WHERE id IN (
  SELECT DISTINCT questionnaire_id FROM posts WHERE team_id = @target_team_id AND questionnaire_id IS NOT NULL
)
UNION ALL SELECT 'schedules', COUNT(*) FROM schedules WHERE team_id = @target_team_id
UNION ALL SELECT 'schedule_comments', COUNT(*) FROM schedule_comments WHERE schedule_id IN (
  SELECT id FROM schedules WHERE team_id = @target_team_id
)
UNION ALL SELECT 'categories', COUNT(*)
FROM categories
WHERE team_id REGEXP '^[0-9]+$'
  AND CAST(team_id AS UNSIGNED) = @target_team_id
UNION ALL SELECT 'members', COUNT(*) FROM members WHERE team_id = @target_team_id
UNION ALL SELECT 'users_to_delete', COUNT(*)
FROM users u
WHERE u.id IN (
  SELECT DISTINCT m.user_id
  FROM members m
  WHERE m.team_id = @target_team_id
    AND m.user_id IS NOT NULL
)
AND NOT EXISTS (
  SELECT 1
  FROM members mx
  WHERE mx.user_id = u.id
    AND mx.team_id <> @target_team_id
);

-- 削除対象IDを一時テーブルに退避
DROP TEMPORARY TABLE IF EXISTS tmp_target_posts;
CREATE TEMPORARY TABLE tmp_target_posts (id INT PRIMARY KEY)
SELECT id AS id FROM posts WHERE team_id = @target_team_id;

DROP TEMPORARY TABLE IF EXISTS tmp_target_post_comments;
CREATE TEMPORARY TABLE tmp_target_post_comments (id INT PRIMARY KEY)
SELECT pc.id AS id
FROM post_comments pc
INNER JOIN tmp_target_posts tp ON tp.id = pc.post_id;

DROP TEMPORARY TABLE IF EXISTS tmp_target_questionnaires;
CREATE TEMPORARY TABLE tmp_target_questionnaires (id INT PRIMARY KEY)
SELECT DISTINCT questionnaire_id AS id
FROM posts
WHERE team_id = @target_team_id
  AND questionnaire_id IS NOT NULL;

DROP TEMPORARY TABLE IF EXISTS tmp_target_schedules;
CREATE TEMPORARY TABLE tmp_target_schedules (id INT PRIMARY KEY)
SELECT id AS id FROM schedules WHERE team_id = @target_team_id;

DROP TEMPORARY TABLE IF EXISTS tmp_target_users;
CREATE TEMPORARY TABLE tmp_target_users (id INT PRIMARY KEY)
SELECT DISTINCT m.user_id AS id
FROM members m
WHERE m.team_id = @target_team_id
  AND m.user_id IS NOT NULL;

-- 子テーブル -> 親テーブルの順で削除
DELETE pca
FROM post_comment_attachments pca
INNER JOIN tmp_target_post_comments tpc ON tpc.id = pca.post_comment_id;

DELETE pcr
FROM post_comment_responses pcr
INNER JOIN tmp_target_post_comments tpc ON tpc.id = pcr.post_comment_id;

DELETE pc
FROM post_comments pc
INNER JOIN tmp_target_posts tp ON tp.id = pc.post_id;

DELETE pa
FROM post_attachments pa
INNER JOIN tmp_target_posts tp ON tp.id = pa.post_id;

DELETE pr
FROM post_responses pr
INNER JOIN tmp_target_posts tp ON tp.id = pr.post_id;

DELETE qa
FROM questionnaire_answers qa
INNER JOIN tmp_target_questionnaires tq ON tq.id = qa.questionnaire_id;

DELETE p
FROM posts p
INNER JOIN tmp_target_posts tp ON tp.id = p.id;

DELETE q
FROM questionnaires q
INNER JOIN tmp_target_questionnaires tq ON tq.id = q.id;

DELETE sc
FROM schedule_comments sc
INNER JOIN tmp_target_schedules ts ON ts.id = sc.schedule_id;

DELETE FROM schedules WHERE team_id = @target_team_id;
DELETE FROM categories
WHERE team_id REGEXP '^[0-9]+$'
  AND CAST(team_id AS UNSIGNED) = @target_team_id;
DELETE FROM members WHERE team_id = @target_team_id;

-- members削除で紐づきがなくなったusersを削除
# SELECT *
# FROM users u
#          INNER JOIN tmp_target_users tu ON tu.id = u.id
# WHERE NOT EXISTS (
#     SELECT 1
#     FROM members m
#     WHERE m.user_id = u.id
# );

DELETE u
FROM users u
INNER JOIN tmp_target_users tu ON tu.id = u.id
WHERE NOT EXISTS (
  SELECT 1
  FROM members m
  WHERE m.user_id = u.id
);

-- 必要なら team 本体も削除(運用方針に合わせてコメント解除)
-- DELETE FROM teams WHERE id = @target_team_id;

-- 事後確認(残件数)
SELECT 'posts' AS table_name, COUNT(*) AS cnt FROM posts WHERE team_id = @target_team_id
UNION ALL SELECT 'schedules', COUNT(*) FROM schedules WHERE team_id = @target_team_id
UNION ALL SELECT 'categories', COUNT(*)
FROM categories
WHERE team_id REGEXP '^[0-9]+$'
  AND CAST(team_id AS UNSIGNED) = @target_team_id
UNION ALL SELECT 'members', COUNT(*) FROM members WHERE team_id = @target_team_id
UNION ALL SELECT 'users_orphan', COUNT(*)
FROM users u
WHERE u.id IN (SELECT id FROM tmp_target_users)
AND NOT EXISTS (
  SELECT 1
  FROM members m
  WHERE m.user_id = u.id
);

SELECT team_id,count(*),max(created_at) FROM posts GROUP BY team_id ORDER BY team_id;

DROP TEMPORARY TABLE IF EXISTS tmp_target_posts;
DROP TEMPORARY TABLE IF EXISTS tmp_target_post_comments;
DROP TEMPORARY TABLE IF EXISTS tmp_target_questionnaires;
DROP TEMPORARY TABLE IF EXISTS tmp_target_schedules;
DROP TEMPORARY TABLE IF EXISTS tmp_target_users;

COMMIT;
-- ROLLBACK;

