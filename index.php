<?php
require __DIR__ . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * Consolidated preview index.php — fixed ordering and safe shims
 *
 * What this does (high level)
 * - Registers required Twig helper shims (themeAsset, getBrandsJson, getNopicImageUrl, getProducts, macro_global, path)
 *   and marks getBrandsJson as safe for HTML output so Twig won't escape it.
 * - Adds Twig globals (theme, site, visitor, preferences, navigationMenu, brands) before rendering.
 * - Ensures required third-party libs (jQuery, Popper, Bootstrap CSS/JS, Font Awesome, Slick) are included in the <head>
 *   where appropriate (jQuery must be present before any JS that expects it).
 * - Ensures theme JS (theme.js, navigation-menu.js, product.js) are loaded after the body so inline template JS
 *   (for example: var navigationMenu = ...) that appears in header.twig runs first.
 * - Provides a minimal IdeaApp / IdeaCart shim in the head so theme scripts won't fail when run outside
 *   the Ideasoft platform.
 *
 * Notes on addressing the errors you reported:
 * - Unexpected token '&' when parsing brands: getBrandsJson is returned as a JSON string and marked safe to prevent
 *   Twig from escaping it (so {{ getBrandsJson() }} will output a raw JSON string and scripts like
 *   var brands = JSON.parse('{{ getBrandsJson() }}') will parse correctly). If your templates use JSON.parse on a
 *   quoted string, marking the function safe avoids HTML-entity escaping.
 * - "$ is not defined" / "jQuery is not defined": jQuery is now loaded in <head> before any scripts in the body run.
 * - "navigationMenu is not defined": templates render an inline "var navigationMenu = ..." inside header; theme JS is
 *   loaded AFTER the body to ensure that inline variables are defined before external JS runs.
 * - "IdeaCart / IdeaApp is not defined": an inline lightweight shim is declared in <head>. Expand as needed later.
 */

// -----------------------------
// Preview product class
// -----------------------------
class PreviewProduct {
    public $id;
    public $url;
    public $fullName;
    public $brand;
    public $primaryImage;
    public $isNew = false;
    public $hasGift = false;
    public $isDigitalProduct = false;
    public $realAmount = 10;
    public $isDiscounted = false;
    public $discountPercent = 0;
    protected $price;
    protected $discountedPrice;

    public function __construct($id, $name, $price, $discountedPrice = null, $imageUrl = '', $brand = null) {
        $this->id = $id;
        $this->fullName = $name;
        $this->price = (float)$price;
        $this->discountedPrice = $discountedPrice !== null ? (float)$discountedPrice : $this->price;
        $this->primaryImage = (object)['thumbUrl' => $imageUrl, 'alt' => $name];
        $this->brand = $brand ? (object)$brand : null;
        if ($this->discountedPrice < $this->price) {
            $this->isDiscounted = true;
            $this->discountPercent = (int)round((($this->price - $this->discountedPrice) / max(0.01, $this->price)) * 100);
        }
        $this->url = '/product/' . $this->id;
    }

    public function priceWithTax($currency = null) { return $this->price; }
    public function discountedPriceWithTax($currency = null) { return $this->discountedPrice; }
}

// -----------------------------
// Twig environment
// -----------------------------
$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader, [
    'cache' => false,
    'auto_reload' => true,
    'debug' => true,
]);

// -----------------------------
// Safe registration helpers (local registry to avoid double-add races)
// -----------------------------
$registeredFunctions = [];
$registeredFilters = [];

function safe_add_function(Environment $twig, array &$registry, string $name, callable $callable, array $options = []) {
    if (isset($registry[$name])) return;
    try {
        $twig->addFunction(new TwigFunction($name, $callable, $options));
        $registry[$name] = true;
    } catch (\LogicException $e) {
        // ignore duplicate registration/race
        $registry[$name] = true;
    }
}

function safe_add_filter(Environment $twig, array &$registry, string $name, callable $callable, array $options = []) {
    if (isset($registry[$name])) return;
    try {
        $twig->addFilter(new TwigFilter($name, $callable, $options));
        $registry[$name] = true;
    } catch (\LogicException $e) {
        $registry[$name] = true;
    }
}

// -----------------------------
// Simple asset resolver to produce web paths (for themeAsset in Twig)
// -----------------------------
$resolveAsset = function (string $path) {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');
    $candidates = [
        __DIR__ . '/' . $path,
        __DIR__ . '/assets/' . $path,
        __DIR__ . '/assets/uploads/' . $path,
        __DIR__ . '/assets/images/' . $path,
        __DIR__ . '/assets/javascript/' . $path,
        __DIR__ . '/assets/css/' . $path,
        __DIR__ . '/' . basename($path),
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) {
            $web = str_replace(__DIR__, '', $c);
            $web = ltrim($web, '/');
            return '/' . $web;
        }
    }
    return '/assets/' . $path;
};

