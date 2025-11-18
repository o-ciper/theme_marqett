<?php
/**
 * compile-watch-scss.php (improved)
 *
 * Render SCSS (with Twig placeholders) and compile it to CSS.
 * - Renders assets/scss/theme.scss through Twig (so {{ theme.settings.* }} and themeAsset() calls work)
 * - Writes rendered SCSS to assets/scss/_rendered_theme.scss
 * - Runs dart-sass (sass) to compile to assets/css/theme.css
 * - Watches the source SCSS file for changes and recompiles automatically
 *
 * Usage:
 *   1) Ensure dependencies:
 *      - composer require twig/twig
 *      - Install dart-sass CLI (sass) and ensure it's on PATH:
 *           npm install -g sass
 *         or follow https://sass-lang.com/install
 *   2) From theme root run:
 *        php compile-watch-scss.php
 *
 * Notes:
 *  - If the sass CLI is missing the script will exit with a helpful message.
 *  - The script prints sass output and returns a non-zero status on sass failures.
 */

require __DIR__ . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

$projectRoot = realpath(__DIR__);
$scssTemplate = 'assets/scss/theme.scss';
$renderedScss = 'assets/scss/_rendered_theme.scss';
$outCss = 'assets/css/theme.css';
$sassCmd = 'sass'; // change to full path if necessary

// --------------------
// Check sass CLI presence
// --------------------
$whichSass = trim((string)shell_exec('which ' . escapeshellcmd($sassCmd) . ' 2>/dev/null'));
if ($whichSass === '') {
    fwrite(STDERR, "[error] 'sass' CLI not found on PATH.\n");
    fwrite(STDERR, "Install dart-sass and ensure 'sass' is available. Example:\n");
    fwrite(STDERR, "  npm install -g sass\n");
    fwrite(STDERR, "or visit https://sass-lang.com/install\n");
    exit(2);
}
$sassCmd = $whichSass; // use full path

// --------------------
// Simple asset resolver for themeAsset used in SCSS rendering
// --------------------
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
    return '/assets/' . $path;
};

// --------------------
// Load settings
// --------------------
$settings = [];
$settingsFile = $projectRoot . '/configs/settings_data.json';
if (file_exists($settingsFile)) {
    $raw = file_get_contents($settingsFile);
    $settings = json_decode($raw, true) ?: [];
} else {
    echo "[info] configs/settings_data.json not found; rendering with defaults.\n";
}
$themeSettings = is_array($settings) && isset($settings['settings']) ? $settings['settings'] : $settings;
$theme = ['settings' => $themeSettings];

// --------------------
// Setup Twig and register helper functions BEFORE rendering
// --------------------
$loader = new FilesystemLoader($projectRoot);
$twig = new Environment($loader, ['cache' => false, 'auto_reload' => true]);

// themeAsset (so theme.scss can call themeAsset('...'))
try {
    $twig->addFunction(new TwigFunction('themeAsset', function ($p) use ($resolveAsset) {
        return $resolveAsset((string)$p);
    }));
} catch (\LogicException $e) {
    // Already registered; ignore
}

// getNopicImageUrl (safe fallback)
try {
    $twig->addFunction(new TwigFunction('getNopicImageUrl', function () use ($resolveAsset) {
        return $resolveAsset('assets/uploads/nopic_image.png');
    }));
} catch (\LogicException $e) { /* ignore */ }

// Add theme global so {{ theme.settings.* }} works
try {
    $twig->addGlobal('theme', $theme);
} catch (\LogicException $e) {
    fwrite(STDERR, "[error] Failed to add Twig global 'theme': " . $e->getMessage() . "\n");
    exit(3);
}

/**
 * Render SCSS through Twig and compile it to CSS using dart-sass.
 * Returns true on success.
 */
