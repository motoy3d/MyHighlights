# 本番移行計画（AL2023切り替え）

作業の**進め方**をまとめたもの。
設定値の突き合わせなど項目単位の確認は
`docs/PRODUCTION-CUTOVER-CHECKLIST.md`、
移行で何が変わったかは `docs/MIGRATION-al2023.md` を参照。

---

## 0. 作業環境と接続方式

**作業はローカルの Claude Code CLI から行う。**
本番AWSアカウントの資格情報を使い捨てのクラウド環境に置かずに済むため。

**新サーバへの接続は SSH ではなく AWS Systems Manager (SSM) を使う。**

- インバウンドポートを一切開けない（22番も含めて）
- SSH鍵の配布・管理が不要
- 踏み台サーバが不要
- 操作履歴がCloudTrailに残る

そのため**インスタンスロールには次の2つが必要**になる。
どちらもEC2作成時にアタッチする。

| 権限 | 目的 | 欠けると |
| --- | --- | --- |
| SES送信 (`ses:SendRawEmail`) | 通知メール送信 | メールが無言で全滅する |
| `AmazonSSMManagedInstanceCore` | SSM経由の操作 | 以後の操作手段が無くなる |

AL2023はSSMエージェントを同梱しているので、追加インストールは不要。

### コマンドの流し方

`deploy/ssm-run.sh` を用意した。実行前に対象アカウントを確認し、
完了を待って標準出力・標準エラーを表示する。

```bash
export AWS_PROFILE=<本番アカウントのプロファイル>
export EXPECT_ACCOUNT=<本番のアカウントID>   # 誤爆防止（任意だが推奨）

deploy/ssm-run.sh i-0123456789abcdef0 'php -v'
deploy/ssm-run.sh i-0123456789abcdef0 -f deploy/setup-al2023.sh
```

SSMが返す出力は24,000文字で打ち切られる。
`setup-al2023.sh` のようにdnfの出力が長いものは途中までしか見えないので、
全文はインスタンス側の `/var/log/tsubasa-deploy.log` を見る
（`ssm-run.sh` が自動で追記している）。

### 画面確認の仕方

セキュリティグループを開けずに、手元のブラウザから新サーバを見られる。

```bash
aws ssm start-session \
  --target i-0123456789abcdef0 \
  --document-name AWS-StartPortForwardingSession \
  --parameters '{"portNumber":["80"],"localPortNumber":["8080"]}'
```

`http://localhost:8080` で新サーバに繋がる。
**この方式なら、フェーズ1・2の動作確認に一時ホスト名も証明書も要らない。**
ただし `.env` の `APP_URL` を `http://localhost:8080` に合わせること
（合っていないと `/api/*` が全て401になる。問題4の下に詳述）。
`SESSION_SECURE_COOKIE` も平文HTTPの間は `false` にしておく。

> **当夜の「切り替え前スモークテスト」だけは別**。
> あちらは本番ドメイン・本番証明書での確認が目的なので、
> hostsファイルに新サーバのIPを書いて 443 で確認する。
> そのため**切り替え前までにセキュリティグループの443は開けておく**
> （切り替え後は全利用者が使うので、いずれにせよ必要）。

---

## 1. 当初案への評価

想定していた流れ:

> 新サーバー作成 → アプリデプロイ → テストデータ投入してユニットテスト →
> 手動テスト → テストデータクリア → 本番データ移行 → 参照系テスト →
> 更新系テスト(テストチーム) → 本番一時停止 → 差分データ移行 →
> DNS切り替え → 新本番開始

**大枠の順番は妥当。** ただし以下4点はこのままだと当夜に破綻する。

### 問題1: 「更新系テスト → 差分データ移行」は成立しない

テストチームが本番データのコピーに対して更新系テストをすると、
投稿・コメント・アンケート回答・メンバー退会などの
**テスト由来のデータが本番データに混ざる**。
差分移行は「増えた分を足す」操作なので、混ざったゴミは消えない。
そのまま本番開始すると、テスト投稿が利用者に見える。

**→ 差分移行をやめ、当夜に「フル再取り込み」にする。**
更新系テストで汚れたDBは丸ごと捨てて、本番停止直後のダンプから
取り直す。工程が1つ減り、かつ「本番と完全に同一」が保証できる。

成立条件はフルダンプ＋リストアの所要時間。数分で終わる規模なら
迷わずこちらがよい。**フェーズ3のリハーサルで必ず実測する。**
もし1時間以上かかるようなら差分方式を検討するが、その場合は
更新系テストを本番データのコピーではなく**別DB**で行う必要がある。

