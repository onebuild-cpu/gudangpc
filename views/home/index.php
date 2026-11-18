<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

<div>
    <section class="parallax-hero">
        <!-- Layer 1: Background -->
        <div class="parallax-layer layer-1" data-speed="0.2">
            <img src="<?= $base ?>/uploads/images/layer1-background.webp" alt="Layer 1: Background" class="parallax-img">
            <div class="parallax-fallback">LAYER 1 (Large Items)</div>
        </div>

        <!-- Layer 2: Middle -->
        <!-- <div class="parallax-layer layer-2" data-speed="0.5">
            <img src="<?= $base ?>/uploads/images/layer2-middle.webp" alt="Layer 2: Middle" class="parallax-img">
            <div class="parallax-fallback">LAYER 2 (Medium Items)</div>
        </div> -->

        <!-- Layer 3: Foreground -->
        <!-- <div class="parallax-layer layer-3" data-speed="0.8">
            <img src="<?= $base ?>/uploads/images/layer3-foreground.webp" alt="Layer 3: Foreground" class="parallax-img">
            <div class="parallax-fallback">LAYER 3 (Small Items)</div>
        </div> -->

        <!-- Brand Text -->
        <div class="brand-container">
            <span class="brand-display">Gudang<span class="red">PC</span></span>
            <h1><strong>Banyak Toko, Satu Gudang!</strong><h1>
            <h2 class="hero-tagline">Cari Harga Lebih Murah, Nggak Pakai Ribet.</h2>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <?php if (!empty($newestProducts)): ?>
            <div class="mb-5">
                <h2 class="section-title fw-bold mb-4">Produk Terbaru</h2>
                <div class="row g-4">
                    <?php foreach ($newestProducts as $product): ?>
                    <?php include __DIR__ . '/../public/_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Popular Categories -->
            <div class="mb-5">
                <h2 class="section-title fw-bold mb-4">Kategori Populer</h2>
                <div class="row g-4">
                    <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryImagePath = '';
                    if (isset($category['image'])) {
                        $categoryImagePath = (string) $category['image'];
                    }
                    ?>
                    <div class="col-md-3">
                        <a href="<?= $base ?>/<?= htmlspecialchars((string) $category['slug']) ?>" class="category-card h-100 text-decoration-none text-dark">
                            <div class="category-card-img">
                                <?php if ($categoryImagePath === ''): ?>
                                    <i class="bi bi-tag category-card-icon"></i>
                                <?php else: ?>
                                    <img src="<?= $base . htmlspecialchars($categoryImagePath) ?>" alt="<?= htmlspecialchars((string) $category['name']) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="category-card-name">
                                <?= htmlspecialchars((string) $category['name']) ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($categorySections as $section): ?>
            <?php
            $sectionCategory = $section['category'];
            $sectionProducts = $section['products'];
            ?>
            <div class="mb-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="section-title fw-bold mb-0">Terbaru di <?= htmlspecialchars((string) $sectionCategory['name']) ?></h2>
                    <a href="<?= $base ?>/<?= htmlspecialchars((string) $sectionCategory['slug']) ?>">Lihat semua</a>
                </div>
                <div class="row g-4">
                    <?php foreach ($sectionProducts as $product): ?>
                    <?php include __DIR__ . '/../public/_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($brands)): ?>
            <div class="mb-5">
                <h2 class="section-title fw-bold mb-4">Merek</h2>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <?php foreach ($brands as $brand): ?>
                    <?php
                    $brandLogoPath = '';
                    if (isset($brand['logo'])) {
                        $brandLogoPath = (string) $brand['logo'];
                    }
                    ?>
                    <a href="<?= $base ?>/brand/<?= htmlspecialchars((string) $brand['slug']) ?>" class="text-decoration-none">
                        <?php if ($brandLogoPath !== ''): ?>
                            <img src="<?= $base . htmlspecialchars($brandLogoPath) ?>" alt="<?= htmlspecialchars((string) $brand['name']) ?> logo" height="40">
                        <?php else: ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars((string) $brand['name']) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script nonce="<?= $nonce ?>">
    window.addEventListener('load', () => {
        const layers = document.querySelectorAll('.parallax-layer');
        const brandContainer = document.querySelector('.brand-container');
        const heroSection = document.querySelector('.parallax-hero');
        let ticking = false;

        if (layers.length === 0) {
            return;
        }

        // Handle image fallbacks - hide failed images and show placeholders
        const parallaxImgs = document.querySelectorAll('.parallax-img');
        parallaxImgs.forEach(img => {
            img.onerror = function() {
                this.style.display = 'none';
                const placeholder = this.nextElementSibling;
                if (placeholder && placeholder.classList.contains('parallax-fallback')) {
                    placeholder.style.display = 'flex';
                }
            };
        });

        const updateLayers = () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    const scrolled = document.documentElement.scrollTop || document.body.scrollTop || window.pageYOffset;
                    const heroHeight = heroSection.offsetHeight;

                    if (scrolled <= heroHeight) {
                        layers.forEach(layer => {
                            const speed = parseFloat(layer.getAttribute('data-speed'));
                            const yPos = -(scrolled * speed);
                            layer.style.transform = `translateY(${yPos}px)`;
                        });

                        if (brandContainer) {
                            // Text stays fixed at bottom center - no movement on scroll
                            brandContainer.style.opacity = 1;
                        }
                    }
                    ticking = false;
                });
                ticking = true;
            }
        };

        window.addEventListener('scroll', updateLayers);
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>