#!/usr/bin/env python3
"""
Script para subir el archivo de restauración al servidor FTP.
Ejecutar: python3 subir_fix.py
"""

from ftplib import FTP
import os

# Credenciales FTP
FTP_HOST = 'a0110381.ferozo.com'
FTP_USER = 'ftp@a0110381.ferozo.com'
FTP_PASS = '2gUF/aa@bCsk1iX'

# Archivo a subir
ARCHIVO_LOCAL = os.path.join(os.path.dirname(__file__), 'public_html', 'api', 'restore-from-github.php')
ARCHIVO_REMOTO = '/public_html/api/restore-from-github.php'

def main():
    print('=== Subiendo archivo de restauración ===')
    print(f'Archivo local: {ARCHIVO_LOCAL}')
    
    if not os.path.exists(ARCHIVO_LOCAL):
        print('ERROR: No se encuentra el archivo local')
        return
    
    try:
        print(f'\nConectando a {FTP_HOST}...')
        ftp = FTP(FTP_HOST, timeout=30)
        ftp.login(FTP_USER, FTP_PASS)
        print('Conectado exitosamente!')
        
        # Listar directorio
        print('\nDirectorio actual:')
        ftp.retrlines('LIST')
        
        # Subir archivo
        print(f'\nSubiendo a {ARCHIVO_REMOTO}...')
        with open(ARCHIVO_LOCAL, 'rb') as f:
            ftp.storbinary(f'STOR {ARCHIVO_REMOTO}', f)
        
        print('Archivo subido exitosamente!')
        
        # Verificar
        print('\nArchivos en public_html/api/:')
        ftp.cwd('/public_html/api/')
        ftp.retrlines('LIST')
        
        ftp.quit()
        
        print('\n=== COMPLETADO ===')
        print('Ahora accede a:')
        print('https://propiedadesmar.cl/api/restore-from-github.php?token=restore2026')
        
    except Exception as e:
        print(f'ERROR: {e}')

if __name__ == '__main__':
    main()
