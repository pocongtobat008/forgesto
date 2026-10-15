<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renders PHP view files, optionally wrapped in a layout.
 */
class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($view, $data);

        if ($layout === null) {
            return $content;
        }

        return self::partial($layout, array_merge($data, [
            'content' => $content,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';

        if (! is_file($file)) {
            throw new \RuntimeException("View not found: {$view} ({$file})");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
