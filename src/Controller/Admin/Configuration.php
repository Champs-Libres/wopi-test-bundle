<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Configuration\WopiConfigurationInterface;
use ChampsLibres\WopiTestBundle\Form\Type\Configuration as FormTypeConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class Configuration extends AbstractController
{
    private WopiConfigurationInterface $wopiConfiguration;

    public function __construct(WopiConfigurationInterface $wopiConfiguration)
    {
        $this->wopiConfiguration = $wopiConfiguration;
    }

    /**
     * @Route(path="configuration", name="configuration")
     */
    public function __invoke(Request $request)
    {
        $form = $this->createForm(
            FormTypeConfiguration::class,
            $this->wopiConfiguration->jsonSerialize()
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('reset')->isClicked()) {
                $request->getSession()->remove('configuration');

                return new RedirectResponse('configuration');
            }

            $request->getSession()->set('configuration', $form->getData());
        }

        return $this
            ->render(
                '@WopiTest/form/configuration.html.twig',
                [
                    'form' => $form->createView(),
                ]
            );
    }
}
