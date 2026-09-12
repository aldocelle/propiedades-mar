#!/usr/bin/env python3
"""
Script para subir el .htaccess corregido al servidor FTP de PropiedadesMar.
Ejecutar: python3 subir_htaccess.py
"""

from ftplib import FTP_TLS
import os
import tempfile

FTP_HOST = 'a0110381.ferozo.com'
FTP_USER = 'ftp@a0110381.ferozo.com'
FTP_PASS = '2gUF/aa@bCsk1iX'

ARCHIVO_LOCAL = os.path.join(os.path.dirname(__file__), 'public_html', '.htaccess')
ARCHIVO_REMOTO = '/public_html/.htaccess'


def main():
    print('=== Subiendo .htaccess corregido al hosting ===')
    print(f'Archivo local: {ARCHIVO_LOCAL}')

    if not os.path.exists(ARCHIVO_LOCAL):
        print('ERROR: No se encuentra el archivo local')
        return

    try:
                print(f'\nConectando a {FTP_HOST}...')
        ftp = FTP_TLS(FTP_HOST, timeout=30)
        ftp.auth()
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        print('Conectado exitosamente!')

        print(f'\nSubiendo a {ARCHIVO_REMOTO}...')
        with open(ARCHIVO_LOCAL, 'rb') as f:
            ftp.storbinary(f'STOR {ARCHIVO_REMOTO}', f)

        print('Archivo subido exitosamente!')

        print('\nVerificando CSP en .htaccess remoto:')
        with tempfile.NamedTemporaryFile(mode='w+b', delete=False, dir='/tmp') as tmp:
            tmp_path = tmp.name
            ftp.retrbinary(f'RETR {ARCHIVO_REMOTO}', tmp.write)

        with open(tmp_path, 'r') as f:
            for i, line in enumerate(f, 1):
                if 'Content-Security-Policy' in line:
                    print(f'Line {i}: {line.strip()}')

        os.unlink(tmp_path)
        ftp.quit()

        print('\n=== COMPLETADO ===')
        print('Verifica en: https://propiedadesmar.cl')
    except Exception as e:
        print(f'ERROR: {e}')


if __name__ == '__main__':
    main()
