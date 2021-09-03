<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 * @coversNothing
 */
final class Test implements DashboardControllerInterface
{
    private DashboardController $dashboardController;

    private DocumentRepository $documentRepository;

    private EntityManagerInterface $entityManager;

    private JWTTokenManagerInterface $JWTTokenManager;

    private ResponderInterface $responder;

    private RouterInterface $router;

    private Security $security;

    public function __construct(
        DashboardController $dashboardController,
        DocumentRepository $documentRepository,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTTokenManager,
        ResponderInterface $responder,
        RouterInterface $router,
        Security $security
    ) {
        $this->dashboardController = $dashboardController;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
        $this->JWTTokenManager = $JWTTokenManager;
        $this->responder = $responder;
        $this->router = $router;
        $this->security = $security;
    }

    public function configureActions(): Actions
    {
        return $this->dashboardController->configureActions();
    }

    public function configureAssets(): Assets
    {
        return $this->dashboardController->configureAssets();
    }

    public function configureCrud(): Crud
    {
        return $this->dashboardController->configureCrud();
    }

    public function configureDashboard(): Dashboard
    {
        return $this->dashboardController->configureDashboard();
    }

    public function configureFilters(): Filters
    {
        return $this->dashboardController->configureFilters();
    }

    public function configureMenuItems(): iterable
    {
        return $this->dashboardController->configureMenuItems();
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return $this->dashboardController->configureUserMenu($user);
    }

    public function index(): Response
    {
        return $this->dashboardController->index();
    }

    /**
     * @Route(path="wopi_test", name="wopi_test")
     */
    public function test(): Response
    {
        $document = $this->documentRepository->findOneBy([
            'extension' => 'wopitest',
            'size' => '0',
        ]);

        if (null === $document) {
            $document = new Document();
            $document->setName(uniqid('document_'));
            $document->setExtension('wopitest');
            $this->entityManager->persist($document);
            $this->entityManager->flush();
        }

        $url = $this->router->generate('checkFileInfo', ['fileId' => sprintf('%s-%s', $document->getId(), 1)], RouterInterface::ABSOLUTE_URL);

        $command = sprintf(
            'docker-compose run wopivalidator -- -w %s -l 0 -t %s',
            $url,
            $this->JWTTokenManager->create($this->security->getUser())
        );

        return $this
            ->responder
            ->render(
                '@WopiTest/test.html.twig',
                [
                    'command' => $command,
                ]
            );
    }
}
