<?php
/**
 * compile-watch-scss.php
 *
 * Render SCSS that contains Twig placeholders and compile it to CSS.
 *  - Renders assets/scss/theme.scss through Twig (so {{ theme.settings.* }} and themeAsset() calls work)
 *  - Writes rendered SCSS to assets/scss/_rendered_theme.scss
 *  - Runs dart-sass (sass) to compile to assets/css/theme.css
 *  - Watches the source SCSS file for changes and recompiles automatically
 *
 * Usage:
 *   1) Ensure dependencies:
 *      - composer require twig/twig
 *      - Install dart-sass CLI (sass) and ensure it's on PATH (e.g. npm i -g sass or from https://sass-lang.com/install)
 *      - (Optional) inotifywait (inotify-tools) for a more efficient watch loop
 *
 *   2) From theme root (where html/, assets/, configs/ live) run:
 *      php compile-watch-scss.php
 *
 * The script falls back to a polling loop if inotify is not available.
 */

require __DIR__ . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

$projectRoot = realpath(__DIR__);
$scssTemplate = 'assets/scss/theme.scss';
$renderedScss = 'assets/scss/_rendered_theme.scss';
$outCss = 'assets/css/theme.css';
$sassCmd = 'sass'; // assume 'sass' is on PATH (dart-sass). You can edit this to full path if necessary.

/**
 * Resolve an asset path in the theme folder to a web-style path.
 * This is used by the themeAsset Twig function while rendering SCSS.
 */
$resolveAsset = function (string $path) use ($projectRoot) : string {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');

    $candidates = [
        $projectRoot . '/' . $path,
        $projectRoot . '/assets/' . $path,
        $projectRoot . '/assets/uploads/' . $path,
        $projectRoot . '/assets/images/' . $path,
        $projectRoot . '/assets/javascript/' . $path,
        $projectRoot . '/assets/css/' . $path,
        $projectRoot . '/' . basename($path),
    ];

    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $web = str_replace($projectRoot, '', $c);
            $web = ltrim($web, '/');
            return '/' . $web;
        }
    }
    // fallback: return a logical assets path
    return '/assets/' . $path;
};

// --------------------
// Load theme settings
// --------------------
$settings = [];
$settingsFile = $projectRoot . '/configs/settings_data.json';
if (file_exists($settingsFile)) {
    $raw = file_get_contents($settingsFile);
    $settings = json_decode($raw, true) ?: [];
} else {
    echo "[info] configs/settings_data.json not found — rendering with default/empty settings.\n";
}
$themeSettings = is_array($settings) && isset($settings['settings']) ? $settings['settings'] : $settings;
$theme = ['settings' => $themeSettings];

// --------------------
// Setup Twig
// --------------------
$loader = new FilesystemLoader($projectRoot);
$twig = new Environment($loader, ['cache' => false, 'auto_reload' => true]);

// Register a themeAsset Twig function so theme.scss can call themeAsset('...') while rendering
try {
    $twig->addFunction(new TwigFunction('themeAsset', function ($p) use ($resolveAsset) {
        return $resolveAsset((string)$p);
    }));
} catch (\LogicException $e) {
    // If already registered for some reason, ignore
}

// Other helper shims that sometimes appear in SCSS (safe to add)
try {
    $twig->addFunction(new TwigFunction('getNopicImageUrl', function () use ($resolveAsset) {
        return $resolveAsset('assets/uploads/nopic_image.png');
    }));
} catch (\LogicException $e) { /* ignore */ }

