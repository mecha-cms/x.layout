<?php

class Layout extends Genome {

    protected static $lot;

    public static function __callStatic(string $kin, array $lot = []) {
        if (parent::_($kin)) {
            return parent::__callStatic($kin, $lot);
        }
        $kin = p2f($kin);
        // `self::fake('foo/bar', ['key' => 'value'])`
        if ($lot) {
            // `self::fake(['key' => 'value'])`
            if (is_array($lot[0])) {
                // → is equal to `self::fake("", ['key' => 'value'])`
                array_unshift($lot, "");
            }
            $kin = trim($kin . '/' . array_shift($lot), '/');
        }
        return self::get($kin, ...$lot);
    }

    public static function get($key, array $lot = [], int $status = null) {
        if (!$value = self::of($key)) {
            return null;
        }
        if (isset($status) && !headers_sent()) {
            status($status);
        }
        foreach ($lot as $k => $v) {
            lot($k, $v);
        }
        if (is_callable($value)) {
            return call_user_func($value, $key, $lot, $status);
        }
        if (is_file($value)) {
            $layout = new static;
            $layout->data = $lot;
            $layout->name = strtok(substr($value, strlen(LOT . D . 'y' . D)), D);
            $layout->path = $value;
            $layout->route = "" !== $key ? '/' . strtr($key, D, '/') : null;
            lot('layout', $layout);
            lot('lot', $lot);
            return (static function () {
                ob_start();
                extract(lot());
                require $layout->path;
                return ob_get_clean();
            })();
        }
        return null;
    }

    public static function of($key) {
        if ($path = self::path($key)) {
            return $path;
        }
        if (is_array($key)) {
            foreach ($key as $v) {
                if (null !== ($r = self::of($v))) {
                    return $r;
                }
            }
            return null;
        }
        $c = static::class;
        $key = strtr($key, D, '/');
        foreach (step($key, '/') as $v) {
            if (isset(self::$lot[$c][1][$v]) && is_callable($r = self::$lot[$c][1][$v]) && !isset(self::$lot[$c][0][$v])) {
                return $r;
            }
        }
        return null;
    }

    public static function path($key) {
        $c = static::class;
        $path = LOT . D . 'y';
        if (is_string($key)) {
            // Full path, be quick!
            if (0 === strpos($key, PATH) && is_file($key)) {
                return $key;
            }
            $key = strtr($key, D, '/');
            // Added by the `Layout::set()`
            if (isset(self::$lot[$c][1][$key]) && is_string(self::$lot[$c][1][$key]) && !isset(self::$lot[$c][0][$key])) {
                return exist(self::$lot[$c][1][$key], 1) ?: null;
            }
            // Guessing…
            $keys = array_unique(array_values(step($key, '/')));
        } else {
            $keys = (array) $key;
        }
        $files = [];
        foreach ($keys as $v) {
            if (!is_string($v)) {
                continue;
            }
            $v = strtr($v, '/', D);
            // Iterate over the `.\lot\y` folder to find active layout(s)
            foreach (g($path, 0) as $kk => $vv) {
                if (!is_file($kk . D . 'index.php')) {
                    continue;
                }
                $files[] = 0 !== strpos($v, $kk) ? $kk . D . $v . '.php' : $v;
            }
        }
        return exist($files) ?: null;
    }

    public static function let($key = null) {
        if (is_array($key)) {
            foreach ($key as $v) {
                self::let($v);
            }
        } else if (isset($key)) {
            $c = static::class;
            $key = strtr($key, D, '/');
            self::$lot[$c][0][$key] = 1;
            unset(self::$lot[$c][1][$key]);
        } else {
            self::$lot[$c] = [];
        }
    }

    public static function set($key, $value) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::set($k, $v);
            }
        } else {
            $c = static::class;
            if (!isset(self::$lot[$c][0][$key])) {
                $key = strtr($key, D, '/');
                self::$lot[$c][1][$key] = $value;
            }
        }
    }

    public $data;
    public $name;
    public $path;
    public $route;

}