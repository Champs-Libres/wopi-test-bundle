<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Service\ProofValidatorInterface;
use Psr\Http\Message\RequestInterface;

final class RelaxProofValidator implements ProofValidatorInterface
{
    private ProofValidatorInterface $proofValidator;

    public function __construct(ProofValidatorInterface $proofValidator)
    {
        $this->proofValidator = $proofValidator;
    }

    public function isValid(RequestInterface $request): bool
    {
        if (true === $request->hasHeader('X-WOPI-Proof') && true === $request->hasHeader('X-WOPI-ProofOld')) {
            return $this->proofValidator->isValid(($request));
        }

        return true;
    }
}
