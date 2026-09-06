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
QUEUE_CONNECTION=database  # sync にしない。PostNotificationJob は宛先1人ごとに sleep(1) するので
                           # 同期実行にすると投稿の POST が20秒以上かかる(実測)。database のまま
                           # tsubasa-queue を止めておけば jobs に溜まるだけで、当夜の TRUNCATE で消える
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

> **この節の数値は 2026-09-05 時点のもの**（`posts` 4,303 / 添付 15,799ファイル / `logs` 1,387万行）。
> 本番は稼働中なので日々増える。移行後の突き合わせに使った値は §フェーズ2以降を見ること
> （2026-09-06 時点で `posts` 4,306 / 添付 15,816ファイル）。

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

- `authenticator = webroot`。**webroot は証明書ごとに違う**(2026-09-06 に訂正):
  - `smartj.mobi` … `/var/www/html`(旧サーバに残る。新には持ち込まない)
  - **`tsubasa.smartj.mobi` … `/var/www/MyHighlights/public`**(新サーバも同じパス。書き換え不要)

> 当初「`/etc/letsencrypt` をコピーするだけでは更新が失敗する」と書いていたが、
> それは `smartj.mobi` 側の設定を見た誤り。tsubasa の renewal 設定はそのまま使える。
> 新サーバの `:80` vhost は `/.well-known/acme-challenge/` を https に 301 しないことを curl で確認済み。
> ただし **AL2023 の certbot rpm は timer を同梱しない**ので、`configure-runtime.sh` が `certbot-renew.timer` を入れる。

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
| **新インスタンス** | **`i-0626d85c720708c64`**（**t4g.medium / arm64 / Graviton** / ap-northeast-1a） |
| 新インスタンスのIP | プライベート `172.31.8.218`（固定）/ パブリックIPは**自動割当なので停止・起動で変わる** |
| 新インスタンスのAMI | `ami-0abcde8261ecfaf82`（AL2023 **arm64**） |
| 新インスタンスのIAMロール | `TsubasaAppServer`（`AmazonSSMManagedInstanceCore` + インライン `SesSend`） |
| 新インスタンスのSG | `sg-06a9c13cfebdfd595`（`tsubasa-al2023`。**インバウンド規則なし**） |
| 新インスタンスのEBS | 40GB gp3 / **暗号化あり**（旧サーバは暗号化なし） |
| 転送用S3バケット | `tsubasa-migration-796478799102`（30日で自動失効） |
| 旧サーバのEBSスナップショット | `snap-093c44ec9df53972f` |

> **x86版の `i-0421f25f72d67e67b` は削除済み。** Graviton へ作り直したため。

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
| 6. `phpunit` 199件 | **完了（199件成功）** |
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
| ポートフォワードで画面が見られない | `tsubasa.conf` は :80 を全てhttpsへ301し、`ServerName` も固定。:443 は証明書未配置のまま `SSLEngine on` | 検証用に `deploy/tsubasa-phase1.conf` を一時的に使った。**その後 `tsubasa.conf`（証明書パスを明記）に差し替え済みで、HTTPS はポートフォワード経由で確認できるため phase1 用の vhost は削除した** |
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

## フェーズ2の進捗（2026-09-06）

### フェーズ0の残りを完了

| 項目 | 結果 |
| --- | --- |
| 旧サーバの `.env` の退避 | `~/tsubasa-migration-backup/production.env`（53行、`APP_KEY` 確認済み、権限600） |
| 旧サーバの `/etc/letsencrypt` の退避 | `~/tsubasa-migration-backup/letsencrypt.tar.gz`（2.0MB / 1,817ファイル、3ドメイン分の証明書） |
| 旧サーバのEBSスナップショット | **`snap-093c44ec9df53972f`**（`vol-08582e221df91c230` から取得） |

> **退避先はリポジトリ外のホームディレクトリ（権限700）。**
> `APP_KEY` を含むのでコミットしないこと。

### メールの封じ込め（実施済み・実証済み）

新サーバの `.env` を封じ込めた。`MAIL_MAILER` > `MAIL_DRIVER`、
`QUEUE_CONNECTION` > `QUEUE_DRIVER` の優先順だが、
**片方だけ直すと外れた時に送信が復活する**ので両方を潰してある。

```
MAIL_MAILER=log / MAIL_DRIVER=log
QUEUE_CONNECTION=database / QUEUE_DRIVER=database(ワーカー停止。sync は投稿が20秒以上かかるため不採用)
tsubasa-queue: 停止
```

実際に送信を試み、**SESには出ず `storage/logs` に落ちること**を確認済み。
`.env` の先頭に当夜の戻し忘れ防止のコメントを入れてある。
戻す前の内容は `.env.before-containment` に残してある。

### 本番データの取り込み — 実測値

**フェーズ3・4のメンテナンス窓は、この数字が根拠になる。**

