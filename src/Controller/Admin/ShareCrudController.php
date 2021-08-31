<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\Share;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ShareCrudController extends AbstractCrudController
{
    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        yield AssociationField::new('document')
            ->hideWhenUpdating();

        yield TextField::new('uuid')
            ->onlyOnIndex();

        yield TextField::new('uuid')
            ->setLabel('Download link')
            ->setTemplatePath('@WopiTest/fields/sharedlink.html.twig')
            ->onlyOnIndex();
    }

    public static function getEntityFqcn(): string
    {
        return Share::class;
    }
}
