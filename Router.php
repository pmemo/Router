<?php
class Router {
    const METHODS = ['get', 'post', 'put', 'patch', 'delete'];
    private static $routes = [];
    private static $urlPrefix;
    private static $request;

    public static function __callStatic($method, $args) {
        if(in_array($method, self::METHODS)) {
            $url = array_shift($args);
            self::addRoute($method, $url, $args);
        }

        return new static();
    }

    private static function addRoute($method, $url, $callbacks)
    {
        array_push(self::$routes, [
            'method' => $method,
            'url' => self::$urlPrefix.$url,
            'callbacks' => $callbacks
        ]);
    }

    public static function use(...$callbacks) {
        if(!self::isUrlMatch($callbacks[0])) return;

        if(is_string($callbacks[0])) {
            self::$urlPrefix .= $callbacks[0];
            $quote = array_shift($callbacks);
            self::call($callbacks);
            self::$urlPrefix = preg_replace('/'.preg_quote($quote, '/').'$/', '', self::$urlPrefix);
        } else {
            self::call($callbacks);
        }
    }

    private static function isUrlMatch($url) {
        $URI = explode('?', $_SERVER['REQUEST_URI'])[0];
        $regex = '/\:([a-zA-Z0-9_]+)/';
        preg_match_all($regex, $url, $indexes);
        unset($indexes[0]); $indexes = $indexes[1];
        $url = preg_replace($regex, substr($regex, 3, -1), $url);
        return preg_match("#{$url}#i", $URI);
    }

    private static function call($callbacks)
    {
        $req = self::$request;
        $res = new Response();

        foreach($callbacks as $callback) {
            if (is_callable($callback)) {
                $result = call_user_func_array($callback, [$req, $res]);
            } elseif (is_string($callback)) {
                $result = self::loadClass($callback, [$req, $res]);
            } else {
                throw new Exception('[Router] Cannot call a callback.');
            }

            if(!$result) break;
        }

        return false;
    }

    private static function loadClass($classStr, $args)
    {
        $class = null;
        if (strpos($classStr, '@')) {
            $class = explode('@', $classStr);
        } else {
            throw new Exception('[Router] Cannot load class.');
        }

        require_once $class[0].'.php';

        $className = explode('/', $class[0]);
        $className = end($className);

        $method = $class[1];
        $classInstance = $className;

        if (method_exists($classInstance, $method) || method_exists($classInstance, '__call')) {
            $classInstance = new $className(self::$request);
            return call_user_func_array([$classInstance, $method], $args);
        }
    }

    public static function run()
    {
        self::$request = new Request();

        $routes = array_filter(self::$routes, function ($route) {
            return strtoupper($route['method']) == $_SERVER['REQUEST_METHOD'];
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
                self::call($route['callbacks']);
                return;
            }
        }

        http_response_code(404);
    }
}

class Request {
    private $headers = [];
    private $props = [];
    private $params = [];
    private $query = [];
    private $body = [];
    private $files = [];

    public function __construct() {
        $this->headers = apache_request_headers();
        $this->query = $_GET;
        $this->body = $_POST ? $_POST : json_decode(file_get_contents("php://input"), true);
        $this->files = $_FILES;
    }

    public function setParams($params) {
        $this->params = $params;
    }

    private function _getData($dataSet, $key = null) {
        if($key !== null) {
            return isset($this->$dataSet[$key]) ? $this->$dataSet[$key] : null;
        } else {
            return isset($this->$dataSet) ? $this->$dataSet: null;
        }
    }

    // Data methods
    public function params($key = null) {
        return $this->_getData('params', $key);
    }

    public function query($key = null) {
        return $this->_getData('query', $key);
    }

    public function body($key = null) {
        return $this->_getData('body', $key);
    }

    public function files($key = null) {
        return $this->_getData('files', $key);
    }

    public function all() {
        return array_merge(
            $this->_getData('query'),
            $this->_getData('files'),
            $this->_getData('params'),
            $this->_getData('body')
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
        return isset($this->headers[$key]) ? $this->headers[$key] : null;
    }
}

class Response {
    public function status($code) {
        http_response_code($code);
        return $this;
    }

    public function json($message) {
        header('Content-Type: application/json');
        echo json_encode($message);
    }
}