| 区間 | 実測 |
| --- | ---: |
| 本番ダンプ取得＋ローカルへ転送（logs以外 12MB） | 2秒 |
| 本番ダンプ取得＋ローカルへ転送（logs 96MB / 展開後1.14GB） | 31秒 |
| S3へアップロード（107MB） | 8秒 |
| 新サーバへダウンロード | 3秒 |
| DB作り直し（`DROP` → `CREATE`） | 0秒 |
| 取り込み（logs以外） | 7秒 |
| **取り込み（logs 4,433,756行）** | **81秒** |
| `migrate --force`（`failed_jobs` へのuuid列追加1件） | 0秒 |
| キャッシュ再生成 | 4秒 |
| **合計** | **約2分16秒** |

**目標60分に対して大幅に余裕がある。**
`logs` を直近2年に絞った判断が効いていて、取り込みの81秒が最大の区間。
全件（1,389万行）だと単純計算で4分程度になるが、それでも窓には収まる。

> **残るのは添付ファイルの差分同期だけ**（`deploy/sync-attachments.sh --since`。下記）。
> ここが読めないと当夜の合計は確定しない。

### 取り込み結果の検証

| テーブル | 件数 |
| --- | ---: |
| users | 651 |
| members | 1,664 |
| teams | 23 |
| posts | 4,306 |
| post_comments | 24,186 |
| post_attachments | 2,490 |
| schedules | 3,743 |
| questionnaires | 2,768 |
| **logs** | **4,433,756** |
| jobs / failed_jobs | 0 / 0 |

- `logs` の期間は **2024-09-06 〜 2026-09-06** で、意図どおり2年で切れている
- **絵文字も日本語も化けていない**（`【⚠️重要⚠️】会費改定に伴う臨時総会について`）
- 文字セットは本番と完全一致（`utf8mb4_unicode_ci` 26 / `utf8mb4_general_ci` 7）
- `failed_jobs.uuid` 列が作られ、`migrate:status` に未適用なし
- 新サーバのディスク使用量は 4.0G / 40G（10%）

### 転送経路

新サーバはインバウンドを開けていないため、**S3を経由**する。
**DBダンプと添付で経路が違う。** サイズが2桁違うため。

```
DBダンプ(108MB): 旧サーバ --(ssh|gzip)--> ローカル --> S3 --> 新サーバ
添付(5.2GB)    : 旧サーバ --(署名付きURLでPUT)--> S3 --> 新サーバ
```

**添付をローカル経由で送ろうとして53分かけて失敗した。**
15,817個の小さいファイルは `aws s3 sync` の往復が重く、
1つのtarにまとめてもローカルの上り回線が持たなかった。

**旧サーバから直接S3へ送ると桁違いに速い**（AWS内で完結するため）。

| 対象 | ローカル経由 | 旧サーバから直接 |
| --- | --- | --- |
| post_attachment 2.2GB | — | **20秒** |
| comment_attachment 3.0GB | — | **27秒** |
| 5.2GB 全体 | 53分で失敗 | 合計約2分 |

旧サーバにS3権限が無いので、**手元で署名付きPUT URLを発行**して
`curl -X PUT --upload-file` で送る。**IAMは一切変更しない**
（`SESFromEC2` に触らずに済む）。単一PUTの上限が5GBなので
ディレクトリ単位に分ける。

```bash
# 手元で発行（boto3が要る）
python3 -c "
import boto3
s3 = boto3.client('s3', region_name='ap-northeast-1')
print(s3.generate_presigned_url('put_object',
    Params={'Bucket':'tsubasa-migration-796478799102','Key':'parts/post_attachment.tar'},
    ExpiresIn=43200, HttpMethod='PUT'))"

# 旧サーバで
cd /var/www/MyHighlights/storage/app/public
tar cf /var/tmp/post_attachment.tar post_attachment
curl -X PUT --upload-file /var/tmp/post_attachment.tar "<URL>"
rm -f /var/tmp/post_attachment.tar        # 空きが7.7GBしかないので都度消す
```

> **`deploy/sync-attachments.sh` は旧経路（ローカル中継）のままなので、
> 当夜までにこの方式へ書き換えること。**

| リソース | 値 |
| --- | --- |
| バケット | `tsubasa-migration-796478799102`（ap-northeast-1） |
| 公開設定 | パブリックアクセスを全てブロック |
| ライフサイクル | **30日で自動削除**（消し忘れ防止） |
| 新サーバの権限 | `TsubasaAppServer` にインラインポリシー `MigrationBucketRead`（このバケットの読み取りのみ） |

> **旧サーバの `SESFromEC2` ロールには一切触れていない。**
> 本番が使用中のため。

### 添付ファイルの転送（完了）

**15,817ファイル / 5.2GB** を転送し、展開まで確認した。
DB上の `post_attachments` 2,490件との突き合わせも通っている。

### DNS（完了）

| レコード | 変更後 | 状態 |
| --- | --- | --- |
| `smartj.mobi` A | 52.199.118.63 | ✅ |
| `www.smartj.mobi` A | 52.199.118.63 | ✅ |
| `tsubasademo.smartj.mobi` A | 削除 | ✅ |
| `tsubasa.smartj.mobi` A | 52.199.130.187（変更なし） | ✅ |
| **`aws.smartj.mobi` A** | **削除** | **済**(2026-09-06、`dig` で空を確認) |

