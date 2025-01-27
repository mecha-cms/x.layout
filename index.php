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
    \lot('t', $t = new \Anemone([$state->title], ' &#x00b7; '));
}

namespace x\layout {
    function content($content) {
        if (
            false !== ($a = \strpos($content, '<html ')) ||
            false !== ($a = \strpos($content, "<html\n")) ||
            false !== ($a = \strpos($content, "<html\r")) ||
            false !== ($a = \strpos($content, "<html\t"))
        ) {
            if (false !== ($b = \strpos($content, '>', $a))) {
                $e = new \HTML(\substr($content, $a, ($b + 1) - $a));
                if (isset($e['class'])) {
                    $c = true === $e['class'] ? [] : \preg_split('/\s+/', $e['class'] ?? "");
                    $c = \array_unique(\array_merge($c, \array_keys(\array_filter((array) \State::get('[y]', true)))));
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
        $content = \Hook::fire('route', [null, $url->path, $url->query, $url->hash]);
        if (\is_array($content) || \is_object($content)) {
            if (!\error_get_last()) {
                \type('application/json');
            }
            echo \To::JSON($content, true);
        } else {
            echo $content;
        }
    }
    function route($content, $path) {
        \ob_start();
        \ob_start(!\error_get_last() ? "\\ob_gzhandler" : null);
        // `$content = ['page', [], 200];`
        if (\is_array($content) && isset($content[0]) && \is_string($content[0])) {
            if (null !== ($r = \Layout::get(...$content))) {
                $content = $r;
            } else if (\defined("\\TEST") && \TEST && \function_exists("\\abort")) {
                \status(403);
                $k = \glob(\LOT . \D . 'y' . \D . '*' . \D . 'index.php', \GLOB_NOSORT);
                if (isset($k[0])) {
                    $k = \dirname(\substr($k[0], \strlen(\LOT . \D . 'y' . \D)));
                } else {
                    $k = '*';
                }
                $v = \strtr(\LOT, [$r = \PATH . \D => '.' . \D]) . \D . 'y' . \D . $k . \D;
                $content = \abort(\i('Requires both a %s file and a %s file to run.', [
                    '<code>' . $v . 'index.php</code>',
                    '<code>' . (0 === \strpos($content[0], $r) ? \strtr($content[0], [$r => '.' . \D]) : $v . \strtr($content[0], '/', \D) . '.php') . '</code>',
                ]));
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
        \State::set('[x]', []);
        foreach (['are', 'as', 'can', 'has', 'is', 'not', 'of', 'with'] as $v) {
            foreach ((array) \State::get($v, true) as $kk => $vv) {
                \State::set('[y].' . $v . ':' . $kk, $vv);
            }
        }
        if ($x = \State::get('is.error')) {
            \State::set('[y].error:' . $x, true);
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

namespace x\layout\route {
    function page($content) {
        if (\is_array($content) && \class_exists("\\Page")) {
            $page = \lot('page') ?? new \Page;
            if ($page && $page instanceof \Page && $page->exist() && ($layout = $page->layout)) {
                // `$content = ['/lot/y/log/page/video.php', [], 200];`
                if (0 === \strpos($layout, '/')) {
                    $layout = \PATH . \strtr($layout, '/', \D);
                    $layout = \stream_resolve_include_path($layout) ?: stream_resolve_include_path($layout . '.php');
                }
                // `$content = ['page/video', [], 200];`
                $content[0] = $layout;
            }
        }
        return $content;
    }
    \Hook::set('route', __NAMESPACE__ . "\\page", 900);
}