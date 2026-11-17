<?php
$paginationPage = 1;
if (isset($page)) {
    $paginationPage = (int) $page;
}
$paginationTotalPages = 1;
if (isset($totalPages)) {
    $paginationTotalPages = (int) $totalPages;
}
$paginationPath = '';
if (isset($pagePath)) {
    $paginationPath = (string) $pagePath;
}
$paginationParams = [];
if (isset($pageParams) && is_array($pageParams)) {
    $paginationParams = $pageParams;
}
$paginationBase = '';
if (isset($base)) {
    $paginationBase = (string) $base;
}
?>
<?php if ($paginationTotalPages > 1): ?>
<nav aria-label="Halaman produk">
    <ul class="pagination justify-content-center mt-4">
        <?php
        $prevPage = $paginationPage - 1;
        $prevDisabledClass = '';
        $prevHref = '';
        if ($paginationPage <= 1) {
            $prevDisabledClass = 'disabled';
        } else {
            $prevParams = $paginationParams;
            if ($prevPage > 1) {
                $prevParams['page'] = $prevPage;
            } else {
                unset($prevParams['page']);
            }
            $prevQuery = http_build_query($prevParams);
            $prevHref = $paginationBase . $paginationPath;
            if ($prevQuery !== '') {
                $prevHref = $prevHref . '?' . $prevQuery;
            }
        }
        ?>
        <li class="page-item <?= $prevDisabledClass ?>">
            <?php if ($prevHref !== ''): ?>
            <a class="page-link" href="<?= htmlspecialchars($prevHref) ?>">Sebelumnya</a>
            <?php else: ?>
            <span class="page-link">Sebelumnya</span>
            <?php endif; ?>
        </li>
        <?php for ($pageNumber = 1; $pageNumber <= $paginationTotalPages; $pageNumber++): ?>
            <?php
            $pageParamsForLink = $paginationParams;
            if ($pageNumber > 1) {
                $pageParamsForLink['page'] = $pageNumber;
            } else {
                unset($pageParamsForLink['page']);
            }
            $pageQuery = http_build_query($pageParamsForLink);
            $pageHref = $paginationBase . $paginationPath;
            if ($pageQuery !== '') {
                $pageHref = $pageHref . '?' . $pageQuery;
            }
            $pageActiveClass = '';
            if ($pageNumber === $paginationPage) {
                $pageActiveClass = 'active';
            }
            ?>
            <li class="page-item <?= $pageActiveClass ?>"><a class="page-link" href="<?= htmlspecialchars($pageHref) ?>"><?= $pageNumber ?></a></li>
        <?php endfor; ?>
        <?php
        $nextPage = $paginationPage + 1;
        $nextDisabledClass = '';
        $nextHref = '';
        if ($paginationPage >= $paginationTotalPages) {
            $nextDisabledClass = 'disabled';
        } else {
            $nextParams = $paginationParams;
            $nextParams['page'] = $nextPage;
            $nextQuery = http_build_query($nextParams);
            $nextHref = $paginationBase . $paginationPath . '?' . $nextQuery;
        }
        ?>
        <li class="page-item <?= $nextDisabledClass ?>">
            <?php if ($nextHref !== ''): ?>
            <a class="page-link" href="<?= htmlspecialchars($nextHref) ?>">Berikutnya</a>
            <?php else: ?>
            <span class="page-link">Berikutnya</span>
            <?php endif; ?>
        </li>
    </ul>
</nav>
<?php endif; ?>
