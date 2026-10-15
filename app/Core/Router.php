<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal regex router supporting {param} placeholders and
 * "Controller@method" handlers.
 */
class Router
{
    /** @var array<string, list<array{regex:string,names:list<string>,handler:mixed}>> */
    protected array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'PATCH'  => [],
        'DELETE' => [],
    ];

    public function get(string $pattern, mixed $handler): self
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): self
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): self
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): self
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, mixed $handler): self
    {
        $method = strtoupper($method);
        $names = [];

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$names): string {
                $names[] = $m[1];
                return '([^/]+)';
            },
            rtrim($pattern, '/') === '' ? '/' : rtrim($pattern, '/')
        );

        $this->routes[$method][] = [
            'regex'   => '#^' . $regex . '$#',
            'names'   => $names,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Resolve the request to a handler and return its output.
     */
    public function dispatch(string $method, string $path): mixed
    {
        $method = strtoupper($method);
        $path = $path === '' ? '/' : $path;

        foreach ($this->routes[$method] ?? [] as $route) {
            if (! preg_match($route['regex'], $path)) {
                continue;
            }

            preg_match($route['regex'], $path, $matches);
            array_shift($matches);

            $params = $route['names'] === []
                ? []
                : array_combine($route['names'], $matches);

            return $this->invoke($route['handler'], $params ?: []);
        }

        throw new HttpException(404, 'Route tidak ditemukan: /' . ltrim($path, '/'));
    }

    private function invoke(mixed $handler, array $params): mixed
    {
        if (is_callable($handler) && ! is_string($handler)) {
            return $handler($params);
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$controller, $action] = explode('@', $handler, 2);
            $class = str_contains($controller, '\\')
                ? $controller
                : 'App\\Controllers\\' . $controller;

            if (! class_exists($class)) {
                throw new HttpException(500, "Controller {$class} tidak ditemukan.");
            }

            $instance = new $class();

            if (! method_exists($instance, $action)) {
                throw new HttpException(500, "Method {$class}@{$action} tidak ditemukan.");
            }

            return $instance->{$action}($params);
        }

        throw new HttpException(500, 'Handler route tidak valid.');
    }
}
