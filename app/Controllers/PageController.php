<?php
namespace App\Controllers;

use PDO;
use App\Core\Controller;

/**
 * Serves static informational and legal pages.
 */
class PageController extends Controller
{
    /**
     * Show the About page.
     */
    public function about(): void
    {
        $this->view('pages/about', [
            'pageTitle' => 'Tentang Kami',
            'metaDescription' => 'Tentang GudangPC.',
        ]);
    }

    /**
     * Show the How It Works page.
     */
    public function howItWorks(): void
    {
        $this->view('pages/how-it-works', [
            'pageTitle' => 'Cara Kerja',
            'metaDescription' => 'Cara kerja GudangPC.',
        ]);
    }

    /**
     * Show the Contact page.
     */
    public function contact(): void
    {
        $this->view('pages/contact', [
            'pageTitle' => 'Hubungi Kami',
            'metaDescription' => 'Hubungi GudangPC.',
        ]);
    }

    /**
     * Show the Privacy Policy page.
     */
    public function privacy(): void
    {
        $this->view('pages/privacy', [
            'pageTitle' => 'Kebijakan Privasi',
            'metaDescription' => 'Kebijakan privasi GudangPC.',
        ]);
    }

    /**
     * Show the Terms and Conditions page.
     */
    public function terms(): void
    {
        $this->view('pages/terms', [
            'pageTitle' => 'Syarat & Ketentuan',
            'metaDescription' => 'Syarat dan ketentuan GudangPC.',
        ]);
    }

    /**
     * Show the Affiliate Disclosure page.
     */
    public function disclosure(): void
    {
        $this->view('pages/disclosure', [
            'pageTitle' => 'Pengungkapan Afiliasi',
            'metaDescription' => 'Pengungkapan afiliasi GudangPC.',
        ]);
    }
}