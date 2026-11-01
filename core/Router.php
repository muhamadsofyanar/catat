<?php

namespace Core;

class Router {
    protected $routes = [];

    public function add($method, $path, $controller, $action) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function dispatch($uri, $method) {
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Remove script name (index.php) and trailing slashes
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/') {
            $uri = str_replace($scriptName, '', $uri);
        }
        $uri = trim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        } else {
            $uri = '/' . $uri;
        }

        foreach ($this->routes as $route) {
            if ($route['path'] === $uri && $route['method'] === $method) {
                $controllerClass = $route['controller'];
                $action = $route['action'];

                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $action)) {
                        return $controller->$action();
                    }
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        echo "404 Not Found";
    }
}
