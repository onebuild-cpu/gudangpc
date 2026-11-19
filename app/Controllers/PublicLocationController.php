<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\Seo;
use App\Models\VendorModel;

/**
 * Serves the public store-locations page.
 */
class PublicLocationController extends Controller
{
    private VendorModel $vendorModel;

    /**
     * Inject the database connection and build the vendor model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->vendorModel = new VendorModel($pdo);
    }

    /**
     * List locations with their eligible sellers.
     */
    public function index(): void
    {
        $locationRows = $this->vendorModel->findLocationsWithSellerCounts();
        $sellerRows = $this->vendorModel->findEligibleSellersWithLocation();

        $sellersByLocation = [];
        foreach ($sellerRows as $seller) {
            $seller['rating_text'] = $this->ratingText($seller);
            $locationId = (int) $seller['location_id'];
            if (!isset($sellersByLocation[$locationId])) {
                $sellersByLocation[$locationId] = [];
            }
            $sellersByLocation[$locationId][] = $seller;
        }

        $locationSections = [];
        foreach ($locationRows as $locationRow) {
            $locationId = (int) $locationRow['id'];
            $sellers = [];
            if (isset($sellersByLocation[$locationId])) {
                $sellers = $sellersByLocation[$locationId];
            }
            if (count($sellers) < 1) {
                continue;
            }
            $locationSections[] = [
                'name' => (string) $locationRow['name'],
                'slug' => (string) $locationRow['slug'],
                'seller_count' => count($sellers),
                'sellers' => $sellers,
            ];
        }

        $this->view('public/locations/index', [
            'pageTitle' => 'Lokasi Toko - GudangPC',
            'metaDescription' => 'Temukan toko dan penjual komponen PC berdasarkan lokasi di GudangPC.',
            'locationSections' => $locationSections,
            'seo' => null,
            'canonicalUrl' => Seo::absoluteUrl('/locations'),
            'ogImage' => '',
        ]);
    }

    /**
     * Format a seller rating the same way as the homepage.
     */
    private function ratingText(array $seller): string
    {
        $ratingText = '';
        $storeRating = '';
        if (isset($seller['store_rating']) && $seller['store_rating'] !== null && $seller['store_rating'] !== '') {
            $storeRating = (string) $seller['store_rating'];
        }
        if ($storeRating !== '') {
            $ratingText = number_format((float) $storeRating, 2, '.', '');
        } elseif (isset($seller['rating']) && $seller['rating'] !== null && $seller['rating'] !== '') {
            $ratingText = number_format((float) $seller['rating'], 2, '.', '');
        }

        return $ratingText;
    }
}
