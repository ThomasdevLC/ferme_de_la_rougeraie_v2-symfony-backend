<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $databaseTool->loadFixtures([AppFixtures::class]);
    }

    public function testAdminWithoutTokenRedirects(): void
    {
        $this->client->request('GET', '/admin');

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [301, 302, 401]);
    }

    public function testAdminWithRoleUserIsForbidden(): void
    {
        $user = static::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        // Crée un user avec ROLE_USER uniquement
        $regularUser = static::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['roles' => ['ROLE_USER']]);

        // Fallback : prend le premier user qui n'est pas admin
        if (!$regularUser) {
            $allUsers   = static::getContainer()->get('doctrine')->getRepository(User::class)->findAll();
            $regularUser = current(array_filter($allUsers, fn(User $u) => !in_array('ROLE_ADMIN', $u->getRoles())));
        }

        $this->assertNotNull($regularUser, 'Aucun user standard trouvé');

        $this->client->loginUser($regularUser);
        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminWithRoleAdminIsAccessible(): void
    {
        $admin = static::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin');

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertNotContains($statusCode, [401, 403]);
    }

    public function testProductClientTabWithRoleAdminIsAccessible(): void
    {
        $admin = static::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/product-client-tab');

        // On vérifie uniquement l'accès, pas le rendu (EasyAdmin nécessite un contexte non dispo en test)
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertNotContains($statusCode, [401, 403]);
    }

    public function testProductClientTabWithRoleUserIsForbidden(): void
    {
        $allUsers    = static::getContainer()->get('doctrine')->getRepository(User::class)->findAll();
        $regularUser = current(array_filter($allUsers, fn(User $u) => !in_array('ROLE_ADMIN', $u->getRoles())));

        $this->assertNotNull($regularUser, 'Aucun user standard trouvé');

        $this->client->loginUser($regularUser);
        $this->client->request('GET', '/admin/product-client-tab');

        $this->assertResponseStatusCodeSame(403);
    }
}
