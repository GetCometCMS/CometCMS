<?php

declare(strict_types=1);

namespace CometCMS\Fields;

final class FieldRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    public function register(string $name, FieldType $type): void
    {
        $this->types[$name] = $type;
    }

    public function get(string $name): FieldType
    {
        return $this->types[$name] ?? $this->types['text'];
    }

    public function names(): array
    {
        return array_keys($this->types);
    }

    public static function builtins(): self
    {
        $registry = new self();
        $registry->register('text', new TextFieldType());
        $registry->register('textarea', new TextareaFieldType());
        $registry->register('markdown', new MarkdownFieldType());
        $registry->register('html', new HtmlFieldType());
        $registry->register('number', new NumberFieldType());
        $registry->register('range', new RangeFieldType());
        $registry->register('boolean', new BooleanFieldType());
        $registry->register('select', new SelectFieldType());
        $registry->register('date', new DateFieldType());
        $registry->register('datetime', new DateTimeFieldType());
        $registry->register('media', new MediaFieldType());
        $registry->register('slug', new SlugFieldType());
        $registry->register('json', new JsonFieldType());
        $registry->register('relation', new RelationFieldType());
        $registry->register('repeater', new RepeaterFieldType());
        $registry->register('color', new ColorFieldType());
        $registry->register('page', new PageFieldType());

        return $registry;
    }
}