// -----------------------------
// Load theme settings (if present) and defaults
// -----------------------------
$settings = [];
if (file_exists(__DIR__ . '/configs/settings_data.json')) {
    $settings = json_decode(file_get_contents(__DIR__ . '/configs/settings_data.json'), true) ?: [];
}
$defaults = [
    'logo' => 'assets/uploads/logo.png',
    'name' => 'Local Template Preview',
    'use_lazy_load' => false,
    'cart_title' => 'Cart',
    'search_placeholder' => 'Search products...',
];
$theme_settings = array_replace($defaults, is_array($settings) && isset($settings['settings']) ? $settings['settings'] : $settings);
$theme = ['settings' => $theme_settings, 'name' => $theme_settings['name'] ?? 'Local Template Preview'];

// -----------------------------
// Basic mocks for templates
// -----------------------------
$site = [
    'menu_items' => [
        'row1' => [
            ['link' => '/about', 'target' => '', 'label' => 'About'],
            ['link' => '/contact', 'target' => '', 'label' => 'Contact'],
            ['link' => '/blog', 'target' => '', 'label' => 'Blog'],
        ],
        'row2' => [
            ['link' => '/sale', 'target' => '', 'label' => 'Sale'],
        ],
    ],
];

$visitor = ['isMember' => false];
$preferences = ['member_signup' => true, 'default_currency' => 'USD', 'sales_allowed' => true];

$navigationMenu = [
    'settings' => ['useCategoryImage' => true, 'thirdLevelCategoryCount' => 6],
    'categories' => [
        ['name' => 'Clothing', 'url' => '/category/clothing', 'imageUrl' => 'assets/uploads/slider_picture_1.png', 'subCategories' => []],
    ],
];

$brands = [
    ['name' => 'Brand A', 'logo' => '/assets/uploads/slider_picture_1.png', 'url' => '/brand/1'],
    ['name' => 'Brand B', 'logo' => '/assets/uploads/slider_picture_2.png', 'url' => '/brand/2'],
];

// -----------------------------
// Register Twig helper shims (guarded)
// -----------------------------
safe_add_function($twig, $registeredFunctions, 'themeAsset', function ($path) use ($resolveAsset) {
    return $resolveAsset($path);
});

safe_add_function($twig, $registeredFunctions, 'getNopicImageUrl', function () use ($resolveAsset) {
    return $resolveAsset('assets/uploads/nopic_image.png');
});

