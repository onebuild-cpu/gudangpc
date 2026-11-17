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
    $pageTitle = 'Search Analytics';
}
if (!isset($metaDescription)) {
    $metaDescription = 'Review visitor search queries and sitemap visibility';
}
if (!isset($queries)) {
    $queries = [];
}
if (!isset($search)) {
    $search = '';
}
if (!isset($page)) {
    $page = 1;
}
if (!isset($totalPages)) {
    $totalPages = 1;
}
if (!isset($perPage)) {
    $perPage = 20;
}
if (!isset($total)) {
    $total = 0;
}

$showingStart = 0;
$showingEnd = 0;
if ($total > 0) {
    $showingStart = ($page - 1) * $perPage + 1;
    $showingEnd = $page * $perPage;
    if ($showingEnd > $total) {
        $showingEnd = $total;
    }
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css" nonce="<?= $nonce ?>">

<div class="container-fluid">
    <div class="row">
        <?php require_once __DIR__ . '/../_sidebar.php'; ?>

        <section class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Search Analytics</h1>
            </div>

            <form method="GET" action="<?= $base ?>/dashboard/search" class="row g-2 mb-4">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars((string) $search) ?>" placeholder="Search query text...">
                        <button type="submit" class="btn btn-outline-secondary">Search</button>
                    </div>
                </div>
                <?php if ($search !== ''): ?>
                <div class="col-12 col-md-3">
                    <a class="btn btn-outline-secondary w-100" href="<?= $base ?>/dashboard/search">Clear</a>
                </div>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Query Text</th>
                            <th scope="col">Result Count</th>
                            <th scope="col">Tanggal Dibuat</th>
                            <th scope="col">Status Sitemap</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($queries)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No search queries found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queries as $queryRow): ?>
                            <?php
                            $rowId = 0;
                            if (isset($queryRow['id'])) {
                                $rowId = (int) $queryRow['id'];
                            }

                            $queryText = '';
                            if (isset($queryRow['query_text'])) {
                                $queryText = (string) $queryRow['query_text'];
                            }

                            $resultCount = 0;
                            if (isset($queryRow['result_count'])) {
                                $resultCount = (int) $queryRow['result_count'];
                            }

                            $createdAt = '';
                            if (isset($queryRow['created_at'])) {
                                $createdAt = (string) $queryRow['created_at'];
                            }

                            $isIndexed = false;
                            if (isset($queryRow['is_indexed'])) {
                                if ((int) $queryRow['is_indexed'] === 1) {
                                    $isIndexed = true;
                                }
                            }

                            $sitemapBadgeClass = 'bg-secondary';
                            $sitemapLabel = 'Ignored';
                            $toggleStatus = '1';
                            $toggleLabel = 'Tampilkan';
                            $toggleIcon = 'bi-eye';
                            $toggleButtonClass = 'btn-outline-success';

                            if ($isIndexed) {
                                $sitemapBadgeClass = 'bg-success';
                                $sitemapLabel = 'Indexed';
                                $toggleStatus = '0';
                                $toggleLabel = 'Sembunyikan';
                                $toggleIcon = 'bi-eye-slash';
                                $toggleButtonClass = 'btn-outline-secondary';
                            }
                            ?>
                        <tr>
                            <td><?= htmlspecialchars($queryText) ?></td>
                            <td><?= $resultCount ?></td>
                            <td><?= htmlspecialchars($createdAt) ?></td>
                            <td><span class="badge <?= $sitemapBadgeClass ?>"><?= htmlspecialchars($sitemapLabel) ?></span></td>
                            <td class="text-end">
                                <form method="POST" action="<?= $base ?>/dashboard/search/<?= $rowId ?>/toggle" class="d-inline">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($toggleStatus) ?>">
                                    <button type="submit" class="btn btn-sm <?= $toggleButtonClass ?>">
                                        <i class="bi <?= $toggleIcon ?>"></i> <?= htmlspecialchars($toggleLabel) ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav aria-label="Search query pagination">
                <ul class="pagination justify-content-center">
                    <?php
                    $isDisabledPrev = false;
                    if ($page <= 1) {
                        $isDisabledPrev = true;
                    }
                    $isDisabledNext = false;
                    if ($page >= $totalPages) {
                        $isDisabledNext = true;
                    }
                    ?>
                    <?php if (!$isDisabledPrev): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $base ?>/dashboard/search?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" aria-label="Previous">
                            <span aria-hidden="true">«</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php
                        $pageItemClass = 'page-item';
                        if ($i === $page) {
                            $pageItemClass = $pageItemClass . ' active';
                        }
                        ?>
                        <li class="<?= $pageItemClass ?>">
                            <a class="page-link" href="<?= $base ?>/dashboard/search?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if (!$isDisabledNext): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $base ?>/dashboard/search?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" aria-label="Next">
                            <span aria-hidden="true">»</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <?php if ($total > 0): ?>
            <small class="text-muted">Showing <?= $showingStart ?>-<?= $showingEnd ?> of <?= $total ?> queries</small>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
