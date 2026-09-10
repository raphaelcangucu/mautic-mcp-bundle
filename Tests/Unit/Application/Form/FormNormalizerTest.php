<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMcpBundle\Tests\Unit\Application\Form;

use Mautic\FormBundle\Entity\Action;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticMcpBundle\Application\Form\FormNormalizer;
use PHPUnit\Framework\TestCase;

final class FormNormalizerTest extends TestCase
{
    public function testEmptyPropertyMapsAreSerializedAsJsonObjects(): void
    {
        $form = new Form();
        $field = new Field();
        $field->setLabel('Message')->setAlias('message')->setType('textarea')->setForm($form);
        $form->addField('new-field', $field);
        $action = new Action();
        $action->setName('Change tags')->setType('lead.changetags')->setForm($form);
        $form->addAction('new-action', $action);

        $normalizer = new FormNormalizer();
        $result = $normalizer->normalize($form);

        self::assertInstanceOf(\stdClass::class, $result['fields'][0]['properties']);
        self::assertInstanceOf(\stdClass::class, $result['fields'][0]['validation']);
        self::assertInstanceOf(\stdClass::class, $result['fields'][0]['conditions']);
        self::assertInstanceOf(\stdClass::class, $result['actions'][0]['properties']);
        self::assertSame('{}', json_encode($result['fields'][0]['properties'], JSON_THROW_ON_ERROR));
        self::assertSame([], $normalizer->normalizeField($field)['properties']);
    }
}
