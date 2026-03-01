
echo "=============================="
echo "Fecha: $(date)"
echo "=============================="

echo "[1] Estado Cowrie:"
pgrep -f cowrie >/dev/null && echo "Cowrie activo" || echo "Cowrie NO activo"

echo "[2] Estado Wazuh Manager:"
systemctl is-active wazuh-manager

echo "[3] Estado SSH real (2222):"
ss -tulnp | grep 2222

echo "[4] Espacio en disco:"
df -h /

echo "[5] Última línea cowrie.json:"
tail -n 1 /opt/cowrie/var/log/cowrie/cowrie.json 2>/dev/null

echo ""
