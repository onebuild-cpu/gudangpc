<?php
$base = '';
if (isset($GLOBALS['base'])) {
    $base = $GLOBALS['base'];
}
$nonce = '';
if (isset($GLOBALS['csp_nonce'])) {
    $nonce = $GLOBALS['csp_nonce'];
}
?>
<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css" nonce="<?= $nonce ?>">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/_sidebar.php'; ?>

        <!-- Main content -->
        <section class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Click Analytics</h1>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Affiliate Clicks</h5>
                            <p class="card-text display-4 fw-bold"><?= $totalClicks ?></p>
                            <p class="card-text small text-muted">Total clicks recorded on all offers</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Product Clicks</h5>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php
                                            $idDir = 'asc';
                                            if ($sort === 'id' && $dir === 'asc') {
                                                $idDir = 'desc';
                                            }
                                            $nameDir = 'asc';
                                            if ($sort === 'name' && $dir === 'asc') {
                                                $nameDir = 'desc';
                                            }
                                            $clicksDir = 'desc';
                                            if ($sort === 'clicks' && $dir === 'desc') {
                                                $clicksDir = 'asc';
                                            }
                                            ?>
                                            <th scope="col"><a href="<?= $base ?>/dashboard/clicks?sort=id&amp;dir=<?= $idDir ?>">Product ID</a></th>
                                            <th scope="col"><a href="<?= $base ?>/dashboard/clicks?sort=name&amp;dir=<?= $nameDir ?>">Product</a></th>
                                            <th scope="col"><a href="<?= $base ?>/dashboard/clicks?sort=clicks&amp;dir=<?= $clicksDir ?>">Clicks</a></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($clickStats)): ?>
                                        <tr>
                                            <td colspan="3" class="text-muted">No products found.</td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php foreach ($clickStats as $row): ?>
                                        <tr>
                                            <td><?= (int) $row['id'] ?></td>
                                            <td><?= htmlspecialchars((string) $row['name']) ?></td>
                                            <td><?= (int) $row['clicks'] ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="card-text small text-muted mb-0">Clicks are counted per offer click and summed by product. Sorted by <?= htmlspecialchars((string) $sort) ?> (<?= htmlspecialchars((string) $dir) ?>).</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>