<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use ChampsLibres\WopiTestBundle\Entity\Document;

/**
 * @ORM\Table(name="documents_audit")
 * @ORM\Entity
 */
class DocumentRevision
{
    /**
     * @ORM\Column(name="content", type="blob", nullable=true)
     */
    private $content;

    /**
     * @ORM\Column(name="extension", type="string", length=10, nullable=false)
     */
    private string $extension;

    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private int $id;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $rev;

    /**
     * @ORM\Column(name="lock", type="string", length=255, nullable=true)
     */
    private ?string $lock;

    /**
     * @ORM\Column(name="revType", type="string", length=255, nullable=true)
     */
    private string $revType;

    /**
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private string $name;

    /**
     * @ORM\Column(name="size", type="bigint", options={"default": "0"}, nullable=true)
     */
    private int $size;

    /**
     * @ORM\OneToOne(targetEntity="Revision")
     * @ORM\JoinColumn(name="rev", referencedColumnName="id", nullable=true)
     */
    private ?Revision $revision;

    /**
     * @ORM\ManyToOne(targetEntity="Document", inversedBy="documentRevisions")
     * @ORM\JoinColumn(name="id", referencedColumnName="id", nullable=true)
     */
    private ?Document $document;

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->revision->getTimestamp();
    }

    public function getUsername(): ?string
    {
        return $this->getRevision()->getUsername();
    }

    public function getRevision(): ?Revision
    {
        return $this->revision;
    }

    public function getRevType(): string
    {
        return $this->revType;
    }

    public function getRev(): int
    {
        return $this->rev;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function getFilename(): string
    {
        return sprintf('%s.%s', $this->getName(), $this->getExtension());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLock(): ?string
    {
        return $this->lock;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
