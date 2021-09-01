<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Configuration\WopiConfigurationInterface;
use ChampsLibres\WopiLib\Discovery\WopiDiscoveryInterface;
use ChampsLibres\WopiLib\Service\Contract\DocumentLockManagerInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentRevisionField;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentRevisionTimestampField;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\PaginatorFactory;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Exception;
use loophp\psr17\Psr17Interface;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

final class DocumentCrudController extends AbstractCrudController
{
    private AdminUrlGenerator $adminUrlGenerator;

    private AuditReader $auditReader;

    private DocumentLockManagerInterface $documentLockManager;

    private HttpMessageFactoryInterface $httpMessageFactory;

    private Psr17Interface $psr17;

    private RequestStack $requestStack;

    private RouterInterface $router;

    private Security $security;

    private WopiConfigurationInterface $wopiConfiguration;

    private WopiDiscoveryInterface $wopiDiscovery;

    public function __construct(
        WopiConfigurationInterface $wopiConfiguration,
        WopiDiscoveryInterface $wopiDiscovery,
        DocumentLockManagerInterface $documentLockManager,
        RouterInterface $router,
        Psr17Interface $psr17,
        AuditReader $auditReader,
        Security $security,
        AdminUrlGenerator $adminUrlGenerator,
        HttpMessageFactoryInterface $httpMessageFactory,
        RequestStack $requestStack
    ) {
        $this->wopiConfiguration = $wopiConfiguration;
        $this->wopiDiscovery = $wopiDiscovery;
        $this->router = $router;
        $this->psr17 = $psr17;
        $this->auditReader = $auditReader;
        $this->security = $security;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->documentLockManager = $documentLockManager;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->requestStack = $requestStack;
    }

    public function configureActions(Actions $actions): Actions
    {
        $request = $this->httpMessageFactory->createRequest($this->requestStack->getCurrentRequest());

        $unlockDocument = Action::new('unlock', 'Unlock')
            ->linkToCrudAction('unlockDocument')
            ->displayIf(fn (Document $document): bool => $this->documentLockManager->hasLock((string) $document->getId(), $request));

        $showHistory = Action::new('history', 'History')
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
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => $action->displayIf(fn (Document $document): bool => !$this->documentLockManager->hasLock((string) $document->getId(), $request)))
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        yield TextField::new('filename')
            ->onlyOnIndex();

        yield TextField::new('name')
            ->setLabel('Filename');

        yield TextField::new('extension');

        yield IntegerField::new('size')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        yield WopiDocumentRevisionField::new('id');

        yield WopiDocumentRevisionTimestampField::new('id');
    }

    public function edit(AdminContext $context)
    {
        $documentId = $context->getEntity()->getInstance()->getId();
        $documentRevision = $context->getRequest()->query->get('revision');

        if (null === $documentRevision) {
            $documentRevision = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        /** @var Document $document */
        $document = $this->auditReader->find(Document::class, $documentId, $documentRevision);

        $extension = $document->getExtension();
        $configuration = $this->wopiConfiguration->jsonSerialize();

        if ([] === $discoverExtension = $this->wopiDiscovery->discoverExtension($extension, 'edit')) {
            throw new Exception('Unsupported extension.');
        }

        $configuration['access_token'] = $this->security->getUser()->getUserIdentifier();
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
                                    'fileId' => sprintf('%s-%s', $document->getId(), $documentRevision),
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),                    ]
                )
            );

        $this->get(EntityFactory::class)->processActions($context->getEntity(), $context->getCrud()->getActionsConfig());

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new(array_merge(
            $configuration,
            [
                'pageName' => Crud::PAGE_EDIT,
                'templatePath' => '@WopiTest/editor.html.twig',
                'entity' => $context->getEntity(),
            ]
        )));

        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->get('event_dispatcher')->dispatch($event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
    }

    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function showHistory(AdminContext $context)
    {
        $event = new BeforeCrudActionEvent($context);
        $this->get('event_dispatcher')->dispatch($event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        if (!$this->isGranted(Permission::EA_EXECUTE_ACTION, ['action' => Action::INDEX, 'entity' => null])) {
            throw new ForbiddenActionException($context);
        }

        $fields = FieldCollection::new($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->get(FilterFactory::class)->create($context->getCrud()->getFiltersConfig(), $fields, $context->getEntity());
        $queryBuilder = $this->createIndexQueryBuilder($context->getSearch(), $context->getEntity(), $fields, $filters);
        $paginator = $this->get(PaginatorFactory::class)->create($queryBuilder);

        // this can happen after deleting some items and trying to return
        // to a 'index' page that no longer exists. Redirect to the last page instead
        if ($paginator->isOutOfRange()) {
            return $this->redirect($this->get(AdminUrlGenerator::class)
                ->set(EA::PAGE, $paginator->getLastPage())
                ->generateUrl());
        }

        $entity = $context->getEntity();
        $entities = $this->auditReader->findRevisions($context->getEntity()->getFqcn(), $entity->getInstance()->getId());

        foreach ($entities as $key => $revision) {
            $entities[$key]->edit = $this
                ->adminUrlGenerator
                ->setController(DocumentCrudController::class)
                ->setAction(Crud::PAGE_EDIT)
                ->set('fileId', sprintf('%s-%s', $entity->getInstance()->getId(), $revision->getRev()));
        }

        $responseParameters = $this->configureResponseParameters(KeyValueStore::new([
            'revisions' => $entities,
            'entity' => $entity,
            'batch_actions' => [],
            'filters' => [],
            'global_actions' => [],
            'paginator' => $paginator,
            'pageName' => Crud::PAGE_DETAIL,
            'templatePath' => '@WopiTest/history.html.twig',
        ]));

        $event = new AfterCrudActionEvent($context, $responseParameters);
        $this->get('event_dispatcher')->dispatch($event);

        if ($event->isPropagationStopped()) {
            return $event->getResponse();
        }

        return $responseParameters;
    }

    public function unlockDocument(AdminContext $context)
    {
        $document = $context->getEntity()->getInstance();

        $this->documentLockManager->deleteLock((string) $document->getId(), $this->httpMessageFactory->createRequest($context->getRequest()));

        return $this->redirect($context->getReferrer());
    }

    public function unlockDocuments(BatchActionDto $batchActionDto)
    {
        $entityManager = $this->getDoctrine()->getManagerForClass($batchActionDto->getEntityFqcn());

        foreach ($batchActionDto->getEntityIds() as $id) {
            $document = $entityManager->find(Document::class, $id);
            $this->documentLockManager->deleteLock((string) $document->getId(), $this->httpMessageFactory->createRequest($context->getRequest()));
        }

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}
