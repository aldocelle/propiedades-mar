#!/usr/bin/env python3
"""
Script para subir la carpeta /subir/ (Vue uploader) al hosting Ferozo.
Ejecutar: python3 subir_uploader.py
"""

from ftplib import FTP_TLS
import os
import io

FTP_HOST = 'a0110381.ferozo.com'
FTP_USER = 'ftp@a0110381.ferozo.com'
FTP_PASS = 'b4tRH@P0NoWC2iC'

BASE_LOCAL = os.path.join(os.path.dirname(__file__), 'public_html', 'subir')
BASE_REMOTO = '/public_html/subir'
CHUNK_SIZE = 10000


def subir_chunked(ftp, local_path, remote_path):
    filename = os.path.basename(local_path)
    print(f'  Subiendo: {filename}')
    chunk_count = 0
    with open(local_path, 'rb') as f:
        while True:
            data = f.read(CHUNK_SIZE)
            if not data:
                break
            cmd = 'STOR ' + remote_path if chunk_count == 0 else 'APPE ' + remote_path
            ftp.storbinary(cmd, io.BytesIO(data))
            chunk_count += 1
    print(f'  OK {filename} ({os.path.getsize(local_path)} bytes, {chunk_count} chunks)')


def subir_archivo_unico(ftp, local_path, remote_path):
    filename = os.path.basename(local_path)
    print(f'  Subiendo: {filename}')
    with open(local_path, 'rb') as f:
        ftp.storbinary('STOR ' + remote_path, f)
    print(f'  OK {filename} ({os.path.getsize(local_path)} bytes)')


def main():
    print('=== Subiendo carpeta /subir/ al hosting ===')
    if not os.path.exists(BASE_LOCAL):
        print(f'ERROR: No existe {BASE_LOCAL}')
        return
    try:
        print(f'Conectando a {FTP_HOST}...')
        ftp = FTP_TLS(FTP_HOST, timeout=30)
        ftp.auth()
        ftp.login(FTP_USER, FTP_PASS)
        ftp.prot_p()
        print('Conectado exitosamente!')

        for root, dirs, files in os.walk(BASE_LOCAL):
            # Excluir carpetas que no son parte del código fuente
            dirs[:] = [d for d in dirs if d not in ('avisos', 'uploads', 'data')]
            for filename in files:
                # Excluir archivos ocultos (.DS_Store, .gitignore, etc.)
                if filename.startswith('.'):
                    continue
                local_file = os.path.join(root, filename)
                rel_path = os.path.relpath(local_file, BASE_LOCAL)
                remote_path = BASE_REMOTO + '/' + rel_path.replace(os.sep, '/')
                if os.path.getsize(local_file) > CHUNK_SIZE:
                    subir_chunked(ftp, local_file, remote_path)
                else:
                    subir_archivo_unico(ftp, local_file, remote_path)
        print('\n=== COMPLETADO ===')
        print('Verifica en: https://propiedadesmar.cl/subir/')
        ftp.quit()
    except Exception as e:
        print(f'ERROR: {e}')


if __name__ == '__main__':
    main()
