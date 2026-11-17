<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}

if (!isset($pageTitle)) {
    $pageTitle = 'SEO Defaults';
}

if (!isset($metaDescription)) {
    $metaDescription = 'Manage SEO Defaults';
}

if (!isset($settings)) {
    $settings = [];
}

$schemaValue = '';
if (isset($settings['default_schema'])) {
    $schemaValue = $settings['default_schema'];
}

$customHeadValue = '';
if (isset($settings['default_custom_head'])) {
    $customHeadValue = $settings['default_custom_head'];
}

$customBodyValue = '';
if (isset($settings['default_custom_body'])) {
    $customBodyValue = $settings['default_custom_body'];
}

$customFooterValue = '';
if (isset($settings['default_custom_footer'])) {
    $customFooterValue = $settings['default_custom_footer'];
}

$ogDescValue = '';
if (isset($settings['default_og_desc'])) {
    $ogDescValue = $settings['default_og_desc'];
}

$twitterDescValue = '';
if (isset($settings['default_twitter_desc'])) {
    $twitterDescValue = $settings['default_twitter_desc'];
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css" nonce="<?= $nonce ?>">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/../_sidebar.php'; ?>

        <!-- Main content -->
        <section class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">SEO Defaults</h1>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/dashboard">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?= $base ?>/dashboard/settings/update" novalidate>
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                        <div class="mb-3">
                            <label for="default_schema" class="form-label">Schema Markup</label>
                            <textarea class="form-control" id="default_schema" name="default_schema" rows="6" placeholder="Paste default JSON-LD here"><?= htmlspecialchars((string) $schemaValue) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_custom_head" class="form-label">Custom Head Tags</label>
                            <textarea class="form-control" id="default_custom_head" name="default_custom_head" rows="4" placeholder="Paste default tracking codes (GA, GTM, hreflang) here"><?= htmlspecialchars((string) $customHeadValue) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_custom_body" class="form-label">Custom Body (After &lt;body&gt;)</label>
                            <textarea class="form-control" id="default_custom_body" name="default_custom_body" rows="4" placeholder="Paste default tags to inject after the opening body tag"><?= htmlspecialchars((string) $customBodyValue) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_custom_footer" class="form-label">Custom Footer (Before &lt;/body&gt;)</label>
                            <textarea class="form-control" id="default_custom_footer" name="default_custom_footer" rows="4" placeholder="Paste default tags to inject before the closing body tag"><?= htmlspecialchars((string) $customFooterValue) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_og_desc" class="form-label">OG Description</label>
                            <textarea class="form-control" id="default_og_desc" name="default_og_desc" rows="3"><?= htmlspecialchars((string) $ogDescValue) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="default_twitter_desc" class="form-label">Twitter Description</label>
                            <textarea class="form-control" id="default_twitter_desc" name="default_twitter_desc" rows="3"><?= htmlspecialchars((string) $twitterDescValue) ?></textarea>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Save SEO Defaults</button>
                            <a class="btn btn-secondary" href="<?= $base ?>/dashboard">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
