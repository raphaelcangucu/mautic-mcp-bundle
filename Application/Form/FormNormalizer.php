<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Application\Form;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\ProjectBundle\Entity\Project;

final class FormNormalizer
{
    public function normalize(Form $form): array
    {
        $category = $form->getCategory();
        $fields = array_map(
            fn (Field $field): array => $this->withJsonObjectMaps($this->normalizeField($field), ['properties', 'validation', 'conditions']),
            $form->getFields()->toArray(),
        );
        usort($fields, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);
        $actions = array_map(
            fn (Action $action): array => $this->withJsonObjectMaps($this->normalizeAction($action), ['properties']),
            $form->getActions()->toArray(),
        );
        usort($actions, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return [
            'form' => [
                'id'                        => $form->getId(),
                'name'                      => $form->getName(),
                'alias'                     => $form->getAlias(),
                'description'               => $form->getDescription(),
                'isPublished'               => (bool) $form->getIsPublished(),
                'publishStatus'              => $form->getPublishStatus(),
                'publishUp'                  => $form->getPublishUp()?->format(DATE_ATOM),
                'publishDown'                => $form->getPublishDown()?->format(DATE_ATOM),
                'postAction'                 => $form->getPostAction(),
                'postActionProperty'         => $form->getPostActionProperty(),
                'template'                   => $form->getTemplate(),
                'language'                   => $form->getLanguage(),
                'inKioskMode'                => (bool) $form->getInKioskMode(),
                'renderStyle'                => (bool) $form->getRenderStyle(),
                'noIndex'                    => $form->getNoIndex(),
                'formAttributes'             => $form->getFormAttributes(),
                'progressiveProfilingLimit'  => $form->getProgressiveProfilingLimit(),
                'submissionLimit'            => $form->getSubmissionLimit(),
                'submissionLimitMessage'     => $form->getSubmissionLimitMessage(),
                'submissionCount'            => $form->getSubmissionCount(),
                'categoryId'                 => $category?->getId(),
                'category'                   => null === $category ? null : [
                    'id'    => $category->getId(),
                    'title' => $category->getTitle(),
                ],
                'projects'                   => array_values(array_map(
                    static fn (Project $project): array => ['id' => $project->getId(), 'name' => $project->getName()],
                    $form->getProjects()->toArray(),
                )),
                'createdBy'                  => $form->getCreatedBy(),
                'dateAdded'                  => $form->getDateAdded()?->format(DATE_ATOM),
                'dateModified'               => $form->getDateModified()?->format(DATE_ATOM),
            ],
            'fields' => $fields,
            'actions' => $actions,
        ];
    }

    public function normalizeField(Field $field): array
    {
        $data = $field->convertToArray();
        $normalized = array_intersect_key($data, array_flip([
            'id',
            'label',
            'showLabel',
            'alias',
            'type',
            'defaultValue',
            'isRequired',
            'validationMessage',
            'helpMessage',
            'order',
            'properties',
            'validation',
            'conditions',
            'parent',
            'labelAttributes',
            'inputAttributes',
            'containerAttributes',
            'leadField',
            'saveResult',
            'isAutoFill',
            'isReadOnly',
            'showWhenValueExists',
            'showAfterXSubmissions',
            'alwaysDisplay',
            'mappedObject',
            'mappedField',
            'fieldWidth',
        ]));
        $normalized['id'] = $field->getId();
        $normalized['order'] = (int) $field->getOrder();
        // Retain the old MCP key while publishing the canonical Mautic API key.
        $normalized['fieldOrder'] = $normalized['order'];

        return $normalized;
    }

    public function normalizeAction(Action $action): array
    {
        $normalized = [
            'id'          => $action->getId(),
            'name'        => $action->getName(),
            'description' => $action->getDescription(),
            'type'        => $action->getType(),
            'order'       => (int) $action->getOrder(),
            'properties'  => $action->getProperties(),
        ];
        // Retain the old MCP key while publishing the canonical Mautic API key.
        $normalized['actionOrder'] = $normalized['order'];

        return $normalized;
    }

    /**
     * PHP represents both an empty JSON object and an empty JSON array as []. Ensure map-shaped
     * values are emitted as {} while keeping raw arrays in the write service's internal payloads.
     *
     * @param string[] $keys
     */
    private function withJsonObjectMaps(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (!isset($data[$key]) || [] === $data[$key]) {
                $data[$key] = new \stdClass();
            }
        }

        return $data;
    }
}
