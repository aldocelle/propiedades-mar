#!/bin/bash
# Script para subir el archivo de restauración y ejecutarlo
# Haz doble clic en este archivo o ejecuta: bash subir_y_restaurar.command

cd "$(dirname "$0")"

echo "=== Subiendo archivo de restauración por FTP ==="

# Usar curl para subir
curl -s --max-time 30 \
  -u 'ftp@a0110381.ferozo.com:2gUF/aa@bCsk1iX' \
  -T "public_html/api/restore-from-github.php" \
  "ftp://a0110381.ferozo.com/public_html/api/restore-from-github.php"

if [ $? -eq 0 ]; then
  echo ""
  echo "=== Archivo subido exitosamente ==="
  echo ""
  echo "Ahora abre en tu navegador:"
  echo "https://propiedadesmar.cl/api/restore-from-github.php?token=restore2026"
  echo ""
  read -p "Presiona Enter para salir..."
else
  echo ""
  echo "=== ERROR al subir el archivo ==="
  echo "Intenta usar FileZilla con estos datos:"
  echo "  Host: a0110381.ferozo.com"
  echo "  Usuario: ftp@a0110381.ferozo.com"
  echo "  Contraseña: 2gUF/aa@bCsk1iX"
  echo ""
  read -p "Presiona Enter para salir..."
fi
