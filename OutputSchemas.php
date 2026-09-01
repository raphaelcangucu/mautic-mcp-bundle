<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle;

final class OutputSchemas
{
    public const EMAIL_HTML = [
        'type'       => 'object',
        'required'   => ['emailId', 'customHtml', 'publicPreview', 'previewUrl'],
        'properties' => [
            'emailId'         => ['type' => 'integer', 'minimum' => 1],
            'name'            => ['type' => 'string'],
            'subject'         => ['type' => 'string'],
            'customHtml'      => ['type' => 'string'],
            'plainText'       => ['type' => ['string', 'null']],
            'preheaderText'   => ['type' => ['string', 'null']],
            'template'        => ['type' => ['string', 'null']],
            'publicPreview'   => ['type' => 'boolean'],
            'previewUrl'      => ['type' => 'string'],
            'dateModified'    => ['type' => ['string', 'null']],
        ],
        'additionalProperties' => false,
    ];

    public const OBJECT = [
        'type'                 => 'object',
        'properties'           => [
            'status'     => ['type' => 'string'],
            'page'       => ['type' => 'integer', 'minimum' => 1],
            'limit'      => ['type' => 'integer', 'minimum' => 1],
            'count'      => ['type' => 'integer', 'minimum' => 0],
            'total'      => ['type' => 'integer', 'minimum' => 0],
            'hasMore'    => ['type' => 'boolean'],
            'nextPage'   => ['type' => ['integer', 'null']],
            'nextCursor' => ['type' => 'integer', 'minimum' => 0],
            'items'      => ['type' => 'array', 'items' => ['type' => 'object']],
            'rows'       => ['type' => 'array', 'items' => ['type' => 'object']],
            'successIds' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'failureIds' => ['type' => 'array', 'items' => ['type' => 'integer']],
        ],
        'additionalProperties' => true,
    ];
}
