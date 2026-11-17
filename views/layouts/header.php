<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}
// Set a default page title only if the controller did not provide one
if (!isset($pageTitle)) {
    $pageTitle = 'GudangPC';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
     $metaDescription = 'Perbandingan harga GudangPC';
}

// Summernote is only needed on dashboard forms that use rich-text editors.
if (!isset($loadSummernote)) {
    $loadSummernote = false;
}

// Optional SEO variables supplied by public controllers.
if (!isset($seo)) {
    $seo = null;
}
if (!is_array($seo)) {
    $seo = null;
}
if (!isset($canonicalUrl)) {
    $canonicalUrl = '';
}
if (!isset($ogImage)) {
    $ogImage = '';
}
if (!isset($ogType)) {
    $ogType = 'website';
}
if (!isset($robotsOverride)) {
    $robotsOverride = '';
}
if (!isset($jsonLd)) {
    $jsonLd = [];
}

$robotsValue = 'index, follow';
if ($seo !== null) {
    if (isset($seo['robots_tag']) && $seo['robots_tag'] !== '') {
        $robotsValue = (string) $seo['robots_tag'];
    }
}
if ($robotsOverride !== '') {
    $robotsValue = $robotsOverride;
}

$ogTitleValue = (string) $pageTitle;
$ogDescriptionValue = (string) $metaDescription;
$twitterTitleValue = '';
$twitterDescriptionValue = '';
$ogImageValue = '';

if ($seo !== null) {
    if (isset($seo['seo_title']) && $seo['seo_title'] !== '') {
        $ogTitleValue = (string) $seo['seo_title'];
    }
    if (isset($seo['og_title']) && $seo['og_title'] !== '') {
        $ogTitleValue = (string) $seo['og_title'];
    }
    if (isset($seo['seo_description']) && $seo['seo_description'] !== '') {
        $ogDescriptionValue = (string) $seo['seo_description'];
    }
    if (isset($seo['og_description']) && $seo['og_description'] !== '') {
        $ogDescriptionValue = (string) $seo['og_description'];
    }
    if (isset($seo['twitter_title']) && $seo['twitter_title'] !== '') {
        $twitterTitleValue = (string) $seo['twitter_title'];
    }
    if (isset($seo['twitter_description']) && $seo['twitter_description'] !== '') {
        $twitterDescriptionValue = (string) $seo['twitter_description'];
    }
    if (isset($seo['og_image']) && $seo['og_image'] !== '') {
        $ogImageValue = (string) $seo['og_image'];
        if (preg_match('~^https?://~i', $ogImageValue) !== 1) {
            $ogImageValue = \App\Core\Seo::absoluteUrl($ogImageValue);
        }
    }
}

if ($ogImageValue === '') {
    $ogImageValue = (string) $ogImage;
}

if ($twitterTitleValue === '') {
    $twitterTitleValue = $ogTitleValue;
}
if ($twitterDescriptionValue === '') {
    $twitterDescriptionValue = $ogDescriptionValue;
}

$twitterCardValue = 'summary';
if ($ogImageValue !== '') {
    $twitterCardValue = 'summary_large_image';
}

$schemaMarkupValue = '';
if ($seo !== null) {
    if (isset($seo['schema_markup']) && $seo['schema_markup'] !== '') {
        $schemaMarkupValue = \App\Core\Seo::cleanJsonLd((string) $seo['schema_markup']);
    }
}

$customHeadTagsValue = '';
if ($seo !== null) {
    if (isset($seo['custom_head_tags']) && $seo['custom_head_tags'] !== '') {
        $customHeadTagsValue = (string) $seo['custom_head_tags'];
    }
}

$customBodyValue = '';
if ($seo !== null) {
    if (isset($seo['custom_body']) && $seo['custom_body'] !== '') {
        $customBodyValue = (string) $seo['custom_body'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - GudangPC</title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="robots" content="<?= htmlspecialchars($robotsValue) ?>">
    <?php if ($canonicalUrl !== ''): ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <?php endif; ?>

    <meta property="og:title" content="<?= htmlspecialchars($ogTitleValue) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescriptionValue) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType) ?>">
    <?php if ($canonicalUrl !== ''): ?>
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="GudangPC">
    <?php if ($ogImageValue !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImageValue) ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="<?= htmlspecialchars($twitterCardValue) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitleValue) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescriptionValue) ?>">

    <?php
    $jsonLdToPrint = [];
    foreach ($jsonLd as $jsonLdString) {
        $skipProductSchema = false;
        if ($schemaMarkupValue !== '') {
            if (strpos((string) $jsonLdString, '"@type":"Product"') !== false) {
                $skipProductSchema = true;
            }
        }
        if (!$skipProductSchema) {
            $jsonLdToPrint[] = $jsonLdString;
        }
    }
    ?>
    <?php foreach ($jsonLdToPrint as $jsonLdString): ?>
    <?php /* Ready-made JSON-LD is printed raw on purpose; it is JSON generated by the application. */ ?>
    <script type="application/ld+json" nonce="<?= $nonce ?>"><?= $jsonLdString ?></script>
    <?php endforeach; ?>
    <?php if ($schemaMarkupValue !== ''): ?>
    <?php /* Admin-authored JSON-LD is validated by Seo::cleanJsonLd() and replaces the generated Product schema; printed raw on purpose. */ ?>
    <script type="application/ld+json" nonce="<?= $nonce ?>"><?= $schemaMarkupValue ?></script>
    <?php endif; ?>
    <?php if ($customHeadTagsValue !== ''): ?>
    <?php /* Admin-authored head tags from seo_meta, printed raw on purpose. */ ?>
    <?= $customHeadTagsValue ?>
    <?php endif; ?>

    <link rel="icon" type="image/x-icon" href="<?= $base ?>/favicon.ico"> 

    <link rel="stylesheet" href="<?= $base ?>/assets/css/bootstrap-icons.min.css?v=1.0.1">
    <?php if ($loadSummernote): ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/summernote-bs5.min.css?v=1.0.1" nonce="<?= $nonce ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= $base ?>/assets/css/bootstrap.min.css?v=1.0.1">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/frontend.css?v=1.0.1">
    
    <!-- CSP Nonce for inline scripts/styles -->
    <meta name="csp-nonce" content="<?= $nonce ?>">
    <meta name="csrf-token" content="<?= \App\Core\CsrfProtection::getToken() ?>">

</head>
<?php
if (!isset($bodyClass)) {
    $bodyClass = '';
}
?>
<body class="<?= htmlspecialchars($bodyClass) ?>">
<?php if ($customBodyValue !== ''): ?>
<?php /* Admin-authored body snippet from seo_meta, printed raw on purpose. */ ?>
<?= $customBodyValue ?>
<?php endif; ?>