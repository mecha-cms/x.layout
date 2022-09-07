<?php

namespace {
    $GLOBALS['date'] = $GLOBALS['time'] = new \Time($_SERVER['REQUEST_TIME'] ?? \time());
    // Alias for `State`
    \class_alias("\\State", "\\Site");
    // Alias for `Time`
    \class_alias("\\Time", "\\Date");
    // Alias for `$state`
    $GLOBALS['site'] = $site = $state;
    // Base title for the layout
    $GLOBALS['t'] = $t = new \Anemone([$state->title], ' &#x00B7; ');
}

namespace x\layout {
    function content($content) {
        if (false !== \strpos($content, '</html>')) {
            return \preg_replace_callback('/<html(?:\s[^>]*)?>/', static function ($m) {
                if (
                    false !== \strpos($m[0], ' class="') ||
                    false !== \strpos($m[0], ' class ') ||
                    ' class>' === \substr($m[0], -7)
                ) {
                    $r = new \HTML($m[0]);
                    $c = true === $r['class'] ? [] : \preg_split('/\s+/', $r['class'] ?? "");
                    $c = \array_unique(\array_merge($c, \array_keys(\array_filter((array) \State::get('[y]', true)))));
                    \sort($c); // Sort class name(s)
                    $r['class'] = \trim(\implode(' ', $c));
                    return $r;
                }
                return $m[0];
            }, $content);
        }
        return $content;
    }
    function get() {
        if (!\class_exists("\\Asset")) {
            return;
        }
        foreach ($GLOBALS['Y'][1] ?? [] as $use) {
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
                        if ($path = \Asset::path(\dirname($use) . \D . $kk)) {
                            \Asset::let($kk);
                            \Asset::set($path, $vv['stack'], $vv[2]);
                        }
                    }
                }
            }
        }
    }
    function page($content) {
        if (\is_array($content) && \class_exists("\\Page")) {
            $page = $GLOBALS['page'] ?? new \Page;
            if ($page && $page instanceof \Page && $page->exist() && ($layout = $page->layout)) {
                // `$content = ['.\lot\y\log\page\gallery.php', [], 200];`
                if (0 === \strpos($layout, ".\\")) {
                    $layout = \stream_resolve_include_path(\PATH . \D . \strtr(\substr($layout, 2), ["\\" => \D]));
                }
                // `$content = ['page/gallery', [], 200];`
                $content[0] = $layout;
            }
        }
        return $content;
    }
    function route($content, $path) {
        \ob_start();
        \ob_start("\\ob_gzhandler");
        // `$content = ['page', [], 200];`
        if (\is_array($content) && isset($content[0]) && \is_string($content[0])) {
            if ($r = \Layout::get(...$content)) {
                $content = $r;
            } else if (\defined("\\TEST") && \TEST) {
                \status(403);
                $content = \abort(\i('Current route response is %s, but no layout file can be loaded because it does not meet the criteria or does not contain any %s file.', ['<code>' . \json_encode($content) . '</code>', '<code>index.php</code>']));
            }
        }
        echo \Hook::fire('content', [$content]);
        \ob_end_flush();
        // <https://www.php.net/manual/en/function.ob-get-length.php#59294>
        \header('content-length: ' . \ob_get_length());
        return \ob_get_clean();
    }
    \Hook::set('content', __NAMESPACE__ . "\\content", 20);
    \Hook::set('get', __NAMESPACE__ . "\\get", 0);
    \Hook::set('route', __NAMESPACE__ . "\\page", 900);
    \Hook::set('route', __NAMESPACE__ . "\\route", 1000);
}

namespace x\layout\state {
    function y() {
        foreach (['are', 'as', 'can', 'has', 'is', 'not', 'of', 'with'] as $v) {
            foreach ((array) \State::get($v, true) as $kk => $vv) {
                \State::set('[y].' . $v . ':' . $kk, $vv);
            }
        }
        if ($x = \State::get('is.error')) {
            \State::set('[y].error:' . $x, true);
        }
    }
    \Hook::set('content', __NAMESPACE__ . "\\y", 0);
}