### 問題2: 本番データのコピーでテストすると、実ユーザーにメールが飛ぶ

参照系・更新系テストの対象データには**実在の会員のメールアドレス**が
入っている。この状態でメンバー招待・パスワード再設定・投稿通知・
コメント通知を動かすと、**本物のメールが実際の利用者に届く**。
移行前に「身に覚えのない通知」が飛ぶ事故になる。

**→ 本番データを入れる前に、新サーバの `.env` でメールを封じ込める。**

```dotenv
MAIL_MAILER=log        # storage/logs/laravel.log に出るだけになる
QUEUE_CONNECTION=sync  # ワーカー経由の送信も同じ経路に乗せる
```

送信内容そのものを確認したい場合は Mailtrap 等の
キャッチオール用SMTPに向ける。
**切り替え当夜に本番の値へ戻すこと**（当夜の手順に組み込み済み）。

あわせて、本番データ取り込み後は `jobs` / `failed_jobs` を空にする。
未処理ジョブを持ち込むと、切り替え後にワーカーが起動した瞬間に
**古い通知が一斉送信される。**

```sql
TRUNCATE TABLE jobs;
TRUNCATE TABLE failed_jobs;
```

### 問題3: 切り替え方式 —— EIP付け替えに決定（DNSは触らない）

当初案の「DNS切り替え」は、夜中でも即座には終わらない。
TTLが切れるまで旧サーバにアクセスが流れ続け、その間は利用者ごとに
新旧どちらに繋がるか分かれる。**旧サーバ側で投稿されたデータは
新サーバに入らず消える**（split-brain）。

**→ 移行先は本番と同じAWSアカウントで、EIP付け替えが可能と確認済み。
切り替えは Elastic IP の付け替えで行う。**

これにより、当初案から次が**不要**になった。

- DNSのTTL短縮（2〜3日前の事前作業）
- レコード変更と伝播待ち
- 伝播中のsplit-brain対策

切り替えは数秒で完了し、**切り戻しもEIPを旧インスタンスに戻すだけで数秒**。
当夜のリスクが大きく下がる。

```bash
# 実行前に対象アカウントを必ず確認する（本番アカウントであること）
aws sts get-caller-identity

# 付け替え（ALLOCATION_ID / 新インスタンスIDは事前に控えておく）
aws ec2 associate-address \
  --allocation-id <eipalloc-xxxxxxxx> \
  --instance-id  <新インスタンスID>

# 切り戻し
aws ec2 associate-address \
  --allocation-id <eipalloc-xxxxxxxx> \
  --instance-id  <旧インスタンスID>
```

> `associate-address` は、既に別インスタンスに紐付いているEIPでも
> そのまま付け替えられる（暗黙の再割り当て）。事前に
> `disassociate-address` する必要はなく、その方が無通信時間が短い。
>
> **当夜までに控えておくもの:** EIPの `AllocationId`、
> 新旧それぞれのインスタンスID。当夜に調べ始めない。

> **注意: EIPを付け替えても、旧サーバは生きたまま。**
> 旧サーバのローカルIPやプライベート経路が残るため、
> 「00:00に旧サーバをメンテナンスモードにする」手順は
> EIP方式でも**省略しない**（書き込みを確実に止めるため）。

### 問題4: 証明書のニワトリタマゴ問題

切り替え前は、EIP（＝ドメインが指すIP）を旧サーバが握っている。
そのため新サーバは HTTP-01 チャレンジで
`tsubasa.smartj.mobi` の証明書を取得できない
（検証リクエストが旧サーバに届いてしまう）。
**当夜に初めて気づくと詰む。**

**→ 旧サーバの `/etc/letsencrypt` を丸ごとコピーする。**
EIP付け替え方式ではこれが最も素直。ドメインもアカウントも同じで、
付け替えた瞬間から新サーバがそのIPを持つため、
**以後の更新は HTTP-01 のまま何もせず通る。**

```bash
# 旧サーバで
sudo tar czf /tmp/le.tar.gz -C /etc letsencrypt
# 新サーバで展開後
sudo systemctl list-timers | grep certbot   # 自動更新タイマーが有効か
```

期限が近い場合や、そもそもコピーが取れない場合の代替:

