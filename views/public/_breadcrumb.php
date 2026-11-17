<?php
$breadcrumbItems = [];
if (isset($breadcrumbs) && is_array($breadcrumbs)) {
    $breadcrumbItems = $breadcrumbs;
}
$breadcrumbTotal = count($breadcrumbItems);
?>
<?php if ($breadcrumbTotal > 0): ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <?php foreach ($breadcrumbItems as $breadcrumbIndex => $crumb): ?>
            <?php
            $crumbLabel = '';
            if (isset($crumb['label'])) {
                $crumbLabel = (string) $crumb['label'];
            }
            $crumbUrl = '';
            if (isset($crumb['url'])) {
                $crumbUrl = (string) $crumb['url'];
            }
            $crumbIsLast = false;
            if ($breadcrumbIndex === $breadcrumbTotal - 1) {
                $crumbIsLast = true;
            }
            ?>
            <?php if ($crumbIsLast): ?>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($crumbLabel) ?></li>
            <?php elseif ($crumbUrl !== ''): ?>
            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($crumbUrl) ?>"><?= htmlspecialchars($crumbLabel) ?></a></li>
            <?php else: ?>
            <li class="breadcrumb-item"><?= htmlspecialchars($crumbLabel) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
