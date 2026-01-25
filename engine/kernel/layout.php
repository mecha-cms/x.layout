<?php

class Layout extends Genome {

    protected static $of;

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

    public static function get($key, array $lot = [], ?int $status = null) {
        if (!$value = self::of($key)) {
            return null;
        }
        if (isset($status) && !headers_sent()) {
            status($status);
        }
        if (is_callable($value)) {
            return call_user_func($value, $key, $lot, $status);
        }
        if (is_file($value)) {
            $layout = new static;
            $layout->key = $key;
            $layout->lot = $lot;
            $layout->name = strstr(substr($value, strlen(LOT . D . 'y' . D)), D, true);
            $layout->path = $value;
            $layout->route = "" !== $key && is_string($key) ? '/' . strtr($key, D, '/') : null;
            $lot['layout'] = $layout;
            return (static function ($lot) {
                ob_start();
                extract(lot($lot), EXTR_SKIP);
                require $layout->path;
                return ob_get_clean();
            })($lot);
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
            if (isset(self::$of[$c][1][$v]) && is_callable($r = self::$of[$c][1][$v]) && !isset(self::$of[$c][0][$v])) {
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
            if (isset(self::$of[$c][1][$key]) && is_string(self::$of[$c][1][$key]) && !isset(self::$of[$c][0][$key])) {
                return exist(self::$of[$c][1][$key], 1) ?: null;
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
            self::$of[$c][0][$key] = 1;
            unset(self::$of[$c][1][$key]);
        } else {
            self::$of[$c] = [];
        }
    }

    public static function set($key, $value) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                self::set($k, $v);
            }
        } else {
            $c = static::class;
            if (!isset(self::$of[$c][0][$key])) {
                $key = strtr($key, D, '/');
                self::$of[$c][1][$key] = $value;
            }
        }
    }

    public $key;
    public $lot;
    public $name;
    public $path;
    public $route;

}