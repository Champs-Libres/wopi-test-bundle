<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="revisions")
 * @ORM\Entity
 */
class Revision
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\Column(name="timestamp", type="datetime", nullable=false)
     */
    private DateTimeInterface $timestamp;

    /**
     * @ORM\Column(name="username", type="string", length=255, nullable=true)
     */
    private string $username;

    public function getId(): int
    {
        return $this->id;
    }

    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getName(): string
    {
        return sprintf('Revision');
    }
}