> **`aws.smartj.mobi` は当初把握していなかった5つ目のホスト名。**
> `smartj.mobi` と中身が完全に同一（md5一致）で、
> デフォルトvhostに落ちているだけの別名だった。
> **このままEIPを付け替えると新サーバへ流れ込む。**

### まだ終わっていないこと

- **ブラウザテストの更新系11件が通っていない**（参照系22件は通過）。
  アプリ側は正常で、`POST /api/posts` は200を返し投稿も作成されている
  （アクセスログとDBで確認済み）。テストハーネス側の問題だが
  **根本原因を特定できていない**
- テストチームによる参照系・更新系テスト（フェーズ2の本体）
- リハーサル（フェーズ3）

### フェーズ2用のテストアカウント

本番データのコピーに検証用アカウントを作ってある。
実ユーザーのレコードには触れていない。

| | |
| --- | --- |
| メール | `browser-test@example.invalid`（配送されないTLD） |
| user_id | 880 |
| 所属 | team 41「横浜SCつばさ41st」/ team 42「横浜SCつばさ42nd」 |

**複数チーム所属なので、チーム切り替えの検証に使える。**
パスワードはリポジトリに書かない。当夜の再取り込みで消える。

---

## Graviton (arm64) への切り替え

**t3.medium (x86_64) から t4g.medium (arm64) へ作り直した。**
Node 20 が既にEOLだった件を調べた流れで、アーキテクチャも見直した。

### 実測で確認したこと

t4g.micro を一時的に立てて `setup-al2023.sh` が入れる**21パッケージ全て**を確認した。

```
php8.4         8.4.24-1.amzn2023.0.1      ← x86_64 と同一バージョン
mariadb1011    3:10.11.18-1.amzn2023.0.1  ← 同一
nodejs24       1:24.18.1-1.amzn2023.0.2   ← 同一
httpd / composer / certbot / mod_ssl …    すべて arm64 に存在
```

構築後の検証:

| 項目 | 結果 |
| --- | --- |
| `npm run build` | 成功。`@esbuild/linux-arm64` が正しく入り、**アセットのハッシュは x86 版と同一** |
| `phpunit` | **199件成功**（本番データ＋本番 `.env`） |
| `logs` 443万行の取り込み | **78秒**（x86 は81秒） |
| 添付 15,817ファイル | 転送・展開・DBとの突き合わせ完了 |

**アプリは素のPHPでアーキテクチャ依存のコードが無く、動作面の差は見られなかった。**

> 参考: redsmylife を将来この構成へ移す場合、arm64 には
> `java-1.8.0-amazon-corretto` / `java-11` / `java-17` / `java-21` と
> `tomcat9` / `tomcat10` / `tomcat11` が揃っている（`tomcat8` は無い）。

### 作り直して見つかった不具合

**`storage:link` が新規サーバで作られず、`/storage/*` が全て404になっていた。**

`deploy.sh` の artisan を `sudo -u apache` で実行するようにした際の副作用。
`storage:link` は **root所有の `public/` 配下**にシンボリックリンクを作るため
apache では権限が無く、`|| true` で握り潰されていた。

x86 の時は最初のデプロイが root で走ってリンクができていたため露見しなかった。
**新規サーバでは必ず踏む。** 当夜に新サーバを立てていたら、
スモークテストの「既存の添付ファイルが表示できる」で落ちていた。

`storage:link` だけ root で実行し、作れたことを検証して、
できていなければ `ln -s` で作り、それでも駄目なら異常終了するようにした。

---

## AWSコストの現状と削減余地

実績は**月およそ $64**（2026年6〜8月の平均）。

| 項目 | 月額 | 備考 |
| --- | ---: | --- |
| EC2 Compute | $40.47 | t3.medium 1台ぶん |
| EC2 - Other | $7.29 | EBS・スナップショット |
| Tax | $5.87 | |
| VPC | $3.72 | public IPv4 の課金 |
| Security Hub | $3.60 | |
| SES | $2.34 | |
| GuardDuty | $0.48 | |

削減の効き目が大きい順。

| # | 施策 | 削減 | 状態 |
| --- | --- | ---: | --- |
| 1 | Compute Savings Plan（1年） | 約 -$12 | 未（**アーキテクチャとサイズを決めてから買う**） |
| 2 | **Graviton (t4g)** | 約 -$8 | **実施済み** |
| 3 | Security Hub と GuardDuty の要否見直し | -$4.08 | 未（要判断） |
| 4 | 2022年の古いスナップショット2件を削除 | -$2〜4 | 未 |
| 5 | 将来 t4g.small (2GB) へ | -$15 | **フェーズ2で実負荷を見てから判断** |

新サーバのアイドル時のメモリ使用は 521MB / 3.8GB、load average 0.00。
ただし旧サーバは実負荷で 3765MB を使い切ってスワップしている
（うち Tomcat の Java が約1.1GB、mysqld が812MB で、これは3サイト分）。
**Tsubasa単体なら t4g.small でも足りる可能性があるが、
本番データで実操作をしてから決めること。**

---

## ブラウザ自動テストと Vue 3 化の方針

