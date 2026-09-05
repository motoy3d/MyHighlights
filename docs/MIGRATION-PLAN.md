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
| ~~DBサイズ~~ | **2,964 MB（実測）** | **うち `logs` が 2,867MB＝96.7%。`logs` は直近2年分のみ移行と決定 → 移行量は約1.0GBに減る（下記2.4）** |
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
**直近2年分のみ移行すると決定**（下記2.4）。

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
tsubasa.smartj.mobi       -> 52.199.130.187   ← 移行対象（これだけ）
tsubasademo.smartj.mobi   -> 52.199.130.187   ← 廃止（移行しない）
smartj.mobi               -> 52.199.130.187   ← 旧サーバに残す
www.smartj.mobi           -> 52.199.130.187   ← 旧サーバに残す
```

| vhost | DocumentRoot | 扱い |
| --- | --- | --- |
| `smartj.mobi`（default） | `/var/www/html` | **旧サーバに残す**。`/redsmylife/` を AJP で Tomcat にプロキシ |
| `tsubasa.smartj.mobi` | `/var/www/MyHighlights/public` | **移行する（唯一の移行対象）** |
| `tsubasademo.smartj.mobi` | `/var/www/MyHighlights2/public` | **廃止。移行しない** |

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

### 実施状況と確定したリソースID（2026-09-05）

**本番アカウント: `796478799102`（IAMユーザー `Motoi` / プロファイル `motoikataoka`）**

| 用途 | 値 |
| --- | --- |
| **当夜に付け替えるEIP**（tsubasa用） | `52.199.130.187` / **`eipalloc-e5f15181`** |
| 旧インスタンス | **`i-0ea1f248078364fbe`**（t3.medium / ap-northeast-1a / Name=`RedsMyLife-Web/DB`） |
| 旧インスタンスのENI | `eni-76e2b738`（subnet `subnet-a0f1e3d4` / SG `sg-4f8cc028`） |
| プライマリ プライベートIP | `172.31.8.179` |
| **新EIP**（smartj.mobi / www 退避用） | **`52.199.118.63`** / `eipalloc-051478db829ae4ae2` |
| 新EIPのセカンダリ プライベートIP | `172.31.9.135`（assoc `eipassoc-0df28cc8b28fb6bd3`） |
| **新インスタンス** | **`i-0421f25f72d67e67b`**（t3.medium / ap-northeast-1a） |
| 新インスタンスのIP | プライベート `172.31.4.190`（固定）/ パブリックIPは**自動割当なので停止・起動で変わる** |
| 新インスタンスのAMI | `ami-0794a632d5c1058bf`（AL2023 / 2026-09-01版） |
| 新インスタンスのIAMロール | `TsubasaAppServer`（`AmazonSSMManagedInstanceCore` + インライン `SesSend`） |
| 新インスタンスのSG | `sg-06a9c13cfebdfd595`（`tsubasa-al2023`。**インバウンド規則なし**） |
| 新インスタンスのEBS | 40GB gp3 / **暗号化あり**（旧サーバは暗号化なし） |

> **旧サーバの `SESFromEC2` ロールには手を触れていない。**
> 旧サーバが本番で使用中のため。新サーバには別ロールを作った。
> 権限は `AmazonSESFullAccess`（旧）ではなく
> `ses:SendRawEmail` / `ses:SendEmail` / `ses:GetSendQuota` に絞ってある。
> **フェーズ1でメールが実際に届くかを必ず確認すること**（絞りすぎていた場合はここで分かる）。

### 作業上の注意: `aws login` のトークンは15分で切れ、自動更新されない

プロファイル `motoikataoka` は `login_session` 方式
（`clientId: arn:aws:signin:::devtools/same-device`）で、
**アクセストークンの有効期間は15分**（`idToken` の `iat`/`exp` で確認）。

`aws login help` には「リフレッシュトークンが有効な限り CLI が自動更新する」と
あり、キャッシュにも `refreshToken` は入っている。
**しかし実際には自動更新が機能していない。**

```
login cache の最終更新: 23:14:53   ← ログイン時刻のまま一度も更新されない
トークン        iat: 23:14:53  exp: 23:29:53
```

キャッシュが書き換わらないため、`aws` プロセスは起動のたびに
期限切れのキャッシュを読み、ローテーション済みで無効になった
リフレッシュトークンで更新を試みて失敗する:

```
ValidationException: The provided authorization grant is
invalid, expired, revoked, or malformed
```

**結果として、`aws login` から15分でAWS操作が全て止まる。**
`aws login` に有効期間を指定するオプションは無い（`aws login help` で確認済み）。

> 当初これを「AWS CLIの並列実行によるリフレッシュ競合」と推測したが、
> **誤りだった。** 並列実行していない場面でも同様に失敗する。
> キャッシュの更新時刻がログイン時刻から動いていないことが根拠。

長時間の作業をするなら、次のいずれかが必要:

| 方法 | 有効期間 | 備考 |
| --- | --- | --- |
| **IAMアクセスキーを `~/.aws/credentials` に置く** | 無期限 | 最も確実。静的な長期資格情報がディスクに残るのと引き換え |
| 上記＋`aws sts get-session-token --duration-seconds` | 最大36時間 | 長期キーが前提。一時資格情報からは呼べない |
| IAM Identity Center (SSO) へ移行 | セッション最大90日 / ロール最大12時間 | 別アカウントで既に使っている方式 |
| 都度 `aws login` し直す | 15分 | **切り替え当夜には向かない。** 作業の途中で必ず止まる |

> **当夜までに必ず解消しておくこと。** 15分で認証が切れる状態のまま
> 深夜作業に入ると、EIP付け替えの直前で操作不能になりうる。

---

## フェーズ1の進捗

| 手順 | 状態 |
| --- | --- |
| 1. EC2(AL2023)起動＋IAMロール | **完了** |
| 2. `setup-al2023.sh` で環境構築 | **完了** |
| 3. リポジトリ配置と `deploy.sh` | **完了** |
| 4. 本番 `.env` の配置 | **完了** |
| 5. メール実送信の確認 | **完了（実受信を確認）** |
| 6. `phpunit` 197件 | **完了（197件成功 / 510アサーション）** |
| 7. シーダー投入と画面確認 | **完了** |

構築後の検証結果:

| コンポーネント | バージョン |
| --- | --- |
| PHP | 8.4.24 |
| MariaDB | 10.11.18 |
| Apache | 2.4.68 |
| Node.js | 24.18.1 |
| Composer | 2.10.2 |
| certbot | 2.6.0 |

本番 `.env` を置いた状態での設定解決値:

```
app.env                       'production'
app.url                       'http://localhost:8080'   ← フェーズ1のみ
app.timezone                  'Asia/Tokyo'
app.locale                    'ja'
session.cookie                'tsubasaup_session'       ← 旧と同じ
session.secure                false                     ← フェーズ1のみ
mail.default                  'ses'
queue.default                 'database'
logging.default               'daily'                   ← 要対応4を解消
filesystems.disks.public.url  'http://localhost:8080/storage'
```

DBは `'tsubasa'@'localhost'` で作成し、ソケット接続を実地確認した
（`pdo_mysql.default_socket` と MariaDB の `socket` がどちらも
`/var/lib/mysql/mysql.sock` で一致。**要対応3を解消**）。
テスト用DBは `tsubasa_phpunit` を別に作ってある。

画面確認（SSMポートフォワード → `http://localhost:8080`）:
ログイン画面の描画（ヘッダとボタンが青く、OnsenUIが効いている）、
ログイン、タイムラインへの投稿表示、カレンダーの月表示と予定表示、
`/.env` が403で拒否されることを確認した。

