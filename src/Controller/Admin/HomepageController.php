<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomepageController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('WOPI App')
            ->renderContentMaximized()
            ->renderSidebarMinimized(true);
    }

    /**
     * @Route("/admin/wopi/index", name="homepage_admin_wopi")
     */
    public function index(): Response
    {
        return $this
            ->render(
                '@WopiTest/homepage.html.twig',
                [
                    'user' => uniqid(),
                ]
            );
    }
}
