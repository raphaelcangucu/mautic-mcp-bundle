<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Application\Form;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\FormBundle\Model\FormModel;
use MauticPlugin\MauticMcpBundle\Application\Form\FormNormalizer;
use MauticPlugin\MauticMcpBundle\Application\Form\FormWriteService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FormWriteServiceTest extends TestCase
{
    public function testCreateDefaultsToUnpublished(): void
    {
        $form = new Form();
        $model = $this->createMock(FormModel::class);
        $model->expects(self::once())->method('getEntity')->willReturn($form);
        $model->method('getCustomComponents')->willReturn(['fields' => [], 'actions' => []]);
        $model->expects(self::once())->method('saveEntity')->with(self::callback(
            static fn (Form $saved): bool => false === $saved->getIsPublished()
                && true === $saved->getRenderStyle()
                && 'return' === $saved->getPostAction(),
        ));

        $permissions = $this->createMock(CorePermissions::class);
        $permissions->expects(self::once())->method('isGranted')->with('form:forms:create')->willReturn(true);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $fieldHelper = $this->createStub(FormFieldHelper::class);
        $fieldHelper->method('getTypes')->willReturn([]);

        $service = new FormWriteService(
            $model,
            $this->createStub(FieldModel::class),
            $fieldHelper,
            $permissions,
            $this->createStub(EntityManagerInterface::class),
            $validator,
            new FormNormalizer(),
        );

        $result = $service->write('create', null, ['name' => 'MCP form'], false, null);

        self::assertSame('created', $result['status']);
        self::assertFalse($result['form']['isPublished']);
    }

    public function testDeleteRequiresExplicitConfirmation(): void
    {
        $form = $this->createStub(Form::class);
        $form->method('getId')->willReturn(42);
        $model = $this->createMock(FormModel::class);
        $model->method('getEntity')->with(42)->willReturn($form);
        $model->expects(self::never())->method('deleteEntities');

        $service = $this->serviceWithWritableForm($model);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('confirm=true');
        $service->write('delete', 42, [], false, null);
    }

    public function testUpdateRejectsStaleExpectedDateModified(): void
    {
        $form = $this->createStub(Form::class);
        $form->method('getId')->willReturn(42);
        $form->method('getDateModified')->willReturn(new \DateTimeImmutable('2026-09-09T10:00:00+00:00'));
        $model = $this->createMock(FormModel::class);
        $model->method('getEntity')->with(42)->willReturn($form);
        $model->expects(self::never())->method('saveEntity');

        $service = $this->serviceWithWritableForm($model);

        $this->expectException(ConflictHttpException::class);
        $service->write('update', 42, ['name' => 'Changed'], false, '2026-09-09T09:59:59+00:00');
    }

    public function testNestedDeletionRequiresExplicitConfirmation(): void
    {
        $form = $this->createStub(Form::class);
        $form->method('getId')->willReturn(42);
        $model = $this->createMock(FormModel::class);
        $model->method('getEntity')->with(42)->willReturn($form);
        $model->expects(self::never())->method('deleteFields');

        $service = $this->serviceWithWritableForm($model);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('confirm=true');
        $service->write('update', 42, ['deleteFieldIds' => [9]], false, null);
    }

    private function serviceWithWritableForm(FormModel $model): FormWriteService
    {
        $permissions = $this->createStub(CorePermissions::class);
        $permissions->method('hasEntityAccess')->willReturn(true);

        return new FormWriteService(
            $model,
            $this->createStub(FieldModel::class),
            $this->createStub(FormFieldHelper::class),
            $permissions,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ValidatorInterface::class),
            new FormNormalizer(),
        );
    }
}
