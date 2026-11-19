<?php
$requestUri = '';
if (isset($GLOBALS['requestUri'])) {
    $requestUri = $GLOBALS['requestUri'];
}
// $requestUri is trimmed (no leading/trailing slashes): 'login', 'register', 'dashboard', 'dashboard/profile', '' (home)

$navUsername = 'Pengguna';
if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
    $navUsername = $_SESSION['username'];
} elseif (isset($_SESSION['phone']) && $_SESSION['phone'] !== '') {
    $navUsername = $_SESSION['phone'];
}

$navPhone = '';
if (isset($_SESSION['phone'])) {
    $navPhone = $_SESSION['phone'];
}

$isLoggedIn = false;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != 0) {
    $isLoggedIn = true;
}

$navRole = '';
if (isset($_SESSION['role'])) {
    $navRole = $_SESSION['role'];
}

$isVisitor = false;
if ($isLoggedIn && $navRole === 'visitor') {
    $isVisitor = true;
}

$navDisplay = $navUsername;
$navContact = $navPhone;

if ($isVisitor) {
    $navDisplay = '';
    if (isset($_SESSION['username'])) {
        $navDisplay = $_SESSION['username'];
    } else {
        if (isset($_SESSION['email'])) {
            $navDisplay = $_SESSION['email'];
        }
    }

    $navContact = '';
    if (isset($_SESSION['username'])) {
        if (isset($_SESSION['email'])) {
            $navContact = $_SESSION['email'];
        }
    }
}

$dashboardActive = '';
if (stripos($requestUri, 'dashboard') === 0) {
    $dashboardActive = 'active';
}

$memberActive = '';
if (stripos($requestUri, 'member') === 0) {
    $memberActive = 'active';
}

$categoriesActive = '';
if (stripos($requestUri, 'categories') === 0) {
    $categoriesActive = 'active';
}

$brandsActive = '';
if (stripos($requestUri, 'brands') === 0) {
    $brandsActive = 'active';
}

$locationsActive = '';
if (stripos($requestUri, 'locations') === 0) {
    $locationsActive = 'active';
}

$profileHref = '/dashboard/profile';
if ($isVisitor) {
    $profileHref = '/member/profile';
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm" style="position: sticky; top: 0; z-index: 1050; background-color: #fff;">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $base ?>" style="font-family: 'Poppins'; font-size: 1.75rem; letter-spacing: -1px; font-weight: 400;">Gudang<span class="text-danger">PC</span></a>

        <div class="d-flex align-items-center">
            <?php if ($isLoggedIn && !$isVisitor): ?>
            <button class="navbar-toggler me-2" type="button" id="sidebarToggle" aria-label="Buka atau tutup menu samping">
                <span>&#x25E7;</span>
            </button>
            <?php endif; ?>

            <button class="navbar-toggler" type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#navbarNav"
                    aria-controls="navbarNav"
                    aria-expanded="false"
                    aria-label="Buka atau tutup navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="navbarNav">
            <?php
            $searchQuery = '';
            if (isset($_GET['q'])) {
                $searchQuery = (string) $_GET['q'];
            }
            ?>
            <form class="d-flex mx-auto my-2 my-lg-0 search-form" action="<?= $base ?>/search" method="GET">
                <div class="input-group">
                    <input class="form-control search-input" type="search" name="q" placeholder="Cari komponen, monitor, CPU..." aria-label="Cari" value="<?= htmlspecialchars((string) $searchQuery) ?>">
                    <button class="btn btn-search" type="submit">
                        <i class="bi bi-search"></i> <span class="d-none d-md-inline">Cari</span>
                    </button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <?php
                    $homeActive = '';
                    if ($requestUri === '' || $requestUri === '/') {
                        $homeActive = 'active';
                    }
                    ?>
                    <a class="nav-link <?= $homeActive ?>" href="<?= $base ?>">Beranda</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $categoriesActive ?>" href="<?= $base ?>/categories">Kategori</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $brandsActive ?>" href="<?= $base ?>/brands">Merek</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $locationsActive ?>" href="<?= $base ?>/locations">Lokasi Toko</a>
                </li>
                <?php /* Commented out because registration can only be done by root/admin
                <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $requestUri === 'login' ? 'active' : '' ?>" href="<?= $base ?>/login">Sign In</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= $requestUri === 'register' ? 'active' : '' ?>" href="<?= $base ?>/register">Register</a>
                    </li>
                
                <?php else: ?>
                */ ?>
                <?php if (!$isLoggedIn): ?>
                    <li class="nav-item">
                        <?php
                        $loginActive = '';
                        if ($requestUri === 'login') {
                            $loginActive = 'active';
                        }
                        ?>
                        <a class="nav-link <?= $loginActive ?>" href="<?= $base ?>/login">Masuk</a>
                    </li>
                    <li class="nav-item">
                        <?php
                        $registerActive = '';
                        if ($requestUri === 'register') {
                            $registerActive = 'active';
                        }
                        ?>
                        <a class="nav-link <?= $registerActive ?>" href="<?= $base ?>/register">Daftar</a>
                    </li>
                <?php else: ?>
                    <?php if ($isVisitor): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $memberActive ?>" href="<?= $base ?>/member">Area Anggota</a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $dashboardActive ?>" href="<?= $base ?>/dashboard">Dasbor</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($_SESSION['avatar'])): ?>
                                <img src="<?= $base . htmlspecialchars($_SESSION['avatar']) ?>" alt="Avatar" class="rounded-circle me-2 avatar-sm">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2 avatar-sm">
                                    <i class="bi bi-person-fill text-white fs-6"></i>
                                </div>
                            <?php endif; ?>
                            <span><?= htmlspecialchars($navDisplay) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?= htmlspecialchars($navDisplay) ?></h6></li>
                            <?php if ($navContact !== ''): ?>
                            <li><small class="dropdown-item-text text-muted"><?= htmlspecialchars($navContact) ?></small></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $base . $profileHref ?>"><i class="bi bi-person me-2"></i>Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="<?= $base ?>/logout" class="m-0">
                                    <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style nonce="<?= $nonce ?>">
.avatar-sm {
    width: 32px;
    height: 32px;
}
.avatar-sm img {
    object-fit: cover;
}
.fs-6 {
    font-size: 0.875rem !important;
}
</style>

<script nonce="<?= $nonce ?>">
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebarMenu');
    const overlay = document.getElementById('sidebarOverlay');
    const base = <?= json_encode($base) ?>;
    
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            } else {
                // Redirect to dashboard if sidebar doesn't exist on this page
                window.location.href = base + '/dashboard';
            }
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768) {
            if (sidebar && !sidebar.contains(e.target) && sidebarToggle && !sidebarToggle.contains(e.target)) {
                closeSidebar();
            }
        }
    });
    
    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            closeSidebar();
        }
    });
});
</script>

<?php // Open the main content area now that the navbar is done ?>
<main class="main-content">

    <?php // Render flash messages from session, then clear them ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
