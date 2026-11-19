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
    $pageTitle = 'Product';
}
if (!isset($metaDescription)) {
    $metaDescription = '';
}
if (!isset($bodyClass)) {
    $bodyClass = 'product-detail-page';
}

$nameValue = '';
if (isset($product['name'])) {
    $nameValue = (string) $product['name'];
}

$modelNumberValue = '';
if (isset($product['model_number'])) {
    $modelNumberValue = (string) $product['model_number'];
}

$shortDescriptionValue = '';
if (isset($product['short_description'])) {
    $shortDescriptionValue = (string) $product['short_description'];
}

$descriptionValue = '';
if (isset($product['description'])) {
    $descriptionValue = (string) $product['description'];
}
// cleaned by RichText::clean(); only unescaped output on this page.
$descriptionHtml = \App\Core\RichText::clean($descriptionValue);

$imageValue = '';
if (isset($product['image'])) {
    $imageValue = (string) $product['image'];
}

$productId = '';
if (isset($product['id'])) {
    $productId = (int) $product['id'];
}

$imagePath = $imageValue;

$imageAltValue = $nameValue;
if (isset($product['image_alt']) && $product['image_alt'] !== '') {
    $imageAltValue = (string) $product['image_alt'];
}

$brandValue = '';
if (isset($brandName)) {
    $brandValue = (string) $brandName;
}

$disclosureText = 'We may earn a commission from purchases made through our links.';

if (!isset($offers)) {
    $offers = [];
}
if (!isset($related)) {
    $related = [];
}
$inWishlistValue = false;
if (isset($inWishlist)) {
    $inWishlistValue = (bool) $inWishlist;
}

$offerCount = count($offers);

$lowestPrice = 0;
$cheapestOfferId = 0;
foreach ($offers as $offerIndex => $offerRow) {
    $offerPrice = 0;
    if (isset($offerRow['price_idr'])) {
        $offerPrice = (int) $offerRow['price_idr'];
    }
    if ($offerIndex === 0) {
        $lowestPrice = $offerPrice;
        if (isset($offerRow['id'])) {
            $cheapestOfferId = (int) $offerRow['id'];
        }
    } elseif ($offerPrice < $lowestPrice) {
        $lowestPrice = $offerPrice;
        if (isset($offerRow['id'])) {
            $cheapestOfferId = (int) $offerRow['id'];
        }
    }
}

$latestCheckedAt = '';
foreach ($offers as $offerRow) {
    if (isset($offerRow['last_checked_at']) && $offerRow['last_checked_at'] !== null && $offerRow['last_checked_at'] !== '') {
        $checkedTimestamp = strtotime((string) $offerRow['last_checked_at']);
        if ($checkedTimestamp !== false) {
            $latestTimestamp = 0;
            if ($latestCheckedAt !== '') {
                $latestTimestamp = (int) strtotime($latestCheckedAt);
            }
            if ($latestTimestamp < $checkedTimestamp) {
                $latestCheckedAt = (string) $offerRow['last_checked_at'];
            }
        }
    }
}

$updatedText = 'Unknown';
if ($latestCheckedAt !== '') {
    $latestCheckedTimestamp = strtotime($latestCheckedAt);
    if ($latestCheckedTimestamp !== false) {
        $elapsed = time() - $latestCheckedTimestamp;
        if ($elapsed < 3600) {
            $updatedText = 'just now';
        } elseif ($elapsed < 86400) {
            $hoursAgo = (int) floor($elapsed / 3600);
            $updatedText = $hoursAgo . ' hour(s) ago';
        } else {
            $daysAgo = (int) floor($elapsed / 86400);
            $updatedText = $daysAgo . ' day(s) ago';
        }
    }
}

$visibleOffers = array_slice($offers, 0, 10);
$hiddenOffers = array_slice($offers, 10);
$hiddenOfferCount = count($hiddenOffers);
$buyUrl = $base . '/go/' . $cheapestOfferId;
$wishlistUrl = $base . '/wishlist/add/' . $productId;
$wishlistLabel = 'Save to wishlist';
if ($inWishlistValue) {
    $wishlistUrl = $base . '/wishlist/remove/' . $productId;
    $wishlistLabel = 'Remove from wishlist';
}
?>
<?php require_once __DIR__ . '/../../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../../layouts/navbar.php'; ?>

