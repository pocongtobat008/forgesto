<?php

/**
 * StockForge - Global helper functions.
 */

use App\Core\Auth;
use App\Core\Session;

if (! function_exists('config')) {
    /**
     * Read a config value with dot notation. config('app.name', 'fallback').
     */
    function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $GLOBALS['__config'] ?? [];

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }
}

if (! function_exists('e')) {
    /** Escape a value for safe HTML output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('url')) {
    /**
     * Build an absolute-facing URL relative to the app base path.
     * Works both with clean rewrites and the ?r= fallback.
     */
    function url(string $path = ''): string
    {
        $base = App\Core\Request::basePath();
        $path = ltrim($path, '/');

        if ($base === '') {
            return $path === '' ? '/' : '/' . $path;
        }

        return $path === '' ? $base : $base . '/' . $path;
    }
}

if (! function_exists('asset')) {
    /** Build an asset URL (public/assets/...). */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (! function_exists('asset_v')) {
    /**
     * Cache-busted asset URL (public/assets/...?v=<mtime>).
     * The version tag changes whenever the file changes, so browsers and the
     * Cloudflare edge always fetch the fresh file after a deploy.
     */
    function asset_v(string $path): string
    {
        $rel  = 'assets/' . ltrim($path, '/');
        $root = dirname(__DIR__, 2); // app/Helpers -> project root
        $full = $root . '/public/' . $rel;
        $v    = is_file($full) ? (string) filemtime($full) : (string) time();

        return url($rel) . '?v=' . $v;
    }
}

if (! function_exists('redirect')) {
    /** Redirect to an in-app path and stop execution. */
    function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}

if (! function_exists('old')) {
    /** Fetch a previously submitted value (after a validation redirect). */
    function old(string $key, mixed $default = ''): mixed
    {
        $input = Session::get('_old', []);
        return $input[$key] ?? $default;
    }
}

if (! function_exists('errors')) {
    /** Fetch validation errors keyed by field. */
    function errors(?string $key = null): mixed
    {
        $errors = Session::get('_errors', []);

        return $key === null ? $errors : ($errors[$key] ?? null);
    }
}

if (! function_exists('flash')) {
    /** Queue a flash message. type = success|error|info|warning */
    function flash(string $type, string $message): void
    {
        $messages = Session::get('_flash', []);
        $messages[] = ['type' => $type, 'message' => $message];
        Session::put('_flash', $messages);
    }
}

if (! function_exists('consume_flash')) {
    /** Pull and clear queued flash messages. */
    function consume_flash(): array
    {
        $messages = Session::get('_flash', []);
        Session::forget('_flash');

        return is_array($messages) ? $messages : [];
    }
}

if (! function_exists('csrf_token')) {
    /** Current CSRF token, generated on first use. */
    function csrf_token(): string
    {
        $key = (string) config('app.csrf_token_key', '_csrf');
        $token = Session::get($key);

        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put($key, $token);
        }

        return $token;
    }
}

if (! function_exists('csrf_field')) {
    /** Hidden input carrying the CSRF token. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">'
            . '<input type="hidden" name="_idem" value="' . e(\App\Core\IdempotencyGuard::token()) . '">';
    }
}

if (! function_exists('csrf_valid')) {
    /** Validate an incoming CSRF token. */
    function csrf_valid(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $expected = Session::get((string) config('app.csrf_token_key', '_csrf'));

        return is_string($expected) && hash_equals($expected, $token);
    }
}

if (! function_exists('auth')) {
    /** The currently active user (array) or null. */
    function auth(): ?array
    {
        return Auth::user();
    }
}

if (! function_exists('can')) {
    /** Ability check for the active user. */
    function can(string $ability): bool
    {
        return Auth::can($ability);
    }
}

if (! function_exists('can_action')) {
    /** Cek izin aksi per menu untuk role/user aktif (view, add, edit, delete, post, pic_post). */
    function can_action(string $menuKey, string $action, ?string $role = null): bool
    {
        return \App\Models\UserRule::canAction($menuKey, $action, $role);
    }
}

if (! function_exists('can_view_menu')) {
    /** Cek apakah role/user aktif diizinkan melihat / mengakses menu tertentu. */
    function can_view_menu(string $menuKey, ?string $role = null): bool
    {
        return \App\Models\UserRule::canView($menuKey, $role);
    }
}

