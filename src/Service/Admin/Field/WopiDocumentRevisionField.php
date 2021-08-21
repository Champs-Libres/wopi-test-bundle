<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Service\Admin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;

final class WopiDocumentRevisionField implements FieldInterface
{
    use FieldTrait;

    /**
     * @param false|string|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setProperty($propertyName)
            ->setLabel('Version')
            ->hideWhenCreating()
            ->hideWhenUpdating();

        // this template is used in 'index' and 'detail' pages
//            ->setTemplatePath('@WopiTest/Admin/Field/WopiEditField.html.twig')

//            ->addCssClass('field-wopi-edit-link');
    }
}
