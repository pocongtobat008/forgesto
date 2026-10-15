<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with view rendering, validation, CSRF and access helpers.
 */
abstract class Controller
{
    /**
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): string
    {
        if (! headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function authorize(string $ability): void
    {
        if (! Auth::check()) {
            $this->abort(403, 'Tidak ada user aktif.');
        }

        if (! Auth::can($ability)) {
            $this->abort(403, 'Role Anda tidak memiliki akses untuk aksi ini (' . $ability . ').');
        }
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    /**
     * Verify the CSRF token for state-changing requests.
     */
    protected function verifyCsrf(): void
    {
        if (! Request::isPost()) {
            return;
        }

        if (! csrf_valid((string) Request::input('_token', ''))) {
            $this->abort(419, 'Token keamanan tidak valid atau kedaluwarsa.');
        }

        if (! IdempotencyGuard::claim(Request::path(), Auth::id())) {
            $this->abort(409, 'Permintaan ini sudah diproses. Muat ulang halaman untuk melihat hasilnya, jangan kirim ulang.');
        }
    }

    /**
     * Validate the request, redirecting back with errors when invalid.
     *
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    protected function validate(array $rules, array $labels = []): array
    {
        $data = Request::all();
        $validator = Validator::make($data, $rules, $labels);

        if ($validator->fails()) {
            $this->back(Request::string('_return', '/'), $data, $validator->errors());
        }

        return $data;
    }

    /**
     * Redirect back to a form preserving submitted values and errors.
     */
    protected function back(string $fallback, array $input = [], array $errors = []): never
    {
        Session::flashInput($input, $errors);
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer !== '' && ! $input && ! $errors) {
            header('Location: ' . $referer);
            exit;
        }

        redirect($fallback);
    }

    protected function redirectWith(string $path, string $type, string $message): never
    {
        flash($type, $message);
        redirect($path);
    }

    protected function notFound(string $message = 'Data tidak ditemukan.'): never
    {
        $this->abort(404, $message);
    }

    /**
     * Assert that a specific group is within the active user's scope.
     */
    protected function ensureGroupAccess(int|string|null $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        if (! Auth::canSeeGroup((int) $groupId)) {
            $this->abort(403, 'Anda tidak memiliki akses ke group ini.');
        }
    }
}
