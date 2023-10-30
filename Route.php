<?php

namespace ARUSH;

class Route
{
    private $method;
    private $path;
    private $handler;
    private $parameters = [];

    public function __construct($method, $path, $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }

    public function matches($method, $path): bool
    {
        $matches = [];
        if ($this->method === $method && preg_match($this->path, $path, $matches)) {
            // Store the matched parameters
            unset($matches[0]);
            $this->parameters = $matches;

            return true;
        }

        return false;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function execute()
    {
        if (is_callable($this->handler)) {
            return is_array($this->getParameters()) ? call_user_func_array($this->handler, $this->getParameters()) : call_user_func($this->handler, $this->getParameters());
        } elseif (is_string($this->handler)) {
            $parts = explode('@', $this->handler);
            $controllerClass = $parts[0];
            $method = $parts[1];
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                if (method_exists($controller, $method)) {
                    return $controller->$method();
                }
            }
        }

        // Handle 500 (Internal Server Error) for invalid routes or handlers
        http_response_code(500);
        echo '500 Internal Server Error';
    }
}