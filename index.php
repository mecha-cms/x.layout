<?php

namespace {
    function layout(...$lot) {
        if (\is_array($lot[1] ?? [])) {
            return \Layout::get(...$lot);
        }
        return \count($lot) < 2 ? \Layout::get(...$lot) : \Layout::set(...$lot);
    }
    \lot('date', \lot('time', new \Time($_SERVER['REQUEST_TIME'] ?? \time())));
    // Alias for `State`
    \class_alias("\\State", "\\Site");
    // Alias for `Time`
    \class_alias("\\Time", "\\Date");
    // Alias for `$state`
    \lot('site', $site = $state);
    // Default layout title
    \lot('t', $t = new \Batch([$state->title], ' &#x00b7; '));
}

namespace x\layout {
    function content($content) {
        if (!$content || false === \strpos($content, '</')) {
            return $content;
        }
        // Capture the `<html>` part
        if (false !== ($a = \strpos($content, '<html')) && \strspn($content, " \n\r\t", $a + 5)) {
            if (false !== ($b = \strpos($content, '>', $a))) {
                $e = new \HTML(\substr($content, $a, ($b + 1) - $a));
                if (isset($e['class'])) {
                    $c = true === $e['class'] ? [] : \preg_split('/\s+/', $e['class'] ?? "");
                    $c = \array_unique(\array_merge($c, \array_keys(\array_filter((array) \State::get('q.state', true)))));
                    \sort($c); // Sort class name(s)
                    $e['class'] = "" !== ($c = \trim(\implode(' ', $c))) ? $c : true;
                }
                return \substr_replace($content, (string) $e, $a, ($b + 1) - $a);
            }
        }
        return $content;
    }
    function get() {
        \extract(\lot());
        $content = \Hook::fire('route', [null, $link->path, $link->query, $link->hash]);
        if (\is_array($content) || \is_object($content)) {
            if (!$error = \error_get_last()) {
                \type('application/json');
            }
            \status($error ? 400 : 200);
            echo \To::JSON($content, true);
        } else {
            echo $content;
        }
    }
    function route($content, $path) {
        \ob_start();
        \ob_start(!\error_get_last() ? "\\ob_gzhandler" : null);
        if (\is_array($content)) {
            $content = new \Layout(\array_is_list($content) ? [
                'lot' => $content[1] ?? [],
                'status' => $content[0] ?? 403
            ] : $content);
        } else if (\is_int($content)) {
            $content = new \Layout([
                'lot' => [],
                'status' => $content
            ]);
        }
        if (\is_object($content)) {
            if (null !== ($r = \Layout::get('index', $content->lot, $content->status))) {
                $content = $r;
            }
        }
        echo \Hook::fire('content', [$content]);
        \ob_end_flush();
        // <https://www.php.net/manual/en/function.ob-get-length.php#59294>
        \header('content-length: ' . \ob_get_length());
        return \ob_get_clean();
    }
    \Hook::set('content', __NAMESPACE__ . "\\content", 20);
    \Hook::set('get', __NAMESPACE__ . "\\get", 1000);
    \Hook::set('route', __NAMESPACE__ . "\\route", 1000);
}

namespace x\layout\content {
    function state() {
        foreach (['are', 'as', 'can', 'has', 'is', 'not', 'of', 'with'] as $v) {
            foreach ((array) \State::get($v, true) as $kk => $vv) {
                \State::set('q.state.' . $v . '-' . $kk, $vv);
            }
        }
        if ($x = \State::get('is.error')) {
            \State::set('q.state.error-' . $x, true);
        }
    }
    \Hook::set('content', __NAMESPACE__ . "\\state", 0);
}

namespace x\layout\get {
    function asset() {
        if (!\class_exists("\\Asset")) {
            return;
        }
        foreach (\lot('Y')[1] ?? [] as $index) {
            // Detect relative asset path to the `.\lot\y\*` folder
            if ($assets = \Asset::get()) {
                foreach ($assets as $k => $v) {
                    foreach ($v as $kk => $vv) {
                        // Full path, no change!
                        if (
                            0 === \strpos($kk, \PATH) ||
                            0 === \strpos($kk, '//') ||
                            false !== \strpos($kk, '://')
                        ) {
                            continue;
                        }
                        if ($path = \Asset::path(\dirname($index) . \D . $kk)) {
                            \Asset::let($kk);
                            \Asset::set($path, $vv['stack'], $vv[2]);
                        }
                    }
                }
            }
        }
    }
    \Hook::set('get', __NAMESPACE__ . "\\asset", 0);
}