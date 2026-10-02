<?php

class Layout extends Proxy {

    protected static $of;

    public function __construct(array $lot) {
        foreach ($lot as $k => $v) {
            $this->{$k} = $v;
        }
    }

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
            $lot['layout'] = new static([
                'key' => $key,
                'lot' => $lot,
                'name' => strstr(substr($value, strlen(LOT . D . 'y' . D)), D, true),
                'path' => $value,
                'status' => $status,
                'y' => "" !== $key && is_string($key) ? '/' . strtr($key, D, '/') : null,
            ]);
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
            foreach ($key as $k) {
                if (null !== ($r = self::of($k))) {
                    return $r;
                }
            }
            return null;
        }
        $c = static::class;
        foreach (step(strtr($key, D, '/'), '/') as $k) {
            if (is_callable($r = self::$of[$c][1][$k] ?? 0) && !isset(self::$of[$c][0][$k])) {
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
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = strtr($key, '/', D);
            // Iterate over the `.\lot\y` folder to find active layout(s)
            foreach (g($path, 0) as $k => $v) {
                if (!is_file($k . D . 'index.php')) {
                    continue;
                }
                $files[] = $k . D . $key . '.phtml';
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
            $key = strtr($key, D, '/');
            if (!isset(self::$of[$c][0][$key])) {
                self::$of[$c][1][$key] = $value;
            }
        }
    }

    public $key;
    public $lot;
    public $name;
    public $path;
    public $status;
    public $y;

}