<?php
namespace App\Core;

/**
 * Slugs that must never be used by a public content row, because the
 * fixed routes would be shadowed by the public content page.
 */
final class ReservedSlugs
{
    private const SLUGS = [
        'login', 'logout', 'register', 'verify-email',
        'member', 'dashboard',
        'categories', 'category', 'brands', 'brand', 'seller', 'locations',
        'product', 'products', 'store', 'stores',
        'search', 'wishlist',
        'compare', 'go', 'csp-report',
        'uploads', 'assets', 'favicon', 'robots', 'sitemap',
        'auth',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }
}
