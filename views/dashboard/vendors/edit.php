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
    $pageTitle = 'Edit Vendor';
}

// Set a default meta description only if the controller did not provide one
if (!isset($metaDescription)) {
    $metaDescription = 'Edit a marketplace vendor';
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

$ratingValue = '';
if (isset($old['rating'])) {
    $ratingValue = $old['rating'];
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
                <h1 class="h2">Edit Vendor</h1>
                <a class="btn btn-outline-secondary" href="<?= $base ?>/dashboard/vendors">
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
                    <form method="POST" action="<?= $base ?>/dashboard/vendors/<?= (int) $vendor['id'] ?>/update" novalidate>
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>

                        <div class="mb-3">
                            <label for="store_id" class="form-label">Store (Platform)</label>
                            <select class="form-select" id="store_id" name="store_id" required>
                                <option value="">Select store</option>
                                <?php foreach ($stores as $store): ?>
                                    <?php
                                    $storeOptionSelected = '';
                                    if (isset($old['store_id']) && $old['store_id'] == (int) $store['id']) {
                                        $storeOptionSelected = 'selected';
                                    }
                                    ?>
                                    <option value="<?= (int) $store['id'] ?>" <?= $storeOptionSelected ?>>
                                        <?= htmlspecialchars((string) $store['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Vendor Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars((string) $nameValue) ?>" maxlength="255" required>
                            <div class="form-text">Required. Maximum 255 characters.</div>
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars((string) $slugValue) ?>" maxlength="255">
                            <div class="form-text">Lowercase letters, numbers and dashes.</div>
                        </div>

                        <div class="mb-3">
                            <label for="rating" class="form-label">Rating</label>
                            <input type="number" class="form-control" id="rating" name="rating" value="<?= htmlspecialchars((string) $ratingValue) ?>" step="0.01" min="0" max="5" placeholder="Rating (0-5)">
                            <div class="form-text">Optional. Between 0 and 5.</div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a class="btn btn-secondary" href="<?= $base ?>/dashboard/vendors">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