| 方法 | 条件 | 備考 |
| --- | --- | --- |
| DNS-01チャレンジ | DNSのAPIが使える（Route53等） | ワイルドカードもこれで取れる。DNSは切り替えないので、レコード変更は `_acme-challenge` のTXTのみ |
| 切り替え後に取得 | — | 取得できるまで証明書エラーが出る。**非推奨** |

**切り替え後に `certbot renew --dry-run` を必ず実行する**
（今回のトラブルの再発防止。ここを飛ばすと同じ失効を繰り返す）。

### 抜けていた工程

- **添付ファイルの移行**（`storage/app/public`）。DBだけ移しても
  添付と画像が全部リンク切れになる
- **ロールバック手順と判断期限**
- **リハーサル**（＝当夜の所要時間の実測）。これが無いと
  「夜中の何分で終わるか」が分からないまま当夜を迎えることになる
- **利用者への告知**（メンテナンス時間、および
  **全員が一度ログアウトされる**こと）
- **旧DBの事前健全性チェック**（ゼロ日付・文字コード）。
  取り込みが落ちる典型パターンで、当夜に踏むと復旧が長引く

### 夜中に実施する範囲について

夜中にやるべきなのは**フェーズ4（切り替え）だけ**。
テストチームの更新系テストまで夜中に押し込むと、
人が確保できないうえに判断が雑になる。
**準備とテストは日中に済ませ、夜中は「止めて・移して・切り替える」だけ**
にするのが、短く終わらせる唯一の方法。

---

## 2. 最初に確定させること

これが分からないと当夜の手順が確定しない。**最優先で調べる。**

| 調べること | 調べ方 | 計画への影響 |
| --- | --- | --- |
| **どのAWSアカウントか** | `aws sts get-caller-identity` | **以降の全調査の前提。本番アカウントの資格情報で実行しているか毎回確認する** |
| ~~Elastic IPか~~ | **確認済み** | **同一アカウント・EIP付け替えで実施と決定。DNSは触らない** |
| **EIPの AllocationId と新旧インスタンスID** | `aws ec2 describe-addresses` / `describe-instances` | **当夜の付け替えコマンドに使う。事前に控える** |
| ~~DNSのTTL短縮~~ | — | **EIP付け替えのため不要になった**（切り戻し不能時の保険として、ホストゾーンの所在だけ把握しておく） |
| 証明書の取得方法 | 旧サーバの `/etc/letsencrypt/renewal/*.conf` | 上表のどの手を使うか |
| ~~DBサイズ~~ | **2,964 MB（実測）** | **うち `logs` が 2,867MB＝96.7%。全件移行と決定したため、旧サーバの空き7.6GBに置けない。中間ファイルを作らずパイプで流すのが必須** |
| ~~旧MySQLのバージョンと文字セット~~ | **5.7.35-log / utf8mb4（実測）** | **列レベルは79列すべて `utf8mb4_unicode_ci` で統一。utf8mb4でないテーブルはゼロ＝絵文字は化けない** |
| ~~ゼロ日付の有無~~ | **該当なし（67列を実データで走査、0件）** | **旧環境が既に `NO_ZERO_DATE`＋`STRICT_TRANS_TABLES` で7年運用されていた。取り込み失敗のリスクは消滅** |
| ~~添付の容量とファイル数~~ | **5.2 GB / 15,799件（実測）** | **当夜に転送するには大きい。「事前フル同期→当夜は差分のみ」を必ず採用する** |
| ~~cron / バッチの棚卸し~~ | **Tsubasa用は0本（実測）** | **稼働中の4本は別アプリ redsmylife 用。移設対象なし。詳細は「2.1 事前調査の実測結果」** |
| 本番 `.env` の実物 | 旧サーバから取得 | **`APP_KEY` を失うと復号不能。最優先で退避**（内容の突き合わせは検証済み → チェックリスト参照） |
| ~~SES送信用のIAMロール~~ | **方針決定済み** | **EC2作成時に必須でアタッチする運用とした**（SES送信 + `AmazonSSMManagedInstanceCore` の2つ）。`deploy/setup-al2023.sh` が未アタッチなら停止する |
| ローカルのAWSプロファイル | `aws sts get-caller-identity` | 本番アカウントを指していること。`EXPECT_ACCOUNT` に設定して誤爆を防ぐ |
| DBユーザーのホスト指定 | `SELECT user,host FROM mysql.user;` | `DB_HOST=localhost` なので `'tsubasa'@'localhost'` が必要 |

