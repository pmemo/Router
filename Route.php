<?php

class Router
{
    private static $methods = ['get', 'post', 'put', 'patch', 'delete'];
    private static $request;
    private static $routes = [];
    private static $middlewares = [];
    private static $status = [];
    private static $namespace = [
        'current' => '',
        'last' => ''
    ];

    public static function __callStatic($method, $args) {
        if(in_array($method, self::$methods) && count($args) == 2) {
            self::addRoute($method, $args[0], $args[1]);
        }

        return new static();
    }

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

    private static function loadClass($classStr, $args)
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
            return call_user_func_array([$classInstance, $method], $args);
        }
    }

    private static function getAccess($url)
    {
        $middlewares = array_filter(self::$middlewares, function ($middleware) use ($url) {
            return strpos($url, $middleware['url']) !== false;
        });

        $access = true;
        foreach ($middlewares as $middleware) {
            $return = self::call($middleware['callback']);

            if(is_object($return)){
                self::$request = $return;
            } elseif (is_bool($return)) {
                if($return === false) $access = false;
            } elseif(is_array($return)) {
                if($return[1] === false) $access = false;
            }

            if(!$access && $middleware['code']) {
                self::handleStatus($middleware['code']);
                exit();
            }
        }

        return $access;
    }

    private static function loadRoute($route)
    {
        if (self::getAccess($route['url'])) {
            self::call($route['callback']);
        } else {
            self::handleStatus(403);
        }
    }

    private static function call($callback)
    {
        $req = self::$request;
        $res = new Response();

        if (is_callable($callback)) {
            return call_user_func_array($callback, [$req, $res]);
        } elseif (is_string($callback)) {
            return self::loadClass($callback, [$req, $res]);
        } elseif (is_bool($callback)) {
            return $callback;
        } else {
            throw new Exception('[Route] Cannot call a callback.');
        }

        return false;
    }

    public static function middleware($callback, $code = null)
    {
        if(is_array($callback)) {
            foreach($callback as $middleware) {
                array_push(self::$middlewares, [
                    'url' => self::$namespace['current'].self::$namespace['last'],
                    'callback' => $middleware,
                    'code' => $code
                ]);
            }
        } else {
            array_push(self::$middlewares, [
                'url' => self::$namespace['current'].self::$namespace['last'],
                'callback' => $callback,
                'code' => $code
            ]);
        }

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
        self::$request = new Request();

        $routes = array_filter(self::$routes, function ($route) {
            return strtoupper($route['method']) == $_SERVER['REQUEST_METHOD'];
        });

        usort($routes, function ($r1, $r2) {
            return (strstr($r1['url'], ':')) ? 1 : -1;
        });

        $URI = explode('?', $_SERVER['REQUEST_URI'])[0];
        foreach ($routes as $route) {
            $regex = '/\:([a-zA-Z0-9_]+)/';
            preg_match_all($regex, $route['url'], $indexes);
            unset($indexes[0]); $indexes = $indexes[1];
            $url = preg_replace($regex, substr($regex, 3, -1), $route['url']);
            if (preg_match('#^'.$url.'$#', $URI, $matched)) {
                unset($matched[0]);
                self::$request->setParams(array_combine($indexes, $matched));
                self::loadRoute($route);
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

class Request {
    private $props = [];
    private $params = [];
    private $query = [];
    private $body = [];
    private $files = [];

    public function __construct() {
        $this->query = $_GET;
        $this->body = $_POST;
        $this->files = $_FILES;
    }

    public function setParams($params) {
        $this->params = $params;
    }

    private function _getData($dataSet, $key = null) {
        if($key !== null) {
            return isset($this->$dataSet[$key]) ? $this->$dataSet[$key] : null;
        } else {
            return isset($this->$dataSet);
        }
    }

    // Data methods
    public function param($key) {
        return $this->_getData('params', $key);
    }

    public function query($key) {
        return $this->_getData('query', $key);
    }

    public function body($key) {
        return $this->_getData('body', $key);
    }

    public function file($key) {
        return $this->_getData('files', $key);
    }

    public function getData($name) {
        return $this->_getData($name);
    }

    public function getAllData() {
        return array_merge(
            $this->_getData('query', $key),
            $this->_getData('files', $key),
            $this->_getData('params', $key),
            $this->_getData('body', $key)
        );
    }

    public function set($name, $value) {
        $this->props[$name] = $value;
        return $this;
    }

    public function get($name) {
        return isset($this->props[$name]) ? $this->props[$name] : null;
    }

    public function header($key) { 
        $headers = apache_request_headers();
        return isset($headers[$key]) ? $headers[$key] : null;
    }
}

class Response {
    public function send($code, $message = null) {
        if($message) {
            echo json_encode($message);
        }

        http_response_code($code);
    }
}
