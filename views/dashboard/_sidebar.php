<?php
if (!isset($base)) {
    $base = '';
    if (isset($GLOBALS['base'])) {
        $base = $GLOBALS['base'];
    }
}
if (!isset($currentPage)) {
    $currentPage = 'index';
}
?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
<li class="nav-item">
            <?php
            $overviewActive = '';
            if ($currentPage === 'index') {
                $overviewActive = 'active';
            }
            ?>
            <a class="nav-link <?= $overviewActive ?>" href="<?= $base ?>/dashboard">
                <i class="bi bi-speedometer2"></i> Overview
            </a>
        </li>
            <?php
            $adminsActive = '';
            if ($currentPage === 'admins') {
                $adminsActive = 'active';
            }
            ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'root'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $adminsActive ?>" href="<?= $base ?>/dashboard/admins">
                    <i class="bi bi-people"></i> Admins
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
            <?php
            $categoriesActive = '';
            if ($currentPage === 'categories') {
                $categoriesActive = 'active';
            }
            ?>
            <a class="nav-link <?= $categoriesActive ?>" href="<?= $base ?>/dashboard/categories">
                <i class="bi bi-tags"></i> Categories
            </a>
        </li>
            <li class="nav-item">
            <?php
            $brandsActive = '';
            if ($currentPage === 'brands') {
                $brandsActive = 'active';
            }
            ?>
            <a class="nav-link <?= $brandsActive ?>" href="<?= $base ?>/dashboard/brands">
                <i class="bi bi-award"></i> Brands
            </a>
        </li>
            <li class="nav-item">
            <?php
            $storesActive = '';
            if ($currentPage === 'stores') {
                $storesActive = 'active';
            }
            ?>
            <a class="nav-link <?= $storesActive ?>" href="<?= $base ?>/dashboard/stores">
                <i class="bi bi-shop"></i> Stores
            </a>
        </li>
            <li class="nav-item">
            <?php
            $vendorsActive = '';
            if ($currentPage === 'vendors') {
                $vendorsActive = 'active';
            }
            ?>
            <a class="nav-link <?= $vendorsActive ?>" href="<?= $base ?>/dashboard/vendors">
                <i class="bi bi-person-badge"></i> Vendors
            </a>
        </li>
            <li class="nav-item">
            <?php
            $locationsActive = '';
            if ($currentPage === 'locations') {
                $locationsActive = 'active';
            }
            ?>
            <a class="nav-link <?= $locationsActive ?>" href="<?= $base ?>/dashboard/locations">
                <i class="bi bi-geo-alt"></i> Locations
            </a>
        </li>
            <li class="nav-item">
            <?php
            $productsActive = '';
            if ($currentPage === 'products' || $currentPage === 'products-import') {
                $productsActive = 'active';
            }
            ?>
            <a class="nav-link <?= $productsActive ?>" href="<?= $base ?>/dashboard/products">
                <i class="bi bi-box"></i> Products
            </a>
        </li>
            <li class="nav-item">
            <?php
            $productImportActive = '';
            if ($currentPage === 'products-import') {
                $productImportActive = 'active';
            }
            ?>
            <a class="nav-link ps-4 <?= $productImportActive ?>" href="<?= $base ?>/dashboard/products/import">
                <i class="bi bi-upload"></i> Import Products
            </a>
        </li>
            <?php
            $searchActive = '';
            if ($currentPage === 'search') {
                $searchActive = 'active';
            }
            ?>
            <li class="nav-item">
                <a class="nav-link <?= $searchActive ?>" href="<?= $base ?>/dashboard/search">
                    <i class="bi bi-search"></i> Search Analytics
                </a>
            </li>
            <?php
            $settingsActive = '';
            if ($currentPage === 'settings') {
                $settingsActive = 'active';
            }
            $canManageSettings = false;
            if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'root'], true)) {
                $canManageSettings = true;
            }
            ?>
            <?php if ($canManageSettings): ?>
            <li class="nav-item">
                <a class="nav-link <?= $settingsActive ?>" href="<?= $base ?>/dashboard/settings">
                    <i class="bi bi-gear"></i> SEO Defaults
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
            <?php
            $profileActive = '';
            if ($currentPage === 'profile') {
                $profileActive = 'active';
            }
            ?>
            <a class="nav-link <?= $profileActive ?>" href="<?= $base ?>/dashboard/profile">
                <i class="bi bi-person"></i> Profile
            </a>
        </li>
            <?php if ($_SESSION['role'] === 'root'): ?>
                <li class="nav-item">
                    <?php
                    $membersActive = '';
                    if ($currentPage === 'members') {
                        $membersActive = 'active';
                    }
                    ?>
                    <a class="nav-link <?= $membersActive ?>" href="<?= $base ?>/dashboard/members">
                        <i class="bi bi-people-fill"></i> Members
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <?php
                $clicksActive = '';
                if ($currentPage === 'clicks') {
                    $clicksActive = 'active';
                }
                ?>
                <a class="nav-link <?= $clicksActive ?>" href="<?= $base ?>/dashboard/clicks">
                    <i class="bi bi-graph-up"></i> Clicks
                </a>
            </li>
            <li class="nav-item">
                <form method="POST" action="<?= $base ?>/logout">
                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                    <button type="submit" class="nav-link btn btn-link text-start w-100">
                        <i class="bi bi-box-arrow-right"></i> Sign Out
                    </button>
                </form>
            </li>
        </ul>
    </div>
</nav>