### フェーズ1で見つかった問題

いずれも**当夜に踏むと復旧が長引く**もの。修正済み。

| 症状 | 原因 | 対処 |
| --- | --- | --- |
| `deploy.sh` が composer で落ちる | SSMの `AWS-RunShellScript` はrootかつ **`HOME` 未設定**で実行する。composerは `HOME` か `COMPOSER_HOME` が無いと起動できない | `ssm-run.sh` が `HOME` を補うようにした。`deploy.sh` でも `COMPOSER_HOME` を固定 |
| **`/api/*` が全て500** | `storage/framework/cache/data` が **root所有**で apache が書けず、レートリミッタがファイルキャッシュに書けなかった。artisanをrootで流すと発生する | `deploy.sh` が artisan を `sudo -u apache` で実行するようにした。`deploy/fix-permissions.sh` も追加 |
| `ssm-run.sh` が複数行コマンドで落ちる | `--comment` に改行が入り、SSMの `^.{0,100}$` 制約に違反 | 空白に潰してから切り詰めるようにした |
| ポートフォワードで画面が見られない | `tsubasa.conf` は :80 を全てhttpsへ301し、`ServerName` も固定。:443 は証明書未配置のまま `SSLEngine on` | 検証用の `deploy/tsubasa-phase1.conf` を追加。**切り替え前に `tsubasa.conf` へ差し替える** |
| シーダーが実行できない | `DevelopmentSeeder` に `app()->isProduction()` の停止ガードがあり、本番 `.env`（`APP_ENV=production`）とは両立しない | 投入の間だけ `APP_ENV` を落として戻す。フェーズ1のDBは使い捨てなので問題ない |

