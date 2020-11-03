<?php

class Route
{
    private static $routes = [];
    private static $middlewares = [];
    private static $status = [];
    private static $namespace = [
        'current' => '',
        'last' => ''
    ];

    private static function namespace($case, $level)
    {
        switch ($case) {
            case 'next':
                self::$namespace['current'] .= $level;
            break;
            case 'prev':
                self::$namespace['current'] = str_replace($level, '', self::$namespace['current']);
                break;
            case 'last':
                self::$namespace['last'] = $level;
            break;
        }
    }

    private static function addRoute($method, $url, $callback)
    {
        array_push(self::$routes, [
            'method' => $method,
            'url' => self::$namespace['current'].$url,
            'callback' => $callback
        ]);
        self::namespace('last', $url);
    }

    private static function loadClass($classStr, $args = [])
    {
        $class = null;
        if (strpos($classStr, '@')) {
            $class = explode('@', $classStr);
        } else {
            throw new Exception('[Route] Cannot load class.');
        }

        require_once $class[0].'.php';

        $className = explode('/', $class[0]);
        $className = end($className);

        $method = $class[1];
        $classInstance = $className;

        if (method_exists($classInstance, $method) || method_exists($classInstance, '__call')) {
            $classInstance = new $className;
            call_user_func_array([$classInstance, $method], $args);
        }
    }

    private static function getAccess($url)
    {
        $middlewares = array_filter(self::$middlewares, function ($middleware) use ($url) {
            return strpos($url, $middleware['url']) !== false;
        });

        foreach ($middlewares as $middleware) {
            if (self::call($middleware['callback']) === false) {
                if ($middleware['code']) {
                    self::handleStatus($middleware['code']);
                    exit();
                }
                return false;
            }
        }

        return true;
    }

    private static function loadRoute($route, $args = [])
    {
        if (self::getAccess($route['url'])) {
            $args = array_merge($args, $_GET, $_POST, $_FILES);
            self::call($route['callback'], [$args]);
        } else {
            self::handleStatus(403);
        }
    }

    private static function call($callback, $args = [])
    {
        if (is_callable($callback)) {
            return call_user_func_array($callback, $args);
        } elseif (is_string($callback)) {
            return self::loadClass($callback, $args);
        } elseif (is_bool($callback)) {
            return $callback;
        } else {
            throw new Exception('[Route] Cannot call a callback.');
        }

        return false;
    }

    public static function get($url, $callback)
    {
        self::addRoute('GET', $url, $callback);
        return new static();
    }

    public static function post($url, $callback)
    {
        self::addRoute('POST', $url, $callback);
        return new static();
    }

    public static function middleware($callback, $code = null)
    {
        array_push(self::$middlewares, [
            'url' => self::$namespace['current'].self::$namespace['last'],
            'callback' => $callback,
            'code' => $code
        ]);
        return new static();
    }

    public static function group($url, $callback)
    {
        self::namespace('next', $url);
        self::call($callback);
        self::namespace('prev', $url);
        self::namespace('last', $url);
        return new static();
    }

    public static function run()
    {
        $routes = array_filter(self::$routes, function ($route) {
            return $route['method'] == $_SERVER['REQUEST_METHOD'];
        });

        $URI = explode('?', $_SERVER['REQUEST_URI'])[0];
        foreach ($routes as $route) {
            $regex = '/<([a-zA-Z0-9_]+)>/';
            preg_match_all($regex, $route['url'], $indexes);
            unset($indexes[0]); $indexes = $indexes[1];
            $url = preg_replace($regex, substr($regex, 2, -2), $route['url']);
            if (preg_match('#^'.$url.'$#', $URI, $matched)) {
                unset($matched[0]);
                self::loadRoute($route, array_combine($indexes, $matched));
                return;
            }
        }

        self::handleStatus(404);
    }
    
    public static function handleStatus($code)
    {
        if (array_key_exists($code, self::$status)) {
            self::call(self::$status[$code]);
        } else {
            http_response_code($code);
        }
    }

    public static function status($code, $callback)
    {
        self::$status[$code] = $callback;
    }

    public static function redirect($url)
    {
        header("Location: $url");
        exit();
    }
}