<div class="container py-4">
    <?php include __DIR__ . '/../_breadcrumb.php'; ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card product-detail-card">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <?php if ($imagePath === ''): ?>
                                <div class="product-detail-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php else: ?>
                                <img src="<?= $base . htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($imageAltValue) ?>" class="img-fluid product-detail-image">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <?php if ($brandValue !== ''): ?>
                                <div class="product-detail-brand"><?= htmlspecialchars($brandValue) ?></div>
                            <?php endif; ?>
                            <h1 class="product-detail-name"><?= htmlspecialchars($nameValue) ?></h1>
                            <?php if ($modelNumberValue !== ''): ?>
                                <div class="product-detail-model">
                                    <span class="text-muted">Model:</span> <?= htmlspecialchars($modelNumberValue) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($shortDescriptionValue !== ''): ?>
                                <p class="product-detail-short"><?= htmlspecialchars($shortDescriptionValue) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card product-detail-card mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Compare Prices</h2>
                    <?php if ($offerCount === 0): ?>
                        <p class="text-muted mb-0">No active offers right now.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 price-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Marketplace</th>
                                        <th scope="col">Seller</th>
                                        <th scope="col">Listing Title</th>
                                        <th scope="col">Seller Rating</th>
                                        <th scope="col">Product Rating</th>
                                        <th scope="col">Sold</th>
                                        <th scope="col">Price</th>
                                        <th scope="col" class="text-end">Buy</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($visibleOffers as $offerIndex => $offer): ?>
                                    <?php
                                    $marketplaceValue = '';
                                    if (isset($offer['store_name'])) {
                                        $marketplaceValue = (string) $offer['store_name'];
                                    }

                                    $sellerValue = '';
                                    if (isset($offer['vendor_name']) && $offer['vendor_name'] !== null) {
                                        $sellerValue = (string) $offer['vendor_name'];
                                    }
                                    if ($sellerValue === '') {
                                        $sellerValue = 'General';
                                    }

                                    $sellerSlugValue = '';
                                    if (isset($offer['vendor_slug']) && $offer['vendor_slug'] !== null) {
                                        $sellerSlugValue = (string) $offer['vendor_slug'];
                                    }
                                    $sellerSlugText = htmlspecialchars($sellerSlugValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    $sellerIsLinked = false;
                                    if ($sellerSlugValue !== '') {
                                        $sellerIsLinked = true;
                                    }

                                    $sellerLocationText = '';
                                    if (isset($offer['vendor_location_name']) && $offer['vendor_location_name'] !== null && $offer['vendor_location_name'] !== '') {
                                        $sellerLocationText = htmlspecialchars((string) $offer['vendor_location_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                    }

                                    $sellerTitleValue = '';
                                    if (isset($offer['seller_title']) && $offer['seller_title'] !== null) {
                                        $sellerTitleValue = (string) $offer['seller_title'];
                                    }

                                    $sellerRatingValue = '';
                                    if (isset($offer['vendor_store_rating']) && $offer['vendor_store_rating'] !== null && $offer['vendor_store_rating'] !== '') {
                                        $sellerRatingValue = number_format((float) $offer['vendor_store_rating'], 2, '.', '');
                                    } elseif (isset($offer['vendor_rating']) && $offer['vendor_rating'] !== null && $offer['vendor_rating'] !== '') {
                                        $sellerRatingValue = number_format((float) $offer['vendor_rating'], 2, '.', '');
                                    }

                                    $productRatingValue = '';
                                    if (isset($offer['product_rating']) && $offer['product_rating'] !== null && $offer['product_rating'] !== '') {
                                        $productRatingValue = number_format((float) $offer['product_rating'], 2, '.', '');
                                    }

                                    $soldValue = '';
                                    if (isset($offer['sold_count']) && $offer['sold_count'] !== null && $offer['sold_count'] !== '') {
                                        $soldValue = number_format((int) $offer['sold_count'], 0, ',', '.') . ' sold';
                                    }

                                    $priceValue = 0;
                                    if (isset($offer['price_idr'])) {
                                        $priceValue = (int) $offer['price_idr'];
                                    }
                                    $priceText = 'Rp ' . number_format($priceValue, 0, ',', '.');

                                    $offerId = 0;
                                    if (isset($offer['id'])) {
                                        $offerId = (int) $offer['id'];
                                    }
                                    $offerBuyUrl = $base . '/go/' . $offerId;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($marketplaceValue) ?></td>
                                        <td>
                                            <?php if ($sellerIsLinked): ?>
                                                <a href="<?= $base ?>/seller/<?= $sellerSlugText ?>"><?= htmlspecialchars($sellerValue) ?></a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($sellerValue) ?>
                                            <?php endif; ?>
                                            <?php if ($sellerLocationText !== ''): ?>
                                                <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= $sellerLocationText ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="offer-title"><?= htmlspecialchars($sellerTitleValue) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($sellerRatingValue !== ''): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                                <?= htmlspecialchars($sellerRatingValue) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($productRatingValue !== ''): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                                <?= htmlspecialchars($productRatingValue) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($soldValue !== ''): ?>
                                                <?= htmlspecialchars($soldValue) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="offer-price">
                                            <?php if ($offerIndex === 0): ?>
                                                <span class="badge bg-success">Lowest Price</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($priceText) ?>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-danger btn-sm" href="<?= htmlspecialchars($offerBuyUrl) ?>" target="_blank" rel="sponsored nofollow noopener">Buy</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($hiddenOfferCount > 0): ?>
                            <div class="mt-3">
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#moreOffers" aria-expanded="false" aria-controls="moreOffers">Show all <?= $offerCount ?> offers</button>
                            </div>
                            <div class="collapse mt-3" id="moreOffers">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0 price-table">
                                        <thead>
                                            <tr>
                                        <th scope="col">Marketplace</th>
                                        <th scope="col">Seller</th>
                                        <th scope="col">Listing Title</th>
                                        <th scope="col">Seller Rating</th>
                                        <th scope="col">Product Rating</th>
                                        <th scope="col">Sold</th>
                                        <th scope="col">Price</th>
                                        <th scope="col" class="text-end">Buy</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($hiddenOffers as $offer): ?>
                                            <?php
                                            $marketplaceValue = '';
                                            if (isset($offer['store_name'])) {
                                                $marketplaceValue = (string) $offer['store_name'];
                                            }

                                            $sellerValue = '';
                                            if (isset($offer['vendor_name']) && $offer['vendor_name'] !== null) {
                                                $sellerValue = (string) $offer['vendor_name'];
                                            }
                                            if ($sellerValue === '') {
                                                $sellerValue = 'General';
                                            }

                                            $sellerSlugValue = '';
                                            if (isset($offer['vendor_slug']) && $offer['vendor_slug'] !== null) {
                                                $sellerSlugValue = (string) $offer['vendor_slug'];
                                            }
                                            $sellerSlugText = htmlspecialchars($sellerSlugValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            $sellerIsLinked = false;
                                            if ($sellerSlugValue !== '') {
                                                $sellerIsLinked = true;
                                            }

                                            $sellerLocationText = '';
                                            if (isset($offer['vendor_location_name']) && $offer['vendor_location_name'] !== null && $offer['vendor_location_name'] !== '') {
                                                $sellerLocationText = htmlspecialchars((string) $offer['vendor_location_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                            }

                                            $sellerTitleValue = '';
                                            if (isset($offer['seller_title']) && $offer['seller_title'] !== null) {
                                                $sellerTitleValue = (string) $offer['seller_title'];
                                            }

                                            $sellerRatingValue = '';
                                            if (isset($offer['vendor_store_rating']) && $offer['vendor_store_rating'] !== null && $offer['vendor_store_rating'] !== '') {
                                                $sellerRatingValue = number_format((float) $offer['vendor_store_rating'], 2, '.', '');
                                            } elseif (isset($offer['vendor_rating']) && $offer['vendor_rating'] !== null && $offer['vendor_rating'] !== '') {
                                                $sellerRatingValue = number_format((float) $offer['vendor_rating'], 2, '.', '');
                                            }

                                            $productRatingValue = '';
                                            if (isset($offer['product_rating']) && $offer['product_rating'] !== null && $offer['product_rating'] !== '') {
                                                $productRatingValue = number_format((float) $offer['product_rating'], 2, '.', '');
                                            }

                                            $soldValue = '';
                                            if (isset($offer['sold_count']) && $offer['sold_count'] !== null && $offer['sold_count'] !== '') {
                                                $soldValue = number_format((int) $offer['sold_count'], 0, ',', '.') . ' sold';
                                            }

                                            $priceValue = 0;
                                            if (isset($offer['price_idr'])) {
                                                $priceValue = (int) $offer['price_idr'];
                                            }
                                            $priceText = 'Rp ' . number_format($priceValue, 0, ',', '.');

                                            $offerId = 0;
                                            if (isset($offer['id'])) {
                                                $offerId = (int) $offer['id'];
                                            }
                                            $offerBuyUrl = $base . '/go/' . $offerId;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($marketplaceValue) ?></td>
                                                <td>
                                                    <?php if ($sellerIsLinked): ?>
                                                        <a href="<?= $base ?>/seller/<?= $sellerSlugText ?>"><?= htmlspecialchars($sellerValue) ?></a>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($sellerValue) ?>
                                                    <?php endif; ?>
                                                    <?php if ($sellerLocationText !== ''): ?>
                                                        <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= $sellerLocationText ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="offer-title"><?= htmlspecialchars($sellerTitleValue) ?></div>
                                                </td>
                                                <td>
                                                    <?php if ($sellerRatingValue !== ''): ?>
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                        <?= htmlspecialchars($sellerRatingValue) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($productRatingValue !== ''): ?>
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                        <?= htmlspecialchars($productRatingValue) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($soldValue !== ''): ?>
                                                        <?= htmlspecialchars($soldValue) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="offer-price"><?= htmlspecialchars($priceText) ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-danger btn-sm" href="<?= htmlspecialchars($offerBuyUrl) ?>" target="_blank" rel="sponsored nofollow noopener">Buy</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card product-detail-card mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Description</h2>
                    <?php if ($descriptionHtml !== ''): ?>
                        <div class="product-description"><?= $descriptionHtml ?></div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No description available.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($related)): ?>
            <div class="mt-4">
                <h2 class="h5 mb-3">Related Products</h2>
                <div class="row g-4">
                    <?php foreach ($related as $product): ?>
                    <?php include __DIR__ . '/../_product_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card price-box">
                <div class="card-body">
                    <?php if ($offerCount > 0): ?>
                        <h2 class="h4 fw-bold mb-1">From Rp <?= number_format($lowestPrice, 0, ',', '.') ?></h2>
                        <p class="small text-muted mb-1"><?= $offerCount ?> sellers</p>
                        <p class="small text-muted">Updated <?= htmlspecialchars($updatedText) ?></p>
                        <a class="btn btn-danger w-100 mb-3" href="<?= htmlspecialchars($buyUrl) ?>" target="_blank" rel="sponsored nofollow noopener">Buy</a>
                    <?php else: ?>
                        <p class="text-muted">No active offers right now.</p>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($wishlistUrl) ?>" class="m-0">
                        <?= \App\Core\CsrfProtection::getHiddenInput() ?>
                        <button type="submit" class="btn btn-outline-primary w-100"><?= htmlspecialchars($wishlistLabel) ?></button>
                    </form>

                    <p class="disclosure-text mt-3 mb-0"><?= htmlspecialchars($disclosureText) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../layouts/footer.php'; ?>