> **`/api/*` の500は特に注意。** 画面上は「ログインはできるが
> タイムラインもカレンダーも空」という出方をする。
> 401ではないのでSanctumの設定を疑っても見つからない。
> **当夜のタイムラインは 00:30 に `migrate` と `config:cache` を
> 流し、その直後の 00:33 がスモークテスト。** ここでartisanを
> rootで流すと同じ状態になるため、`deploy.sh` 経由で流すか、
> 流した後に `deploy/fix-permissions.sh` を実行すること。

### メール送信の確認結果（手順5）

新サーバから `Mail::raw` で1通送信し、**実際に受信箱まで届くことを確認した。**

| メトリクス (CloudWatch AWS/SES) | 値 |
| --- | ---: |
| Send | 1 |
| **Delivery** | **1** |
| Bounce / Reject / Complaint | 0 |

**旧サーバの `AmazonSESFullAccess` まで広げなくても、
`ses:SendRawEmail` / `ses:SendEmail` / `ses:GetSendQuota` に
絞ったロールで送信できる**ことが確定した。

> `ses:GetSendStatistics` はロールに含めていないため、
> インスタンス上から `aws ses get-send-statistics` は AccessDenied になる。
> 送信自体には影響しない。統計を見たい場合は手元の資格情報で見る。

SESは本番アクセス有効（サンドボックス外）、`smartj.mobi` と
`system@smartj.mobi` が検証済み、直近24時間で1,866通送信中。

### 残っているもの

- テスト実行のために入れた開発依存は、その後の `deploy.sh` 再実行で
  `--no-dev` に戻っている。
- **フェーズ2に入る前にメールを封じ込めること**（問題2）。
  本番データを入れた状態で今回と同じことをすると、
  実在の会員に本物の通知が飛ぶ。

> **インスタンスは使い終わったら停止すること。**
> `aws ec2 stop-instances --instance-ids i-0421f25f72d67e67b`

--- | --- |
| 1. EC2(AL2023)起動＋IAMロール | **完了** |
| 2. `setup-al2023.sh` で環境構築 | **完了** |
| 3. リポジトリ配置と `deploy.sh` | 未 |
| 4. 本番 `.env` の配置 | 未 |
| 5. メール実送信の確認 | 未 |
| 6. `phpunit` 197件 | 未 |
| 7. シーダー投入と手動テスト | 未 |

構築後の検証結果:

| コンポーネント | バージョン |
| --- | --- |
| PHP | 8.4.24 |
| MariaDB | 10.11.18 |
| Apache | 2.4.68 |
| Node.js | 24.18.1 |
| Composer | 2.10.2 |
| certbot | 2.6.0 |

`mariadb` / `php-fpm` / `httpd` はいずれも active + enabled。

> **Node は当初 nodejs20 を入れたが 24 に上げた。**
> Node 20 (LTS Iron) は 2026-04 にEOLを迎えている。
> AL2023 が提供するのは nodejs18(EOL) / nodejs22(2027-04まで) /
> **nodejs24(2028-04まで)** の3系統。
> Node は Vite のビルドにしか使わず実行時には登場しないため
> リスクは低いが、サーバを数年使う前提だと nodejs22 では
> 7か月後に同じ話になるので 24 を選んだ。
> Node 24.18.1 / npm 11.16.0 でクリーンビルドし直し、
> 画面表示とコンソールエラー無しを確認済み。
PHP拡張 `pdo_mysql` `mbstring` `xml` `gd` `bcmath` `intl` `zip` `opcache` は全て導入済み。

