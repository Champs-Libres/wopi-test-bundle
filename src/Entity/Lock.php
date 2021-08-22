<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="lock")
 * @ORM\Entity
 */
class Lock
{
    /**
     * @ORM\OneToOne(targetEntity="Document", mappedBy="lock")
     */
    private Document $document;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="lock", type="string", length=255, nullable=true)
     */
    private ?string $lock;

    /**
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private DateTimeInterface $timestamp;

    public function __construct()
    {
        $this->setTimestamp(new DateTimeImmutable());
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLock(): ?string
    {
        return $this->lock;
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    public function isValid(): bool
    {
        return 100 >= ((new DateTimeImmutable())->getTimestamp() - $this->getTimestamp()->getTimestamp());
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function setLock(?string $lock): self
    {
        $this->lock = $lock;

        return $this;
    }

    public function setTimestamp(DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }
}
