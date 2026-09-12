<?php
// GET  /api/admin/settings
// POST /api/admin/settings
require_once dirname(dirname(__DIR__)) . '/_config.php';
cors_headers();
require_admin();

// ── GET ──────────────────────────────────────────────────────
if (method() === 'GET') {
    $row = db()->query('SELECT * FROM settings WHERE id = 1')->fetch();

    if (!$row) {
        send(200, default_settings());
    }

    send(200, [
        'siteName'           => $row['site_name'],
        'siteDescription'    => $row['site_description'],
        'siteUrl'            => $row['site_url'],
        'phone'              => $row['phone'],
        'email'              => $row['email'],
        'whatsapp'           => $row['whatsapp'],
        'mainRegion'         => $row['main_region'],
        'address'            => $row['address'],
        'showFeaturedFirst'  => (bool) $row['show_featured_first'],
        'enableComments'     => (bool) $row['enable_comments'],
        'propertiesPerPage'  => (int)  $row['properties_per_page'],
        'metaTitle'          => $row['meta_title'],
        'metaDescription'    => $row['meta_description'],
        'gaId'               => $row['ga_id'],
        'enableDebug'        => (bool) $row['enable_debug'],
        'maintenanceMode'    => (bool) $row['maintenance_mode'],
        'appVersion'         => $row['app_version'],
    ]);
}

// ── POST ─────────────────────────────────────────────────────
if (method() === 'POST') {
    $b = json_body();

    if (empty($b['siteName'])) { send(400, ['error' => 'siteName es requerido.']); }

    db()->prepare(
        "INSERT INTO settings (id, site_name, site_description, site_url, phone, email, whatsapp,
            main_region, address, show_featured_first, enable_comments, properties_per_page,
            meta_title, meta_description, ga_id, enable_debug, maintenance_mode, app_version)
         VALUES (1, :site_name, :site_description, :site_url, :phone, :email, :whatsapp,
            :main_region, :address, :show_featured_first, :enable_comments, :properties_per_page,
            :meta_title, :meta_description, :ga_id, :enable_debug, :maintenance_mode, :app_version)
         ON CONFLICT(id) DO UPDATE SET
            site_name           = excluded.site_name,
            site_description    = excluded.site_description,
            site_url            = excluded.site_url,
            phone               = excluded.phone,
            email               = excluded.email,
            whatsapp            = excluded.whatsapp,
            main_region         = excluded.main_region,
            address             = excluded.address,
            show_featured_first = excluded.show_featured_first,
            enable_comments     = excluded.enable_comments,
            properties_per_page = excluded.properties_per_page,
            meta_title          = excluded.meta_title,
            meta_description    = excluded.meta_description,
            ga_id               = excluded.ga_id,
            enable_debug        = excluded.enable_debug,
            maintenance_mode    = excluded.maintenance_mode,
            app_version         = excluded.app_version,
            updated_at          = datetime('now')"
    )->execute([
        ':site_name'           => $b['siteName']          ?? 'Propiedades Mar',
        ':site_description'    => $b['siteDescription']   ?? null,
        ':site_url'            => $b['siteUrl']           ?? null,
        ':phone'               => $b['phone']             ?? null,
        ':email'               => $b['email']             ?? null,
        ':whatsapp'            => $b['whatsapp']          ?? null,
        ':main_region'         => $b['mainRegion']        ?? 'Valparaíso',
        ':address'             => $b['address']           ?? null,
        ':show_featured_first' => (int) ($b['showFeaturedFirst'] ?? 1),
        ':enable_comments'     => (int) ($b['enableComments']   ?? 0),
        ':properties_per_page' => (int) ($b['propertiesPerPage'] ?? 12),
        ':meta_title'          => $b['metaTitle']         ?? null,
        ':meta_description'    => $b['metaDescription']   ?? null,
        ':ga_id'               => $b['gaId']              ?? null,
        ':enable_debug'        => (int) ($b['enableDebug']      ?? 0),
        ':maintenance_mode'    => (int) ($b['maintenanceMode']  ?? 0),
        ':app_version'         => $b['appVersion']        ?? '2.0.0',
    ]);

    send(200, ['success' => true, 'message' => 'Settings guardados.']);
}

send(405, ['error' => 'Método no permitido.']);

function default_settings(): array {
    return [
        'siteName' => 'Propiedades Mar', 'siteDescription' => '', 'siteUrl' => '',
        'phone' => '', 'email' => '', 'whatsapp' => '', 'mainRegion' => 'Valparaíso',
        'address' => '', 'showFeaturedFirst' => true, 'enableComments' => false,
        'propertiesPerPage' => 12, 'metaTitle' => '', 'metaDescription' => '',
        'gaId' => '', 'enableDebug' => false, 'maintenanceMode' => false, 'appVersion' => '2.0.0',
    ];
}
