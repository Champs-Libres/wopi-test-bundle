<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

final class ProofKey implements DashboardControllerInterface
{
    private DashboardController $dashboardController;

    private ResponderInterface $responder;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        WopiDiscoveryInterface $wopiDiscovery,
        ResponderInterface $responder,
        DashboardController $dashboardController
    ) {
        $this->wopiDiscovery = $wopiDiscovery;
        $this->responder = $responder;
        $this->dashboardController = $dashboardController;
    }

    /**
     * @Route(path="proof_key", name="proof_key")
     */
    public function proofKey(): Response
    {
        try {
            $proofKey = $this->wopiDiscovery->getPublicKey();
        } catch (Throwable $e) {
            $proofKey = [
                'error' => $e->getMessage(),
            ];
        }

        return $this
            ->responder
            ->render(
                '@WopiTest/proof-key.html.twig',
                [
                    'proofKey' => $proofKey,
                ]
            );
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
}
