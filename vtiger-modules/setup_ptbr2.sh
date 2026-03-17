#!/bin/sh
# Cria pt_br a partir de en_us em qualquer arquivo de linguagem dos módulos
count=0

# Padrão 1: modules/X/language/en_us.lang.php -> pt_br.lang.php
for f in $(find /var/www/html/modules -name "en_us.lang.php" 2>/dev/null); do
  ptbr=$(echo "$f" | sed 's/en_us\.lang\.php/pt_br.lang.php/')
  if [ ! -f "$ptbr" ]; then
    cp "$f" "$ptbr"
    count=$((count+1))
  fi
done

# Padrão 2: modules/Settings/X/language/en_us.php -> pt_br.php
for f in $(find /var/www/html/modules/Settings -name "en_us.php" 2>/dev/null); do
  ptbr=$(echo "$f" | sed 's/en_us\.php/pt_br.php/')
  if [ ! -f "$ptbr" ]; then
    cp "$f" "$ptbr"
    count=$((count+1))
  fi
done

# Padrão 3: layouts/v7/modules/Settings/X/language/en_us.php -> pt_br.php
for f in $(find /var/www/html/layouts -name "en_us.php" 2>/dev/null); do
  ptbr=$(echo "$f" | sed 's/en_us\.php/pt_br.php/')
  if [ ! -f "$ptbr" ]; then
    cp "$f" "$ptbr"
    count=$((count+1))
  fi
done

echo "Arquivos pt_br criados: $count"
