<?php
/**
 * CSV importer for products and offers.
 *
 * Shared by the command line script bin/import_products.php and by the
 * dashboard upload page. Categories, brands and stores are never created here;
 * sellers are created from vendor_name when vendor_slug is empty. A row that
 * cannot be validated is rejected and nothing is written for it.
 */
namespace App\Core;

use PDO;
use Exception;
use App\Models\PriceHistoryModel;

class ProductImporter
{
    private PDO $pdo;
    private bool $dryRun;

    /**
     * Inject the database connection and the dry run flag.
     */
    public function __construct(PDO $pdo, bool $dryRun = false)
    {
        $this->pdo = $pdo;
        $this->dryRun = $dryRun;
    }

    /**
     * Import a CSV file and return the report of the run.
     */
    public function importFile(string $csvPath): array
    {
        if (!is_file($csvPath) || !is_readable($csvPath)) {
            throw new Exception('CSV file cannot be read: ' . $csvPath);
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            throw new Exception('CSV file cannot be read: ' . $csvPath);
        }

        // Skip the byte order mark of a file that was saved by Excel.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        try {
            $columns = $this->readHeader($handle, $csvPath);
            $report = $this->importRows($handle, $columns);
        } finally {
            fclose($handle);
        }

        return $report;
    }

    /**
     * Read the header row and return the map of column names to column indexes.
     */
    public function readHeader($handle, string $csvPath = ''): array
    {
        $header = fgetcsv($handle);
        if ($header === false) {
            throw new Exception('CSV file has no header row' . $this->pathSuffix($csvPath));
        }

        $columns = [];
        foreach ($header as $columnIndex => $columnName) {
            $columnName = trim((string) $columnName);
            if ($columnIndex === 0) {
                $columnName = preg_replace('/^\xEF\xBB\xBF/', '', $columnName);
            }
            if ($columnName === '') {
                continue;
            }
            if (!isset($columns[$columnName])) {
                $columns[$columnName] = $columnIndex;
            }
        }

        if (!isset($columns['product_slug'])) {
            throw new Exception('CSV header must contain a product_slug column' . $this->pathSuffix($csvPath));
        }

        return $columns;
    }

    /**
     * Return the file path suffix used in the error messages.
     */
    private function pathSuffix(string $csvPath): string
    {
        if ($csvPath === '') {
            return '';
        }

        return ': ' . $csvPath;
    }

    /**
     * Import every data row of an open CSV file and return the report of the run.
     */
    public function importRows($handle, array $columns): array
    {
        $transactionStarted = false;
        if (!$this->dryRun) {
            $this->pdo->beginTransaction();
            $transactionStarted = true;
        }

        try {
            $report = $this->importRowsInTransaction($handle, $columns);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($transactionStarted) {
            $this->pdo->commit();
        }

        return $report;
    }

    /**
     * Run the row loop. Called inside a transaction for a real import.
     */
    private function importRowsInTransaction($handle, array $columns): array
    {
        $totalRows = 0;
        $createdProducts = 0;
        $createdOffers = 0;
        $updatedOffers = 0;
        $createdVendors = 0;
        $skippedIdenticalOffers = 0;
        $skippedExistingProducts = 0;
        $rejections = [];
        $warnings = [];
        $warnedMarketplaces = [];

        $knownProductIds = [];
        $knownOffers = [];
        $knownOffersByExternal = [];
        $knownVendors = [];
        $pseudoProductId = 0;
        $pseudoVendorId = 0;

        $priceHistory = null;
        if (!$this->dryRun) {
            $priceHistory = new PriceHistoryModel($this->pdo);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $isEmptyRow = true;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $isEmptyRow = false;
                    break;
                }
            }
            if ($isEmptyRow) {
                continue;
            }

            $totalRows++;
            $rowNumber = $totalRows;

            $productSlug = $this->cellValue($row, $columns, 'product_slug');
            $name = $this->cellValue($row, $columns, 'name');
            $categorySlug = $this->cellValue($row, $columns, 'category_slug');
            $brandSlug = $this->cellValue($row, $columns, 'brand_slug');
            $marketplace = $this->cellValue($row, $columns, 'marketplace');
            $vendorSlug = $this->cellValue($row, $columns, 'vendor_slug');
            $priceRaw = $this->cellValue($row, $columns, 'price_idr');
            $affiliateUrl = $this->cellValue($row, $columns, 'affiliate_url');
            $sellerTitle = $this->cellValue($row, $columns, 'seller_title');
            $productRatingRaw = $this->cellValue($row, $columns, 'product_rating');
            $soldCountRaw = $this->cellValue($row, $columns, 'sold_count');
            $storeRatingRaw = $this->cellValue($row, $columns, 'store_rating');
            $shortDescription = $this->cellValue($row, $columns, 'short_description');
            $description = $this->cellValue($row, $columns, 'description');
            $sourceUrl = $this->cellValue($row, $columns, 'source_url');
            $vendorName = $this->cellValue($row, $columns, 'vendor_name');
            $externalIdRaw = $this->cellValue($row, $columns, 'external_id');
            $notes = $this->cellValue($row, $columns, 'notes');

            $externalId = '';
            if ($externalIdRaw !== '') {
                $externalId = $this->truncateText($externalIdRaw, 100);
            }

            if ($notes !== '' && strtolower($notes) !== 'ok') {
                $this->addRejection($rejections, $rowNumber, 'scraper note: ' . $notes, $productSlug);
                continue;
            }

            $vendorProvided = false;
            if ($vendorSlug !== '' || $vendorName !== '') {
                $vendorProvided = true;
            }
            $linkProvided = false;
            if ($affiliateUrl !== '' || $sourceUrl !== '') {
                $linkProvided = true;
            }

            $offerFieldsFilled = 0;
            if ($marketplace !== '') {
                $offerFieldsFilled++;
            }
            if ($vendorProvided) {
                $offerFieldsFilled++;
            }
            if ($priceRaw !== '') {
                $offerFieldsFilled++;
            }
            if ($linkProvided) {
                $offerFieldsFilled++;
            }

            if ($offerFieldsFilled === 0) {
                if ($productSlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing product_slug', '');
                    continue;
                }
                if (!preg_match('/^[a-z0-9-]+$/', $productSlug)) {
                    $this->addRejection($rejections, $rowNumber, 'invalid product_slug', $productSlug);
                    continue;
                }

                $existingProductId = null;
                if (isset($knownProductIds[$productSlug])) {
                    $existingProductId = $knownProductIds[$productSlug];
                } else {
                    $existingProductId = $this->findProductId($productSlug);
                    if ($existingProductId !== null) {
                        $knownProductIds[$productSlug] = $existingProductId;
                    }
                }

                if ($existingProductId !== null) {
                    $skippedExistingProducts++;
                    continue;
                }

                if ($name === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing name', $productSlug);
                    continue;
                }
                if ($categorySlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing category_slug', $productSlug);
                    continue;
                }
                if ($brandSlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing brand_slug', $productSlug);
                    continue;
                }

                $categoryId = $this->findCategoryId($categorySlug);
                if ($categoryId === null) {
                    $this->addRejection($rejections, $rowNumber, 'unknown category: ' . $categorySlug, $productSlug);
                    continue;
                }
                $brandId = $this->findBrandId($brandSlug);
                if ($brandId === null) {
                    $this->addRejection($rejections, $rowNumber, 'unknown brand: ' . $brandSlug, $productSlug);
                    continue;
                }

                $productData = [
                    'name' => $this->truncateText($name, 200),
                    'slug' => $productSlug,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'status' => 'draft',
                ];
                if ($shortDescription !== '') {
                    $productData['short_description'] = $this->truncateText($shortDescription, 500);
                }
                if ($description !== '') {
                    $productData['description'] = RichText::clean($description);
                }

                $newProductId = 0;
                if ($this->dryRun) {
                    $pseudoProductId--;
                    $newProductId = $pseudoProductId;
                } else {
                    $newProductId = $this->insertProduct($productData);
                }

                $knownProductIds[$productSlug] = $newProductId;
                $createdProducts++;
                continue;
            }

            if ($offerFieldsFilled !== 4) {
                $this->addRejection($rejections, $rowNumber, 'incomplete offer data', $productSlug);
                continue;
            }
            if ($productSlug === '') {
                $this->addRejection($rejections, $rowNumber, 'missing product_slug', '');
                continue;
            }
            if (!preg_match('/^[a-z0-9-]+$/', $productSlug)) {
                $this->addRejection($rejections, $rowNumber, 'invalid product_slug', $productSlug);
                continue;
            }

            $storeId = $this->findStoreId($marketplace);
            if ($storeId === null) {
                $this->addRejection($rejections, $rowNumber, 'unknown marketplace: ' . $marketplace, $productSlug);
                continue;
            }

            if ($externalId !== '') {
                $existingProductIdForExternal = null;
                if (isset($knownProductIds[$productSlug])) {
                    $existingProductIdForExternal = $knownProductIds[$productSlug];
                } else {
                    $existingProductIdForExternal = $this->findProductId($productSlug);
                }

                $externalOffer = $this->findOfferByExternalId($storeId, $externalId);
                if ($externalOffer !== null) {
                    $linkedProductId = (int) $externalOffer['product_id'];
                    if ($existingProductIdForExternal === null || $linkedProductId !== (int) $existingProductIdForExternal) {
                        $this->addRejection($rejections, $rowNumber, 'listing already linked to another product', $productSlug);
                        continue;
                    }
                    $knownOffersByExternal[$storeId . ':' . $externalId] = $externalOffer;
                }
            }

            $priceIdr = filter_var($priceRaw, FILTER_VALIDATE_INT);
            if ($priceIdr === false) {
                $this->addRejection($rejections, $rowNumber, 'invalid price_idr', $productSlug);
                continue;
            }
            if ($priceIdr <= 0) {
                $this->addRejection($rejections, $rowNumber, 'invalid price_idr', $productSlug);
                continue;
            }

            $productRatingValue = null;
            if ($productRatingRaw !== '') {
                $productRating = filter_var(str_replace(',', '.', $productRatingRaw), FILTER_VALIDATE_FLOAT);
                if ($productRating === false || $productRating < 0 || $productRating > 5) {
                    $this->addRejection($rejections, $rowNumber, 'invalid product_rating', $productSlug);
                    continue;
                }
                $productRatingValue = (float) $productRating;
            }

            $soldCountValue = null;
            if ($soldCountRaw !== '') {
                $soldCount = filter_var($soldCountRaw, FILTER_VALIDATE_INT);
                if ($soldCount === false || $soldCount < 0) {
                    $this->addRejection($rejections, $rowNumber, 'invalid sold_count', $productSlug);
                    continue;
                }
                $soldCountValue = (int) $soldCount;
            }

            $storeRatingValue = null;
            if ($storeRatingRaw !== '') {
                $storeRating = filter_var(str_replace(',', '.', $storeRatingRaw), FILTER_VALIDATE_FLOAT);
                if ($storeRating === false || $storeRating < 0 || $storeRating > 5) {
                    $this->addRejection($rejections, $rowNumber, 'invalid store_rating', $productSlug);
                    continue;
                }
                $storeRatingValue = (float) $storeRating;
            }

            $sellerTitleValue = null;
            if ($sellerTitle !== '') {
                $sellerTitleValue = $this->truncateText($sellerTitle, 255);
            }

            $vendorId = 0;
            $vendorStoreId = 0;
            if ($vendorSlug !== '') {
                $vendor = $this->findVendor($vendorSlug);
                if ($vendor === null) {
                    $this->addRejection($rejections, $rowNumber, 'unknown vendor: ' . $vendorSlug, $productSlug);
                    continue;
                }
                $vendorId = $vendor['id'];
                $vendorStoreId = $vendor['store_id'];
            } else {
                $vendorNameValue = $this->truncateText($vendorName, 255);
                $generatedVendorSlug = $this->generateSlug($marketplace . '-' . $vendorNameValue);
                if ($generatedVendorSlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'invalid vendor_name', $productSlug);
                    continue;
                }

                if (isset($knownVendors[$generatedVendorSlug])) {
                    $vendorId = $knownVendors[$generatedVendorSlug]['id'];
                    $vendorStoreId = $knownVendors[$generatedVendorSlug]['store_id'];
                } else {
                    $vendor = $this->findVendor($generatedVendorSlug);
                    if ($vendor !== null) {
                        $vendorId = $vendor['id'];
                        $vendorStoreId = $vendor['store_id'];
                    } else {
                        if ($this->dryRun) {
                            $pseudoVendorId--;
                            $vendorId = $pseudoVendorId;
                        } else {
                            $vendorId = $this->insertVendor($storeId, $vendorNameValue, $generatedVendorSlug, $storeRatingValue);
                        }
                        $vendorStoreId = $storeId;
                        $createdVendors++;
                    }
                    $knownVendors[$generatedVendorSlug] = [
                        'id' => $vendorId,
                        'store_id' => $vendorStoreId,
                    ];
                }
            }

            if ($vendorStoreId !== $storeId) {
                $this->addRejection($rejections, $rowNumber, 'vendor does not belong to marketplace', $productSlug);
                continue;
            }

            $resolvedAffiliateUrl = $affiliateUrl;
            if ($resolvedAffiliateUrl === '') {
                $template = $this->findStoreAffiliateTemplate($storeId);
                if ($template !== null && $template !== '') {
                    $resolvedAffiliateUrl = str_replace('{url_encoded}', rawurlencode($sourceUrl), $template);
                    $resolvedAffiliateUrl = str_replace('{url}', $sourceUrl, $resolvedAffiliateUrl);
                    $resolvedAffiliateUrl = str_replace('{external_id}', $externalId, $resolvedAffiliateUrl);
                } else {
                    $resolvedAffiliateUrl = $sourceUrl;
                    if (!isset($warnedMarketplaces[$marketplace])) {
                        $warnedMarketplaces[$marketplace] = true;
                        $warnings[] = 'no affiliate template for ' . $marketplace;
                    }
                }
            }

            if (!preg_match('/^https?:\/\//i', $resolvedAffiliateUrl)) {
                $this->addRejection($rejections, $rowNumber, 'invalid affiliate_url', $productSlug);
                continue;
            }

            $productId = null;
            if (isset($knownProductIds[$productSlug])) {
                $productId = $knownProductIds[$productSlug];
            } else {
                $productId = $this->findProductId($productSlug);
                if ($productId !== null) {
                    $knownProductIds[$productSlug] = $productId;
                }
            }

            if ($productId === null) {
                if ($name === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing name', $productSlug);
                    continue;
                }
                if ($categorySlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing category_slug', $productSlug);
                    continue;
                }
                if ($brandSlug === '') {
                    $this->addRejection($rejections, $rowNumber, 'missing brand_slug', $productSlug);
                    continue;
                }

                $categoryId = $this->findCategoryId($categorySlug);
                if ($categoryId === null) {
                    $this->addRejection($rejections, $rowNumber, 'unknown category: ' . $categorySlug, $productSlug);
                    continue;
                }
                $brandId = $this->findBrandId($brandSlug);
                if ($brandId === null) {
                    $this->addRejection($rejections, $rowNumber, 'unknown brand: ' . $brandSlug, $productSlug);
                    continue;
                }

                $productData = [
                    'name' => $this->truncateText($name, 200),
                    'slug' => $productSlug,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'status' => 'draft',
                ];
                if ($shortDescription !== '') {
                    $productData['short_description'] = $this->truncateText($shortDescription, 500);
                }
                if ($description !== '') {
                    $productData['description'] = RichText::clean($description);
                }

                if ($this->dryRun) {
                    $pseudoProductId--;
                    $productId = $pseudoProductId;
                } else {
                    $productId = $this->insertProduct($productData);
                }

                $knownProductIds[$productSlug] = $productId;
                $createdProducts++;
            }

            if ($storeRatingValue !== null && !$this->dryRun) {
                $this->updateVendorStoreRating($vendorId, $storeRatingValue);
            }

            $offerKey = $productId . ':' . $vendorId;
            $existingOffer = null;
            if ($externalId !== '') {
                $externalKey = $storeId . ':' . $externalId;
                if (isset($knownOffersByExternal[$externalKey])) {
                    $existingOffer = $knownOffersByExternal[$externalKey];
                } else {
                    $existingOffer = $this->findOfferByExternalId($storeId, $externalId);
                }

                if ($existingOffer !== null) {
                    if ((int) $existingOffer['product_id'] !== $productId) {
                        $this->addRejection($rejections, $rowNumber, 'listing already linked to another product', $productSlug);
                        continue;
                    }
                    if (!isset($knownOffers[$offerKey])) {
                        $knownOffers[$offerKey] = $existingOffer;
                    }
                }
            }

            if ($existingOffer === null) {
                if (isset($knownOffers[$offerKey])) {
                    $existingOffer = $knownOffers[$offerKey];
                } else {
                    $existingOffer = $this->findOffer($productId, $vendorId);
                    if ($existingOffer !== null) {
                        $knownOffers[$offerKey] = $existingOffer;
                    }
                }
            }

            if ($existingOffer !== null) {
                $storedExternalIdValue = '';
                if (isset($existingOffer['external_id']) && $existingOffer['external_id'] !== null) {
                    $storedExternalIdValue = (string) $existingOffer['external_id'];
                }
                if ($storedExternalIdValue !== '' && $externalId !== '' && $storedExternalIdValue !== $externalId) {
                    $this->addRejection($rejections, $rowNumber, 'seller already has another listing for this product', $productSlug);
                    continue;
                }
            }

            if ($existingOffer === null) {
                $offerData = [
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'vendor_id' => $vendorId,
                    'price_idr' => $priceIdr,
                    'affiliate_url' => $resolvedAffiliateUrl,
                    'seller_title' => $sellerTitleValue,
                    'in_stock' => 1,
                    'status' => 'published',
                    'last_checked_at' => date('Y-m-d H:i:s'),
                ];
                if ($productRatingValue !== null) {
                    $offerData['product_rating'] = $productRatingValue;
                }
                if ($soldCountValue !== null) {
                    $offerData['sold_count'] = $soldCountValue;
                }
                if ($sourceUrl !== '') {
                    $offerData['source_url'] = $sourceUrl;
                }
                if ($externalId !== '') {
                    $offerData['external_id'] = $externalId;
                }

                $newOfferId = 0;
                if (!$this->dryRun) {
                    $newOfferId = $this->insertOffer($offerData);
                    $priceHistory->record($newOfferId, $priceIdr);
                }

                $knownOffers[$offerKey] = [
                    'id' => $newOfferId,
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'price_idr' => $priceIdr,
                    'affiliate_url' => $resolvedAffiliateUrl,
                    'seller_title' => $sellerTitleValue,
                    'external_id' => $externalId,
                    'source_url' => $sourceUrl,
                ];
                if ($externalId !== '') {
                    $knownOffersByExternal[$storeId . ':' . $externalId] = $knownOffers[$offerKey];
                }
                $createdOffers++;
                continue;
            }

            $storedPriceIdr = (int) $existingOffer['price_idr'];
            $storedAffiliateUrl = (string) $existingOffer['affiliate_url'];
            $storedSellerTitle = null;
            if (isset($existingOffer['seller_title'])) {
                if ($existingOffer['seller_title'] !== null && $existingOffer['seller_title'] !== '') {
                    $storedSellerTitle = (string) $existingOffer['seller_title'];
                }
            }
            $storedProductRating = null;
            if (isset($existingOffer['product_rating']) && $existingOffer['product_rating'] !== null) {
                $storedProductRating = (float) $existingOffer['product_rating'];
            }
            $storedSoldCount = null;
            if (isset($existingOffer['sold_count']) && $existingOffer['sold_count'] !== null) {
                $storedSoldCount = (int) $existingOffer['sold_count'];
            }
            $storedExternalId = '';
            if (isset($existingOffer['external_id']) && $existingOffer['external_id'] !== null) {
                $storedExternalId = (string) $existingOffer['external_id'];
            }
            $storedSourceUrl = '';
            if (isset($existingOffer['source_url']) && $existingOffer['source_url'] !== null) {
                $storedSourceUrl = (string) $existingOffer['source_url'];
            }

            $newExternalId = $storedExternalId;
            if ($storedExternalId === '' && $externalId !== '') {
                $newExternalId = $externalId;
            }
            $newSourceUrl = $storedSourceUrl;
            if ($storedSourceUrl === '' && $sourceUrl !== '') {
                $newSourceUrl = $sourceUrl;
            }

            if ($storedPriceIdr !== $priceIdr) {
                $priceDifference = abs($priceIdr - $storedPriceIdr);
                if ($storedPriceIdr > 0 && ($priceDifference / $storedPriceIdr) > 0.5) {
                    $warnings[] = 'large price change: ' . $productSlug . ' ' . $storedPriceIdr . ' -> ' . $priceIdr;
                }
            }

            $offerChanged = false;
            if ($storedPriceIdr !== $priceIdr) {
                $offerChanged = true;
            }
            if ($storedAffiliateUrl !== $resolvedAffiliateUrl) {
                $offerChanged = true;
            }
            if ($storedSellerTitle !== $sellerTitleValue) {
                $offerChanged = true;
            }
            if ($productRatingValue !== null && $storedProductRating !== $productRatingValue) {
                $offerChanged = true;
            }
            if ($soldCountValue !== null && $storedSoldCount !== $soldCountValue) {
                $offerChanged = true;
            }
            if ($storedExternalId === '' && $externalId !== '') {
                $offerChanged = true;
            }
            if ($storedSourceUrl === '' && $sourceUrl !== '') {
                $offerChanged = true;
            }

            if ($offerChanged) {
                if (!$this->dryRun) {
                    $externalToStore = null;
                    if ($newExternalId !== '') {
                        $externalToStore = $newExternalId;
                    }
                    $sourceToStore = null;
                    if ($newSourceUrl !== '') {
                        $sourceToStore = $newSourceUrl;
                    }
                    $this->updateOffer((int) $existingOffer['id'], $priceIdr, $resolvedAffiliateUrl, $sellerTitleValue, $productRatingValue, $soldCountValue, $externalToStore, $sourceToStore);
                    if ($storedPriceIdr !== $priceIdr) {
                        $priceHistory->record((int) $existingOffer['id'], $priceIdr);
                    }
                }

                $knownOffers[$offerKey] = [
                    'id' => $existingOffer['id'],
                    'product_id' => $productId,
                    'store_id' => $storeId,
                    'price_idr' => $priceIdr,
                    'affiliate_url' => $resolvedAffiliateUrl,
                    'seller_title' => $sellerTitleValue,
                    'external_id' => $newExternalId,
                    'source_url' => $newSourceUrl,
                ];
                if ($newExternalId !== '') {
                    $knownOffersByExternal[$storeId . ':' . $newExternalId] = $knownOffers[$offerKey];
                }
                $updatedOffers++;
                continue;
            }

            if (!$this->dryRun) {
                $this->touchOffer((int) $existingOffer['id']);
            }

            $skippedIdenticalOffers++;
        }

        return [
            'total_rows' => $totalRows,
            'created_products' => $createdProducts,
            'created_offers' => $createdOffers,
            'updated_offers' => $updatedOffers,
            'created_vendors' => $createdVendors,
            'warnings' => $warnings,
            'skipped_identical_offers' => $skippedIdenticalOffers,
            'skipped_existing_products' => $skippedExistingProducts,
            'rejections' => $rejections,
        ];
    }

    /**
     * Read one cell of a CSV row using the header column map.
     */
    private function cellValue(array $row, array $columns, string $name): string
    {
        if (!isset($columns[$name])) {
            return '';
        }

        $index = $columns[$name];
        if (!isset($row[$index])) {
            return '';
        }

        return trim((string) $row[$index]);
    }

    /**
     * Truncate a text value to the given number of characters.
     */
    private function truncateText(string $value, int $limit): string
    {
        if (mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit);
        }

        return $value;
    }

    /**
     * Store a rejected row for the report of the run.
     */
    private function addRejection(array &$rejections, int $rowNumber, string $reason, string $slug): void
    {
        $rejections[] = [
            'row' => $rowNumber,
            'reason' => $reason,
            'slug' => $slug,
        ];
    }

    /**
     * Look up a category id by slug.
     */
    private function findCategoryId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `categories` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Look up a brand id by slug.
     */
    private function findBrandId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `brands` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Look up a store id by slug.
     */
    private function findStoreId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `stores` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Look up a vendor by slug and return its id and its store id.
     */
    private function findVendor(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, store_id FROM `vendors` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'store_id' => (int) $row['store_id'],
        ];
    }

    /**
     * Look up a product id by slug.
     */
    private function findProductId(string $slug): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM `products` WHERE `slug` = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    /**
     * Look up the offer of a product for one vendor.
     */
    private function findOffer(int $productId, int $vendorId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $sql = 'SELECT id, product_id, store_id, price_idr, affiliate_url, seller_title, product_rating, sold_count, external_id, source_url
                FROM `offers`
                WHERE `product_id` = ? AND `vendor_id` = ?
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$productId, $vendorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row;
    }

    /**
     * Look up an offer by its marketplace listing identity.
     */
    private function findOfferByExternalId(int $storeId, string $externalId): ?array
    {
        $sql = 'SELECT id, product_id, store_id, price_idr, affiliate_url, seller_title, product_rating, sold_count, external_id, source_url
                FROM `offers`
                WHERE `store_id` = ? AND `external_id` = ?
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeId, $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $row;
    }

    /**
     * Return the affiliate URL template of a store, when set.
     */
    private function findStoreAffiliateTemplate(int $storeId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT `affiliate_url_template` FROM `stores` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$storeId]);
        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Insert a product row with prepared statements and return the new id.
     */
    private function insertProduct(array $data): int
    {
        $columns = [];
        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $column)) {
                throw new Exception('Invalid column name: ' . $column);
            }
            $columns[] = $column;
        }

        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO `products` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert an offer row with prepared statements and return the new id.
     */
    private function insertOffer(array $data): int
    {
        $columns = [];
        foreach (array_keys($data) as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $column)) {
                throw new Exception('Invalid column name: ' . $column);
            }
            $columns[] = $column;
        }

        $placeholders = array_fill(0, count($columns), '?');
        $sql = 'INSERT INTO `offers` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a vendor with prepared statements and return the new id.
     */
    private function insertVendor(int $storeId, string $name, string $slug, ?float $storeRating): int
    {
        if ($storeRating === null) {
            $sql = 'INSERT INTO `vendors` (`store_id`, `name`, `slug`, `status`) VALUES (?, ?, ?, ?)';
            $params = [$storeId, $name, $slug, 'active'];
        } else {
            $sql = 'INSERT INTO `vendors` (`store_id`, `name`, `slug`, `status`, `store_rating`) VALUES (?, ?, ?, ?, ?)';
            $params = [$storeId, $name, $slug, 'active', $storeRating];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Turn free text into a lowercase dash-separated slug.
     */
    private function generateSlug(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        return $slug;
    }

    /**
     * Update the price, the affiliate url, the seller title and the check time of an offer.
     */
    private function updateOffer(int $offerId, int $priceIdr, string $affiliateUrl, ?string $sellerTitle, ?float $productRating, ?int $soldCount, ?string $externalId, ?string $sourceUrl): void
    {
        $sql = 'UPDATE `offers` SET `price_idr` = ?, `affiliate_url` = ?, `seller_title` = ?, `last_checked_at` = ?, `in_stock` = 1';
        $params = [$priceIdr, $affiliateUrl, $sellerTitle, date('Y-m-d H:i:s')];

        if ($productRating !== null) {
            $sql .= ', `product_rating` = ?';
            $params[] = $productRating;
        }
        if ($soldCount !== null) {
            $sql .= ', `sold_count` = ?';
            $params[] = $soldCount;
        }

        $sql .= ', `external_id` = ?, `source_url` = ?';
        $params[] = $externalId;
        $params[] = $sourceUrl;

        $sql .= ' WHERE `id` = ?';
        $params[] = $offerId;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Mark an offer as confirmed and in stock without changing its data.
     */
    private function touchOffer(int $offerId): void
    {
        $sql = 'UPDATE `offers` SET `last_checked_at` = ?, `in_stock` = 1 WHERE `id` = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), $offerId]);
    }

    /**
     * Update the store rating of a vendor.
     */
    private function updateVendorStoreRating(int $vendorId, float $storeRating): void
    {
        $sql = 'UPDATE `vendors` SET `store_rating` = ? WHERE `id` = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$storeRating, $vendorId]);
    }
}
