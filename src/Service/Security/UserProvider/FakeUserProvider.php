<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Security\UserProvider;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class FakeUserProvider implements UserProviderInterface
{
    private UserPasswordHasherInterface $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function loadUserByIdentifier($identifier)
    {
        if (stripos($identifier, 'invalid') !== false) {
            throw new UserNotFoundException('User %s not found.');
        }

        return new InMemoryUser(
            $identifier,
            $this
                ->userPasswordHasher
                ->hashPassword(
                    new InMemoryUser(
                        $identifier,
                        null,
                        ['ROLE_USER']
                    ),
                    $identifier
                ),
            ['ROLE_USER']
        );
    }

    public function loadUserByUsername(string $username)
    {
        return $this->loadUserByIdentifier($username);
    }

    public function refreshUser(UserInterface $user)
    {
        return $user;
    }

    public function supportsClass(string $class)
    {
        return is_subclass_of($class, UserInterface::class);
    }
}
