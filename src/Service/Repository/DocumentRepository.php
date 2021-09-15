<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Repository;

use ChampsLibres\WopiTestBundle\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

final class DocumentRepository implements ObjectRepository
{
    private EntityManagerInterface $entityManager;

    private ObjectRepository $repository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(Document::class);
    }

    public function find($id): ?Document
    {
        return $this->repository->find($id);
    }

    /**
     * @return array<int, Document>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    /**
     * @param mixed|null $limit
     * @param mixed|null $offset
     *
     * @return array<int, Document>
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria): ?Document
    {
        return $this->repository->findOneBy($criteria);
    }

    public function getClassName(): string
    {
        return Document::class;
    }
}
