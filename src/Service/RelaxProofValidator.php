<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Contract\Service\ProofValidatorInterface;
use ChampsLibres\WopiLib\Contract\Service\WopiInterface;
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
        $hasHeaderProof = $request->hasHeader(WopiInterface::HEADER_PROOF);
        $hasHeaderProofOld = $request->hasHeader(WopiInterface::HEADER_PROOF_OLD);

        if (true === $hasHeaderProof && true === $hasHeaderProofOld) {
            return $this->proofValidator->isValid(($request));
        }

        return true;
    }
}