### Vue 3 化は今回の切り替えには乗せない

Vue 2 は 2023-12 にEOL。今回まとめて対応する案を検討したが、見送った。

判断の決め手は**安全網が無いこと**だった。

- PHPUnitの199件は**全てバックエンド(HTTP/API)のテスト**で、
  Vueコンポーネントを1件も検証していない。JS側のテスト基盤も無かった
- したがって「テストが通っているから安全」は Vue 3 化には当てはまらない

依存関係にも弱点がある。

| パッケージ | 状況 |
| --- | --- |
| `vue-onsenui` 3.0.0（Vue 3対応） | **2022-07-25 リリース。以降4年間、安定版なし** |
| `vue-onsenui` 2.7.2（Vue 2系） | 2022-12-27 — 3.0.0 より後に出ている |
| `vue-moment` | 2019-12-22 が最新。約7年更新なし。Vue 3 はフィルタ構文を廃止したので置き換え必須 |

UIは `<v-ons-*>` を24種・延べ329箇所使っており、
`vue-onsenui` 3 に欠落があっても逃げ道が安くない。

**より本質的な理由はロールバックの切り分け。**
今は「切り戻し＝EIPを戻すだけ」でコードの挙動は新旧同じ。
Vue 3 を同梱すると、当夜に画面がおかしかったときに
OS/PHP/Laravel/DBの移行が原因かフロントの書き換えが原因か
切り分けられない。切り戻し判断期限は30分しかない。

**切り替え後にやれば、Vue 3 化は「安定した土台の上での
巻き戻せるコードデプロイ」になる**（`git revert` して `deploy.sh`）。
メンテナンス窓もインフラリスクも不要で、日中に実施できる。

### 代わりに先にブラウザ自動テストを作った

フェーズ2の回帰確認にそのまま使えるので、Vue 3 化と無関係に元が取れる。
フェーズ2で回して育て、切り替え後にそれを安全網として Vue 3 化する。

`tests/browser/` に Playwright で **31件**。使い方は
`tests/browser/README.md`。PC(Chromium)とモバイル(WebKit)の2構成。

チェックリストの「切り替え前の動作確認」16項目との対応:

| 項目 | 状態 |
| --- | --- |
| ログイン / ログアウト | ✅ |
| 投稿の作成・削除 | ✅（編集は未） |
| **添付のアップロードと表示** | ✅ |
| **画像添付のリサイズ** | ✅ 実ピクセル数で検証 |
| コメント投稿、いいね | ✅ |
| 予定の登録・編集・削除、カレンダー表示 | ✅ |
| アンケートの作成・回答・CSV | ✅ |
| メンバー招待 | ❌ |
| パスワード再設定メール | ❌ |
| チーム切り替え | ⚠️ 複数チームのアカウントを渡せば動く |
| iCal購読 | ❌ |
| 既存のアップロード済みファイルの表示 | ✅ |
| 添付のレスポンスヘッダ | ✅ |
| 添付が壊れていないこと | ✅ |
| ログイン画面の表示崩れ | ✅（パスワード再設定・退会画面は未） |
| OnsenUIのCDN廃止 | ✅ |

**12項目が自動化済み。** 残る未着手はメンバー招待・パスワード再設定・
iCal購読で、いずれもメール送信や外部アプリが絡むため
フェーズ2で人手で確認する。

> **`TSUBASA_MULTI_TEAM_EMAIL` を設定しないとチーム切り替えは skip される。**
> 移行で認可の穴を塞いだ箇所なので、フェーズ2では必ず設定して流すこと。

### 整備中に見つかったこと

| 事象 | 内容 |
| --- | --- |
| **`MemberFactory` が本番と違う型のデータを作っていた** | `members.type` は本番では `'1'`(選手)/`'2'`(監督)/`'3'`(家族) の数値コードだが、ファクトリは `'選手'` を入れていた。画面は `mem.type == 1` で絞るため**メンバー一覧が常に空**になる。手動テストで見たら移行の回帰と誤診する。修正済み |
| **アプリにAPIエラーのハンドリングが無い** | axiosのinterceptorが無く、429も500もネットワーク断も、すべて未処理のPromise拒否になって画面が黙って更新されないだけになる。フェーズ1で踏んだ「`/api/*` が全て500だがログインはできる」が読みにくかったのも同じ理由。**Vue 3 化のタイミングで共通のエラーハンドリングを入れるとよい** |
| レート制限は 60リクエスト/分 | `bootstrap/app.php` の `throttleApi('60,1')`。利用者1人では当たらないが、テストを連続で回すと当たる。`gotoApp()` が429を検出して `Retry-After` の分だけ待つようにしてある |
| **DOMのid重複** | `<v-ons-page id="post">` が7コンポーネント、`<form id="postForm">` が4コンポーネントで重複。OnsenUIは全ページをDOMに残すため同時に複数存在する。画面の特定に `#post` は使えない |
| OnsenUIのスイッチはWebKitで合成クリックが効かない | 実機のタップでは動くのでアプリの不具合ではないが、自動テストでは回避が必要 |
| 予定の保存後はダイアログを閉じないと画面が戻らない | 「登録しました」のOKを押すまでカレンダーが隠れたまま |
| **`DevelopmentSeeder` は開発依存が必要** | faker を使うため、`composer install --no-dev` の状態では `fake()` が未定義で落ちる。シーダーを流す前に開発依存を入れること |
| `DevelopmentSeeder` は `APP_ENV=production` では動かない | 意図的な停止ガード。フェーズ1で流すときは一時的に `APP_ENV` を落として必ず戻す |