```sql
-- DBサイズ
SELECT table_schema,
       ROUND(SUM(data_length + index_length) / 1024 / 1024) AS mb
  FROM information_schema.tables
 WHERE table_schema = 'tsubasa' GROUP BY table_schema;

-- 文字セット/照合順序がテーブルごとにバラけていないか
SELECT table_name, table_collation FROM information_schema.tables
 WHERE table_schema = 'tsubasa';

-- ゼロ日付（MariaDBの厳格モードで取り込みが落ちる）
SELECT VERSION(), @@sql_mode;
```

```bash
# 旧サーバの実パスは /var/www/MyHighlights（計画初版の /var/www/tsubasa は誤り）
du -sh /var/www/MyHighlights/storage/app/public
find /var/www/MyHighlights/storage/app/public -type f | wc -l
```

---

## 2.1 事前調査の実測結果（2026-09-05 実施）

旧サーバ `smartj.mobi` (52.199.130.187 / Amazon Linux AMI 2018.03) 上で実測。

### DB

| 項目 | 実測値 |
| --- | --- |
| バージョン | MySQL **5.7.35-log** |
| `sql_mode` | `ONLY_FULL_GROUP_BY, STRICT_TRANS_TABLES, NO_ZERO_IN_DATE, NO_ZERO_DATE, ERROR_FOR_DIVISION_BY_ZERO, NO_AUTO_CREATE_USER, NO_ENGINE_SUBSTITUTION` |
| サーバ文字セット | `utf8mb4` / `utf8mb4_general_ci` |
| 総サイズ | **2,964 MB**（33テーブル） |
| ゼロ日付 | **なし**（date/datetime/timestamp 67列を全走査、0件） |

**`logs` テーブルが DB の 96.7% を占める。**

| テーブル | 行数 | サイズ |
| --- | ---: | ---: |
| `logs` | 13,875,254 | **2,867 MB** |
| `post_responses` | 145,173 | 24.0 MB |
| `questionnaire_answers` | 122,716 | 17.0 MB |
| `failed_jobs` | 762 | 12.5 MB |
| `posts` | 4,303 | 11.1 MB |
| 残り28テーブル | | 約 32 MB |

`logs` は 2019-04-13 以降 7年5か月分。`AppServiceProvider` が全SQLを出力し続けた結果。
**全件移行と決定済み**（除外すればダンプは約97MBまで縮むが、方針として全件を運ぶ）。

照合順序は `utf8mb4_unicode_ci` × 26テーブル、`utf8mb4_general_ci` × 7テーブル。
後者は `posts_20190308` / `post_responses_20190308` / `post_comments_20190308` 系の
**2019年の退避テーブル（計14.5MB）** のみで、現用テーブルは全て `unicode_ci`。
列レベルでは79列すべて `utf8mb4_unicode_ci` に統一されている。

> フェーズ2の `CREATE DATABASE` は計画初版で `utf8mb4_general_ci` としていたが、
> 現用テーブルに合わせて **`utf8mb4_unicode_ci`** にすること。

### 添付ファイル

| ディレクトリ | 容量 |
| --- | ---: |
| `comment_attachment` | 3.1 GB |
| `post_attachment` | 2.2 GB |
| `prof` | 436 KB |
| **合計** | **5.2 GB / 15,799ファイル** |

`storage` 全体では 7.3GB（差分はログとキャッシュ）。最大ファイルは約19MB。

### cron / バッチ

**Tsubasa 用のジョブは1本も無い**（リポジトリ側にも Laravel スケジューラの定義はゼロで一致）。

| 実行者 | 内容 | 移設 |
| --- | --- | --- |
| root | `certbot renew`（毎日04:00、deploy-hookでhttpd reload） | 新サーバで再構成 |
| ec2-user | `feedEntry.sh` / `standings.sh` / `results.sh` / `video.sh` | **不要**。`com.urawaredsmylife.*` の Java バッチで、Tomcat上の別アプリ redsmylife 用。旧サーバに残す |
| cron.daily | `s3backup` → S3 `smartj.mobi-backup` | 新サーバで再構成 |

### 証明書

- `authenticator = webroot` / **`webroot_path = /var/www/html`**
  — Tsubasa のドキュメントルート (`/var/www/MyHighlights/public`) **ではない**
- `tsubasa.smartj.mobi` の有効期限 **2026-10-29**、`tsubasademo.smartj.mobi` は 2026-11-28
- certbot **0.38.0**（旧版）

> **`/etc/letsencrypt` をコピーするだけでは更新が失敗する。**
> 新サーバにも `/var/www/html` に相当する webroot を用意して
> `.well-known/acme-challenge` を配信できるようにするか、
> renewal の `webroot_path` を新サーバの構成に合わせて書き換えること。

