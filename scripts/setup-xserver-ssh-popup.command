#!/bin/zsh
set -euo pipefail

KEY_FILE="$HOME/.ssh/highlander_xserver"
CONFIG_FILE="$HOME/.ssh/config"
HOST_ALIAS="highlander-xserver"

dialog() {
  osascript -e "display dialog \"$1\" buttons {\"OK\"} default button \"OK\" with title \"Highlander SSH Setup\"" >/dev/null
}

confirm() {
  osascript -e "button returned of (display dialog \"$1\" buttons {\"キャンセル\", \"OK\"} default button \"OK\" cancel button \"キャンセル\" with title \"Highlander SSH Setup\")"
}

prompt() {
  osascript -e "text returned of (display dialog \"$1\" default answer \"$2\" buttons {\"キャンセル\", \"OK\"} default button \"OK\" cancel button \"キャンセル\" with title \"Highlander SSH Setup\")"
}

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

dialog "Xserverの秘密鍵をコピーしてからOKを押してください。\\n\\n-----BEGIN ... PRIVATE KEY----- から\\n-----END ... PRIVATE KEY----- まで、まるごとコピーしてください。\\n\\nこの鍵はWebサイトやGitには入れず、このMacの ~/.ssh にだけ保存します。"

KEY_TEXT="$(pbpaste)"

if [[ -z "$KEY_TEXT" ]]; then
  dialog "クリップボードが空でした。秘密鍵をコピーしてから、もう一度このファイルを開いてください。"
  exit 1
fi

if ! printf '%s\n' "$KEY_TEXT" | grep -q '^-----BEGIN .*PRIVATE KEY-----$'; then
  dialog "秘密鍵の開始行が見つかりませんでした。\\n\\n-----BEGIN ... PRIVATE KEY----- からコピーできているか確認してください。"
  exit 1
fi

if ! printf '%s\n' "$KEY_TEXT" | grep -q '^-----END .*PRIVATE KEY-----$'; then
  dialog "秘密鍵の終了行が見つかりませんでした。\\n\\n-----END ... PRIVATE KEY----- までコピーできているか確認してください。"
  exit 1
fi

if [[ -f "$KEY_FILE" ]]; then
  if ! confirm "すでに SSH 秘密鍵があります。\\n\\n$KEY_FILE\\n\\n上書きしますか？" >/dev/null; then
    dialog "キャンセルしました。既存の秘密鍵は変更していません。"
    exit 0
  fi
fi

TMP_FILE="$(mktemp)"
trap 'rm -f "$TMP_FILE"' EXIT
printf '%s\n' "$KEY_TEXT" > "$TMP_FILE"
install -m 600 "$TMP_FILE" "$KEY_FILE"
chmod 600 "$KEY_FILE"

printf '' | pbcopy

if confirm "秘密鍵を保存しました。\\n\\n$KEY_FILE\\n\\n続けて ssh highlander-xserver で接続できるショートカットも作りますか？" >/dev/null; then
  SSH_HOST="$(prompt "XserverのSSHホスト名を入力してください。\\n例: sv00000.xserver.jp" "")"
  SSH_USER="$(prompt "SSHユーザー名を入力してください。" "")"
  SSH_PORT="$(prompt "SSHポートを入力してください。" "10022")"
  SSH_PORT="${SSH_PORT:-10022}"

  if [[ -z "$SSH_HOST" || -z "$SSH_USER" ]]; then
    dialog "ホスト名またはユーザー名が空だったため、ショートカット作成はスキップしました。\\n秘密鍵の保存は完了しています。"
    exit 0
  fi

  touch "$CONFIG_FILE"
  chmod 600 "$CONFIG_FILE"

  if grep -q "^Host $HOST_ALIAS$" "$CONFIG_FILE"; then
    dialog "SSH設定にはすでに Host $HOST_ALIAS があります。\\n必要なら ~/.ssh/config を手動で確認してください。"
  else
    {
      echo
      echo "Host $HOST_ALIAS"
      echo "  HostName $SSH_HOST"
      echo "  User $SSH_USER"
      echo "  Port $SSH_PORT"
      echo "  IdentityFile $KEY_FILE"
      echo "  IdentitiesOnly yes"
    } >> "$CONFIG_FILE"

    dialog "SSHショートカットを作成しました。\\n\\n接続コマンド:\\nssh $HOST_ALIAS"
  fi
else
  dialog "秘密鍵の保存だけ完了しました。\\n\\nあとで接続情報が分かったら、SSHショートカットを追加できます。"
fi
