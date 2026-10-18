<?php

namespace App\Controller\Admin;

use App\Repository\Admin\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only table of every tracked stock (products and variants), lowest first.
 */
#[Route('/admin/stock-tab', name: 'admin_stock_tab')]
class StockTabController extends AbstractController
{
    public function __invoke(ProductRepository $productRepository): Response
    {
        return $this->render('admin/stock_tab.html.twig', [
            'rows' => $productRepository->findTrackedStocks(),
        ]);
    }
}
