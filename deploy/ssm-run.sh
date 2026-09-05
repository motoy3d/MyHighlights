#!/usr/bin/env bash
#
# ローカルから新サーバへコマンド／スクリプトを流す（AWS Systems Manager 経由）。
# SSHもインバウンドのポート開放も鍵の配布も不要。
#
#   deploy/ssm-run.sh i-0123456789abcdef0 'php -v'
#   deploy/ssm-run.sh i-0123456789abcdef0 -f deploy/setup-al2023.sh
#
# 前提:
#   - インスタンスロールに AmazonSSMManagedInstanceCore が付いていること
#   - ローカルに本番アカウントの資格情報があること
#     (AWS_PROFILE か、環境変数 AWS_ACCESS_KEY_ID/AWS_SECRET_ACCESS_KEY)
#
set -euo pipefail

INSTANCE_ID=${1:-}
if [ -z "${INSTANCE_ID}" ]; then
  echo "usage: $0 <インスタンスID> ['コマンド' | -f スクリプトファイル]" >&2
  exit 2
fi
shift

PAYLOAD_FILE=$(mktemp)
PARAM_FILE=$(mktemp)
trap 'rm -f "${PAYLOAD_FILE}" "${PARAM_FILE}"' EXIT

if [ "${1:-}" = "-f" ]; then
  SCRIPT_FILE=${2:?スクリプトファイルを指定してください}
  cat "${SCRIPT_FILE}" > "${PAYLOAD_FILE}"
  LABEL="$(basename "${SCRIPT_FILE}")"
else
  printf '%s\n' "$*" > "${PAYLOAD_FILE}"
  LABEL="$*"
fi

# --- 対象アカウントの確認 -------------------------------------------------
# 誤ったアカウントで本番操作をしないための最低限のガード
ACCOUNT=$(aws sts get-caller-identity --query Account --output text)
echo "==> 対象アカウント: ${ACCOUNT} / インスタンス: ${INSTANCE_ID}"
if [ -n "${EXPECT_ACCOUNT:-}" ] && [ "${ACCOUNT}" != "${EXPECT_ACCOUNT}" ]; then
  echo "!!! 想定アカウント(${EXPECT_ACCOUNT})と違います。中止します。" >&2
  exit 1
fi

# --- 送信 -----------------------------------------------------------------
# 出力は取り漏らさないようインスタンス側にもログを残す
python3 - "${PAYLOAD_FILE}" > "${PARAM_FILE}" <<'PY'
import json, sys, datetime
body = open(sys.argv[1], encoding='utf-8').read()
log = '/var/log/tsubasa-deploy.log'
wrapped = (
    'set -o pipefail\n'
    f'echo "===== $(date -Is) ssm-run =====" >> {log}\n'
    '{\n' + body + '\n} 2>&1 | tee -a ' + log + '\n'
)
print(json.dumps({"commands": [wrapped]}))
PY

# SSMの --comment は改行を含められず、100文字以内という制約がある
# (^.{0,100}$ で . は改行にマッチしない)。複数行のコマンドをそのまま渡すと
# ValidationException で落ちるので、空白に潰してから切り詰める。
COMMENT=$(printf 'tsubasa: %s' "${LABEL}" | tr '\n\r\t' '   ' | tr -s ' ' | cut -c1-100)

CMD_ID=$(aws ssm send-command \
  --instance-ids "${INSTANCE_ID}" \
  --document-name AWS-RunShellScript \
  --comment "${COMMENT}" \
  --timeout-seconds 3600 \
  --parameters "file://${PARAM_FILE}" \
  --query Command.CommandId --output text)

echo "==> CommandId: ${CMD_ID}  (完了まで待機します)"

# wait は失敗時に非0で返るが、出力は必ず見たいので握りつぶす
set +e
aws ssm wait command-executed --command-id "${CMD_ID}" --instance-id "${INSTANCE_ID}" 2>/dev/null
set -e

STATUS=$(aws ssm get-command-invocation \
  --command-id "${CMD_ID}" --instance-id "${INSTANCE_ID}" \
  --query Status --output text)

echo "----- stdout ---------------------------------------------------------"
aws ssm get-command-invocation \
  --command-id "${CMD_ID}" --instance-id "${INSTANCE_ID}" \
  --query StandardOutputContent --output text

ERR=$(aws ssm get-command-invocation \
  --command-id "${CMD_ID}" --instance-id "${INSTANCE_ID}" \
  --query StandardErrorContent --output text)
if [ -n "${ERR}" ] && [ "${ERR}" != "None" ]; then
  echo "----- stderr -------------------------------------------------------"
  printf '%s\n' "${ERR}"
fi
echo "----------------------------------------------------------------------"
echo "==> 結果: ${STATUS}"
echo "    ※ SSMが返す出力は24,000文字で打ち切られる。全文はインスタンス側の"
echo "      /var/log/tsubasa-deploy.log を見ること"

[ "${STATUS}" = "Success" ]