if (! function_exists('is_col_hidden')) {
    /** Cek apakah kolom tabel disembunyikan untuk role/user aktif pada menu tertentu. */
    function is_col_hidden(string $menuKey, string $columnKey, ?string $role = null): bool
    {
        return \App\Models\UserRule::isColumnHidden($menuKey, $columnKey, $role);
    }
}

if (! function_exists('can_see_column')) {
    /** Cek apakah kolom tabel boleh dilihat oleh role/user aktif pada menu tertentu. */
    function can_see_column(string $menuKey, string $columnKey, ?string $role = null): bool
    {
        return ! \App\Models\UserRule::isColumnHidden($menuKey, $columnKey, $role);
    }
}

if (! function_exists('param')) {
    /** A route parameter from the cache (route params passed to controllers). */
    function param(array $params, string $key, mixed $default = null): mixed
    {
        return $params[$key] ?? $default;
    }
}

if (! function_exists('active')) {
    /** Return 'is-active' when the current path starts with the given prefix. */
    function active(string $prefix): string
    {
        $path = '/' . trim(App\Core\Request::path(), '/');
        $prefix = '/' . trim($prefix, '/');

        return str_starts_with($path, $prefix) ? 'is-active' : '';
    }
}

if (! function_exists('format_date')) {
    function format_date(?string $value, string $format = 'd M Y H:i'): string
    {
        if (empty($value)) {
            return '-';
        }

        $ts = strtotime($value);

        return $ts ? date($format, $ts) : '-';
    }
}

