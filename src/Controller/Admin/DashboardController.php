<?php

namespace App\Controller\Admin;

use App\Entity\Message;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }
    public function index( ): Response
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(\EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator::class);

        $url = $adminUrlGenerator
            ->setController(\App\Controller\Admin\ProductCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/images/logo.png" alt="Ferme De La Rougeraie" height="100">');
    }



    public function configureMenuItems(): iterable
    {
        $soldOutCount = \count($this->productRepository->findSoldOutProductIds());

        yield MenuItem::linkToCrud('Produits', 'fa-solid fa-carrot', Product::class)
            ->setController(\App\Controller\Admin\ProductCrudController::class);

        yield MenuItem::linkToCrud('Commandes', 'fas fa-box', Order::class);
        yield MenuItem::linkToRoute('Tableau commandes', 'fa-solid fa-table-list', 'admin_product_client_tab');

        if ($soldOutCount > 0) {
            yield MenuItem::linkToCrud('Produits épuisés', 'fa fa-xmark', Product::class)
                ->setController(\App\Controller\Admin\SoldOutProductCrudController::class)
                ->setBadge($soldOutCount, 'danger');
        }
        yield MenuItem::linkToCrud('Messages', 'fas fa-comment', Message::class);
        yield MenuItem::linkToRoute('Utilisateurs', 'fa-solid fa-users', 'admin_user_list_tab');
    }
}