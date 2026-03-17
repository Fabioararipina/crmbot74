#!/bin/sh
errors=0
for f in /var/www/html/languages/pt_br/*.php; do
  result=$(php -l "$f" 2>&1)
  if echo "$result" | grep -q "Parse error"; then
    echo "ERRO: $f"
    echo "$result" | grep "line "
    errors=$((errors+1))
  fi
done
echo "Total arquivos com erro: $errors"
