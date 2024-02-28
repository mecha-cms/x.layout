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
        if (!$f = self::of($key)) {
            return null;
        }
        $data = [];
        foreach (array_replace($GLOBALS, $lot) as $k => $v) {
            // Sanitize array key
            $k = preg_replace('/\W/', "", strtr($k, '-', '_'));
            $data[$k] = $v;
        }
        if (isset($status) && !headers_sent()) {
            status($status);
        }
        if (is_callable($f)) {
            return call_user_func($f, $key, $lot, $status);
        }
        if (is_file($f)) {
            $data['layout'] = (object) array_replace_recursive([
                'key' => $key,
                'lot' => $lot,
                'name' => strtok(substr($f, strlen(LOT . D . 'y' . D)), D),
                'path' => $f
            ], (array) ($lot['layout'] ?? []));
            $data['lot'] = $lot;
            return (static function ($data, $f) {
                extract($data, EXTR_SKIP);
                if (isset($data['data'])) {
                    $data = $data['data'];
                }
                ob_start();
                require $f;
                return ob_get_clean();
            })($data, $f);
        }
        return null;
    }

    public static function of($key) {
        if (is_array($key)) {
            foreach ($key as $v) {
                if (null !== ($fn = self::of($v))) {
                    return $fn;
                }
            }
            return self::path($key);
        }
        $c = static::class;
        $key = strtr($key, D, '/');
        foreach (step($key, '/') as $v) {
            if (isset(self::$lot[$c][1][$v]) && is_callable($fn = self::$lot[$c][1][$v])) {
                return $fn;
            }
        }
        return self::path($key);
    }

    public static function path($value) {
        $out = [];
        $c = static::class;
        $path = LOT . D . 'y';
        if (is_string($value)) {
            // Full path, be quick!
            if (0 === strpos($value, PATH) && is_file($value)) {
                return $value;
            }
            $key = strtr($value, D, '/');
            // Added by the `Layout::set()`
            if (isset(self::$lot[$c][1][$key]) && !isset(self::$lot[$c][0][$key])) {
                return exist(self::$lot[$c][1][$key], 1) ?: null;
            }
            // Guessing…
            $out = array_unique(array_values(step($key, '/')));
        } else {
            $out = (array) $value;
        }
        $files = [];
        foreach ($out as $v) {
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

}