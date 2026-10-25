<?php
declare(strict_types=1);

namespace VTD\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): self
    {
        $normalized = '/' . trim($path, '/');
        // Converte {param} para regex (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $normalized);
        $regex = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'      => $method,
            'path'        => $normalized,
            'regex'       => $regex,
            'handler'     => $handler,
            'middlewares' => $middlewares
        ];

        return $this;
    }

    public function dispatch(): void
    {
        $request = Request::createFromGlobals();
        $this->dispatchRequest($request);
    }

    public function dispatchRequest(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        if ($method === 'OPTIONS') {
            if (!headers_sent()) {
                http_response_code(200);
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
            }
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Executa middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        $middleware($request);
                    } elseif (is_string($middleware) && class_exists($middleware)) {
                        (new $middleware())->handle($request);
                    }
                }

                // Executa o handler
                $handler = $route['handler'];
                if (is_callable($handler)) {
                    $handler($request, $params);
                    return;
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    $controller = is_object($class) ? $class : new $class();
                    $controller->$action($request, $params);
                    return;
                }
            }
        }

        Response::error('ROUTE_NOT_FOUND', "Endpoint '{$path}' [{$method}] não encontrado.", 404);
    }
}
