<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Entity\Share;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('WOPI Bundle admin')
            ->renderContentMaximized()
            ->renderSidebarMinimized(true);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('WOPI Host');

        yield MenuItem::linkToCrud('Documents', 'fas fa-list', Document::class);

        yield MenuItem::linkToCrud('Shares', 'fas fa-list', Share::class);

        yield MenuItem::section('WOPI Client');

        yield MenuItem::linkToRoute('Configuration', 'fas fa-cogs', 'configuration');

        yield MenuItem::linkToRoute('Capabilities', 'fas fa-info', 'hosting_capabilities');

        yield MenuItem::section('WOPI Validator');

        yield MenuItem::linkToRoute('Tests', 'fas fa-info', 'wopi_test');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->displayUserName(true)
            ->displayUserAvatar(true);
    }

    /**
     * @Route("/admin/wopi", name="admin_wopi")
     */
    public function index(): Response
    {
        return $this->render('@WopiTest/welcome.html.twig');
    }
}