---

> **インスタンスは使い終わったら停止すること。**
> `aws ec2 stop-instances --instance-ids i-0421f25f72d67e67b`

--- | --- |
| 1. EC2(AL2023)起動＋IAMロール | **完了** |
| 2. `setup-al2023.sh` で環境構築 | **完了** |
| 3. リポジトリ配置と `deploy.sh` | 完了 |
| 4. 本番 `.env` の配置 | 完了 |
| 5. メール実送信の確認 | 完了 |
| 6. `phpunit` 199件 | 完了 |
| 7. シーダー投入と手動テスト | 完了 |

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

## 2.5 多角的点検（2026-09-06）— 「毎回新しい問題が出る」への対応

フェーズ2までのテストは全て通っていたが、作業のたびに新しい問題が出ていた。
原因は「**当夜に初めて動く経路がまだ残っていた**」こと。この点検では文書を読み直すのではなく、
旧サーバ・新サーバ・AWS の実態を機械的に採取して突き合わせ、当夜の経路を実際に踏んだ。

### 見つかった差分と対処

| # | 発見 | 当夜どうなっていたか | 対処 |
| --- | --- | --- | --- |
| 1 | **OSタイムゾーンが旧 JST / 新 UTC**。MariaDB は `time_zone=SYSTEM` | DB側の `CURRENT_TIMESTAMP` / `NOW()` が UTC、Laravel(Asia/Tokyo) が書く値が JST になり **9時間ずれる**。実データで `logs.log_timestamp` と `created_at` が9時間ずれていた | `timedatectl set-timezone Asia/Tokyo`。TIMESTAMP 型は内部 UTC で持つので、既に取り込んだデータも正しく読めるようになったことを同じ行で確認 |
| 2 | **php.ini が既定値のまま**（upload 2M / post 8M / memory 128M。旧は 20M / 20M / 256M） | **2MB を超える写真の投稿が全て失敗**する。自動テストの画像は10KBなので検知できなかった | `/etc/php.d/99-tsubasa.ini` で旧と同値に |
| 3 | MariaDB が `character_set_server=latin1`、`innodb_buffer_pool_size=128M`（旧 utf8mb4 / 512M） | 接続単位では Laravel が utf8mb4 を指定するので文字化けはしないが、440万行の `logs` に対してバッファが1/4 | `/etc/my.cnf.d/99-tsubasa.cnf` で旧と同値に |
| 4 | **`tsubasa.conf` に証明書パスが無い**（`SSLEngine on` だけ） | 当夜 vhost を差し替えた瞬間に **httpd が起動しない** | 旧 vhost と同じ `/etc/letsencrypt/live/tsubasa.smartj.mobi/` を明記し、実際に差し替えて起動・HTTPS 応答を確認 |
| 5 | `tsubasa-queue.service` が**未インストール**（チェックリストは「起動する」としか書いていない） | 通知メールが `jobs` に溜まるだけで送られない | 登録・enable し、リハーサルで実際にジョブを処理させた |
| 6 | **certbot の自動更新が無い**（AL2023 の rpm は timer 同梱せず、cronie も未導入） | 53日後（2026-10-29）に証明書失効 | `certbot-renew.timer`（毎日04時、旧の root cron と同内容）を登録 |
| 7 | `/etc/letsencrypt` が未配置 | 4 と同じく httpd が起動しない | 旧サーバから丸ごと持ち込み。移行しない `tsubasademo` と `smartj.mobi` の更新設定は外した（残すと毎日 renew が失敗し続ける） |
| 8 | 新サーバに旧に無い添付が57ファイル、DBに親のない添付行が27件 | 実害なし（当夜フル取り込みで消える）が、件数突合ができない | ブラウザテストの残骸と確認して削除。**ファイル数・行数とも旧と完全一致** |
| 9 | 新サーバが**日次スナップショットの対象外**（DLM はタグ `Name=RedsMyLife-Web/DB` のみ） | 切り替え後、バックアップ無しで稼働 | 同じポリシーの対象タグに `Name=Tsubasa-AL2023-arm64` を追加（保持1世代・04:00 JST） |
| 10 | 新インスタンスの削除保護が無効 | 誤操作で消せる | 有効化した |
| 11 | ロールバック手順の EIP コマンドが `--instance-id` 指定 | 旧 ENI には EIP が2本あり、プライマリIPに戻る保証がない | ENI + プライベートIP 指定に修正、`--dry-run` で確認 |
| 12 | 当夜手順の「`systemctl stop php-fpm` でメンテナンス」 | 旧は mod_php で php-fpm が無い。redsmylife も巻き込む | `php artisan down` に変更 |
| 13 | `deploy/sync-attachments.sh` が旧経路（ローカル中継） | 53分かかって失敗した経路 | 署名付き PUT 方式に書き換え、差分モードを実走（11秒） |
| 14 | スクリプト側: macOS bash 3.2 に連想配列が無い / ssh 引数の `&` / `pipefail` 下の `\| head` | 当夜スクリプトが途中で止まる | いずれも修正して再実走 |
| 15 | リハーサル中にキューワーカーが `ModelNotFoundException` で失敗（テストが投稿を即削除するため） | — | **旧サーバでも同じ失敗が日常的に起きている**（failed_jobs 1,674 件、最終 2026-09-05）ので移行の回帰ではない。チェックリストの「failed_jobs が 0」の判定基準を修正 |
| 16 | Playwright の `page.request` はホスト名の差し替えが効かない | 本番相当のリハーサルで 5 件が偽の失敗 | ページ内 fetch に置き換え |

