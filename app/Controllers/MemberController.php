<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Models\MemberModel;
use Exception;

/**
 * Manages member accounts for root users.
 */
class MemberController extends Controller
{
    private MemberModel $memberModel;

    /**
     * Inject the database connection and build the member model.
     */
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->memberModel = new MemberModel($pdo);
    }

    /**
     * List all members with their status.
     */
    public function index(): void
    {
        $this->requireRoot();

        $members = $this->memberModel->findAllMembersForList();

        $this->view('dashboard/members/index', [
            'pageTitle' => 'Members',
            'metaDescription' => 'Manage public member accounts',
            'currentPage' => 'members',
            'members' => $members,
        ]);
    }

    /**
     * Activate a member (POST only).
     */
    public function activate(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/members');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;

        try {
            $this->memberModel->updateStatus($id, 'active');
            $_SESSION['success'] = 'Member activated successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/members');
    }

    /**
     * Ban a member (POST only).
     */
    public function ban(string $id): void
    {
        $this->requireRoot();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/dashboard/members');
            return;
        }

        CsrfProtection::requireValidToken();

        $id = (int) $id;

        try {
            $this->memberModel->updateStatus($id, 'banned');
            $_SESSION['success'] = 'Member banned successfully.';
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        $this->redirect('/dashboard/members');
    }
}
