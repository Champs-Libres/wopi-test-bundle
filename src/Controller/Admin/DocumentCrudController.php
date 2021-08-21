<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Configuration\WopiConfigurationInterface;
use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentRevisionField;
use ChampsLibres\WopiTestBundle\Service\Controller\ResponderInterface;
use ChampsLibres\WopiTestBundle\Service\Repository\DocumentRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Exception;
use loophp\psr17\Psr17Interface;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class DocumentCrudController extends AbstractCrudController
{
    private DocumentRepository $documentRepository;

    private Psr17Interface $psr17;

    private ResponderInterface $responder;

    private RouterInterface $router;

    private WopiConfigurationInterface $wopiConfiguration;

    private WopiDiscoveryInterface $wopiDiscovery;

    private AuditReader $auditReader;

    public function __construct(
        DocumentRepository $documentRepository,
        WopiConfigurationInterface $wopiConfiguration,
        WopiDiscoveryInterface $wopiDiscovery,
        ResponderInterface $responder,
        RouterInterface $router,
        Psr17Interface $psr17,
        AuditReader $auditReader
    ) {
        $this->documentRepository = $documentRepository;
        $this->wopiConfiguration = $wopiConfiguration;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->responder = $responder;
        $this->router = $router;
        $this->psr17 = $psr17;
        $this->auditReader = $auditReader;
    }

    public function configureActions(Actions $actions): Actions
    {
        $unlockDocument = Action::new('unlock', 'Unlock', 'fa fa-unlock')
            ->linkToCrudAction('unlockDocument')
            ->displayIf(static fn (Document $document): bool => null !== $document->getLock());

        $showHistory = Action::new('history', 'History', 'fa fa-clock')
            ->linkToCrudAction('showHistory');

        return $actions
            ->addBatchAction(
                Action::new('unlockDocuments', 'Unlock')
                    ->linkToCrudAction('unlockDocuments')
                    ->addCssClass('btn btn-primary')
                    ->setIcon('fa fa-unlock')
            )
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(
                Crud::PAGE_INDEX,
                $unlockDocument
            )
            ->add(
                Crud::PAGE_INDEX,
                $showHistory
            )
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->displayIf(static fn (Document $document): bool => null === $document->getLock()))
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->add(Crud::PAGE_EDIT, Action::INDEX);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideWhenCreating()->hideWhenUpdating();

        yield TextField::new('name');

        yield TextField::new('extension');

        yield IntegerField::new('size')->hideWhenCreating()->hideWhenUpdating();

        yield DateTimeField::new('lastModified', 'Last modified')->hideWhenCreating()->hideWhenUpdating();

        yield TextField::new('lock')
            ->hideWhenCreating()
            ->hideWhenUpdating()
            ->setTemplatePath('@WopiTest/fields/lock.html.twig');

        yield WopiDocumentRevisionField::new('id');
    }

    public function edit(AdminContext $context)
    {
        $entity = $context->getEntity()->getInstance();
        $fileId = $entity->getId();

        $document = $this->documentRepository->findOneBy(['id' => $fileId]);

        $extension = $document->getExtension();
        $configuration = $this->wopiConfiguration->jsonSerialize();

        if ([] === $discoverExtension = $this->wopiDiscovery->discoverExtension($extension)) {
            throw new Exception('Unsupported extension.');
        }

        $configuration['server'] = $this
            ->psr17
            ->createUri($discoverExtension[0]['urlsrc'])
            ->withQuery(
                http_build_query(
                    [
                        'WOPISrc' => $this
                            ->router
                            ->generate(
                                'checkFileInfo',
                                [
                                    'fileId' => $document->getId(),
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            )                    ]
                )
            );

        $this->get(EntityFactory::class)->processActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $variables = array_merge(
            $configuration,
            [
                'pageName' => Crud::PAGE_EDIT,
                'templateName' => 'crud/edit',
                'entity' => $context->getEntity(),
            ]
        );

        return $this
            ->responder
            ->render(
                '@WopiTest/Editor.html.twig',
                $variables
            );
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function unlockDocument(AdminContext $context)
    {
        $document = $context->getEntity()->getInstance();

        $document->setLock(null);
        $this->documentRepository->add($document);

        return $this->redirect($context->getReferrer());
    }

    public function unlockDocuments(BatchActionDto $batchActionDto)
    {
        $entityManager = $this->getDoctrine()->getManagerForClass($batchActionDto->getEntityFqcn());

        foreach ($batchActionDto->getEntityIds() as $id) {
            $entityManager->find(Document::class, $id)->setLock(null);
        }

        $entityManager->flush();

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function showHistory(AdminContext $context): Response
    {

    }
}