### その他

- 旧サーバの **uptime 1195日**（無再起動）。EBSスナップショットは必須
- ルートディスク 40GB / **使用81%・空き7.6GB** — 3GBのダンプを置く余裕は乏しい
- PHP **7.1.33**、Apache 2.4、Tomcat 8（AJP 8009）
- `/var/www/MyHighlights` のパーミッションが **777**（新サーバでは引き継がない）
- **`/backup/php_backup_s3/backup.php` にAWSアクセスキーが平文でハードコードされている。**
  無効化と再発行を推奨。新サーバではIAMロールに寄せる

---

## 2.2 旧サーバは Tsubasa 専用機ではなかった —— 切り替え方式の修正

調査で判明した最大の事実。**同一EIPを4つのホスト名・3サイト・2アプリサーバが共有している。**

```
tsubasa.smartj.mobi       -> 52.199.130.187   ← 移行対象
tsubasademo.smartj.mobi   -> 52.199.130.187   ← 移行対象（追加）
smartj.mobi               -> 52.199.130.187   ← 旧サーバに残す
www.smartj.mobi           -> 52.199.130.187   ← 旧サーバに残す
```

| vhost | DocumentRoot | 扱い |
| --- | --- | --- |
| `smartj.mobi`（default） | `/var/www/html` | **旧サーバに残す**。`/redsmylife/` を AJP で Tomcat にプロキシ |
| `tsubasa.smartj.mobi` | `/var/www/MyHighlights/public` | 移行する |
| `tsubasademo.smartj.mobi` | `/var/www/MyHighlights2/public` | 移行する |

**このまま単純にEIPを付け替えると、`smartj.mobi` / `www.smartj.mobi` / `redsmylife` が
同時に落ちる。** フェーズ5の「2週間後に旧サーバを停止」も、そのままでは
redsmylife とその4本のバッチを恒久的に止めてしまう。

### 修正: 旧サーバに2つ目のEIPを事前に付けて分離する

**EIP付け替え方式は維持する**（当夜の切り替えは数秒、切り戻しも数秒のまま）。

1. **事前（当夜の数日前）**: 旧サーバのENIにセカンダリプライベートIPを追加し、
   **新しいEIPをもう1つ割り当てて紐付ける**。この時点で旧サーバは2つのIPを持つ
2. **事前**: `smartj.mobi` と `www.smartj.mobi` のAレコードを**新しいEIP**に向ける
   — 両方のIPが同じ旧サーバを指しているので、**伝播中も無停止**。リスクゼロ
3. 伝播完了後、**元のEIPは実質 tsubasa 系専用**になる
4. **当夜**: 元のEIPを新サーバに付け替える（従来どおり数秒。切り戻しも数秒）

これで当初の方針（EIP付け替え・DNSは当夜触らない）を保ったまま、
他サイトを巻き込まずに切り替えられる。DNS変更は事前作業として
**無停止で**済ませられる点が重要。

> DNSは Route53 ではなく **dnsv.jp**（`01〜04.dnsv.jp`）。TTLは3600秒。
> 手作業での変更が1回必要。
> DNS-01チャレンジを使う場合もこのDNSのAPI可否が前提になる。

---

## 2.3 tsubasademo（テスト環境）も移行対象

| 項目 | 本番 | demo |
| --- | --- | --- |
| ディレクトリ | `/var/www/MyHighlights` | `/var/www/MyHighlights2` |
| ホスト名 | `tsubasa.smartj.mobi` | `tsubasademo.smartj.mobi` |
| DB | `tsubasa`（33テーブル / 2,964MB） | **`tsubasa_test`**（28テーブル / 5MB） |
| 添付 | 5.2GB / 15,799件 | 444KB / 21件 |
| `APP_DEBUG` | `false` | `true` |
| `QUEUE_DRIVER` | `database` | `sync` |
| Laravel | 5.6.40 | 5.6.40（master `869bc00`） |

demo も現行は **Laravel 5.6.40 / PHP 7.1**。AL2023 には PHP 7.1 が無いため、
**demo も新コードベース（Laravel 13 / PHP 8.4）に載せ替える必要がある。**
コードは同一なので、`.env` とDBを分けるだけで同居できる。

### ⚠️ DB名の衝突に注意

**demo の本番DB名が `tsubasa_test`** で、これは自動テスト用に作ろうとしていたDB名と同じ。
新サーバでこの名前のまま `phpunit` を走らせると、`RefreshDatabase` が
**demo環境のデータを消す。**

