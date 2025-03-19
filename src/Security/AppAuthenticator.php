<?php
namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AppAuthenticator extends AbstractAuthenticator
{
    private RouterInterface $router;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(RouterInterface $router, JWTTokenManagerInterface $jwtManager)
    {
        $this->router = $router;
        $this->jwtManager = $jwtManager;
    }

    public function supports(Request $request): bool
    {
        // Vérifie si c'est un login API (JSON) ou un login formulaire
        return $request->isMethod('POST') && ($request->attributes->get('_route') === 'app_login' || $request->attributes->get('_route') === 'api_login');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new PasswordUpgradeBadge($password),
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token', ''))
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $user = $token->getUser();

        // Vérifie si c'est une requête API (JSON)
        if ($request->getContentTypeFormat() === 'json') {
            $jwt = $this->jwtManager->create($user);
            return new JsonResponse(['token' => $jwt]);
        }

        // Sinon, redirige vers la page d'accueil après login par formulaire
        return new RedirectResponse($this->router->generate('home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->getContentTypeFormat() === 'json') {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->router->generate('app_login'));
    }
}
