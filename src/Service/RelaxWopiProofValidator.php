<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service;

use ChampsLibres\WopiLib\Service\Contract\WopiProofValidatorInterface;
use Psr\Http\Message\RequestInterface;

final class RelaxWopiProofValidator implements WopiProofValidatorInterface
{
    private WopiProofValidatorInterface $wopiProofValidator;

    public function __construct(WopiProofValidatorInterface $wopiProofValidator)
    {
        $this->wopiProofValidator = $wopiProofValidator;
    }

    public function isValid(RequestInterface $request): bool {
        if (true === $request->hasHeader('X-WOPI-Proof') && true === $request->hasHeader('X-WOPI-ProofOld')) {
            return $this->wopiProofValidator->isValid(($request));
        }

        return true;
    }
}
