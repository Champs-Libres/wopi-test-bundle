<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="share")
 */
class Share
{
    /**
     * @ORM\ManyToOne(targetEntity=Document::class, inversedBy="share")
     * @ORM\JoinColumn(nullable=false)
     */
    private $document;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="uuid", unique=true)
     */
    private Uuid $uuid;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }
}
