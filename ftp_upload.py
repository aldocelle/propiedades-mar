#!/usr/bin/env python3
"""
Script para subir archivos al servidor FTP de PropiedadesMar
Uso: python3 ftp_upload.py
"""

from ftplib import FTP
import os
import sys

# Configuración FTP
FTP_HOST = 'a0110381.ferozo.com'
FTP_USER = 'ftp@a0110381.ferozo.com'
FTP_PASS = '2gUF/aa@bCsk1iX'

# Archivos a subir
ARCHIVOS = [
    {
        'local': '/Users/aldocelle/Documents/aldocelleweb/WEBSITES/PropiedadesMar recovery/public_html/api/fix-db.php',
        'remoto': '/public_html/api/fix-db.php'
    }
]

def subir_archivo(ftp, ruta_local, ruta_remota):
    """Sube un archivo al servidor FTP"""
    try:
        with open(ruta_local, 'rb') as f:
            ftp.storbinary(f'STOR {ruta_remota}', f)
        print(f'✓ Subido: {ruta_local} -> {ruta_remota}')
        return True
    except Exception as e:
        print(f'✗ Error subiendo {ruta_local}: {e}')
        return False

def main():
    print(f'Conectando a {FTP_HOST}...')
    
    try:
        ftp = FTP(FTP_HOST, timeout=30)
        ftp.login(FTP_USER, FTP_PASS)
        print(f'Conectado. Bienvenido: {ftp.getwelcome()}')
        
        # Listar directorio actual
        print('\nDirectorio actual:')
        ftp.retrlines('LIST')
        
        # Subir archivos
        print('\nSubiendo archivos...')
        for archivo in ARCHIVOS:
            subir_archivo(ftp, archivo['local'], archivo['remoto'])
        
        # Verificar archivos subidos
        print('\nVerificando archivos en public_html/api/:')
        ftp.cwd('/public_html/api/')
        ftp.retrlines('LIST')
        
        ftp.quit()
        print('\n✓ Proceso completado')
        
    except Exception as e:
        print(f'✗ Error de conexión FTP: {e}')
        sys.exit(1)

if __name__ == '__main__':
    main()