### 差分が無かったもの（確認済み）

- `.env` のキー差分: 旧にある `LINE_NOTIFY_*` / `PUSHER_*` / `TEST_IP` はコードから参照されていない
- Laravel スケジューラ: 定義が無く、旧サーバにも `schedule:run` の cron は無い（旧の cron は全て redsmylife のバッチ）
- `noimage.png` の 404 は旧サーバでも同じ（既存の不具合。移行の回帰ではない）
- 自動テストで検出できない Apache 設定は curl で確認: http→https 301、`/.well-known/acme-challenge/` は 301 されず 200、`/storage/` の画像が `image/png` で返る
- EIP 付け替え・SG 開放のコマンドは `--dry-run` で権限・構文を確認

### 本番相当でのリハーサル結果

本番 vhost(`tsubasa.conf`)・旧サーバの証明書・`SESSION_SECURE_COOKIE=true`・
`APP_URL=https://tsubasa.smartj.mobi:8443`・キューワーカー稼働、の状態で
Playwright(chromium)を SSM ポートフォワード越しに実行: **32 passed / 1 skipped(複数チーム)**。
キューワーカーは投稿通知ジョブを実際に処理し、メールは `MAIL_MAILER=log` によりログに落ちた。
テスト後のデータ件数・添付ファイル数は旧サーバと一致。
(mobile=WebKit はホスト名の差し替えができないため HTTP で実施済みの結果をもって代える)

### 最終点検（2026-09-07）— 手法を変えて再点検

「自分が書いたテストを通す」以外の角度で見直した。

| 手法 | 結果 |
| --- | --- |
| **実トラフィックの再生** — 旧サーバのアクセスログ(6〜9月、97万行)から利用者が実際に叩いた URL 839 件を新サーバに再生 | 利用者に見える差分なし。**iCal 購読 URL 15 本は全て 200**(`+` や `$` を含むトークンも)。旧 200 / 新 404 は bot の探索(`/config/.env` 等。旧は default vhost の `Options Indexes` が 200 を返していた)、旧 mix のアセット(`/js/app.js?id=`。新 HTML は参照しない)、削除済み投稿の添付(旧にも無い)、`/img/LINE_APP.png`(LINE Notify 廃止で意図的に削除) |
| **iCal 内容の旧新比較** | DTSTART/DTEND/SUMMARY は一致。ライブラリ更新で UID の形式・ヘッダ(`VTIMEZONE`、`Content-Type: text/calendar`)が変わる。**購読側では初回取得時に全イベントが入れ替わる**(重複はしない)。終日イベントの `DTEND` が省略されるが、予定は `schedule_date` の単日のみなので意味は同じ |
| **内容レベルのデータ照合** — 件数ではなく行のハッシュ(`BIT_XOR(CRC32(...))`)で旧新比較 | posts / post_comments / schedules / members / teams / users / categories / questionnaires / logs(10万行)すべて一致(ダンプ後に旧で更新された数行を除外)。**TIMESTAMP 型を含む列も一致**=タイムゾーン修正の裏取り |
| **再起動試験**(旧は 1197 日無再起動) | httpd / php-fpm / mariadb / SSM / certbot timer / queue が全て自動起動。TZ・php.ini・DB 設定・storage リンク・権限も維持 |
| **利用者の端末 × TLS** | 新サーバは TLS 1.2+(AL2023 DEFAULT ポリシー)。旧は 1.0/1.1 も受けていたが、ログ上の 1.2 非対応クライアントは bot のみ。**iOS 14/15 の実機が 2.5 か月で 13 回**あり、Vite 7 の既定ビルド対象(Safari 16+)では画面が出ない可能性 → `vite.config.js` の `build.target` を `safari13/ios13` に下げた |
| **IAM の机上シミュレーション** | `simulate-principal-policy` で `ses:SendRawEmail` が allowed、`ec2:TerminateInstances` は deny。SES は本番アクセス済み(sandbox ではない)、`smartj.mobi` ドメイン検証済み |
| **当夜の DB 手順の通しリハーサル** — `deploy/cutover-db.sh` を本番 DB(稼働中)に対して実行 | **合計 183 秒**(境界 id 取り直し 8秒、ダンプ 9MB+75MB、取り込み 6秒+83秒、migrate 1件)。件数は旧と完全一致、時刻の整合も確認。旧サーバの空きは 7.5GB で圧縮ダンプ 84MB なら問題ない |
| **第三者のプレモーテム**(文脈を持たない別エージェントに文書とスクリプトを読ませた) | 13 件の指摘。有効だったもの: **フル取り込みで検証アカウントが消える**(→ `smoke:account` コマンドを追加し当夜手順に組み込み)、**`ssm-run.sh` が 100 秒で待機を打ち切る**(→ 実測で再現。完了までポーリングする形に修正し 130 秒で確認)、**スモークの投稿通知がワーカー起動後に実メンバーへ飛ぶ**(→ スモーク中は `MAIL=log` のまま、`TRUNCATE jobs` 後に ses へ)、`.env` 戻しの時点が文書間で矛盾、EIP 付与後に `hosts` が死んだ IP を指す、当夜の DB 手順にスクリプトが無い(→ `cutover-db.sh` を追加)、旧サーバの外向き通信(→ 当夜の確認項目に)。誤認だったもの: webroot(古い記述を読んだもの。訂正済み)、`QUEUE=sync`(同)。確認して問題なし: パスワードは全件 bcrypt |