> **自動テスト用のDBは `tsubasa_phpunit` など別名にすること。**
> `phpunit.xml` / `.env.testing` の `DB_DATABASE` を確認する。

---

## 3. フェーズ構成

所要日数は目安。フェーズ1〜3は日中作業。

### フェーズ0: 事前調査（0.5日）

- 上表の項目をすべて埋める
- **旧サーバの `.env` と `/etc/letsencrypt` を手元に退避**
- 旧サーバのEBSスナップショットを取得（保険）
- 利用者への告知（メンテナンス日時、全員ログアウトされる旨）

### フェーズ1: 新サーバ構築とアプリ単体の検証（1日）

DNSもEIPも触らない。**SSM経由で構築し、ポートフォワードで画面を確認する**
（インバウンドは何も開けない）。

1. **EC2(AL2023)を起動する。作成時に次を含むIAMロールを必ずアタッチする**
   - SES送信権限（`ses:SendRawEmail`）
   - `AmazonSSMManagedInstanceCore`

   `deploy/setup-al2023.sh` はロールが付いていなければ停止する。
2. 環境構築とデプロイ

   ```bash
   deploy/ssm-run.sh <インスタンスID> -f deploy/setup-al2023.sh
   # 以降、リポジトリ配置と deploy/deploy.sh も同じ要領で流す
   ```
3. **本番の `.env` を配置**する。内容の突き合わせは検証済み
   （`docs/PRODUCTION-CUTOVER-CHECKLIST.md`）だが、配置後に
   `php artisan tinker` で設定解決を再確認する。
   検証で見つかった要対応のうち残り3点
   （キューワーカー必須 / `DB_HOST=localhost` なのでDBユーザーを
   `'tsubasa'@'localhost'` で作る / ログのローテーション）を
   ここで潰しておく（IAMロールは手順1で対応済み）

   このフェーズの間は `APP_URL=http://localhost:8080`、
   `SESSION_SECURE_COOKIE=false` にしておく（ポートフォワードで見るため）。
   **当夜に本番値へ戻す。**
4. **メールが実際に届くことを確認する。**
   IAMロールが付いていても、そのロールに `ses:SendRawEmail` が
   無ければ送信は失敗する。アタッチの有無ではなく
   「実際に届くか」で確認すること

   ```bash
   php artisan tinker --execute='
     Mail::raw("SES疎通確認", fn($m) => $m->to("自分のアドレス")->subject("test"));'
   ```
5. `./vendor/bin/phpunit` を実行（197件）。
   特に `ConfigInvariantTest` は本番 `.env` を置いた状態で通すこと
   （`APP_URL` と Sanctum のずれをここで検出する）
6. `php artisan db:seed --class=DevelopmentSeeder` で
   テストデータを入れ、**手動テスト**（疎通確認レベル）

   ```bash
   aws ssm start-session --target <インスタンスID> \
     --document-name AWS-StartPortForwardingSession \
     --parameters '{"portNumber":["80"],"localPortNumber":["8080"]}'
   # → ブラウザで http://localhost:8080
   ```

> **注意:** `APP_URL` と実際にアクセスしているURLは、
> スキーム・ホスト・ポートまで一致していないと
> `/api/*` が全て401になり、`/home` と `/login` の無限リダイレクトになる。
> ポートフォワードのポート番号を変えたら `APP_URL` も変えること。

> **HTTPSでの確認について。** ポートフォワードは平文HTTPなので、
> 証明書やSecure Cookieを含めた最終確認は当夜の
> 「切り替え前スモークテスト」で行う（hostsファイル方式）。
> フェーズ1で先にHTTPSまで通したい場合のみ、一時ホスト名
> （例 `new.tsubasa.smartj.mobi`）を新サーバのIPに向けて証明書を取る。
> **EIP付け替え方式では必須ではない。**

### フェーズ2: 本番データでのテスト（1〜2日、テストチーム）

**このフェーズに入る前にメールを封じ込める**（問題2）。

1. テストデータを捨てる。行を消すのではなく作り直す:
   ```bash
   mysql -e 'DROP DATABASE tsubasa; CREATE DATABASE tsubasa
             CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
   ```
   テストで作られた添付ファイルも消す
2. 本番からダンプを取り、新サーバに取り込む（**ここで所要時間を計測**）
3. 添付ファイルを rsync
4. `php artisan migrate --force`
   （本番データに対して走るのは `failed_jobs` へのuuid列追加1件のみ）
