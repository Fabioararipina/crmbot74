#!/bin/sh
# Preenche pt_br com chaves en_us faltantes como fallback
EN=/var/www/html/languages/en_us
PT=/var/www/html/languages/pt_br
TOTAL=0

for enfile in $EN/*.php; do
    base=$(basename "$enfile")
    ptfile="$PT/$base"
    [ ! -f "$ptfile" ] && continue

    # Extrai chaves do en_us: linhas com 'KEY' =>
    grep -oP "^\s*'\K[^']+(?='\s*=>)" "$enfile" | sort > /tmp/en_keys_$$.txt
    # Extrai chaves do pt_br
    grep -oP "^\s*'\K[^']+(?='\s*=>)" "$ptfile" | sort > /tmp/pt_keys_$$.txt

    # Chaves em en_us que não estão em pt_br
    missing=$(comm -23 /tmp/en_keys_$$.txt /tmp/pt_keys_$$.txt | wc -l)

    if [ "$missing" -gt 0 ]; then
        echo "  $base: +$missing chaves"
        # Para cada chave faltante, pega o valor completo do en_us e adiciona ao pt_br
        comm -23 /tmp/en_keys_$$.txt /tmp/pt_keys_$$.txt | while read key; do
            # Pega a linha completa do en_us para aquela chave (primeira ocorrência)
            line=$(grep -m1 "'\''${key}'\''" "$enfile" | sed "s/'${key}'/'\/*PTBR*\/${key}'/")
            # Se não encontrou com escape, tenta direto
            if [ -z "$line" ]; then
                line=$(grep -m1 "\"${key}\"" "$enfile")
            fi
            [ -n "$line" ] && echo "    $line" >> "$ptfile"
        done
        TOTAL=$((TOTAL + missing))
    fi

    rm -f /tmp/en_keys_$$.txt /tmp/pt_keys_$$.txt
done

echo "Total: $TOTAL chaves adicionadas"
