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
    $pageTitle = 'Import Products';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Import products and offers from a CSV file';
}

if (!isset($report)) {
    $report = null;
}

$rejections = [];
$warnings = [];
$totalRows = 0;
$createdProducts = 0;
$createdOffers = 0;
$updatedOffers = 0;
$createdVendors = 0;
$skippedIdenticalOffers = 0;
$skippedExistingProducts = 0;

if (is_array($report)) {
    if (isset($report['total_rows'])) {
        $totalRows = (int) $report['total_rows'];
    }
    if (isset($report['created_products'])) {
        $createdProducts = (int) $report['created_products'];
    }
    if (isset($report['created_offers'])) {
        $createdOffers = (int) $report['created_offers'];
    }
    if (isset($report['updated_offers'])) {
        $updatedOffers = (int) $report['updated_offers'];
    }
    if (isset($report['created_vendors'])) {
        $createdVendors = (int) $report['created_vendors'];
    }
    if (isset($report['warnings']) && is_array($report['warnings'])) {
        $warnings = $report['warnings'];
    }
    if (isset($report['skipped_identical_offers'])) {
        $skippedIdenticalOffers = (int) $report['skipped_identical_offers'];
    }
    if (isset($report['skipped_existing_products'])) {
        $skippedExistingProducts = (int) $report['skipped_existing_products'];
    }
    if (isset($report['rejections']) && is_array($report['rejections'])) {
        $rejections = $report['rejections'];
    }
}

$skippedRows = $skippedIdenticalOffers + $skippedExistingProducts;
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
                <h1 class="h2">Import Products</h1>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/dashboard/products">
                    <i class="bi bi-arrow-left"></i> Back to Products
                </a>
            </div>

            <?php if (is_array($report)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-clipboard-check"></i> Result of the last import
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $totalRows) ?></div>
                                <div class="small text-muted">Rows read</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $createdProducts) ?></div>
                                <div class="small text-muted">Products created</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $createdVendors) ?></div>
                                <div class="small text-muted">Sellers created</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $createdOffers) ?></div>
                                <div class="small text-muted">Offers created</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $updatedOffers) ?></div>
                                <div class="small text-muted">Offers updated</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) $skippedRows) ?></div>
                                <div class="small text-muted">Rows skipped</div>
                            </div>
                            <div class="col-6 col-md-2 mb-3">
                                <div class="h4 mb-0"><?= htmlspecialchars((string) count($rejections)) ?></div>
                                <div class="small text-muted">Rows rejected</div>
                            </div>
                        </div>

                        <p class="small text-muted mb-0">
                            Skipped rows: <?= htmlspecialchars((string) $skippedIdenticalOffers) ?> identical offer(s),
                            <?= htmlspecialchars((string) $skippedExistingProducts) ?> product(s) that already existed.
                            New products are always created with the status <strong>Draft</strong>.
                        </p>
                    </div>
                </div>

                <?php if (!empty($warnings)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-exclamation-circle"></i> Warnings
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($warnings as $warning): ?>
                                <li class="list-group-item"><?= htmlspecialchars((string) $warning) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($rejections)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="bi bi-exclamation-triangle"></i> Rejected rows
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Row</th>
                                        <th scope="col">Reason</th>
                                        <th scope="col">Product slug</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rejections as $rejection): ?>
                                    <?php
                                    $rejectionSlug = '(no slug)';
                                    if (isset($rejection['slug']) && $rejection['slug'] !== '') {
                                        $rejectionSlug = (string) $rejection['slug'];
                                    }
                                    $rejectionRow = 0;
                                    if (isset($rejection['row'])) {
                                        $rejectionRow = (int) $rejection['row'];
                                    }
                                    $rejectionReason = '';
                                    if (isset($rejection['reason'])) {
                                        $rejectionReason = (string) $rejection['reason'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $rejectionRow) ?></td>
                                        <td><?= htmlspecialchars($rejectionReason) ?></td>
                                        <td><?= htmlspecialchars($rejectionSlug) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-upload"></i> Upload a CSV file
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= $base ?>/dashboard/products/import/store" enctype="multipart/form-data" novalidate>
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV file</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv,text/csv" required>
                            <div class="form-text">Only .csv files, maximum 5 MB. The file is read once and then deleted.</div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Import CSV
                            </button>
                            <a class="btn btn-secondary" href="<?= $base ?>/dashboard/products">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> How the CSV file must look
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        The first row of the file is the header. These columns are needed:
                    </p>
                    <ul class="small">
                        <li><code>product_slug</code> - the identity of the product, lowercase letters, digits and dashes only.</li>
                        <li><code>name</code> - the product name, needed when the product does not exist yet.</li>
                        <li><code>category_slug</code> - an existing category, needed when the product does not exist yet.</li>
                        <li><code>brand_slug</code> - an existing brand, needed when the product does not exist yet.</li>
                        <li><code>marketplace</code> - the store of the offer, for example <code>lazada</code>.</li>
                        <li><code>vendor_slug</code> - an existing vendor of that marketplace.</li>
                        <li><code>price_idr</code> - the price in rupiah, digits only, for example <code>1299000</code>.</li>
                        <li><code>affiliate_url</code> - the link to the offer, it must start with <code>http://</code> or <code>https://</code>.</li>
                        <li><code>seller_title</code> - the product title as the shop wrote it.</li>
                        <li><code>product_rating</code>, <code>sold_count</code>, <code>store_rating</code> - the metrics, they can be empty.</li>
                    </ul>
                    <p class="small mb-2">
                        Categories, brands, stores and vendors are never created by the import. A row that
                        cannot be validated is rejected with a reason and nothing is written for it.
                    </p>
                    <p class="small mb-0">
                        The scraper in <code>bin/scraper</code> produces a ready made file. Run it with
                        <code>python scrape.py --targets sample_targets.csv --out products</code>, fill the five
                        empty columns (product_slug, name, category_slug, brand_slug, vendor_slug) by hand and
                        upload the result here.
                    </p>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