5. `TRUNCATE jobs; TRUNCATE failed_jobs;`
6. **参照系テスト** — 移行前と表示が変わっていないこと。特に:
   - 絵文字・機種依存文字を含む投稿が化けていないこと
   - **既存の添付ファイルと画像が表示できること**
   - 古い予定・アンケートの日付がずれていないこと
7. **更新系テスト（テストチーム）** — 投稿、コメント、いいね、
   予定登録、アンケート作成と回答、メンバー招待、
   **チーム切り替え**（複数チーム所属者）、退会、CSV出力、iCal購読
8. 見つかった不具合を修正し、必要なら1に戻る

> ここで入ったテストデータは**当夜に全部捨てる**ので、
> 遠慮なく壊してよい。

### フェーズ3: 切り替えリハーサル（半日）

**当夜と同じ手順を、通しで一度やる。目的は所要時間の実測。**

フェーズ2で汚れたDBを捨て、フル再取り込み→スモークテストまでを
時間を計りながら実施する。ここで測った時間が、
**当夜のメンテナンス窓の長さ**になる。

計測する区間:

| 区間 | 実測 |
| --- | --- |
| 本番ダンプ取得 | 分 |
| 転送 | 分 |
| 取り込み | 分 |
| 添付ファイルの差分rsync | 分 |
| migrate + キャッシュ再生成 | 分 |
| スモークテスト | 分 |
| **合計** | **分** |

合計が想定より長い場合の短縮策:

- 添付ファイルは**事前に一度フル同期**しておき、当夜は差分だけにする
  （添付は追記のみなので、事前同期しても不整合にならない）
- ダンプは `--single-transaction` で取り、
  `--no-tablespaces` を付ける
- 転送とリストアをパイプで繋いで中間ファイルを作らない

### フェーズ4: 切り替え当夜

次章。

### フェーズ5: 切り替え後（1〜2週間）

---

## 4. 当夜のタイムライン

**目標60分。** 時刻はフェーズ3の実測値に置き換えること。
括弧内は担当を書き込む欄。

| 経過 | 作業 | 中止できるか |
| --- | --- | --- |
| -30分 | 全員の待機開始。旧サーバのEBSスナップショットを取得 | ○ |
| -10分 | 利用中の利用者がいないことを確認 | ○ |
| **00:00** | **旧サーバをメンテナンスモードにする**（`systemctl stop php-fpm`、Apacheはメンテナンス画面だけ返す設定に）。以後、本番への書き込みは発生しない | ○ |
| 00:02 | 旧DBの最終ダンプを取得 | ○ |
| 00:10 | 新サーバのDBを作り直してダンプを取り込む | ○ |
| 00:20 | 添付ファイルの**差分**rsync | ○ |
| 00:25 | `TRUNCATE jobs; TRUNCATE failed_jobs;` | ○ |
| 00:27 | **`.env` を本番値に戻す**（`APP_URL` を `https://tsubasa.smartj.mobi` に、`MAIL_MAILER` を本番SMTPに、`SESSION_SECURE_COOKIE=true`、`APP_DEBUG=false`） | ○ |
| 00:30 | `php artisan migrate --force` → `optimize:clear` → `config:cache route:cache view:cache event:cache` | ○ |
| 00:33 | **切り替え前スモークテスト**（下記） | ○ |
| **00:40** | **切り替え実行** — EIPを新インスタンスに付け替え（数秒） | **ここから切り戻しにコストが発生** |
| 00:45 | 本番URLでスモークテスト再実施 | △ |
| 00:50 | キューワーカー起動 `systemctl start tsubasa-queue`、cron有効化 | △ |
| 01:00 | 監視（ログ、`logs`テーブル、メール送信）開始。問題なければ完了 | △ |
| 01:30 | **切り戻し判断期限** | — |

### 切り替え前スモークテスト（00:33）

**切り替える前に、本番ドメイン・本番データ・本番証明書の状態で確認する。**
作業端末の `hosts` に新サーバのIPを書けば、利用者に見せずに
本番と同じ条件で確認できる。

```
<新サーバのIP>  tsubasa.smartj.mobi
```

> このステップだけは443へ直接アクセスするため、
> **切り替え前までにセキュリティグループの443を開けておくこと**
> （切り替え後は全利用者が使うので、いずれにせよ必要）。

確認する項目（5分で終わる範囲に絞る。網羅はフェーズ2で済んでいる）:

