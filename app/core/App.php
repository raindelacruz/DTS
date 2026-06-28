<?php

class App {

    protected $controller = 'Auth';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {

        $url = $this->parseUrl();

        if (($url[0] ?? '') === 'document') {
            $this->controller = 'Verification';
            $this->method = 'verify';
            $url = [
                'Verification',
                'verify',
                $url[2] ?? ''
            ];
        }

        if(file_exists(APPROOT . '/controllers/' . ucfirst($url[0]) . '.php')) {
            $this->controller = ucfirst($url[0]);
            unset($url[0]);
        }

        require_once APPROOT . '/controllers/' . $this->controller . '.php';

        $this->controller = new $this->controller;

        if(isset($url[1])) {
            if(method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        $this->params = $url ? array_values($url) : [];

        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseUrl() {
        if(isset($_GET['url'])) {
            $url = filter_var(rtrim((string) $_GET['url'], '/'), FILTER_SANITIZE_URL);
            $parts = array_values(array_filter(explode('/', $url), static function ($part) {
                return $part !== '';
            }));

            return !empty($parts) ? $parts : ['auth', 'login'];
        }

        return ['auth', 'login'];
    }
}
