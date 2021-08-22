<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\Lock;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class LockCrudController extends AbstractCrudController
{
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideWhenCreating()->hideWhenUpdating();

        yield TextField::new('lock');

        yield DateTimeField::new('timestamp');

        yield AssociationField::new('document');
    }

    public static function getEntityFqcn(): string
    {
        return Lock::class;
    }
}
