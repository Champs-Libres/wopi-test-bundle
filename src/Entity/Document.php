<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="documents")
 * @ORM\Entity
 */
class Document
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
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private string $name;

    /**
     * @ORM\Column(name="size", type="bigint", options={"default": "0"}, nullable=true)
     */
    private int $size;

    /**
     * @ORM\OneToMany(targetEntity="DocumentRevision", mappedBy="document")
     */
    private Collection $documentRevisions;

    public function getLastModified(): DateTimeInterface
    {
        $revision = $this->documentRevisions->last();

        return $revision->getTimestamp();
    }

    public function __construct()
    {
        $this->documentRevisions = new ArrayCollection();
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

    public function setContent($content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function setLock(?string $lock): self
    {
        $this->lock = $lock;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }
}
