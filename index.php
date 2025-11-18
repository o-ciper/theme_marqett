<?php
require __DIR__ . '/vendor/autoload.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * Preview index.php (fixed)
 * - All Twig functions/filters/globals are registered BEFORE rendering.
 * - Provides sample data to allow rendering of theme Twig templates locally.
 */

// -----------------------------
// Simple Preview Product object
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
        $this->primaryImage = (object)[
            'thumbUrl' => $imageUrl,
            'alt' => $name,
        ];
        $this->brand = $brand ? (object)$brand : null;
        if ($this->discountedPrice < $this->price) {
            $this->isDiscounted = true;
            $this->discountPercent = round((($this->price - $this->discountedPrice) / max(0.01, $this->price)) * 100);
        }
        $this->url = '/product/' . $this->id;
    }

    public function priceWithTax($currency = null) {
        return $this->price;
    }

    public function discountedPriceWithTax($currency = null) {
        return $this->discountedPrice;
    }
}

// -----------------------------
// Twig setup
// -----------------------------
$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader, [
    'cache' => false,
    'auto_reload' => true,
    'debug' => true,
]);

// -----------------------------
// Utility: guarded registration helpers
// -----------------------------
function twig_has_function(Environment $twig, string $name): bool {
    try {
        if (method_exists($twig, 'getFunction')) {
            return $twig->getFunction($name) !== null;
        }
        if (method_exists($twig, 'getFunctions')) {
            $funcs = $twig->getFunctions();
            foreach ($funcs as $f) {
                if ($f instanceof \Twig\TwigFunction && $f->getName() === $name) return true;
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }
    return false;
}

function register_twig_function(Environment $twig, string $name, callable $callable, array $options = []) {
    if (twig_has_function($twig, $name)) return;
    try {
        $twig->addFunction(new TwigFunction($name, $callable, $options));
    } catch (\LogicException $e) {
        // ignore duplicate registration races in preview
    }
}

function twig_has_filter(Environment $twig, string $name): bool {
    try {
        if (method_exists($twig, 'getFilter')) {
            return $twig->getFilter($name) !== null;
        }
        if (method_exists($twig, 'getFilters')) {
            $filters = $twig->getFilters();
            foreach ($filters as $f) {
                if ($f instanceof \Twig\TwigFilter && $f->getName() === $name) return true;
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }
    return false;
}

function register_twig_filter(Environment $twig, string $name, callable $callable, array $options = []) {
    if (twig_has_filter($twig, $name)) return;
    try {
        $twig->addFilter(new TwigFilter($name, $callable, $options));
    } catch (\LogicException $e) {
        // ignore duplicates
    }
}

// -----------------------------
// Load settings (if any) and defaults
// -----------------------------
$settings = [];
if (file_exists(__DIR__ . '/configs/settings_data.json')) {
    $settings = json_decode(file_get_contents(__DIR__ . '/configs/settings_data.json'), true) ?: [];
}

$defaults = [
    'banner_img_1' => 'assets/uploads/banner_img_1.png',
    'banner_img_2' => 'assets/uploads/banner_img_2.png',
    'banner_img_3' => 'assets/uploads/banner_img_3.png',
    'banner_img_4' => 'assets/uploads/banner_img_4.png',
    'banner_img_5' => 'assets/uploads/banner_img_5.png',
    'banner_img_6' => 'assets/uploads/banner_img_6.png',
    'banner_img_7' => 'assets/uploads/banner_img_7.png',
    'banner_link_1' => '',
    'banner_link_2' => '',
    'banner_link_3' => '',
    'banner_link_4' => '',
    'banner_link_5' => '',
    'banner_link_6' => '',
    'banner_link_7' => '',
    'banner_target_1' => 0,
    'banner_target_2' => 0,
    'banner_target_3' => 0,
    'banner_target_4' => 0,
    'banner_target_5' => 0,
    'banner_target_6' => 0,
    'banner_target_7' => 0,
    'slide_status' => true,
    'slide_varyant' => 'default',
    'home_title' => 'Featured Products',
    'featured_title' => 'Featured',
    'popular_title' => 'Popular',
    'new_title' => 'New',
    'discounted_title' => 'Discounted',
    'show_brands_carousel' => false,
    'brands_title' => '',
    'logo' => 'assets/uploads/logo.png',
    'cart_title' => 'Cart',
    'search_placeholder' => 'Search products...',
    'use_search_auto_complete' => false,
    'user_signup' => 'Sign up',
    'user_login' => 'Login',
    'user_myaccount' => 'My Account',
    'user_logout' => 'Logout',
    'label_new' => 'NEW',
    'label_digital' => 'DIGITAL',
    'label_soldout' => 'SOLD OUT',
    'showcase_view' => 'View',
    'addtocart_button' => 'Add to cart',
    'nostock_button' => 'Notify me',
    'navigation_showall' => 'Show all',
    'name' => 'Local Template Preview',
];

$theme_settings = array_replace($defaults, is_array($settings) && isset($settings['settings']) ? $settings['settings'] : $settings);

$theme = [
    'settings' => $theme_settings,
    'name' => $theme_settings['name'] ?? 'Local Template Preview',
];

// -----------------------------
// Minimal global data used by many snippets
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

$preferences = [
    'member_signup' => true,
    'default_currency' => 'USD',
    'sales_allowed' => true,
];

// navigationMenu mock (categories for the menu)
$navigationMenu = [
    'settings' => [
        'useCategoryImage' => true,
        'thirdLevelCategoryCount' => 6,
    ],
    'categories' => [
        [
            'name' => 'Clothing',
            'url' => '/category/clothing',
            'imageUrl' => 'assets/uploads/slider_picture_1.png',
            'subCategories' => [
                [
                    'name' => 'Men',
                    'url' => '/category/clothing/men',
                    'imageUrl' => 'assets/uploads/slider_picture_2.png',
                    'subCategories' => [
                        ['name' => 'Shirts', 'url' => '/category/clothing/men/shirts'],
                        ['name' => 'Pants', 'url' => '/category/clothing/men/pants'],
                        ['name' => 'Shoes', 'url' => '/category/clothing/men/shoes'],
                    ],
                ],
                [
                    'name' => 'Women',
                    'url' => '/category/clothing/women',
                    'imageUrl' => '',
                    'subCategories' => [
                        ['name' => 'Dresses', 'url' => '/category/clothing/women/dresses'],
                        ['name' => 'Shoes', 'url' => '/category/clothing/women/shoes'],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Electronics',
            'url' => '/category/electronics',
            'imageUrl' => '',
            'subCategories' => [
                [
                    'name' => 'Phones',
                    'url' => '/category/electronics/phones',
                    'imageUrl' => '',
                    'subCategories' => [],
                ],
            ],
        ],
    ],
];

// Sample brands for brand carousel
$brands = [
    ['name' => 'Brand A', 'logo' => '/assets/uploads/slider_picture_1.png', 'url' => '/brand/1'],
    ['name' => 'Brand B', 'logo' => '/assets/uploads/slider_picture_2.png', 'url' => '/brand/2'],
    ['name' => 'Brand C', 'logo' => '/assets/uploads/slider_picture_3.png', 'url' => '/brand/3'],
];

// -----------------------------
// Asset resolver
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

    // fallback: return /assets/<path>
    return '/assets/' . $path;
};

// -----------------------------
// Register Twig functions/filters (guarded) - BEFORE rendering
// -----------------------------
register_twig_function($twig, 'themeAsset', function ($path) use ($resolveAsset) {
    return $resolveAsset($path);
});

register_twig_function($twig, 'getNopicImageUrl', function () use ($resolveAsset) {
    return $resolveAsset('assets/uploads/nopic_image.png');
});

register_twig_function($twig, 'path', function ($route = 'entry', $params = []) {
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
        $q = http_build_query($params);
        $url .= ($q ? '?' . $q : '');
    }
    return $url;
});

// getProducts returns items as objects with 'product' property (PreviewProduct)
register_twig_function($twig, 'getProducts', function ($key = 'home', $options = []) use ($resolveAsset) {
    $sample = [];
    for ($i = 1; $i <= 12; $i++) {
        $price = rand(5, 200) + rand(0, 99)/100;
        $hasDiscount = ($i % 3) === 0;
        $discountedPrice = $hasDiscount ? round($price * (0.7 + (rand(0,20)/100)), 2) : null;
        $brand = ['url' => '/brand/' . $i, 'name' => 'Brand ' . $i];
        $image = $resolveAsset('assets/uploads/nopic_image.png');
        $product = new PreviewProduct($i, ucfirst($key) . " Product {$i}", $price, $discountedPrice, $image, $brand);
        if ($i % 4 === 0) $product->isNew = true;
        if ($i % 5 === 0) $product->hasGift = true;
        if ($i % 7 === 0) $product->realAmount = 0;
        $item = (object)['product' => $product];
        $sample[] = $item;
    }
    if (is_array($options) && isset($options['limit'])) {
        return array_slice($sample, 0, (int)$options['limit']);
    }
    return $sample;
});

register_twig_function($twig, 'getBrandsJson', function () use ($brands) {
    return json_encode($brands);
}, ['is_safe' => ['html']]);

register_twig_function($twig, 'macro_global', function (Environment $env, $name, $variant = 'default') use ($theme, $site, $visitor, $preferences) {
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

// Filters
register_twig_filter($twig, 'money', function ($v) {
    $n = is_numeric($v) ? (float)$v : floatval(preg_replace('/[^\d.-]/', '', (string)$v));
    return number_format($n, 2, '.', ',');
});

register_twig_filter($twig, 'currency', function ($v) {
    return '$' . number_format((float)$v, 2);
});

// -----------------------------
// Add Twig globals (must be done BEFORE any render/compile)
// -----------------------------
try {
    // addGlobal may throw if called too late; we are doing this before rendering
    $twig->addGlobal('theme', $theme);
    $twig->addGlobal('site', $site);
    $twig->addGlobal('visitor', $visitor);
    $twig->addGlobal('preferences', $preferences);
    $twig->addGlobal('navigationMenu', $navigationMenu);
    $twig->addGlobal('brands', $brands);
} catch (\LogicException $e) {
    // If this happens, it's likely the environment was used earlier; show a helpful message
    echo "<h2>Configuration error</h2><pre>Failed to register Twig globals: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
}

// -----------------------------
// Now render the entry template into $body
// -----------------------------
try {
    $body = $twig->render('html/templates/default/entry.twig', [
        'site_title' => $theme['name'],
    ]);
} catch (\Throwable $e) {
    echo "<h2>Twig error</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit(1);
}

// -----------------------------
// Prepare head assets (CSS / JS order)
// -----------------------------
$themeCss = $resolveAsset('assets/css/theme.css'); // compile SCSS -> CSS into this path for accurate styling
$themeJs  = $resolveAsset('assets/javascript/theme.js');
$navJs    = $resolveAsset('assets/javascript/navigation-menu.js');
$lazyJs   = $resolveAsset('assets/javascript/lazyload.min.js');
$jqueryCdn = 'https://code.jquery.com/jquery-3.6.0.min.js';

// -----------------------------
// Output a minimal HTML wrapper
// -----------------------------
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($theme['name']); ?></title>

<link rel="stylesheet" href="<?php echo htmlspecialchars($themeCss); ?>">

<!-- jQuery first so theme JS depending on it works -->
<script src="<?php echo htmlspecialchars($jqueryCdn); ?>"></script>

<!-- Theme JS (after jQuery) -->
<?php if ($themeJs): ?>
<script src="<?php echo htmlspecialchars($themeJs); ?>"></script>
<?php endif; ?>
<?php if ($navJs): ?>
<script src="<?php echo htmlspecialchars($navJs); ?>"></script>
<?php endif; ?>
<?php if (!empty($theme['settings']['use_lazy_load'])): ?>
<script src="<?php echo htmlspecialchars($lazyJs); ?>"></script>
<?php endif; ?>

</head>
<body>
<?php echo $body; ?>
</body>
</html>
