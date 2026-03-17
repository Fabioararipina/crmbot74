#!/bin/sh
# Cria arquivos pt_br.lang.php copiando de en_us para cada modulo
count=0
for f in $(find /var/www/html/modules -name "en_us.lang.php"); do
  ptbr=$(echo "$f" | sed 's/en_us\.lang\.php/pt_br.lang.php/')
  if [ ! -f "$ptbr" ]; then
    cp "$f" "$ptbr"
    count=$((count+1))
  fi
done
echo "pt_br.lang.php criados: $count"
# Verifica o total
total=$(find /var/www/html/modules -name "pt_br.lang.php" | wc -l)
echo "Total pt_br.lang.php: $total"
