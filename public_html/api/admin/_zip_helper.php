<?php
// Función compartida para extraer imágenes y texto de un ZipArchive.
// Compatible local/prod: resuelve la carpeta de uploads según el entorno.

function zip_uploads_dir(): string {
    // Producción (ferozo): ruta absoluta conocida
    $prod = '/home/a0110381/public_html/uploads/properties/';
    if (is_dir($prod)) { return $prod; }
    // Local / fallback: <raíz>/uploads/properties/ relativo a api/admin/
    $dir = dirname(__DIR__, 2) . '/uploads/properties/';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}

function zip_public_base(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!$host || php_sapi_name() === 'cli') { return 'https://propiedadesmar.cl'; }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    return ($https ? 'https' : 'http') . '://' . $host;
}

function extract_zip_contents(ZipArchive $zip, string $sourceName): array {
    $uploadDir = zip_uploads_dir();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $images      = [];
    $description = '';
    $allowed     = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!$name) continue;

        $base = basename($name);
        if ($base === '' || $base[0] === '.' || strpos($name, '__MACOSX') !== false) continue;

        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));

        // ── Imagen ─────────────────────────────────────────────
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $data = $zip->getFromIndex($i);
            if ($data === false || strlen($data) === 0) continue;

            $tmp = tempnam(sys_get_temp_dir(), 'zimg');
            file_put_contents($tmp, $data);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            unlink($tmp);

            if (!in_array($mime, $allowed, true)) continue;

            $extReal  = ($mime === 'image/jpeg') ? 'jpg' : explode('/', $mime)[1];
            $filename = time() . '_' . bin2hex(random_bytes(4)) . '_' . $i . '.' . $extReal;
            $destPath = $uploadDir . $filename;

            if (file_put_contents($destPath, $data) !== false) {
                chmod($destPath, 0644);
                $images[] = [
                    'url'      => zip_public_base() . '/uploads/properties/' . $filename,
                    'filename' => $base,
                ];
            }
        }

        // ── Texto ──────────────────────────────────────────────
        if (in_array($ext, ['txt', 'rtf', 'json', 'csv'], true) && $description === '') {
            $raw = $zip->getFromIndex($i);
            if ($raw === false) continue;
            if ($ext === 'rtf') {
                $raw = preg_replace('/\{[^{}]*\}/', '', $raw);
                $raw = preg_replace('/\\\\par\b/', "\n", $raw);
                $raw = preg_replace('/\\\\[a-z]+\d*\s?/', '', $raw);
                $raw = str_replace(['\\{', '\\}'], ['{', '}'], $raw);
            }
            $description = trim($raw);
        }
    }

    // Ordenar por nombre natural
    usort($images, fn($a, $b) => strnatcasecmp($a['filename'], $b['filename']));

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-',
        iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $sourceName)
    ), '-'));

    $fields = parse_text_fields($description);

    return [
        'images'      => $images,
        'description' => $description,
        'slug'        => $slug ?: 'propiedad',
        'fields'      => $fields,
    ];
}

// ── Parser de campos desde texto estilo "datos.txt" / RTF ────────────────
function parse_price(string $priceStr): array {
    if (preg_match('/UF\s*([\d.,]+)/i', $priceStr, $m)) {
        $numStr = $m[1];
        if (preg_match('/\d+\.\d+,\d+$/', $numStr)) {
            $numStr = str_replace('.', '', $numStr);
            $numStr = str_replace(',', '.', $numStr);
        } elseif (preg_match('/^\d+,\d+$/', $numStr)) {
            $numStr = str_replace(',', '.', $numStr);
        } elseif (!preg_match('/^\d+\.\d{1,2}$/', $numStr)) {
            $numStr = str_replace([',', '.'], '', $numStr);
        }
        return ['price' => (float) $numStr, 'price_type' => 'UF'];
    }
    if (preg_match('/\$\s*([\d.,]+)/', $priceStr, $m)) {
        $numStr = str_replace(['.', ','], '', $m[1]);
        return ['price' => (float) $numStr, 'price_type' => 'CLP'];
    }
    return ['price' => 0, 'price_type' => 'CLP'];
}

function parse_text_fields(string $text): array {
    $fields = [];
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^Precio:\s*(.+)$/i', $line, $m)) {
            $d = parse_price(trim($m[1]));
            $fields['price'] = $d['price'];
            $fields['price_type'] = $d['price_type'];
        } elseif (preg_match('/^Título:\s*(.+)$/i', $line, $m)) {
            $fields['title'] = trim($m[1]);
        } elseif (preg_match('/^Ubicación:\s*(.+)$/i', $line, $m)) {
            $fields['city'] = trim($m[1]);
        } elseif (preg_match('/^Habitaciones:\s*(\d+)/i', $line, $m)) {
            $fields['bedrooms'] = (int) $m[1];
        } elseif (preg_match('/^Baños:\s*(\d+)/i', $line, $m)) {
            $fields['bathrooms'] = (int) $m[1];
        } elseif (preg_match('/^Estacionamientos:\s*(\d+)/i', $line, $m)) {
            $fields['estacionamientos'] = (int) $m[1];
        } elseif (preg_match('/^Gastos comunes:\s*\$?\s*([\d.,]+)/i', $line, $m)) {
            $fields['gastos_comunes'] = (float) str_replace(['.', ','], '', $m[1]);
        } elseif (preg_match('/^Piso:\s*(\d+)/i', $line, $m)) {
            $fields['numero_piso'] = (int) $m[1];
        } elseif (preg_match('/^Año construcción:\s*(\d+)/i', $line, $m)) {
            $fields['antiguedad'] = max(0, (int) date('Y') - (int) $m[1]);
        } elseif (preg_match('/^Código:\s*(.+)$/i', $line, $m)) {
            $fields['codigo_aviso'] = trim($m[1]);
        } elseif (preg_match('/^Tipo de propiedad:\s*(.+)$/i', $line, $m)) {
            $fields['property_type'] = trim($m[1]);
        } elseif (preg_match('/^Tipo de publicación:\s*(.+)$/i', $line, $m)) {
            $fields['operation_type'] = trim($m[1]);
        } elseif (preg_match('/^Superficie Útil:\s*(\d+)/i', $line, $m)) {
            $fields['superficie_util'] = (int) $m[1];
        }
    }

    // Descripción: bloque entre "DESCRIPCIÓN" y "CONTACTO"
    $descStart = strpos($text, 'DESCRIPCIÓN');
    if ($descStart === false) { $descStart = stripos($text, 'DESCRIPCION'); }
    if ($descStart !== false) {
        $desc = substr($text, $descStart + strlen('DESCRIPCIÓN'));
        $contactPos = stripos($desc, 'CONTACTO');
        if ($contactPos !== false) { $desc = substr($desc, 0, $contactPos); }
        $fields['description'] = trim($desc);
    }

    return $fields;
}
