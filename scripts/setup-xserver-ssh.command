#!/bin/zsh
set -euo pipefail

KEY_FILE="$HOME/.ssh/highlander_xserver"
CONFIG_FILE="$HOME/.ssh/config"
HOST_ALIAS="highlander-xserver"

echo "Highlander Xserver SSH key setup"
echo
echo "This stores the private key only on this Mac:"
echo "  $KEY_FILE"
echo
echo "Paste the full private key, including:"
echo "  -----BEGIN ... PRIVATE KEY-----"
echo "  -----END ... PRIVATE KEY-----"
echo
echo "When the END line is pasted, saving will start automatically."
echo

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

if [[ -f "$KEY_FILE" ]]; then
  echo "A key already exists at $KEY_FILE"
  read "OVERWRITE?Overwrite it? Type YES to overwrite: " || OVERWRITE=""
  if [[ "$OVERWRITE" != "YES" ]]; then
    echo "Canceled. Existing key was not changed."
    exit 0
  fi
fi

TMP_FILE="$(mktemp)"
trap 'rm -f "$TMP_FILE"' EXIT

while IFS= read -r LINE; do
  echo "$LINE" >> "$TMP_FILE"
  if [[ "$LINE" == "-----END "*PRIVATE\ KEY"-----" ]]; then
    break
  fi
done

if ! grep -q '^-----BEGIN .*PRIVATE KEY-----$' "$TMP_FILE"; then
  echo "Error: BEGIN private key line was not found."
  exit 1
fi

if ! grep -q '^-----END .*PRIVATE KEY-----$' "$TMP_FILE"; then
  echo "Error: END private key line was not found."
  exit 1
fi

install -m 600 "$TMP_FILE" "$KEY_FILE"
chmod 600 "$KEY_FILE"

echo
echo "Saved private key:"
echo "  $KEY_FILE"
echo
echo "Optional: add an SSH shortcut now."
echo "You can skip by pressing Enter."
echo
read "SSH_HOST?Xserver host, for example sv00000.xserver.jp: " || SSH_HOST=""

if [[ -n "${SSH_HOST:-}" ]]; then
  read "SSH_USER?SSH user name: " || SSH_USER=""
  read "SSH_PORT?SSH port [10022]: " || SSH_PORT=""
  SSH_PORT="${SSH_PORT:-10022}"

  touch "$CONFIG_FILE"
  chmod 600 "$CONFIG_FILE"

  if grep -q "^Host $HOST_ALIAS$" "$CONFIG_FILE"; then
    echo
    echo "SSH config already has Host $HOST_ALIAS."
    echo "Please edit $CONFIG_FILE manually if you need to change it."
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

    echo
    echo "Added SSH shortcut:"
    echo "  ssh $HOST_ALIAS"
  fi
fi

echo
echo "Done. Keep this key private and do not upload it to the website or Git."
