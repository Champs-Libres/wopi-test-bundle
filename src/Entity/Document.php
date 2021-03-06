<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use ChampsLibres\WopiLib\Contract\Entity\Document as WopiDocument;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

use function strlen;

/**
 * @ORM\Table(name="documents")
 * @ORM\Entity
 */
class Document implements WopiDocument
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
     * @ORM\Column(name="name", type="string", length=255, nullable=false)
     */
    private string $name;

    /**
     * @ORM\OneToMany(targetEntity=Share::class, mappedBy="document", orphanRemoval=true, cascade={"persist"})
     */
    private Collection $share;

    /**
     * @ORM\Column(name="size", type="bigint", options={"default": "0"}, nullable=true)
     */
    private string $size = '0';

    /**
     * @ORM\Column(type="uuid", unique=true)
     */
    private Uuid $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
    }

    public function __toString()
    {
        return $this->getBasename();
    }

    public function addShare(Share $share): self
    {
        if (!$this->share->contains($share)) {
            $this->share[] = $share;
            $share->setDocument($this);
        }

        return $this;
    }

    public function getBasename(): string
    {
        return sprintf('%s.%s', $this->getName(), $this->getExtension());
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getFileId(): string
    {
        return (string) $this->getUuid();
    }

    public function getFilename(): string
    {
        return sprintf('%s.%s', $this->getName(), $this->getExtension());
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return Collection|Share[]
     */
    public function getShare(): Collection
    {
        return $this->share;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getWopiDocId(): string
    {
        return (string) $this->getUuid();
    }

    public function removeShare(Share $share): self
    {
        if ($this->share->removeElement($share)) {
            // set the owning side to null (unless already changed)
            if ($share->getDocument() === $this) {
                $share->setDocument(null);
            }
        }

        return $this;
    }

    public function setBasename(string $basename): void
    {
        $pathinfo = pathinfo($basename);

        $this->setName($pathinfo['basename']);
        $this->setExtension($pathinfo['extension']);
    }

    public function setContent($content): void
    {
        $this->content = $content;
        $this->size = (string) strlen($content);
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    public function setFilename(string $filename): void
    {
        $this->setName($filename);
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setSize(string $size): void
    {
        $this->size = $size;
    }

    public function setUuid(Uuid $uuid): void
    {
        $this->uuid = $uuid;
    }
}