- [ ] ログインできる
- [ ] タイムラインに**移行前と同じ投稿**が並ぶ
- [ ] **既存の添付ファイルと画像が表示できる**
- [ ] カレンダーに予定が出る
- [ ] チーム切り替えが動く（複数チーム所属のアカウントで）
- [ ] 投稿を1件作成できる（作成後に削除する）
- [ ] `storage/logs/laravel.log` にエラーが出ていない
- [ ] `curl -I https://tsubasa.smartj.mobi/storage/<添付>` に
      `Content-Disposition: attachment` が付く

**1つでも落ちたら切り替えない。** この時点なら旧サーバを
メンテナンス解除するだけで完全に元に戻せる。

---

## 5. ロールバック

### 判断基準

**切り替え後30分（01:10）の時点で、上記スモークの必須項目
（ログイン・既存データの表示・添付の表示）が通らなければ切り戻す。**
「あとで直せそう」で引っ張らない。

### 切り戻し手順

1. **EIPを旧インスタンスに戻す**（数秒で完了する）
   ```bash
   aws ec2 associate-address --allocation-id <eipalloc-xxxx> --instance-id <旧インスタンスID>
   ```
2. 旧サーバのメンテナンスモードを解除する
3. 新サーバは**停止せずそのまま残す**（原因調査のため）

### 切り戻しのコスト

**切り替え後に新サーバへ入った投稿・コメント・予定は、
切り戻すと失われる。** そのため:

- 切り戻し判断は早いほど良い（利用者が使い始める前）
- 深夜に切り替えるのは、この「取り返しのつかない書き込み」が
  発生する前に判断できる時間を稼ぐため
- 朝までに問題が出た場合は、切り戻しではなく
  **新サーバ上で直す**方向に切り替える（利用者のデータを失わないため）

---

## 6. 切り替え後

`docs/PRODUCTION-CUTOVER-CHECKLIST.md` の「切り替え後」も参照。

### 当日中

- [ ] `storage/logs/laravel.log` と `logs` テーブルのエラー確認
  ```sql
  SELECT * FROM logs WHERE level='error' ORDER BY id DESC LIMIT 20;
  ```
- [ ] キューワーカーの稼働確認 `systemctl status tsubasa-queue`
- [ ] メールが実際に届くこと（テスト用アカウントでパスワード再設定）
- [ ] **`certbot renew --dry-run`**（今回のトラブルの再発防止）
- [ ] cron / バッチが移設され動いていること
- [ ] 旧サーバのEIPが外れ、新サーバに付いていること
      `aws ec2 describe-addresses`

### 1週間

- [ ] 添付アップロードが実際に使われて問題ないこと
- [ ] ディスク使用量の推移（`AppServiceProvider` が全SQLをログ出力
      する設定のままなので、ログ肥大化に注意）
- [ ] バックアップが新サーバで取れていること

### 2週間後

- [ ] 旧サーバを停止（**削除はしない**）
- [ ] さらに1か月様子を見てから、スナップショットを残して削除

---

## 7. 修正後のステップ（当初案との対応）

| 当初案 | 修正後 |
| --- | --- |
| 新サーバー作成 | フェーズ1-1,2（＋フェーズ0の事前調査を先に） |
| アプリデプロイ | フェーズ1-2,3（本番`.env`の突き合わせを追加） |
| テストデータ入れてユニットテスト | フェーズ1-4,5 |
| 手動テスト | フェーズ1-5,6（疎通確認）＋フェーズ2-6（本番データでの本番確認） |
| テストデータクリア | フェーズ2-1（DROP DATABASEで作り直す） |
| 本番データ移行 | フェーズ2-2〜5（**先にメール封じ込め**） |
| 参照系テスト | フェーズ2-6 |
| 更新系テスト(テストチーム) | フェーズ2-7 |
| — | **フェーズ3: リハーサル（新規。所要時間の実測）** |
| 本番一時停止 | 当夜 00:00 |
| 差分データ移行 | **フル再取り込みに変更**（当夜 00:02〜00:25） |
| — | **添付ファイルの同期（新規）** |
| — | **`.env` を本番値に戻す（新規）** |
| — | **切り替え前スモークテスト（新規。hostsで本番ドメインを向ける）** |
| DNS切り替え | **EIP付け替えに変更**（当夜 00:40、数秒。TTL短縮も伝播待ちも不要） |
| 新本番開始 | 当夜 00:45〜 |
| — | **切り戻し判断期限 01:30（新規）** |
