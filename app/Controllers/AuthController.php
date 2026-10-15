<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;

/**
 * Login memakai ID Karyawan (WExxxxxx) + password.
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

    /** Proses login. */
    public function login(): string
    {
        $this->verifyCsrf();

        $employeeId = strtoupper(trim(Request::string('employee_id')));
        $password   = Request::string('password');

        $errors = [];
        if ($employeeId === '') {
            $errors['employee_id'] = 'ID Karyawan wajib diisi.';
        } elseif (preg_match('/^[A-Z0-9._-]{3,32}$/', $employeeId) !== 1) {
            // Terima ID Karyawan format WExxxxxx maupun akun admin legacy.
            $errors['employee_id'] = 'Format ID Karyawan tidak valid (contoh: WE100001).';
        }
        if ($password === '') {
            $errors['password'] = 'Password wajib diisi.';
        }

        if ($errors !== []) {
            return $this->renderLogin($errors, $employeeId);
        }

        $userModel = new User();
        $user = $userModel->findByUsername($employeeId);

        if ($user === null || ! password_verify($password, (string) $user['password'])) {
            return $this->renderLogin(['employee_id' => 'ID Karyawan atau password salah.'], $employeeId);
        }

        if ((int) $user['is_active'] !== 1) {
            return $this->renderLogin(['employee_id' => 'Akun tidak aktif. Hubungi administrator.'], $employeeId);
        }

        // Mulai sesi login.
        self::regenerateSession();
        Session::put('user_id', (int) $user['id']);
        Auth::reset();

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
