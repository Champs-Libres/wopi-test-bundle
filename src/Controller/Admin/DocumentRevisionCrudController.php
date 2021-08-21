<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Controller\Admin;

use ChampsLibres\WopiTestBundle\Entity\DocumentRevision;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class DocumentRevisionCrudController extends AbstractCrudController
{
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('rev')->hideWhenCreating()->hideWhenUpdating();

        yield TextField::new('name');

        yield TextField::new('extension');

        yield IntegerField::new('size')->hideWhenCreating()->hideWhenUpdating();

        yield TextField::new('lock')
            ->hideWhenCreating()
            ->hideWhenUpdating()
            ->setTemplatePath('@WopiTest/fields/lock.html.twig');

        yield TextField::new('revType');

        yield AssociationField::new('revision');

        yield AssociationField::new('document');
    }

    public static function getEntityFqcn(): string
    {
        return DocumentRevision::class;
    }
}