### 最終点検 その2（2026-09-07）— 本番データの複製と露出の棚卸し

| 観点 | 発見 | 対処 |
| --- | --- | --- |
| **検証データが実メンバーに見える** | 検証アカウントが実チーム(41st/42nd)に所属していた。スモーク中の投稿は削除するまで実メンバーのタイムラインに見え、通知ジョブも実メンバー宛に積まれる | `smoke:account` を**検証専用チーム2つを作る方式に変更**(実チームには一切触れない)。delete は検証チーム配下だけを添付ファイルごと消し、検証チーム外に検証ユーザーのデータがあれば止まる。実チームの members/posts が変わらないことを確認 |
| **S3 移行バケット** | 失敗した中継経路の残骸を含め **7.4GB / 5,697 オブジェクトの本番添付・ダンプ**が残っていた(ライフサイクル30日) | 全削除。当夜はスクリプトが必要な分だけ再アップロードし、取り込み後に消す。切り替え後はバケットごと削除し `MigrationBucketRead` ポリシーも外す |
| **手元の複製** `~/tsubasa-migration-backup` | `.env`(APP_KEY/DB/SMTP)、letsencrypt(秘密鍵)、ダンプ 110MB、失敗した中継の添付 5.2GB。権限 700 | `.env` / letsencrypt / ダンプは切り替え完了まで保持する価値がある。**添付 5.2GB は新サーバに取り込み済みで冗長**(削除は所有者判断) |
| **テストの成果物** | Playwright の失敗時スクリーンショット・動画・trace には本番の投稿が写る | `test-results/` `.auth/` は gitignore 済み。実行後に空であることを確認。/tmp の作業ファイル(iCal トークン入りの URL 一覧など)は削除 |
| **スナップショット** | 2022 年の2本(40GB×2)は移行と無関係に残っている | コスト項目として別途(ユーザー判断) |
| **キューワーカーの自動起動** | `tsubasa-queue` は enable 済みなので、インスタンスを起動し直すと**手で止めていても動き出す**(点検中に実際に起きた)。フェーズ2の間は `MAIL_MAILER=log` なので実送信は起きないが、「止めている」つもりにならないこと | 当夜は起動前に `jobs` が空であることを確認する手順が既にある。テストチームのフェーズ2でも `MAIL=log` を維持する |
| **容量** | 旧 t3.medium は実負荷で 3.7GB を使い切りスワップしていたが、新はスワップ無し。瞬間的な増加で OOM killer が mariadb を落とす | `configure-runtime.sh` で 2GB のスワップファイルと `vm.swappiness=10` |

### まだ当夜まで踏めない経路

