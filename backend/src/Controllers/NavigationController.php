<?php

declare(strict_types=1);

namespace Aigen\Controllers;

use Aigen\Core\Database;
use Aigen\Core\FeatureFlag;
use Aigen\Core\Request;
use Aigen\Core\Response;
use Aigen\Core\Settings;

/**
 * Sidebar dan konfigurasi tampilan.
 *
 * Sidebar dibangun dari tabel nav_menu, bukan array di React. Menambah menu
 * cukup lewat baris database — inilah wujud nyata prinsip NO HARDCODE.
 */
final class NavigationController
{
    public function index(Request $request): void
    {
        $pdo = Database::connection();

        $menus = $pdo->query(
            'SELECT id, menu_key, label, icon, route, status, sort_order
               FROM nav_menu
              WHERE is_visible = 1
              ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        // Detail "coming soon" diambil sekaligus, supaya frontend tidak perlu
        // memanggil endpoint kedua saat menu berstatus coming_soon diklik.
        $comingSoon = [];
        foreach ($pdo->query(
            'SELECT nav_menu_id, id, title, description, progress_percent, eta_label
               FROM coming_soon_items'
        )->fetchAll() as $row) {
            if ($row['nav_menu_id'] !== null) {
                $comingSoon[(int) $row['nav_menu_id']] = [
                    'id'               => (int) $row['id'],
                    'title'            => $row['title'],
                    'description'      => $row['description'],
                    'progress_percent' => (int) $row['progress_percent'],
                    'eta_label'        => $row['eta_label'],
                ];
            }
        }

        $items = [];
        foreach ($menus as $menu) {
            $id = (int) $menu['id'];

            $items[] = [
                'id'          => $id,
                'menu_key'    => $menu['menu_key'],
                'label'       => $menu['label'],
                'icon'        => $menu['icon'],
                'route'       => $menu['route'],
                'status'      => $menu['status'],
                'sort_order'  => (int) $menu['sort_order'],
                'coming_soon' => $comingSoon[$id] ?? null,
            ];
        }

        Response::success(['menus' => $items]);
    }

    /**
     * Konfigurasi publik untuk frontend: branding, tema, flag.
     * Endpoint ini boleh diakses tanpa login karena halaman login pun
     * membutuhkan nama aplikasi dan tema.
     */
    public function config(Request $request): void
    {
        $themes = Database::connection()->query(
            'SELECT preset_key, name, primary_color, accent_color, background_color,
                    card_color, background_mode, radius, is_default
               FROM theme_presets
              ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        foreach ($themes as &$theme) {
            $theme['is_default'] = (bool) (int) $theme['is_default'];
        }
        unset($theme);

        Response::success([
            'branding' => [
                'site_name'    => Settings::string('site_name', 'AIGen'),
                'site_tagline' => Settings::string('site_tagline', ''),
                'logo_url'     => Settings::string('site_logo_url', ''),
                'favicon_url'  => Settings::string('site_favicon_url', ''),
                'support_email'=> Settings::string('support_email', ''),
            ],
            'legal' => [
                'disclaimer' => Settings::string('disclaimer_text', ''),
                'terms_url'  => Settings::string('terms_url', ''),
                'privacy_url'=> Settings::string('privacy_url', ''),
            ],
            'auth' => [
                'allow_registration' => Settings::bool('allow_registration', true),
            ],
            'themes'   => $themes,
            'features' => FeatureFlag::all(),
        ]);
    }
}