> **インスタンスは停止してある。** 再開するときは
> `aws ec2 start-instances --instance-ids i-0421f25f72d67e67b` の後、
> SSMに再登録されるまで1〜2分待つ。パブリックIPは変わるが、
> SSM経由で操作するので影響はない。

**手順1（AWS側）は完了。** 実施内容:

```bash
aws ec2 allocate-address --domain vpc            # → 52.199.118.63
aws ec2 assign-private-ip-addresses \
  --network-interface-id eni-76e2b738 --secondary-private-ip-address-count 1
aws ec2 associate-address \
  --allocation-id eipalloc-051478db829ae4ae2 \
  --network-interface-id eni-76e2b738 --private-ip-address 172.31.9.135
# 旧サーバ側でOSにセカンダリIPを反映
sudo bash -c 'export INTERFACE=eth0; \
  . /etc/sysconfig/network-scripts/ec2net-functions; rewrite_aliases'
```

検証: `https://smartj.mobi/` を新IP `52.199.118.63` に向けて取得した内容が、
旧IP経由と **md5一致**。同じサーバが応答している。本番も無影響
（tsubasa 301 / smartj 200 が作業前後で不変）。

> **`/etc/sysconfig/network-scripts/ifcfg-eth0:1` を作成した。**
> このAMIの `ec2-net-utils` は `plug_interface` から `rewrite_primary` しか
> 呼ばず、**セカンダリIPは再起動で復活しない**ことを確認したため。
> この設定を消すと、再起動後に `smartj.mobi` が新IPで応答しなくなる。

**残るのは手順2〜4（dnsv.jp での手作業）。** これが終わるまで当夜の付け替えはできない。

| レコード | 現在 | 変更後 |
| --- | --- | --- |
| `smartj.mobi` A | 52.199.130.187 | **52.199.118.63** |
| `www.smartj.mobi` A | 52.199.130.187 | **52.199.118.63** |
| `tsubasademo.smartj.mobi` A | 52.199.130.187 | **削除** |
| `tsubasa.smartj.mobi` A | 52.199.130.187 | **変更しない**（EIPごと移動するため） |

### 作業前から存在した問題（移行とは無関係）

新IPでの検証中に見つかったが、**旧IPでも同じ結果**なので移行が原因ではない。

| 事象 | 内容 |
| --- | --- |
| `https://www.smartj.mobi/` が繋がらない | 証明書のSANに `www.smartj.mobi` が無い（`no alternative certificate subject name matches`）。DNSを移しても直らないし、悪化もしない |
| `https://smartj.mobi/redsmylife/` が404 | 旧IP・現行DNSいずれでも404。Tomcatとcron4本は動いているので、Webの入口だけの問題と思われる |

---

## 2.3 tsubasademo（テスト環境）は廃止する

**移行しない。** 現行は `/var/www/MyHighlights2`（Laravel 5.6.40 / PHP 7.1、DB `tsubasa_test`
28テーブル5MB、添付444KB/21件）。AL2023 には PHP 7.1 が無く、移行するなら
Laravel 13 への載せ替えが必要になるが、それに見合う価値が無いと判断した。

廃止に伴う扱い:

| 対象 | 扱い |
| --- | --- |
| `tsubasademo.smartj.mobi` のAレコード | **切り替え前に削除する。** 残したままEIPを付け替えると、新サーバの別vhostに流れ込む |
| DB `tsubasa_test` / ディレクトリ `/var/www/MyHighlights2` | 旧サーバの退役時にまとめて処分。**切り替え当夜には触らない** |
| 証明書 `tsubasademo.smartj.mobi`（2026-11-28まで） | 新サーバにコピーしない。旧サーバの renewal 設定から外す |

> **当夜に消す作業を入れないこと。** 廃止は切り替えとは独立していて急がない。
> 旧サーバは切り替え後もしばらく残すので、その間に落ち着いて処分する。

### 補足: テスト用DB名について

