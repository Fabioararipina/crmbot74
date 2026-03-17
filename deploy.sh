#!/usr/bin/env bash
# ============================================================
#  deploy.sh — Deploy via git pull no VPS
#  Uso LOCAL (Git Bash): bash deploy.sh
#  Ou no VPS diretamente: cd /opt/crmbot74 && bash deploy.sh
# ============================================================
set -e

echo ""
echo "🚀  Deploy crmbot74"
echo "=================================="
echo "  Rode este script diretamente no VPS:"
echo "    ssh root@<VPS_IP>"
echo "    cd /opt/crmbot74 && bash deploy.sh"
echo "=================================="

# ── A partir daqui roda NO VPS ────────────────────────────
CONECTOR="/opt/crmbot74/conector"

echo "📥  git pull origin main..."
cd /opt/crmbot74
git pull origin main

echo ""
echo "📦  npm install (conector)..."
cd "$CONECTOR"
npm install --production 2>&1 | tail -3

echo ""
echo "🔄  Reiniciando conector (PM2)..."
pm2 restart crmbot74-conector

echo ""
echo "⏳  Aguardando 4s..."
sleep 4

echo ""
echo "📋  Logs recentes:"
pm2 logs crmbot74-conector --lines 20 --nostream

echo ""
echo "🩺  Health check:"
STATUS=$(curl -s https://crm.bot74.com.br/health 2>/dev/null)
echo "    $STATUS"

echo ""
echo "✅  Deploy concluído!"
echo "=================================="
