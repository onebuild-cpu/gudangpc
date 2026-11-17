<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Core\Validator;
use App\Models\SettingModel;

/**
 * Manages global SEO default settings for admins and root users.
 */
class AdminSettingController extends Controller
{
    private SettingModel $settingModel;

    /**
     * Inject the database connection and build the settings model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->settingModel = new SettingModel($pdo);
    }

    /**
     * Show the SEO defaults settings form.
     */
    public function index(): void
    {
        $this->requireRoot();

        $rows = $this->settingModel->findAll('setting_key ASC');

        $settings = [];
        foreach ($rows as $row) {
            if (isset($row['setting_key'])) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        $this->view('dashboard/settings/index', [
            'pageTitle' => 'SEO Defaults',
            'metaDescription' => 'Manage SEO Defaults',
            'currentPage' => 'settings',
            'settings' => $settings,
        ]);
    }

    /**
     * Save the submitted SEO default settings.
     */
    public function update(): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/settings');
            return;
        }

        CsrfProtection::requireValidToken();

        $fields = [
            'default_schema',
            'default_custom_head',
            'default_custom_body',
            'default_custom_footer',
            'default_og_desc',
            'default_twitter_desc',
        ];

        foreach ($fields as $field) {
            $rawValue = '';
            if (isset($_POST[$field])) {
                $rawValue = $_POST[$field];
            }
            $value = Validator::sanitize($rawValue, 5000, true);
            $this->settingModel->saveSetting($field, $value);
        }

        $_SESSION['success'] = 'SEO Defaults updated successfully.';
        $this->redirect('/dashboard/settings');
    }
}
