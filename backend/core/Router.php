<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * Roteador REST simples com suporte a parametros, grupos e middlewares.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:callable|array,middlewares:string[]}> */
    private array $routes = [];

    private string $prefix = '';
    /** @var string[] */
    private array $groupMiddlewares = [];

    /**
     * @param string[] $middlewares
     */
    public function group(string $prefix, array $middlewares, callable $callback): void
    {
        $previousPrefix      = $this->prefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->prefix          = $previousPrefix . '/' . trim($prefix, '/');
        $this->groupMiddlewares = array_merge($previousMiddlewares, $middlewares);

        $callback($this);

        $this->prefix          = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    /** @param callable|array{0:class-string,1:string} $handler @param string[] $middlewares */
    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    /** @param string[] $middlewares */
    private function add(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $full = $this->prefix . '/' . trim($path, '/');
        $full = '/' . trim($full, '/');
        if ($full === '') {
            $full = '/';
        }

        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $full);

        $this->routes[] = [
            'method'      => $method,
            'regex'       => '#^' . $regex . '$#',
            'params'      => $params,
            'handler'     => $handler,
            'middlewares' => array_merge($this->groupMiddlewares, $middlewares),
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $methodMismatch = true;
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name];
            }
            $request->setParams($params);

            foreach ($route['middlewares'] as $middlewareClass) {
                /** @var MiddlewareInterface $middleware */
                $middleware = new $middlewareClass();
                $middleware->handle($request);
            }

            $this->runHandler($route['handler'], $request);
            return;
        }

        if ($methodMismatch) {
            throw new HttpException('Metodo nao permitido para esta rota.', 405);
        }

        throw new HttpException('Rota nao encontrada.', 404);
    }

    /** @param callable|array{0:class-string,1:string} $handler */
    private function runHandler(callable|array $handler, Request $request): void
    {
        if (is_array($handler)) {
            [$class, $action] = $handler;
            $controller = new $class();
            $controller->{$action}($request);
            return;
        }

        $handler($request);
    }
}
