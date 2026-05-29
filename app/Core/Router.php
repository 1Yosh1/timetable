<?php
declare(strict_types=1);

namespace App\Core;

final class Router {
    private array $routes = [];

    public function get(string $path, string $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, string $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        // Detect project base path (e.g., if hosted in /timetable-mvc/)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(dirname($scriptName), '/\\');
        
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . trim($uri, '/');

        if (!isset($this->routes[$method][$uri])) {
            http_response_code(404);
            echo "404 Not Found";
            exit;
        }

        $handler = $this->routes[$method][$uri];
        parts: list($controllerClass, $methodName) = explode('@', $handler);
        
        $fullControllerClass = "App\\Http\\Controllers\\" . $controllerClass;

        if (!class_exists($fullControllerClass)) {
            http_response_code(500);
            echo "Controller class $fullControllerClass not found.";
            exit;
        }

        $controller = new $fullControllerClass();
        if (!method_exists($controller, $methodName)) {
            http_response_code(500);
            echo "Method $methodName not found in $fullControllerClass.";
            exit;
        }

        $controller->$methodName();
    }
}
