<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\ProductImporter;
use Exception;

/**
 * Lets admins upload a product CSV file and import it into the catalog.
 */
class AdminProductImportController extends Controller
{
    private const MAX_FILE_SIZE = 5242880;
    private const ALLOWED_EXTENSION = 'csv';

    /**
     * Show the CSV upload form and the report of the last import.
     */
    public function index(): void
    {
        $this->requireAdmin();

        $report = null;
        if (isset($_SESSION['import_report'])) {
            $report = $_SESSION['import_report'];
            unset($_SESSION['import_report']);
        }

        $this->view('dashboard/products/import', [
            'pageTitle' => 'Import Products',
            'metaDescription' => 'Import products and offers from a CSV file',
            'currentPage' => 'products-import',
            'report' => $report,
        ]);
    }

    /**
     * Store the uploaded CSV file in the storage folder and run the import.
     */
    public function store(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/products/import');
            return;
        }

        CsrfProtection::requireValidToken();

        $tempPath = '';
        $uploadedName = '';
        $report = null;
        $error = '';

        try {
            $file = $this->uploadedFile();
            $uploadedName = (string) $file['name'];
            $this->cleanOldTempFiles();
            $tempPath = $this->saveUploadedCsv($file);

            $importer = new ProductImporter($this->pdo, false);
            $report = $importer->importFile($tempPath);
        } catch (Exception $e) {
            $error = $e->getMessage();
            if ($tempPath !== '' && $uploadedName !== '') {
                $error = str_replace($tempPath, $uploadedName, $error);
            }
        } finally {
            if ($tempPath !== '' && is_file($tempPath)) {
                unlink($tempPath);
            }
        }

        if ($error !== '') {
            $_SESSION['error'] = $error;
            $this->redirect('/dashboard/products/import');
            return;
        }

        if (!is_array($report)) {
            $_SESSION['error'] = 'The import finished but produced no report.';
            $this->redirect('/dashboard/products/import');
            return;
        }

        $defaults = [
            'total_rows' => 0,
            'created_products' => 0,
            'created_vendors' => 0,
            'created_offers' => 0,
            'updated_offers' => 0,
        ];
        foreach ($defaults as $key => $val) {
            if (!isset($report[$key]) || !is_int($report[$key])) {
                error_log('ProductImporter::importFile() report missing/invalid key: ' . $key);
                $report[$key] = $val;
            }
        }

        $_SESSION['import_report'] = $report;
        $_SESSION['success'] = 'Import finished: ' . $report['total_rows'] . ' rows read, '
            . $report['created_products'] . ' products created, '
            . $report['created_vendors'] . ' sellers created, '
            . $report['created_offers'] . ' offers created, '
            . $report['updated_offers'] . ' offers updated.';

        $this->redirect('/dashboard/products/import');
    }

    /**
     * Validate the uploaded file and return its details.
     */
    private function uploadedFile(): array
    {
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('Please choose a CSV file.');
        }

        $file = $_FILES['csv_file'];
        if (!is_array($file) || !isset($file['error'])) {
            throw new Exception('The upload did not arrive. Please try again.');
        }

        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception('The file is too large. The maximum size is 5 MB.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('The upload failed. Please try again.');
        }

        $size = (int) $file['size'];
        if ($size <= 0) {
            throw new Exception('The uploaded file is empty.');
        }

        if ($size > self::MAX_FILE_SIZE) {
            throw new Exception('The file is too large. The maximum size is 5 MB.');
        }

        $name = (string) $file['name'];
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($extension !== self::ALLOWED_EXTENSION) {
            throw new Exception('Only .csv files are allowed.');
        }

        $temporaryName = (string) $file['tmp_name'];
        if ($temporaryName === '' || !is_readable($temporaryName)) {
            throw new Exception('The uploaded file could not be read.');
        }

        $head = file_get_contents($temporaryName, false, null, 0, 4096);
        if ($head === false) {
            throw new Exception('The uploaded file could not be read.');
        }

        if (strpos($head, "\0") !== false) {
            throw new Exception('The uploaded file is not a text CSV file.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($temporaryName);
        $allowed = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
        ];
        if (!in_array($mime, $allowed, true)) {
            throw new Exception('The uploaded file is not a valid CSV (detected: ' . $mime . ').');
        }

        return $file;
    }

    /**
     * Save the uploaded CSV file in the storage folder and return its path.
     */
    private function saveUploadedCsv(array $file): string
    {
        $folder = $this->storageFolder();
        $path = $folder . '/import_' . bin2hex(random_bytes(8)) . '.csv';
        $moved = move_uploaded_file((string) $file['tmp_name'], $path);

        if (!$moved) {
            throw new Exception('The uploaded file could not be saved.');
        }

        return $path;
    }

    /**
     * Return the storage folder of the application and create it when needed.
     */
    private function storageFolder(): string
    {
        $folder = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder;
    }

    /**
     * Delete the temporary upload files that are left over by an interrupted import.
     */
    private function cleanOldTempFiles(): void
    {
        $files = glob($this->storageFolder() . '/import_*.csv');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $modified = @filemtime($file);
            if ($modified === false) {
                continue;
            }
            if ($modified < time() - 3600) {
                @unlink($file);
            }
        }
    }
}