// Add the theme global (so {{ theme.settings... }} works)
try {
    $twig->addGlobal('theme', $theme);
} catch (\LogicException $e) {
    // If adding global failed, abort because SCSS rendering will not work reliably
    echo "[error] Failed to add Twig global 'theme': " . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Render SCSS via Twig and compile to CSS.
 * Returns true on success, false on failure.
 */
function render_and_compile(Environment $twig, $scssTemplate, $renderedScss, $outCss, $sassCmd) {
    $projectRoot = realpath(__DIR__);
    $scssTemplatePath = $projectRoot . '/' . $scssTemplate;
    if (!file_exists($scssTemplatePath)) {
        echo "[error] SCSS template not found: {$scssTemplatePath}\n";
        return false;
    }

    // Render using Twig
    try {
        $rendered = $twig->render($scssTemplate);
    } catch (\Throwable $e) {
        echo "[error] Twig render error: " . $e->getMessage() . PHP_EOL;
        return false;
    }

    // Ensure destination dir exists
    $renderedPath = $projectRoot . '/' . $renderedScss;
    $renderedDir = dirname($renderedPath);
    if (!is_dir($renderedDir)) mkdir($renderedDir, 0755, true);
    file_put_contents($renderedPath, $rendered);
    echo "[info] Rendered SCSS -> {$renderedScss}\n";

    // Ensure output directory exists
    $outCssPath = $projectRoot . '/' . $outCss;
    $outCssDir = dirname($outCssPath);
    if (!is_dir($outCssDir)) mkdir($outCssDir, 0755, true);

    // Run sass command
    $cmd = escapeshellcmd($sassCmd) . ' ' . escapeshellarg($renderedPath) . ' ' . escapeshellarg($outCssPath) . ' --no-source-map --style=expanded 2>&1';
    echo "[info] Running: {$cmd}\n";
    $output = null;
    $returnVar = null;
    // Use passthru for live streaming output if available
    $output = shell_exec($cmd);
    if ($output === null) {
        echo "[warning] No output from sass command. Ensure 'sass' CLI is installed and shell_exec is enabled.\n";
        return false;
    }
    echo $output . PHP_EOL;
    if (!file_exists($outCssPath)) {
        echo "[error] sass did not produce output CSS at {$outCss}\n";
        return false;
    }
    echo "[info] Compiled CSS -> {$outCss}\n";
    return true;
}

// Initial compile
echo "=== SCSS render+compile (initial) ===\n";
render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);

// --------------------
// Watch loop
// --------------------
// Prefer: PHP inotify extension -> inotifywait (external) -> polling fallback

$sourceFile = $projectRoot . '/' . $scssTemplate;
if (!file_exists($sourceFile)) {
    echo "[error] Source SCSS file not found: {$sourceFile}\n";
    exit(1);
}

// Helper: touch watcher to avoid continuously recompiling on very frequent edits
$lastCompile = time();

// Use inotify extension if available
if (extension_loaded('inotify')) {
    echo "[info] Using PHP inotify extension to watch {$scssTemplate}\n";
    $fd = inotify_init();
    stream_set_blocking($fd, 0);
    $watchDescriptor = inotify_add_watch($fd, $sourceFile, IN_MODIFY);
    if ($watchDescriptor === false) {
        echo "[warning] inotify_add_watch failed; falling back to polling\n";
    } else {
        while (true) {
            $events = inotify_read($fd);
            if ($events) {
                foreach ($events as $ev) {
                    if ($ev['mask'] & IN_MODIFY) {
                        $now = time();
                        if ($now - $lastCompile < 1) continue; // debounce 1s
                        echo "[info] Detected change to {$scssTemplate} (inotify)\n";
                        render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
                        $lastCompile = $now;
                    }
                }
            }
            usleep(250000);
        }
    }
}

// Next: try inotifywait (external)
$inotifywaitPath = trim((string)shell_exec('which inotifywait 2>/dev/null'));
if ($inotifywaitPath) {
    echo "[info] Using inotifywait ({$inotifywaitPath}) to watch {$scssTemplate}\n";
    // This will block until a modify event occurs
    while (true) {
        $cmd = escapeshellcmd($inotifywaitPath) . ' -e modify -q ' . escapeshellarg($sourceFile) . ' 2>&1';
        // This call blocks; when it returns, a modify happened.
        $ret = null;
        $out = shell_exec($cmd);
        // Debounce and compile
        $now = time();
        if ($now - $lastCompile < 1) {
            // skip
        } else {
            echo "[info] Detected change to {$scssTemplate} (inotifywait)\n";
            render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
            $lastCompile = $now;
        }
        // small sleep to avoid busy loop edgecases
        usleep(300000);
    }
}

// Fallback: polling filemtime
echo "[info] inotify not available; using polling. Watching {$scssTemplate} for changes...\n";
$lastMTime = filemtime($sourceFile);
while (true) {
    clearstatcache(false, $sourceFile);
    $m = filemtime($sourceFile);
    if ($m !== $lastMTime) {
        $lastMTime = $m;
        $now = time();
        if ($now - $lastCompile >= 1) {
            echo "[info] Detected change to {$scssTemplate} (poll)\n";
            render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
            $lastCompile = $now;
        }
    }
    sleep(1);
}