- `certbot renew --dry-run` の HTTP-01: DNS が旧サーバを向いている間は必ず失敗する。設定の読み込みまでは正常
- EIP 付け替えそのもの（`--dry-run` まで）

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
5. `./vendor/bin/phpunit` を実行（199件）。
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
3. 添付ファイルを `deploy/sync-attachments.sh` で同期
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
| 添付ファイルの差分同期 | 11秒（実測） |
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
| -10分 | 利用中の利用者がいないことを確認。**新サーバの SG に 80/443 を開ける**(`sg-06a9c13cfebdfd595`。`--dry-run` 確認済み) | ○ |
| **00:00** | **旧サーバをメンテナンスモードにする** — `cd /var/www/MyHighlights && php artisan down`(旧は mod_php で php-fpm は無い。同居する redsmylife に影響させずに tsubasa だけ 503 にできる)。以後、本番への書き込みは発生しない | ○ |
| 00:02 | **`deploy/cutover-db.sh`** を実行。旧で境界 id の取り直し(8秒)→ ダンプ → S3 → 新で DB を作り直して取り込み → migrate → `TRUNCATE jobs, failed_jobs` → キャッシュ再生成 → 件数の突き合わせ、まで1本で行う(**2026-09-07 リハーサル実測 183 秒**: 旧ダンプ+PUT 63秒、取り込み main 6秒 + logs 442万行 83秒) | ○ |
| 00:10 | 添付ファイルの**差分**同期 `deploy/sync-attachments.sh --since <前回フル同期日> --to-server`(実測 11秒) | ○ |
| 00:12 | **検証アカウントを作り直す**(フル取り込みで消えている) `sudo -u apache php artisan smoke:account create --password='…'`。**実チームには所属せず、【検証】チームを2つ作ってそこに投稿・予定を入れる**。スモーク中の投稿が実メンバーに見えたり、通知ジョブが実メンバー宛に積まれたりしない | ○ |
| 00:15 | **`.env` を本番値に**: `APP_URL=https://tsubasa.smartj.mobi`(今は `:8443` 付き)、`API_RATE_LIMIT` の行を削除、`QUEUE_CONNECTION`/`QUEUE_DRIVER` が `database` であることを確認。**`MAIL_MAILER`/`MAIL_DRIVER` はまだ `log` のまま**(検証チームは隔離してあるが二重の保険)。`sudo -u apache php artisan config:cache` | ○ |
| 00:18 | **切り替え前スモークテスト**(下記)。投稿の作成→削除まで | ○ |
| 00:25 | `TRUNCATE jobs;`(スモークで積まれた通知ジョブを捨てる)→ `MAIL_MAILER`/`MAIL_DRIVER` を `ses` に → `config:cache` → **自分のアカウントでパスワード再設定メールを送り、届くことを確認**(IAM ロール経由の SES 送信の実地確認) | ○ |
| **00:30** | **切り替え実行** — EIP を新インスタンスに付け替え(数秒)。直後に作業端末の `hosts` / `TSUBASA_RESOLVE` を外す(新サーバの自動割当IPは EIP 付与で解放され、残すと死んだIPを指す) | **ここから切り戻しにコストが発生** |
| 00:31 | **旧サーバの外向き通信を確認** `curl -s https://checkip.amazonaws.com`(EIP を外すと自動割当IPに置き換わるはずだが、redsmylife のバッチと certbot が外へ出られることを目視) | △ |
| 00:35 | 本番URLでスモークテスト再実施(接続先が `52.199.130.187` であることを `curl -v` で確認) | △ |
| 00:40 | `SELECT COUNT(*) FROM jobs;` が 0 であることを確認してから **キューワーカー起動** `systemctl start tsubasa-queue`(cron は無い。certbot は timer 登録済み) | △ |
| 00:45 | 監視(ログ、`logs` テーブル、メール送信)開始。問題なければ完了 | △ |
| **01:00** | **切り戻し判断期限**(切り替えから30分) | — |

### 切り替え前スモークテスト（00:33）

**切り替える前に、本番ドメイン・本番データ・本番証明書の状態で確認する。**
作業端末の `hosts` に新サーバのIPを書けば、利用者に見せずに
本番と同じ条件で確認できる。

```
<新サーバのIP>  tsubasa.smartj.mobi
```

`hosts` を触りたくなければ、Chrome を
`--host-resolver-rules="MAP tsubasa.smartj.mobi <新サーバのIP>"` で起動するか、
`curl --resolve tsubasa.smartj.mobi:443:<新サーバのIP>` で確認できる。
自動テストは `TSUBASA_URL=https://tsubasa.smartj.mobi TSUBASA_RESOLVE=<新サーバのIP>`
で Playwright(chromium) が同じことをする（2026-09-06 のリハーサルで使用）。

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

**切り替え後30分（01:00）の時点で、上記スモークの必須項目
（ログイン・既存データの表示・添付の表示）が通らなければ切り戻す。**
「あとで直せそう」で引っ張らない。

### 切り戻し手順

1. **EIPを旧インスタンスに戻す**（数秒で完了する）
   ```bash
   # 旧インスタンスの ENI には EIP が2本付いている(tsubasa 用と redsmylife 用)。
   # --instance-id 指定だとプライマリIPに付くとは限らないので、ENI とプライベートIPを明示する
   aws ec2 associate-address --allocation-id eipalloc-e5f15181 \
     --network-interface-id eni-76e2b738 --private-ip-address 172.31.8.179 \
     --allow-reassociation
   ```
   （`--dry-run` で 2026-09-06 に権限・構文を確認済み）
2. 旧サーバのメンテナンスモードを解除する（`php artisan up`）
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

- 検証アカウントと検証チームを消す `sudo -u apache php artisan smoke:account delete`(検証チーム配下の投稿・添付ごと消える)
- **旧サーバの renewal から tsubasa を外す** `sudo certbot delete --cert-name tsubasa.smartj.mobi`(旧の root cron が毎日 tsubasa の更新に失敗し続けるのを止める。新サーバ側の証明書には影響しない)

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

- 移行用 S3 バケット `tsubasa-migration-796478799102` を削除し、IAM ロール `TsubasaAppServer` から `MigrationBucketRead` を外す

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
| — | **切り戻し判断期限 01:00（新規）** |
