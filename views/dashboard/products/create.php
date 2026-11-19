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
    $pageTitle = 'Create Product';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Create a new product';
}

if (!isset($error)) {
    $error = '';
}

$nameValue = '';
if (isset($old['name'])) {
    $nameValue = $old['name'];
}

$slugValue = '';
if (isset($old['slug'])) {
    $slugValue = $old['slug'];
}

$modelNumberValue = '';
if (isset($old['model_number'])) {
    $modelNumberValue = $old['model_number'];
}

$shortDescriptionValue = '';
if (isset($old['short_description'])) {
    $shortDescriptionValue = $old['short_description'];
}

$descriptionValue = '';
if (isset($old['description'])) {
    $descriptionValue = $old['description'];
}

$imageAltValue = '';
if (isset($old['image_alt'])) {
    $imageAltValue = $old['image_alt'];
}

$statusValue = 'draft';
if (isset($old['status'])) {
    $statusValue = $old['status'];
}

$offerVendorIdValue = '';
if (isset($old['offer_vendor_id'])) {
    $offerVendorIdValue = $old['offer_vendor_id'];
}

$offerPriceIdrValue = '';
if (isset($old['offer_price_idr'])) {
    $offerPriceIdrValue = $old['offer_price_idr'];
}

$offerAffiliateUrlValue = '';
if (isset($old['offer_affiliate_url'])) {
    $offerAffiliateUrlValue = $old['offer_affiliate_url'];
}

$offerInStockChecked = '';
if (isset($old['offer_in_stock']) && $old['offer_in_stock'] !== '') {
    $offerInStockChecked = 'checked';
}

$seoTitleValue = '';
if (isset($old['seo_title'])) {
    $seoTitleValue = $old['seo_title'];
}

$seoDescriptionValue = '';
if (isset($old['seo_description'])) {
    $seoDescriptionValue = $old['seo_description'];
}

$canonicalUrlValue = '';
if (isset($old['canonical_url'])) {
    $canonicalUrlValue = $old['canonical_url'];
}

$robotsTagValue = 'index, follow';
if (isset($old['robots_tag']) && $old['robots_tag'] !== '') {
    $robotsTagValue = $old['robots_tag'];
}

$ogDescriptionValue = '';
if (isset($old['og_description'])) {
    $ogDescriptionValue = $old['og_description'];
}

$twitterDescriptionValue = '';
if (isset($old['twitter_description'])) {
    $twitterDescriptionValue = $old['twitter_description'];
}

$schemaMarkupValue = '';
if (isset($old['schema_markup'])) {
    $schemaMarkupValue = $old['schema_markup'];
}

$customHeadTagsValue = '';
if (isset($old['custom_head_tags'])) {
    $customHeadTagsValue = $old['custom_head_tags'];
}

$customBodyValue = '';
if (isset($old['custom_body'])) {
    $customBodyValue = $old['custom_body'];
}

$customFooterValue = '';
if (isset($old['custom_footer'])) {
    $customFooterValue = $old['custom_footer'];
}

$statusDraftSelected = 'selected';
$statusPublishedSelected = '';
if ($statusValue === 'published') {
    $statusDraftSelected = '';
    $statusPublishedSelected = 'selected';
}

$robotsIndexFollowSelected = 'selected';
$robotsNoindexFollowSelected = '';
$robotsIndexNofollowSelected = '';
$robotsNoindexNofollowSelected = '';
if ($robotsTagValue === 'noindex, follow') {
    $robotsIndexFollowSelected = '';
    $robotsNoindexFollowSelected = 'selected';
} elseif ($robotsTagValue === 'index, nofollow') {
    $robotsIndexFollowSelected = '';
    $robotsIndexNofollowSelected = 'selected';
} elseif ($robotsTagValue === 'noindex, nofollow') {
    $robotsIndexFollowSelected = '';
    $robotsNoindexNofollowSelected = 'selected';
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
                <h1 class="h2">Create Product</h1>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/dashboard/products">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars((string) $error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="<?= $base ?>/dashboard/products/store" enctype="multipart/form-data" novalidate>
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                        <ul class="nav nav-tabs" id="productTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">General</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" type="button" role="tab" aria-controls="seo" aria-selected="false">SEO & Meta</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="productTabsContent">
                            <div class="tab-pane fade show active pt-3" id="general" role="tabpanel" aria-labelledby="general-tab">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) $nameValue) ?>" maxlength="200" required>
                                    <div class="form-text">Required. Maximum 200 characters.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="slug" class="form-label">Slug</label>
                                    <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars((string) $slugValue) ?>" maxlength="220" placeholder="leave-empty-to-generate">
                                    <div class="form-text">Optional. Leave empty to generate from the name.</div>
                                </div>

