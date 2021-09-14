<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Service\ProofValidatorInterface;
use Psr\Http\Message\RequestInterface;

final class NullProofValidator implements ProofValidatorInterface
{
    private ProofValidatorInterface $proofValidator;

    public function __construct(ProofValidatorInterface $proofValidator)
    {
        $this->wopiProofValidator = $proofValidator;
    }

    public function isValid(RequestInterface $request): bool
    {
        return true;
    }
}