if (! function_exists('percent')) {
    function percent(int|float $part, int|float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}

if (! function_exists('role_label')) {
    function role_label(?string $role): string
    {
        $roles = (array) config('app.roles', []);

        return $roles[$role] ?? ucfirst((string) $role);
    }
}

if (! function_exists('query')) {
    /**
     * Merge query string params, preserving the current filter state.
     */
    function query(array $overrides = []): string
    {
        $params = array_merge($_GET, $overrides);
        unset($params['r'], $params['_token']);

        return http_build_query($params);
    }
}

if (! function_exists('format_rupiah')) {
    function format_rupiah(int|float $amount, bool $withSign = false): string
    {
        $sign = '';
        if ($withSign && $amount > 0) {
            $sign = '+';
        } elseif ($amount < 0) {
            $sign = '-';
            $amount = abs($amount);
        }
        return $sign . 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}

if (! function_exists('can_see_stock_qty')) {
    function can_see_stock_qty(string $menuKey = 'items'): bool
    {
        if (\App\Core\Auth::role() === 'poweradmin') {
            return true;
        }
        if (class_exists(\App\Models\UserRule::class)) {
            return ! \App\Models\UserRule::isColumnHidden($menuKey, 'expected');
        }
        return in_array(\App\Core\Auth::role(), ['poweradmin', 'adminsto'], true);
    }
}

if (! function_exists('format_rupiah_compact')) {
    /** Ringkas untuk KPI: 1.250.000.000 -> "Rp 1,25 M"; sisanya tetap penuh. */
    function format_rupiah_compact(int|float $amount, bool $withSign = false): string
    {
        $sign = '';
        if ($withSign && $amount > 0) { $sign = '+'; }
        elseif ($amount < 0) { $sign = '-'; $amount = abs($amount); }
        $a = (float) $amount;
        if (round($a / 1e9, 2) >= 1000) { $txt = number_format($a / 1e12, 2, ',', '.') . ' T'; }
        elseif ($a >= 1e9) { $txt = number_format($a / 1e9, 2, ',', '.') . ' M'; }
        elseif ($a >= 1e6) { $txt = number_format($a / 1e6, 1, ',', '.') . ' jt'; }
        else { $txt = number_format($a, 0, ',', '.'); }
        return $sign . 'Rp ' . $txt;
    }
}

if (! function_exists('list_show_all')) {
    /** Mode "Tampilkan semua" aktif bila ?all=1 */
    function list_show_all(): bool
    {
        return (($_GET['all'] ?? '') === '1');
    }
}
if (! function_exists('list_per_page')) {
    /** Jumlah baris per halaman; mode semua = 2000 (batas aman) */
    function list_per_page(int $default): int
    {
        return list_show_all() ? 2000 : $default;
    }
}
if (! function_exists('list_query')) {
    /** Semua parameter query kecuali page/all, untuk link pager & switch */
    function list_query(): array
    {
        $out = [];
        foreach ($_GET as $k => $v) {
            if ($k === 'page' || $k === 'all') continue;
            if ($v === '' || $v === null || $v === []) continue;
            $out[$k] = $v;
        }
        return $out;
    }
}

/* =====================================================================
   I18N (ID / EN)
   ===================================================================== */
if (! function_exists('current_locale')) {
    /** Locale aktif: ?lang= (disimpan di session & cookie) atau default app.locale. */
    function current_locale(): string
    {
        static $loc = null;
        if ($loc !== null) {
            return $loc;
        }
        $allowed = ['id', 'en'];

        $req = $_GET['lang'] ?? null;
        if (is_string($req) && in_array($req, $allowed, true)) {
            \App\Core\Session::put('locale', $req);
            @setcookie('sf_locale', $req, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
            $loc = $req;
            return $loc;
        }

        $sess = \App\Core\Session::get('locale');
        if (is_string($sess) && in_array($sess, $allowed, true)) {
            $loc = $sess;
            return $loc;
        }

        $cookie = $_COOKIE['sf_locale'] ?? null;
        if (is_string($cookie) && in_array($cookie, $allowed, true)) {
            $loc = $cookie;
            return $loc;
        }

        $def = (string) config('app.locale', 'id');
        $loc = in_array($def, $allowed, true) ? $def : 'id';
        return $loc;
    }
}

if (! function_exists('translations')) {
    /** Muat kamus locale (cache per request). */
    function translations(?string $locale = null): array
    {
        static $cache = [];
        $locale = $locale ?? current_locale();
        if (! isset($cache[$locale])) {
            $file = APP_PATH . '/Lang/' . $locale . '.php';
            $cache[$locale] = is_file($file) ? (array) require $file : [];
        }
        return $cache[$locale];
    }
}

if (! function_exists('__')) {
    /** Terjemahkan kunci. Fallback: kamus ID, lalu kunci itu sendiri. */
    function __(string $key, array $replace = []): string
    {
        $dict = translations();
        $text = $dict[$key] ?? null;
        if ($text === null) {
            $idDict = translations('id');
            $text = $idDict[$key] ?? $key;
        }
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, (string) $v, $text);
        }
        return (string) $text;
    }
}

if (! function_exists('locale_url')) {
    /** URL saat ini dengan ?lang= diganti (untuk tombol switch bahasa). */
    function locale_url(string $locale): string
    {
        $q = $_GET;
        $q['lang'] = $locale;
        $path = trim(\App\Core\Request::path(), '/');
        return url($path === '' ? 'dashboard' : $path) . '?' . http_build_query($q);
    }
}

if (! function_exists('current_menu_key')) {
    /**
     * Mendapatkan menu_key aktif berdasarkan URL path saat ini.
     * Menggunakan longest prefix match agar rute bertingkat (mis. items/memo) akurat.
     */
    function current_menu_key(): string
    {
        $path = trim(\App\Core\Request::path(), '/');
        if ($path === '' || $path === 'index.php') {
            return 'dashboard';
        }
        $allNavPaths = array_keys(\App\Models\UserRule::SYSTEM_MENUS);
        $best = '';
        foreach ($allNavPaths as $cand) {
            $candTrim = trim($cand, '/');
            $matches = ($path === $candTrim) || ($candTrim !== '' && str_starts_with($path, $candTrim . '/'));
            if ($matches && strlen($candTrim) > strlen($best)) {
                $best = $candTrim;
            }
        }
        if ($best !== '') {
            return $best;
        }
        $parts = explode('/', $path);
        return $parts[0] !== '' ? $parts[0] : 'dashboard';
    }
}

if (! function_exists('current_menu_name')) {
    /**
     * Nama menu saat ini untuk label header popup rule.
     */
    function current_menu_name(): string
    {
        $key = current_menu_key();
        if (isset(\App\Models\UserRule::SYSTEM_MENUS[$key])) {
            return \App\Models\UserRule::SYSTEM_MENUS[$key]['name'];
        }
        return ucwords(str_replace(['-', '_', '/'], ' ', $key));
    }
}
