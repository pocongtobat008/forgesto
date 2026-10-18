<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;

/**
 * Login memakai Nomor Karyawan saja (tanpa password).
 *
 * Alur: ID Karyawan → cari akun di tabel users (kolom username = employee_id)
 *       → cek is_active → buat sesi.
 */
class AuthController extends Controller
{
    /** Tampilkan form login. */
    public function showLogin(): string
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }

        return $this->view('auth/login', [
            'title' => __('Masuk'),
        ], 'layouts/blank');
    }

    /** Proses login (ID Karyawan saja). */
    public function login(): string
    {
        $this->verifyCsrf();

        $employeeId = strtoupper(trim(Request::string('employee_id')));

        $errors = [];
        if ($employeeId === '') {
            $errors['employee_id'] = 'Nomor Karyawan wajib diisi.';
        } elseif (preg_match('/^[A-Z0-9._-]{3,32}$/', $employeeId) !== 1) {
            $errors['employee_id'] = 'Format Nomor Karyawan tidak valid (contoh: WE100001).';
        }

        if ($errors !== []) {
            return $this->renderLogin($errors, $employeeId);
        }

        $userModel = new User();
        $user      = $userModel->findByUsername($employeeId);

        if ($user === null) {
            return $this->renderLogin(
                ['employee_id' => 'Nomor Karyawan ' . $employeeId . ' tidak terdaftar. Hubungi admin.'],
                $employeeId
            );
        }

        if ((int) $user['is_active'] !== 1) {
            return $this->renderLogin(
                ['employee_id' => 'Akun tidak aktif. Hubungi admin.'],
                $employeeId
            );
        }

        // Mulai sesi login.
        self::regenerateSession();
        Session::put('user_id', (int) $user['id']);
        Auth::reset();

        // Catat waktu login terakhir (best-effort, tidak menggagalkan login).
        try {
            $userModel->query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
        } catch (\Throwable) {
            // abaikan
        }

        flash('success', 'Selamat datang, ' . (string) $user['name'] . '.');

        redirect('/dashboard');
    }

    /** Logout dan hapus sesi. */
    public function logout(): never
    {
        $params = session_get_cookie_params();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie((string) session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        Auth::reset();

        redirect('/login');
    }

    /** Render ulang form login dengan error + input lama. */
    private function renderLogin(array $errors, string $employeeId): string
    {
        return $this->view('auth/login', [
            'title'  => __('Masuk'),
            'errors' => $errors,
            'old'    => ['employee_id' => $employeeId],
        ], 'layouts/blank');
    }

    /** Amankan sesi dari session fixation saat login. */
    private static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