safe_add_function($twig, $registeredFunctions, 'path', function ($route = 'entry', $params = []) {
    $map = [
        'entry' => '/',
        'member_login' => '/member/login',
        'member_signup' => '/member/signup',
        'member_index' => '/member',
        'member_logout' => '/member/logout',
        'cart_index' => '/cart',
        'search' => '/search',
    ];
    $url = $map[$route] ?? '#';
    if (!empty($params) && is_array($params)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $url;
});

// getProducts -> produce sample items with PreviewProduct instances
safe_add_function($twig, $registeredFunctions, 'getProducts', function ($key = 'home', $options = []) use ($resolveAsset) {
    $sample = [];
    for ($i = 1; $i <= 12; $i++) {
        $price = rand(5, 200) + rand(0, 99) / 100;
        $hasDiscount = ($i % 3) === 0;
        $discountedPrice = $hasDiscount ? round($price * (0.7 + (rand(0, 20) / 100)), 2) : null;
        $brand = ['url' => '/brand/' . $i, 'name' => 'Brand ' . $i];
        $image = $resolveAsset('assets/uploads/nopic_image.png');
        $product = new PreviewProduct($i, ucfirst($key) . " Product {$i}", $price, $discountedPrice, $image, $brand);
        if ($i % 4 === 0) $product->isNew = true;
        if ($i % 5 === 0) $product->hasGift = true;
        if ($i % 7 === 0) $product->realAmount = 0;
        $sample[] = (object)['product' => $product];
    }
    if (is_array($options) && isset($options['limit'])) {
        return array_slice($sample, 0, (int)$options['limit']);
    }
    return $sample;
});

// getBrandsJson: return JS-safe JSON string, mark as safe for Twig so it won't be escaped
safe_add_function($twig, $registeredFunctions, 'getBrandsJson', function () use ($brands) {
    // JSON_HEX_* ensures quotes/apostrophes won't break when embedded in a quoted JS string
    return json_encode($brands, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}, ['is_safe' => ['html']]);

// macro_global: tries several snippet naming variants and renders the first found
safe_add_function($twig, $registeredFunctions, 'macro_global', function (Environment $env, $name, $variant = 'default') use ($theme, $site, $visitor, $preferences) {
    $kebab = strtolower(preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name));
    $kebab = str_replace('_', '-', $kebab);

    $candidates = [
        "html/snippets/{$name}/{$variant}.twig",
        "html/snippets/{$kebab}/{$variant}.twig",
        "html/snippets/{$name}.twig",
        "html/snippets/{$kebab}.twig",
        "html/snippets/{$name}_{$variant}.twig",
        "html/snippets/{$kebab}_{$variant}.twig",
    ];
    foreach ($candidates as $tpl) {
        if ($env->getLoader()->exists($tpl)) {
            return $env->render($tpl, [
                'theme' => $theme,
                'site' => $site,
                'visitor' => $visitor,
                'preferences' => $preferences,
                'variant' => $variant,
                'navigationMenu' => $env->getGlobals()['navigationMenu'] ?? null,
            ]);
        }
    }
    return '';
}, ['needs_environment' => true, 'is_safe' => ['html']]);

// Filters (money, currency)
safe_add_filter($twig, $registeredFilters, 'money', function ($v) {
    $n = is_numeric($v) ? (float)$v : floatval(preg_replace('/[^\d.-]/', '', (string)$v));
    return number_format($n, 2, '.', ',');
});
safe_add_filter($twig, $registeredFilters, 'currency', function ($v) {
    return '$' . number_format((float)$v, 2);
});

// -----------------------------
// Add Twig globals BEFORE rendering templates
// -----------------------------
try {
    $twig->addGlobal('theme', $theme);
    $twig->addGlobal('site', $site);
    $twig->addGlobal('visitor', $visitor);
    $twig->addGlobal('preferences', $preferences);
    $twig->addGlobal('navigationMenu', $navigationMenu);
    $twig->addGlobal('brands', $brands);
} catch (\LogicException $e) {
    echo "<h2>Configuration error</h2><pre>Failed to register Twig globals: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
}

// -----------------------------
// Render the requested template into $body (entry page)
// -----------------------------
try {
    $body = $twig->render('html/templates/default/entry.twig', [
        'site_title' => $theme['name'],
    ]);
} catch (\Throwable $e) {
    echo "<h2>Twig render error</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
}

// -----------------------------
// HEAD libraries (jQuery must be ready before inline scripts in body)
// -----------------------------
$jqueryCdn = 'https://code.jquery.com/jquery-3.6.0.min.js';
$popperCdn = 'https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js';
$bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css';
$bootstrapJs = 'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js';
$fontAwesomeCss = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css';
$slickCss = 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css';
$slickJs = 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js';

// Local theme assets (compiled CSS if present and local JS)
$themeCss = $resolveAsset('assets/css/theme.css');
$themeJs  = $resolveAsset('assets/javascript/theme.js');
$navJs    = $resolveAsset('assets/javascript/navigation-menu.js');
$productJs = $resolveAsset('assets/javascript/product.js');
$elevateJs = $resolveAsset('assets/javascript/jquery.elevatezoom.js');
$lazyJs   = $resolveAsset('assets/javascript/lazyload.min.js');

// -----------------------------
// Output wrapper: include jQuery (and other libs) in head so any inline script produced by templates
// (for example var navigationMenu = ...) can safely exist and external theme JS is loaded AFTER the body.
// -----------------------------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($theme['name']); ?></title>

<!-- CSS libraries -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($bootstrapCss); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($slickCss); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($fontAwesomeCss); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($themeCss); ?>">

<!-- jQuery in head so $ is available for inline scripts in body -->
<script src="<?php echo htmlspecialchars($jqueryCdn); ?>"></script>
<!-- Popper + Bootstrap JS in head so components are available early if needed -->
<script src="<?php echo htmlspecialchars($popperCdn); ?>"></script>
<script src="<?php echo htmlspecialchars($bootstrapJs); ?>"></script>

<!-- IdeaApp / IdeaCart shim (lightweight) — expand if theme JS requires more API -->
<script>
window.IdeaApp = window.IdeaApp || {
    helpers: {
        getRouteGroup: function(){ return 'entry'; },
        matchMedia: function(q){ return window.matchMedia ? window.matchMedia(q).matches : false; }
    },
    plugins: {
        tab: function(){ /* no-op stub */ }
    },
    product: {
        productTab: function(){ /* no-op stub */ }
    },
    cart: {
        // minimal cart API used by theme.js; expand later if needed
        getCount: function(){ return 0; },
        add: function(){},
        remove: function(){}
    }
};
window.IdeaCart = window.IdeaCart || {
    // minimal shim referenced by theme.js
    getCount: function(){ return 0; },
    update: function(){},
};
</script>

</head>
<body>
<?php
// BODY content (the templates may emit inline <script> tags that define JS globals like navigationMenu)
echo $body;
?>

<!-- Third-party JS that should run after template inline vars are defined -->
<!-- Slick JS -->
<script src="<?php echo htmlspecialchars($slickJs); ?>"></script>

<!-- Local third-party libs (if present in the package) -->
<?php if ($elevateJs): ?><script src="<?php echo htmlspecialchars($elevateJs); ?>"></script><?php endif; ?>
<?php if ($lazyJs): ?><script src="<?php echo htmlspecialchars($lazyJs); ?>"></script><?php endif; ?>

<!-- Theme JS files (loaded after inline variables like navigationMenu are present) -->
<?php if ($themeJs): ?><script src="<?php echo htmlspecialchars($themeJs); ?>"></script><?php endif; ?>
<?php if ($navJs): ?><script src="<?php echo htmlspecialchars($navJs); ?>"></script><?php endif; ?>
<?php if ($productJs): ?><script src="<?php echo htmlspecialchars($productJs); ?>"></script><?php endif; ?>

</body>
</html>
