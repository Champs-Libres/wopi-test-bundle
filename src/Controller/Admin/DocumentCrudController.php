<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiLib\Contract\Service\Configuration\ConfigurationInterface;
use ChampsLibres\WopiLib\Contract\Service\Discovery\DiscoveryInterface;
use ChampsLibres\WopiLib\Contract\Service\DocumentManagerInterface;
use ChampsLibres\WopiTestBundle\Entity\Document;
use ChampsLibres\WopiTestBundle\Service\Admin\Field\WopiDocumentLockField;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Security\Permission;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use loophp\psr17\Psr17Interface;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

final class DocumentCrudController extends AbstractCrudController
{
    private AuditReader $auditReader;

    private ConfigurationInterface $configuration;

    private DiscoveryInterface $discovery;

    private DocumentManagerInterface $documentManager;

    private JWTManager $jwtManager;

    private Psr17Interface $psr17;

    private RouterInterface $router;

    private Security $security;

    public function __construct(
        ConfigurationInterface $configuration,
        DiscoveryInterface $discovery,
        RouterInterface $router,
        Psr17Interface $psr17,
        AuditReader $auditReader,
        Security $security,
        JWTTokenManagerInterface $jwtManager,
        DocumentManagerInterface $documentManager
    ) {
        $this->configuration = $configuration;
        $this->discovery = $discovery;
        $this->router = $router;
        $this->psr17 = $psr17;
        $this->auditReader = $auditReader;
        $this->security = $security;
        $this->jwtManager = $jwtManager;
        $this->documentManager = $documentManager;
    }

    public function configureActions(Actions $actions): Actions
    {
        $unlockDocument = Action::new('unlock', 'Unlock')
            ->linkToCrudAction('unlockDocument')
            ->displayIf(fn (Document $document): bool => $this->documentManager->hasLock($document));

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
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('filename')
            ->onlyOnIndex();

        yield TextField::new('name')
            ->setLabel('Name');

        yield TextField::new('extension');

        yield IntegerField::new('size')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        yield WopiDocumentRevisionTimestampField::new('id');

        yield WopiDocumentLockField::new('id')
            ->setTemplatePath('@WopiTest/fields/lock.html.twig')
            ->setSortable(false);
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $documentId = $context->getEntity()->getInstance()->getId();
        $documentRevision = $context->getRequest()->query->get('revision');

        if (null === $documentRevision) {
            $documentRevision = $this->auditReader->getCurrentRevision(Document::class, $documentId);
        }

        /** @var Document $document */
        $document = $this->auditReader->find(Document::class, $documentId, $documentRevision);

        $extension = $document->getExtension();
        $configuration = $this->configuration->jsonSerialize();

        if (null === $action = $this->discovery->discoverAction($extension, 'edit')) {
            throw new Exception('Unsupported action for this extension.');
        }

        $configuration['access_token'] = $this
            ->jwtManager
            ->createFromPayload(
                $this->security->getUser(),
                ['fileUuid' => $document->getUuid()]
            );

        $configuration['server'] = $this
            ->psr17
            ->createUri($action['urlsrc'])
            ->withQuery(
                http_build_query(
                    [
                        'WOPISrc' => $this
                            ->router
                            ->generate(
                                'checkFileInfo',
                                [
                                    'fileId' => $document->getUuid(),
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),                    ]
                )
            );

        $this
            ->get(EntityFactory::class)
            ->processActions(
                $context->getEntity(),
                $context->getCrud()->getActionsConfig()
            );

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
        $filters = $this
            ->get(FilterFactory::class)
            ->create(
                $context->getCrud()->getFiltersConfig(),
                $fields,
                $context->getEntity()
            );
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
        $entities = $this
            ->auditReader
            ->findRevisions(
                $context->getEntity()->getFqcn(),
                $entity->getInstance()->getId()
            );

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

        $this->documentManager->deleteLock($document);

        return $this->redirect($context->getReferrer());
    }

    public function unlockDocuments(BatchActionDto $batchActionDto)
    {
        $entityManager = $this->getDoctrine()->getManagerForClass($batchActionDto->getEntityFqcn());

        foreach ($batchActionDto->getEntityIds() as $id) {
            $document = $entityManager->find(Document::class, $id);
            $this->documentManager->deleteLock($document);
        }

        return $this->redirect($batchActionDto->getReferrerUrl());
    }
}
