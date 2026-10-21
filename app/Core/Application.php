<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Application kernel: loads routes, dispatches the request and renders errors.
 */
class Application
{
    public function run(): void
    {
        $router = new Router();

        /** @noinspection PhpIncludeInspection */
        require BASE_PATH . '/config/routes.php';

        try {
            $this->guardAuth();

            $output = $router->dispatch(Request::method(), Request::path());

            if (is_string($output)) {
                echo $output;
            }
        } catch (HttpException $e) {
            $this->renderError($e->statusCode(), $e->getMessage());
        } catch (Throwable $e) {
            $this->renderError(500, $e->getMessage(), $e);
        } finally {
            Session::clearOldInput();
        }
    }

    /**
     * Wajib login bila app.auth.login_enabled = true.
     * Rute publik: /login, /logout, aset statis, health check.
     */
    private function guardAuth(): void
    {
        if (! (bool) config('app.auth.login_enabled', false)) {
            return;
        }

        $path = '/' . trim(Request::path(), '/');

        foreach (['/login', '/logout', '/health', '/favicon.ico', '/robots.txt'] as $public) {
            if ($path === $public) {
                return;
            }
        }
        // Endpoint pendukung form login (mis. /login/device) tetap publik.
        if (str_starts_with($path, '/login/')) {
            return;
        }

        if (preg_match('#^/(assets|templates|uploads|public)/#', $path) === 1) {
            return;
        }

        if (Auth::check()) {
            return;
        }

        if (Request::isAjax()) {
            if (! headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json');
            }
            echo json_encode(['error' => 'unauthenticated', 'message' => 'Sesi berakhir, silakan login ulang.']);
            exit;
        }

        // Simpan tujuan awal agar bisa kembali setelah login.
        $target = Request::path();
        if ($target !== '' && $target !== '/') {
            Session::put('intended_url', '/' . ltrim($target, '/'));
        }

        redirect('/login');
    }

    private function renderError(int $status, string $message, ?Throwable $exception = null): void
    {
        if (! headers_sent()) {
            http_response_code($status);
        }

        $debug = (bool) config('app.debug', false);

        if (! $debug) {
            $message = HttpException::defaultMessage($status);
            $exception = null;
        }

        // Resolving the user must never break error rendering (e.g. DB down).
        $user = null;
        try {
            $user = Auth::user();
        } catch (Throwable $ignored) {
            $user = null;
        }

        try {
            echo View::render('errors/error', [
                'status'    => $status,
                'message'   => $message,
                'exception' => $exception,
                'user'      => $user,
            ], null);
        } catch (Throwable $renderError) {
            // Absolute last resort.
            echo '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
        }
    }
}
