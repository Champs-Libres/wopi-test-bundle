<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Entity\DocumentRevision;
use ChampsLibres\WopiTestBundle\Entity\Revision;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('WOPI Bundle admin')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('WOPI Host');

        yield MenuItem::linkToCrud('Documents', 'fas fa-list', Document::class);
//        yield MenuItem::linkToCrud('Documents revisions', 'fas fa-list', DocumentRevision::class);
//        yield MenuItem::linkToCrud('Revisions', 'fas fa-list', Revision::class);

        yield MenuItem::section('WOPI Client');

        yield MenuItem::linkToRoute('Configuration', 'fas fa-cogs', 'configuration');

        yield MenuItem::linkToRoute('Capabilities', 'fas fa-info', 'hosting_capabilities');
    }

    /**
     * @Route("/admin", name="admin")
     */
    public function index(): Response
    {
        return $this->render('@WopiTest/welcome.html.twig');
    }
}
