<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Form;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Helper\FormFieldHelper;
use Mautic\FormBundle\Model\FieldModel;
use Mautic\FormBundle\Model\FormModel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class FormWriteService
{
    private const MAX_FIELDS_PER_MUTATION = 200;
    private const MAX_ACTIONS_PER_MUTATION = 100;

    public function __construct(
        private FormModel $formModel,
        private FieldModel $fieldModel,
        private FormFieldHelper $fieldHelper,
        private CorePermissions $permissions,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private FormNormalizer $normalizer,
    ) {}

    public function write(string $action, ?int $id, array $data, bool $confirm, ?string $expectedDateModified): array
    {
        return match ($action) {
            'create'    => $this->save($this->newForm(), $data, true, $confirm),
            'update'    => $this->save($this->formForWrite($id, 'edit', $expectedDateModified), $data, false, $confirm),
            'delete'    => $this->delete($this->formForWrite($id, 'delete', $expectedDateModified), $confirm),
            'publish'   => $this->publish($this->formForWrite($id, 'publish', $expectedDateModified), true),
            'unpublish' => $this->publish($this->formForWrite($id, 'publish', $expectedDateModified), false),
            default     => throw new BadRequestHttpException('Action must be create, update, delete, publish, or unpublish.'),
        };
    }

    private function newForm(): Form
    {
        $this->assertGranted('form:forms:create');
        $form = $this->formModel->getEntity();
        if (!$form instanceof Form) {
            throw new \RuntimeException('Unable to initialize form.');
        }
        // Mautic's entity defaults to published; MCP creation is intentionally safe by default.
        $form->setIsPublished(false);
        $form->setRenderStyle(true);
        $form->setPostAction('return');

        return $form;
    }

    private function save(Form $form, array $data, bool $new, bool $confirm): array
    {
        if ([] === $data) {
            throw new BadRequestHttpException('Form data cannot be empty.');
        }

        $fieldChanges = $this->objectList($data['fields'] ?? [], 'data.fields', self::MAX_FIELDS_PER_MUTATION);
        $actionChanges = $this->objectList($data['actions'] ?? [], 'data.actions', self::MAX_ACTIONS_PER_MUTATION);
        $deleteFieldIds = $this->idList($data['deleteFieldIds'] ?? [], 'data.deleteFieldIds');
        $deleteActionIds = $this->idList($data['deleteActionIds'] ?? [], 'data.deleteActionIds');

        if (([] !== $deleteFieldIds || [] !== $deleteActionIds) && !$confirm) {
            throw new BadRequestHttpException('Removing form fields or actions requires confirm=true.');
        }

        $this->applyFormData($form, $data, $new);
        $this->validateForm($form);

        $fields = [] === $fieldChanges && [] === $deleteFieldIds
            ? []
            : $this->prepareFields($form, $fieldChanges, $deleteFieldIds);
        $actions = [] === $actionChanges && [] === $deleteActionIds
            ? []
            : $this->prepareActions($form, $actionChanges, $deleteActionIds);

        if ([] !== $deleteFieldIds) {
            $this->formModel->deleteFields($form, $deleteFieldIds);
        }
        if ([] !== $deleteActionIds) {
            $this->formModel->deleteActions($form, $deleteActionIds);
        }
        if ([] !== $fieldChanges || [] !== $deleteFieldIds) {
            $this->formModel->setFields($form, $fields);
        }
        if ([] !== $actionChanges || [] !== $deleteActionIds) {
            $this->formModel->setActions($form, $actions);
        }

        $this->formModel->saveEntity($form);

        return ['status' => $new ? 'created' : 'updated'] + $this->normalizer->normalize($form);
    }

    private function delete(Form $form, bool $confirm): array
    {
        if (!$confirm) {
            throw new BadRequestHttpException('Deleting a form requires confirm=true.');
        }
        $id = (int) $form->getId();
        // The batch path also removes the per-form result table and uploaded files.
        $deleted = $this->formModel->deleteEntities([$id]);
        if (!isset($deleted[$id])) {
            throw new ConflictHttpException('The form could not be deleted. Read it again before retrying.');
        }

        return ['status' => 'deleted', 'successIds' => [$id], 'failureIds' => [], 'formId' => $id];
    }

    private function publish(Form $form, bool $published): array
    {
        $form->setIsPublished($published);
        $this->formModel->saveEntity($form);

        return ['status' => $published ? 'published' : 'unpublished'] + $this->normalizer->normalize($form);
    }

    private function applyFormData(Form $form, array $data, bool $new): void
    {
        if (array_key_exists('isPublished', $data) && (bool) $data['isPublished'] !== (bool) $form->getIsPublished()) {
            if ($new) {
                $this->assertGranted('form:forms:publishown');
            } else {
                $this->assertEntityAccess($form, 'publish');
            }
        }

        foreach ([
            'name'                   => 'setName',
            'description'            => 'setDescription',
            'postAction'             => 'setPostAction',
            'postActionProperty'     => 'setPostActionProperty',
            'template'               => 'setTemplate',
            'language'               => 'setLanguage',
            'formAttributes'         => 'setFormAttributes',
            'submissionLimitMessage' => 'setSubmissionLimitMessage',
        ] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $form->{$setter}(null === $data[$key] ? null : (string) $data[$key]);
            }
        }

        foreach ([
            'isPublished' => 'setIsPublished',
            'inKioskMode' => 'setInKioskMode',
            'renderStyle'  => 'setRenderStyle',
            'noIndex'      => 'setNoIndex',
        ] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $form->{$setter}(null === $data[$key] ? null : (bool) $data[$key]);
            }
        }

        foreach ([
            'progressiveProfilingLimit' => 'setProgressiveProfilingLimit',
            'submissionLimit'           => 'setSubmissionLimit',
        ] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $form->{$setter}(null === $data[$key] || '' === $data[$key] ? null : (int) $data[$key]);
            }
        }

        foreach (['publishUp' => 'setPublishUp', 'publishDown' => 'setPublishDown'] as $key => $setter) {
            if (array_key_exists($key, $data)) {
                $form->{$setter}($this->date($data[$key], 'data.'.$key));
            }
        }

        if ($new) {
            $form->setPostAction((string) ($data['postAction'] ?? 'return'));
            if (array_key_exists('alias', $data)) {
                $form->setAlias($this->formModel->cleanAlias((string) $data['alias'], '', 10));
            }
        } elseif (array_key_exists('alias', $data)) {
            if ((string) $data['alias'] !== $form->getAlias()) {
                throw new BadRequestHttpException('A form alias cannot be changed after creation.');
            }
        }

        if (array_key_exists('categoryId', $data)) {
            $form->setCategory($this->category($data['categoryId']));
        }
    }

    private function validateForm(Form $form): void
    {
        if (!in_array($form->getPostAction(), ['return', 'message', 'redirect', 'hideform'], true)) {
            throw new BadRequestHttpException('data.postAction must be return, message, redirect, or hideform.');
        }
        $groups = ['form'];
        $groups[] = match ($form->getPostAction()) {
            'message'  => 'messageRequired',
            'redirect' => 'urlRequired',
            'hideform' => 'hideformRequired',
            default    => 'form',
        };
        if (null !== $form->getProgressiveProfilingLimit()) {
            $groups[] = 'progressiveProfilingLimit';
        }
        $violations = $this->validator->validate($form, null, array_values(array_unique($groups)));
        if (0 === $violations->count()) {
            return;
        }

        $messages = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $messages[] = trim($violation->getPropertyPath().': '.$violation->getMessage(), ': ');
        }
        throw new BadRequestHttpException('Invalid form data: '.implode('; ', $messages));
    }

    private function prepareFields(Form $form, array $changes, array $deleteIds): array
    {
        $fields = [];
        $positions = [];
        foreach ($form->getFields() as $field) {
            if (!$field instanceof Field || in_array((int) $field->getId(), $deleteIds, true)) {
                continue;
            }
            $id = (int) $field->getId();
            $fields[$id] = $this->normalizer->normalizeField($field);
            unset($fields[$id]['fieldOrder']);
            $positions[(string) $id] = (int) $field->getOrder();
        }
        $this->assertChildrenExist($form, $deleteIds, 'field');

        $aliases = array_values(array_filter(array_column($fields, 'alias'), 'is_string'));
        $customFields = $this->formModel->getCustomComponents()['fields'];
        $allowedTypes = array_fill_keys([...array_keys($this->fieldHelper->getTypes()), 'button', ...array_keys($customFields)], true);

        foreach ($changes as $index => $change) {
            if (!array_key_exists('order', $change) && array_key_exists('fieldOrder', $change)) {
                $change['order'] = $change['fieldOrder'];
            }
            $id = $this->optionalId($change['id'] ?? null, sprintf('data.fields[%d].id', $index));
            if (null !== $id) {
                if (!isset($fields[$id])) {
                    throw new NotFoundHttpException(sprintf('Form field %d was not found on this form.', $id));
                }
                if (in_array($id, $deleteIds, true)) {
                    throw new BadRequestHttpException(sprintf('Form field %d cannot be updated and deleted in the same request.', $id));
                }
                $current = $fields[$id];
                if (isset($change['type']) && (string) $change['type'] !== $current['type']) {
                    throw new BadRequestHttpException(sprintf('The type of existing form field %d cannot be changed.', $id));
                }
                if (isset($change['alias']) && $this->fieldModel->cleanAlias((string) $change['alias'], 'f_', 25) !== $current['alias']) {
                    throw new BadRequestHttpException(sprintf('The alias of existing form field %d cannot be changed.', $id));
                }
                $change['id'] = $id;
                $change['alias'] = $current['alias'];
                $change['type'] = $current['type'];
                $field = array_replace($current, $this->fieldProperties($change));
                $key = (string) $id;
            } else {
                $type = trim((string) ($change['type'] ?? ''));
                $label = trim((string) ($change['label'] ?? ''));
                if ('' === $type || '' === $label) {
                    throw new BadRequestHttpException(sprintf('data.fields[%d] requires label and type.', $index));
                }
                $key = 'new'.hash('sha1', uniqid((string) $index, true));
                $change['id'] = $key;
                $change['alias'] = $this->fieldModel->generateAlias((string) ($change['alias'] ?? $label), $aliases);
                $field = $this->fieldProperties($change);
                $field['isCustom'] = isset($customFields[$type]);
                $field['customParameters'] = $customFields[$type] ?? [];
                $positions[$key] = count($positions) + 1;
            }

            if (!empty($field['mappedField']) && empty($field['mappedObject'])) {
                $field['mappedObject'] = 'contact';
            }
            $type = trim((string) ($field['type'] ?? ''));
            if (!isset($allowedTypes[$type])) {
                throw new BadRequestHttpException(sprintf('Unsupported form field type "%s" at data.fields[%d].', $type, $index));
            }
            if ('' === trim((string) ($field['label'] ?? ''))) {
                throw new BadRequestHttpException(sprintf('data.fields[%d].label cannot be empty.', $index));
            }
            foreach (['properties', 'validation', 'conditions'] as $property) {
                if (isset($field[$property]) && !is_array($field[$property])) {
                    throw new BadRequestHttpException(sprintf('data.fields[%d].%s must be an object.', $index, $property));
                }
            }
            if (array_key_exists('order', $change)) {
                $positions[$key] = max(1, (int) $change['order']);
            }
            $fields[$key] = $field;
        }

        return $this->ordered($fields, $positions);
    }

    private function prepareActions(Form $form, array $changes, array $deleteIds): array
    {
        $actions = [];
        $positions = [];
        foreach ($form->getActions() as $action) {
            if (!$action instanceof Action || in_array((int) $action->getId(), $deleteIds, true)) {
                continue;
            }
            $id = (int) $action->getId();
            $actions[$id] = $this->normalizer->normalizeAction($action);
            unset($actions[$id]['actionOrder']);
            $positions[(string) $id] = (int) $action->getOrder();
        }
        $this->assertChildrenExist($form, $deleteIds, 'action');

        $allowedTypes = $this->formModel->getCustomComponents()['actions'];
        foreach ($changes as $index => $change) {
            if (!array_key_exists('order', $change) && array_key_exists('actionOrder', $change)) {
                $change['order'] = $change['actionOrder'];
            }
            $id = $this->optionalId($change['id'] ?? null, sprintf('data.actions[%d].id', $index));
            if (null !== $id) {
                if (!isset($actions[$id])) {
                    throw new NotFoundHttpException(sprintf('Form action %d was not found on this form.', $id));
                }
                if (in_array($id, $deleteIds, true)) {
                    throw new BadRequestHttpException(sprintf('Form action %d cannot be updated and deleted in the same request.', $id));
                }
                $current = $actions[$id];
                if (isset($change['type']) && (string) $change['type'] !== $current['type']) {
                    throw new BadRequestHttpException(sprintf('The type of existing form action %d cannot be changed.', $id));
                }
                $change['id'] = $id;
                $change['type'] = $current['type'];
                $action = array_replace($current, $this->actionProperties($change));
                $key = (string) $id;
            } else {
                $type = trim((string) ($change['type'] ?? ''));
                if ('' === $type) {
                    throw new BadRequestHttpException(sprintf('data.actions[%d].type is required.', $index));
                }
                $key = 'new'.hash('sha1', uniqid((string) $index, true));
                $change['id'] = $key;
                $change['name'] ??= $allowedTypes[$type]['label'] ?? $type;
                $action = $this->actionProperties($change);
                $positions[$key] = count($positions) + 1;
            }

            $type = trim((string) ($action['type'] ?? ''));
            if (!isset($allowedTypes[$type])) {
                throw new BadRequestHttpException(sprintf('Unsupported form action type "%s" at data.actions[%d].', $type, $index));
            }
            if (isset($action['properties']) && !is_array($action['properties'])) {
                throw new BadRequestHttpException(sprintf('data.actions[%d].properties must be an object.', $index));
            }
            if (array_key_exists('order', $change)) {
                $positions[$key] = max(1, (int) $change['order']);
            }
            $actions[$key] = $action;
        }

        return $this->ordered($actions, $positions);
    }

    private function fieldProperties(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'id', 'label', 'showLabel', 'alias', 'type', 'defaultValue',
            'isRequired', 'validationMessage', 'helpMessage', 'order', 'properties', 'validation', 'conditions',
            'parent', 'labelAttributes', 'inputAttributes', 'containerAttributes', 'leadField', 'saveResult',
            'isAutoFill', 'isReadOnly', 'showWhenValueExists', 'showAfterXSubmissions', 'alwaysDisplay',
            'mappedObject', 'mappedField', 'fieldWidth',
        ]));
    }

    private function actionProperties(array $data): array
    {
        return array_intersect_key($data, array_flip(['id', 'name', 'description', 'type', 'order', 'properties']));
    }

    private function ordered(array $items, array $positions): array
    {
        $sequence = 0;
        $sortable = [];
        foreach ($items as $key => $item) {
            $sortable[] = [
                'position' => $positions[(string) $key] ?? PHP_INT_MAX,
                'sequence' => $sequence++,
                'item'     => $item,
            ];
        }
        usort($sortable, static fn (array $a, array $b): int => [$a['position'], $a['sequence']] <=> [$b['position'], $b['sequence']]);

        $ordered = [];
        foreach ($sortable as $entry) {
            $entry['item']['order'] = count($ordered) + 1;
            $ordered[$entry['item']['id']] = $entry['item'];
        }

        return $ordered;
    }

    private function assertChildrenExist(Form $form, array $ids, string $resource): void
    {
        if ([] === $ids) {
            return;
        }
        $existingIds = 'field' === $resource
            ? array_map(static fn (Field $field): int => (int) $field->getId(), $form->getFields()->toArray())
            : array_map(static fn (Action $action): int => (int) $action->getId(), $form->getActions()->toArray());
        $missing = array_values(array_diff($ids, $existingIds));
        if ([] !== $missing) {
            throw new NotFoundHttpException(sprintf('Form %s IDs were not found on this form: %s.', $resource, implode(', ', $missing)));
        }
    }

    private function formForWrite(?int $id, string $permission, ?string $expectedDateModified): Form
    {
        $form = null === $id ? null : $this->formModel->getEntity($id);
        if (!$form instanceof Form) {
            throw new NotFoundHttpException(sprintf('Form %d was not found.', $id ?? 0));
        }
        $this->assertEntityAccess($form, $permission);
        $this->assertExpectedDateModified($form, $expectedDateModified);

        return $form;
    }

    private function assertExpectedDateModified(Form $form, ?string $expected): void
    {
        if (null === $expected || '' === trim($expected)) {
            return;
        }
        try {
            $expectedDate = new \DateTimeImmutable($expected);
        } catch (\Throwable) {
            throw new BadRequestHttpException('expectedDateModified must be a valid date-time.');
        }
        if (null === $form->getDateModified() || $form->getDateModified()->getTimestamp() !== $expectedDate->getTimestamp()) {
            throw new ConflictHttpException('The form changed after it was read. Fetch it again and retry with the current dateModified.');
        }
    }

    private function assertEntityAccess(Form $form, string $permission): void
    {
        if (!$this->permissions->hasEntityAccess(
            'form:forms:'.$permission.'own',
            'form:forms:'.$permission.'other',
            $form->getCreatedBy(),
        )) {
            throw new AccessDeniedException('Permission denied.');
        }
    }

    private function assertGranted(string $permission): void
    {
        if (!$this->permissions->isGranted($permission)) {
            throw new AccessDeniedException('Permission denied for '.$permission.'.');
        }
    }

    private function category(mixed $value): ?Category
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $category = false === $id ? null : $this->entityManager->find(Category::class, $id);
        if (!$category instanceof Category || 'form' !== $category->getBundle()) {
            throw new NotFoundHttpException(sprintf('Form category %d was not found.', (int) $value));
        }

        return $category;
    }

    private function date(mixed $value, string $path): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (!is_string($value)) {
            throw new BadRequestHttpException($path.' must be a valid date-time or null.');
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new BadRequestHttpException($path.' must be a valid date-time or null.');
        }
    }

    private function objectList(mixed $value, string $path, int $maximum): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new BadRequestHttpException($path.' must be an array of objects.');
        }
        if (count($value) > $maximum) {
            throw new BadRequestHttpException(sprintf('%s cannot contain more than %d items.', $path, $maximum));
        }
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                throw new BadRequestHttpException(sprintf('%s[%d] must be an object.', $path, $index));
            }
        }

        return $value;
    }

    private function idList(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new BadRequestHttpException($path.' must be an array of positive integer IDs.');
        }
        $ids = [];
        foreach ($value as $index => $id) {
            $normalized = $this->optionalId($id, sprintf('%s[%d]', $path, $index));
            if (null === $normalized) {
                throw new BadRequestHttpException(sprintf('%s[%d] must be a positive integer ID.', $path, $index));
            }
            $ids[] = $normalized;
        }

        return array_values(array_unique($ids));
    }

    private function optionalId(mixed $value, string $path): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (false === $id) {
            throw new BadRequestHttpException($path.' must be a positive integer ID.');
        }

        return $id;
    }
}
