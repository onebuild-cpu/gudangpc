<?php
namespace App\Core;

/**
 * Small helpers that build SEO output for the public layouts.
 */
class Seo
{
    /**
     * Turn a site-relative path into an absolute URL using APP_URL.
     */
    public static function absoluteUrl(string $path): string
    {
        $base = Dotenv::get('APP_URL', '');
        $base = rtrim((string) $base, '/');

        if ($base === '') {
            return '';
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * Convert stored HTML into a short plain-text description.
     */
    public static function plainText(string $html, int $max): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim((string) $text);

        if ($max > 0 && mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
            $lastSpace = mb_strrpos($text, ' ');
            if ($lastSpace !== false) {
                $text = mb_substr($text, 0, $lastSpace);
            }
            $text = trim($text);
        }

        return $text;
    }

    /**
     * Validate and re-encode a JSON-LD snippet, returning '' when it is invalid.
     */
    public static function cleanJsonLd(string $raw): string
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);

        if ($decoded === null) {
            return '';
        }

        $encoded = json_encode($decoded, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return '';
        }

        return $encoded;
    }

    /**
     * Build a BreadcrumbList JSON-LD string from labelled paths.
     */
    public static function breadcrumbJsonLd(array $crumbs): string
    {
        $base = Dotenv::get('APP_URL', '');
        $base = rtrim((string) $base, '/');

        if ($base === '') {
            return '';
        }

        $items = [];
        $position = 1;

        foreach ($crumbs as $crumb) {
            $label = '';
            if (isset($crumb['label'])) {
                $label = (string) $crumb['label'];
            }
            $path = '';
            if (isset($crumb['path'])) {
                $path = (string) $crumb['path'];
            }

            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $label,
            ];

            if ($path !== '') {
                $item['item'] = self::absoluteUrl($path);
            }

            $items[] = $item;
            $position++;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        $encoded = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return '';
        }

        return $encoded;
    }

    /**
     * Build a Product JSON-LD string with an AggregateOffer for active offers.
     */
    public static function productJsonLd(array $product, string $brandName, array $offers, string $path, string $imagePath): string
    {
        if (empty($offers)) {
            return '';
        }

        $lowPrice = 0;
        $highPrice = 0;
        $first = true;

        foreach ($offers as $offer) {
            if (!isset($offer['price_idr'])) {
                continue;
            }

            $price = (int) $offer['price_idr'];

            if ($first) {
                $lowPrice = $price;
                $highPrice = $price;
                $first = false;
            } else {
                if ($price < $lowPrice) {
                    $lowPrice = $price;
                }
                if ($price > $highPrice) {
                    $highPrice = $price;
                }
            }
        }

        $name = '';
        if (isset($product['name'])) {
            $name = (string) $product['name'];
        }

        $shortDescription = '';
        if (isset($product['short_description'])) {
            $shortDescription = (string) $product['short_description'];
        }

        $longDescription = '';
        if (isset($product['description'])) {
            $longDescription = (string) $product['description'];
        }

        $description = '';
        if ($shortDescription !== '') {
            $description = self::plainText($shortDescription, 300);
        } else {
            $description = self::plainText($longDescription, 300);
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
        ];

        if ($description !== '') {
            $data['description'] = $description;
        }

        if ($imagePath !== '') {
            $data['image'] = self::absoluteUrl($imagePath);
        }

        if ($brandName !== '') {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => $brandName,
            ];
        }

        $mpn = '';
        if (isset($product['model_number'])) {
            $mpn = (string) $product['model_number'];
        }
        if ($mpn !== '') {
            $data['mpn'] = $mpn;
        }

        $data['url'] = self::absoluteUrl($path);
        $data['offers'] = [
            '@type' => 'AggregateOffer',
            'priceCurrency' => 'IDR',
            'lowPrice' => $lowPrice,
            'highPrice' => $highPrice,
            'offerCount' => count($offers),
            'availability' => 'https://schema.org/InStock',
        ];

        $encoded = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return '';
        }

        return $encoded;
    }
}