demo の DB名が `tsubasa_test` で、自動テスト用に使おうとしていた名前と同一だった。
demo を移行しないので新サーバでの衝突は起きないが、
**旧サーバの demo DB は退役まで生き続ける**ため、取り違えの余地を残さないよう
テスト用DBは `tsubasa_phpunit` に改名した（`phpunit.xml`）。
テストの26/27ファイルが `RefreshDatabase` を使うので、
名前を間違えた状態で流すとデータが消える。

---

## 2.4 `logs` は直近2年分のみ移行する

### 年別の分布（`log_timestamp` 基準・実測）

| 年 | 行数 | 比率 |
| --- | ---: | ---: |
| 2019 | 1,209,648 | 8.7% |
| 2020 | 1,010,130 | 7.3% |
| 2021 | 1,414,538 | 10.2% |
| 2022 | 1,945,621 | 14.0% |
| 2023 | 2,330,027 | 16.8% |
| 2024 | 2,229,487 | 16.1% |
| 2025 | 2,134,117 | 15.4% |
| 2026 | 1,601,742 | 11.5% |
| **合計** | **13,875,310** | |

### 2年で切った場合（カットオフ 2024-09-05）

| | 行数 | 比率 | 推定サイズ |
| --- | ---: | ---: | ---: |
| 移行する | **4,425,540** | 31.9% | **約 915 MB** |
| 捨てる | 9,449,770 | 68.1% | 約 1,952 MB |

**DB全体は 2,964 MB → 約 1.0 GB になる（約66%減）。**
旧サーバの空き7.6GBに対する圧迫もかなり緩む。

### ⚠️ ダンプ方法に注意 — `logs` に時刻の索引が無い

`logs` の索引は **`id` の PRIMARY だけ**。`log_timestamp` に索引が無いため、

```bash
# これは1,387万行のフルスキャンになる。遅い
mysqldump ... tsubasa logs --where="log_timestamp >= '2024-09-05'"
```

**`id` が auto_increment で時刻と相関しているので、境界の `id` を事前に1回求めておき、
当夜はPKのレンジスキャンで抜く。**

```bash
# 事前（1回だけ。フルスキャンだが日中に済ませられる）
mysql -e "SELECT MIN(id) FROM logs
          WHERE log_timestamp >= DATE_SUB(NOW(), INTERVAL 2 YEAR);"   # → BOUNDARY_ID
# 2026-09-05 時点の実測: BOUNDARY_ID = 9449781 （= 2024-09-05 00:20:14 以降）
#   max(id) = 13875323 に対し総行数 13875310 で、id と時刻の順序は実質一致している

# 当夜: logs 以外 + logs の直近分、の2本に分けて取る
mysqldump --single-transaction --no-tablespaces \
  --ignore-table=tsubasa.logs tsubasa            > main.sql
mysqldump --single-transaction --no-tablespaces \
  tsubasa logs --where="id >= ${BOUNDARY_ID}"    > logs.sql
```

> **`BOUNDARY_ID` は当夜の直前に取り直すこと。** 事前に求めた値のままだと、
> それ以降に増えた分だけ保持期間が延びる（害は無いが量が増える）。
> 厳密さより速度を優先してよい箇所。

> 旧サーバの空きは7.6GBあり、約1.0GBのダンプなら中間ファイルを置ける。
> それでも**パイプ直結のほうが速い**ので、フェーズ3で両方試して速いほうを採る。

### 切り捨てた分の扱い

**旧サーバは切り替え後も残るので、古いログはそちらに残り続ける。**
監査などで必要になったら旧サーバを参照する。
旧サーバを最終的に削除する際は、EBSスナップショットを残すので
そこからも取り出せる。

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

> **旧サーバは落とせない。** `smartj.mobi` / `www.smartj.mobi` と、
> Tomcat上の `redsmylife`（および ec2-user の cron 4本）が同居しているため、
> 当初の「旧サーバを停止」は **Tsubasa の vhost を止めるだけ** に読み替える。

- [ ] **Tsubasa の vhost だけを止める**（`tsubasa.smartj.mobi`）。
      **旧サーバ自体は停止しない** — `smartj.mobi` / `www` / `redsmylife` が
      動き続けているため（2.2節）
- [ ] tsubasademo を処分する（DB `tsubasa_test`、`/var/www/MyHighlights2`、
      Aレコード、renewal設定）
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
