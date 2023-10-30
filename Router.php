<?php

namespace ARUSH;

class Router
{
    private array $routes = [];

    public function addRoute($method, $path, $handler): void
    {
        $this->routes[] = new Route($method, $path, $handler);
    }

    public function handleRequest($method, $path)
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route->execute();
            }
        }

        // Handle 404 (Not Found)
        http_response_code(404);
        echo '404 Not Found';
    }
}