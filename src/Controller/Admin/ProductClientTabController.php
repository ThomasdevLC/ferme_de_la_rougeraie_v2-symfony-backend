<?php

namespace App\Controller\Admin;
use App\Enum\PickupDay;
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
    #[Route('/admin/product-client-tab', name: 'admin_product_client_tab')]
    public function index(Request $request, ProductClientTabService $service): Response
    {
        $pickupValue = $request->query->get('pickup', PickupDay::TUESDAY->value);
        $pickupDay   = PickupDay::from($pickupValue);

        [$products, $users, $quantitiesTab] = $service
            ->getProductClientQuantities($pickupDay);

        return $this->render('admin/product_client_tab.html.twig', [
            'products'          => $products,
            'users'             => $users,
            'quantitiesTab'     => $quantitiesTab,
            'selectedPickupDay' => $pickupDay,
        ]);
    }
}