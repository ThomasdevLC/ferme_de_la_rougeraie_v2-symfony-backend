<?php

namespace App\Controller\Admin;
use App\Service\Admin\ProductClientTabService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Displays the product-client quantities table for a specific pickup day (Tuesday or FRIDAY).
 *
 * @Route("/admin/product-client-tab", name="admin_product_client_tab")
 *
 * @param Request $request
 *     The current HTTP request, containing query parameters.
 * @param ProductClientTabService $service
 *     The service fetching products, users, and quantities.
 *
 * @return Response
 *     The rendered HTML page with the product-client quantities table.
 */

class ProductClientTabController extends AbstractController
{
// src/Controller/Admin/ProductClientTabController.php

    #[Route('/admin/product-client-tab', name: 'admin_product_client_tab')]
    public function index(Request $request, ProductClientTabService $service): Response
    {
        // Récupère ?pickup=2 ou 5, défaut à 2 (Mardi)
        $weekday = (int) $request->query->get('pickup', 2);

        // On s’assure d’être sur 2 ou 5
        if (!in_array($weekday, [2,5], true)) {
            $weekday = 2;
        }

        [$products, $users, $quantitiesTab] = $service
            ->getProductClientQuantitiesByWeekday($weekday);

        return $this->render('admin/product_client_tab.html.twig', [
            'products'          => $products,
            'users'             => $users,
            'quantitiesTab'     => $quantitiesTab,
            'selectedPickupDay' => $weekday,
        ]);
    }

}