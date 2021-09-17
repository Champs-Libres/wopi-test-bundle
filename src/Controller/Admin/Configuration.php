<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Contract\Service\Configuration\ConfigurationInterface;
use ChampsLibres\WopiTestBundle\Form\Type\Configuration as FormTypeConfiguration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class Configuration extends AbstractController
{
    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @Route(path="configuration", name="configuration")
     */
    public function __invoke(Request $request)
    {
        $form = $this->createForm(
            FormTypeConfiguration::class,
            $this->configuration->jsonSerialize()
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('reset')->isClicked()) {
                $request->getSession()->remove('configuration');

                return new RedirectResponse('configuration');
            }

            foreach ($form->getData() as $key => $value) {
                $this->configuration[$key] = $value;
            }
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