<div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <?php
                                            $categoryIdSelected = '';
                                            if (isset($old['category_id']) && $old['category_id'] == (int) $category['id']) {
                                                $categoryIdSelected = 'selected';
                                            }
                                            ?>
                                            <option value="<?= (int) $category['id'] ?>" <?= $categoryIdSelected ?>>
                                                <?= htmlspecialchars((string) $category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Required.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="brand_id" class="form-label">Brand</label>
                                    <select class="form-select" id="brand_id" name="brand_id">
                                        <option value="">Select brand</option>
                                        <?php foreach ($brands as $brand): ?>
                                            <?php
                                            $brandIdSelected = '';
                                            if (isset($old['brand_id']) && $old['brand_id'] == (int) $brand['id']) {
                                                $brandIdSelected = 'selected';
                                            }
                                            ?>
                                            <option value="<?= (int) $brand['id'] ?>" <?= $brandIdSelected ?>>
                                                <?= htmlspecialchars((string) $brand['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="model_number" class="form-label">Model Number</label>
                                    <input type="text" class="form-control" id="model_number" name="model_number" value="<?= htmlspecialchars((string) $modelNumberValue) ?>" maxlength="100">
                                </div>

                                <div class="mb-3">
                                    <label for="short_description" class="form-label">Short Description</label>
                                    <textarea class="form-control summernote" id="short_description" name="short_description" rows="3"><?= htmlspecialchars((string) $shortDescriptionValue) ?></textarea>
                                    <div class="form-text">Maximum 500 characters of text. Formatting is not saved.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control summernote" id="description" name="description" rows="5"><?= htmlspecialchars((string) $descriptionValue) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="image" class="form-label">Product Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                </div>

                                <div class="mb-3">
                                    <label for="image_alt" class="form-label">Image Alt Text</label>
                                    <input type="text" class="form-control" id="image_alt" name="image_alt" value="<?= htmlspecialchars((string) $imageAltValue) ?>" maxlength="255">
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?= $statusDraftSelected ?>>Draft</option>
                                        <option value="published" <?= $statusPublishedSelected ?>>Published</option>
                                    </select>
                                </div>

                                <hr>
                                <h5 class="mt-3">First offer (optional)</h5>
                                <p class="text-muted small">Leave all fields empty to add offers later.</p>

                                <div class="mb-3">
                                    <label for="offer_vendor_id" class="form-label">Seller</label>
                                    <select class="form-select" id="offer_vendor_id" name="offer_vendor_id">
                                        <option value="">Select vendor</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <?php
                                            $offerVendorSelected = '';
                                            if ($offerVendorIdValue !== '' && $offerVendorIdValue == (int) $vendor['id']) {
                                                $offerVendorSelected = 'selected';
                                            }
                                            ?>
                                            <option value="<?= (int) $vendor['id'] ?>" <?= $offerVendorSelected ?>>
                                                <?= htmlspecialchars((string) $vendor['name']) ?>
                                                (<?= htmlspecialchars((string) $vendor['store_name']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">The store is determined by the selected vendor.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="offer_price_idr" class="form-label">Price IDR</label>
                                    <input type="text" class="form-control" id="offer_price_idr" name="offer_price_idr" placeholder="Price IDR" min="1" value="<?= htmlspecialchars((string) $offerPriceIdrValue) ?>">
                                    <div class="form-text">Whole Rupiah amount, for example 1500000.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="offer_affiliate_url" class="form-label">Affiliate URL</label>
                                    <input type="url" class="form-control" id="offer_affiliate_url" name="offer_affiliate_url" placeholder="Affiliate URL" maxlength="5000" value="<?= htmlspecialchars((string) $offerAffiliateUrlValue) ?>">
                                    <div class="form-text">Must start with http:// or https://.</div>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="offer_in_stock" name="offer_in_stock" value="1" <?= $offerInStockChecked ?>>
                                    <label class="form-check-label" for="offer_in_stock">In Stock</label>
                                </div>
                            </div>

                            <div class="tab-pane fade pt-3" id="seo" role="tabpanel" aria-labelledby="seo-tab">
                                <div class="mb-3">
                                    <label for="seo_title" class="form-label">SEO Title (Browser Title)</label>
                                    <input type="text" class="form-control" id="seo_title" name="seo_title" value="<?= htmlspecialchars((string) $seoTitleValue) ?>" maxlength="255">
                                </div>

                                <div class="mb-3">
                                    <label for="seo_description" class="form-label">Meta Description (Shown in Google search)</label>
                                    <textarea class="form-control" id="seo_description" name="seo_description" rows="4"><?= htmlspecialchars((string) $seoDescriptionValue) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="canonical_url" class="form-label">Canonical URL</label>
                                    <input type="url" class="form-control" id="canonical_url" name="canonical_url" value="<?= htmlspecialchars((string) $canonicalUrlValue) ?>" maxlength="255" placeholder="Leave empty to use the page's own URL. Only enter a full URL (https://...) to override.">
                                </div>

                                <div class="mb-3">
                                    <label for="robots_tag" class="form-label">Robots Tag</label>
                                    <select class="form-select" id="robots_tag" name="robots_tag">
                                        <option value="index, follow" <?= $robotsIndexFollowSelected ?>>index, follow</option>
                                        <option value="noindex, follow" <?= $robotsNoindexFollowSelected ?>>noindex, follow</option>
                                        <option value="index, nofollow" <?= $robotsIndexNofollowSelected ?>>index, nofollow</option>
                                        <option value="noindex, nofollow" <?= $robotsNoindexNofollowSelected ?>>noindex, nofollow</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="og_description" class="form-label">OG Description</label>
                                    <textarea class="form-control" id="og_description" name="og_description" rows="3"><?= htmlspecialchars((string) $ogDescriptionValue) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="twitter_description" class="form-label">Twitter Description</label>
                                    <textarea class="form-control" id="twitter_description" name="twitter_description" rows="3"><?= htmlspecialchars((string) $twitterDescriptionValue) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="schema_markup" class="form-label">Schema Markup</label>
                                    <textarea class="form-control" id="schema_markup" name="schema_markup" rows="6" placeholder="Paste JSON-LD here"><?= htmlspecialchars((string) $schemaMarkupValue) ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="custom_head_tags" class="form-label">Custom Head Tags (Advanced)</label>
                                    <textarea class="form-control" id="custom_head_tags" name="custom_head_tags" rows="4"><?= htmlspecialchars((string) $customHeadTagsValue) ?></textarea>
                                    <div class="form-text">Paste custom tracking codes (GTM, GA, hreflang) here. They will be injected into the <head> of this specific product page.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="custom_body" class="form-label">Custom Body Tags (Advanced)</label>
                                    <textarea class="form-control" id="custom_body" name="custom_body" rows="4"><?= htmlspecialchars((string) $customBodyValue) ?></textarea>
                                    <div class="form-text">Paste custom tags (for example GTM noscript) here. They will be injected at the start of the <body> of this specific product page.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="custom_footer" class="form-label">Custom Footer Tags (Advanced)</label>
                                    <textarea class="form-control" id="custom_footer" name="custom_footer" rows="4"><?= htmlspecialchars((string) $customFooterValue) ?></textarea>
                                    <div class="form-text">Paste custom tags here. They will be injected before the closing </body> of this specific product page.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Create Product</button>
                            <a class="btn btn-secondary" href="<?= $base ?>/dashboard/products">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
<script nonce="<?= $nonce ?>">
window.addEventListener('load', function() {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.summernote !== 'undefined') {
        jQuery('#description').summernote({
            height: 250,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    }
});
</script>