function render_and_compile(Environment $twig, string $scssTemplate, string $renderedScss, string $outCss, string $sassCmd) : bool {
    $projectRoot = realpath(__DIR__);
    $scssTemplatePath = $projectRoot . '/' . $scssTemplate;
    if (!file_exists($scssTemplatePath)) {
        fwrite(STDERR, "[error] SCSS template not found: {$scssTemplatePath}\n");
        return false;
    }

    // Render using Twig
    try {
        $rendered = $twig->render($scssTemplate);
    } catch (\Throwable $e) {
        fwrite(STDERR, "[error] Twig render error: " . $e->getMessage() . PHP_EOL);
        return false;
    }

    // Write rendered SCSS
    $renderedPath = $projectRoot . '/' . $renderedScss;
    $renderedDir = dirname($renderedPath);
    if (!is_dir($renderedDir)) mkdir($renderedDir, 0755, true);
    file_put_contents($renderedPath, $rendered);
    echo "[info] Rendered SCSS -> {$renderedScss}\n";

    // Ensure output directory exists
    $outCssPath = $projectRoot . '/' . $outCss;
    $outCssDir = dirname($outCssPath);
    if (!is_dir($outCssDir)) mkdir($outCssDir, 0755, true);

    // Run sass CLI and capture exit status and output
    $cmd = escapeshellarg($sassCmd) . ' ' . escapeshellarg($renderedPath) . ' ' . escapeshellarg($outCssPath) . ' --no-source-map --style=expanded';
    echo "[info] Running: {$cmd}\n";
    $outputLines = [];
    $ret = 0;
    exec($cmd . ' 2>&1', $outputLines, $ret);
    $output = implode(PHP_EOL, $outputLines);
    if ($ret !== 0) {
        fwrite(STDERR, "[error] sass failed (exit {$ret}). Output:\n");
        fwrite(STDERR, $output . PHP_EOL);
        // If sass failed, remove possibly stale output CSS (avoid using broken file)
        if (file_exists($outCssPath)) {
            @unlink($outCssPath);
            echo "[info] Removed stale output CSS: {$outCss}\n";
        }
        return false;
    }

    if (!file_exists($outCssPath)) {
        fwrite(STDERR, "[error] sass reported success but CSS file not found: {$outCssPath}\n");
        return false;
    }

    echo "[info] Compiled CSS -> {$outCss}\n";
    return true;
}

// --------------------
// Initial render + compile
// --------------------
echo "=== SCSS render+compile (initial) ===\n";
$ok = render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
if (! $ok) {
    fwrite(STDERR, "[error] Initial compilation failed. Fix the errors above and re-run the script.\n");
}

// --------------------
// Watch loop (inotify -> inotifywait -> polling fallback)
// --------------------
$sourceFile = $projectRoot . '/' . $scssTemplate;
if (!file_exists($sourceFile)) {
    fwrite(STDERR, "[error] Source SCSS file not found: {$sourceFile}\n");
    exit(1);
}

$lastCompile = time();
if (extension_loaded('inotify')) {
    echo "[info] Using PHP inotify to watch {$scssTemplate}\n";
    $fd = inotify_init();
    stream_set_blocking($fd, 0);
    $wd = inotify_add_watch($fd, $sourceFile, IN_MODIFY);
    if ($wd === false) {
        echo "[warning] inotify_add_watch failed; falling back to polling\n";
    } else {
        while (true) {
            $events = inotify_read($fd);
            if ($events) {
                foreach ($events as $ev) {
                    if ($ev['mask'] & IN_MODIFY) {
                        $now = time();
                        if ($now - $lastCompile < 1) continue;
                        echo "[info] Change detected (inotify). Recompiling...\n";
                        render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
                        $lastCompile = $now;
                    }
                }
            }
            usleep(250000);
        }
    }
}

$inotifywaitPath = trim((string)shell_exec('which inotifywait 2>/dev/null'));
if ($inotifywaitPath) {
    echo "[info] Using inotifywait ({$inotifywaitPath}) to watch {$scssTemplate}\n";
    while (true) {
        $cmd = escapeshellcmd($inotifywaitPath) . ' -e modify -q ' . escapeshellarg($sourceFile) . ' 2>&1';
        shell_exec($cmd);
        $now = time();
        if ($now - $lastCompile >= 1) {
            echo "[info] Change detected (inotifywait). Recompiling...\n";
            render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
            $lastCompile = $now;
        }
        usleep(300000);
    }
}

echo "[info] inotify not available; using polling. Watching {$scssTemplate} for changes...\n";
$lastMTime = filemtime($sourceFile);
while (true) {
    clearstatcache(false, $sourceFile);
    $m = filemtime($sourceFile);
    if ($m !== $lastMTime) {
        $lastMTime = $m;
        $now = time();
        if ($now - $lastCompile >= 1) {
            echo "[info] Change detected (poll). Recompiling...\n";
            render_and_compile($twig, $scssTemplate, $renderedScss, $outCss, $sassCmd);
            $lastCompile = $now;
        }
    }
    sleep(1);
}
