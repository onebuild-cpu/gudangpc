<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;
use App\Core\CsrfProtection;
use App\Models\WishlistModel;
use App\Models\ProductModel;
use Exception;

/**
 * Wishlist handling for logged-in members.
 */
class WishlistController extends Controller
{
    private WishlistModel $wishlistModel;
    private ProductModel $productModel;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->wishlistModel = new WishlistModel($pdo);
        $this->productModel  = new ProductModel($pdo);
    }

    /**
     * Add a published product to the member's wishlist.
     */
    public function add(string $productId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
            return;
        }

        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
            $_SESSION['error'] = 'Please log in to save to your wishlist.';
            $this->redirect('/login');
            return;
        }

        $role = '';
        if (isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        }
        if ($role !== 'visitor') {
            $_SESSION['error'] = 'Only members can use the wishlist.';
            $this->redirect('/dashboard');
            return;
        }

        CsrfProtection::requireValidToken();

        $productIdInt = (int) $productId;
        $product = $this->productModel->findByIdWithRelations($productIdInt);
        if (!$product || $product['status'] !== 'published') {
            $_SESSION['error'] = 'Product not found.';
            $this->redirect('/');
            return;
        }

        $userId = (int) $_SESSION['user_id'];
        if ($this->wishlistModel->exists($userId, $productIdInt)) {
            $_SESSION['success'] = 'Product is already in your wishlist.';
        } else {
            try {
                if ($this->wishlistModel->add($userId, $productIdInt)) {
                    $_SESSION['success'] = 'Product saved to your wishlist.';
                } else {
                    $_SESSION['error'] = 'Failed to save product.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Failed to save product.';
            }
        }

        $this->redirect('/' . $product['category_slug'] . '/' . $product['slug']);
    }

    /**
     * Remove a product from the member's wishlist.
     */
    public function remove(string $productId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
            return;
        }

        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
            $_SESSION['error'] = 'Please log in to remove from your wishlist.';
            $this->redirect('/login');
            return;
        }

        $role = '';
        if (isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
        }
        if ($role !== 'visitor') {
            $_SESSION['error'] = 'Only members can use the wishlist.';
            $this->redirect('/dashboard');
            return;
        }

        CsrfProtection::requireValidToken();

        $userId = (int) $_SESSION['user_id'];
        if ($this->wishlistModel->remove($userId, (int) $productId)) {
            $_SESSION['success'] = 'Product removed from your wishlist.';
        } else {
            $_SESSION['error'] = 'Failed to remove product.';
        }

        $this->redirect('/member');
    }
}