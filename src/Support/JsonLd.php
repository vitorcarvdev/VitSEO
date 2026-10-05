<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class JsonLd
{
    /**
     * @param list<string> $scripts
     * @return array{types: list<string>, invalid: list<string>, valid_blocks: int, nodes: list<mixed>, entities: list<array{type: string, properties: list<array{name: string, value: string}>}>}
     */
    public static function parse(array $scripts): array
    {
        $types = [];
        $invalid = [];
        $nodes = [];
        $entities = [];
        $valid = 0;

        foreach ($scripts as $index => $raw) {
            $clean = self::clean((string) $raw);
            if ($clean === '') {
                continue;
            }
            try {
                $data = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $invalid[] = 'Bloco ' . ($index + 1) . ' não é JSON válido.';
                continue;
            }
            $valid++;
            $nodes[] = $data;
            self::collect($data, $types, 0);
            self::entities($data, $entities, 0);
        }

        return [
            'types' => array_values(array_unique($types)),
            'invalid' => $invalid,
            'valid_blocks' => $valid,
            'nodes' => $nodes,
            'entities' => $entities,
        ];
    }

    private static function clean(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = preg_replace('/^\s*<!--|-->\s*$/', '', $raw) ?? $raw;
        $raw = preg_replace('/^\s*(?:\/\/\s*)?<!\[CDATA\[|\]\]>\s*$/', '', $raw) ?? $raw;

        return trim($raw);
    }

    private static function collect(mixed $node, array &$types, int $depth): void
    {
        if ($depth > 12 || !is_array($node)) {
            return;
        }

        if (isset($node['@type'])) {
            foreach ((array) $node['@type'] as $type) {
                if (is_string($type) && $type !== '') {
                    $types[] = $type;
                }
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::collect($value, $types, $depth + 1);
            }
        }
    }

    /**
     * @param list<array{type: string, properties: list<array{name: string, value: string}>}> $entities
     */
    private static function entities(mixed $node, array &$entities, int $depth): void
    {
        if ($depth > 8 || !is_array($node) || count($entities) >= 8) {
            return;
        }

        if (!array_is_list($node) && isset($node['@type'])) {
            $types = [];
            foreach ((array) $node['@type'] as $type) {
                if (is_string($type) && $type !== '') {
                    $types[] = $type;
                }
            }
            $properties = [];
            foreach (['name', 'headline', 'description', 'url', 'image', 'logo', 'author', 'datePublished', 'telephone', 'email'] as $key) {
                if (!array_key_exists($key, $node)) {
                    continue;
                }
                $value = self::displayValue($node[$key]);
                if ($value === null || $value === '') {
                    continue;
                }
                $properties[] = ['name' => $key, 'value' => Text::excerpt($value, 180)];
            }
            if ($types !== []) {
                $entities[] = [
                    'type' => implode(', ', $types),
                    'properties' => $properties,
                ];
            }
        }

        foreach ($node as $key => $value) {
            if ($key === '@context' || !is_array($value) || count($entities) >= 8) {
                continue;
            }
            self::entities($value, $entities, $depth + 1);
        }
    }

    private static function displayValue(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $text = trim((string) $value);

            return $text !== '' ? $text : null;
        }
        if (!is_array($value)) {
            return null;
        }
        foreach (['name', 'url', '@id'] as $key) {
            if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
                return trim($value[$key]);
            }
        }

        $parts = [];
        foreach ($value as $item) {
            $text = self::displayValue($item);
            if ($text !== null) {
                $parts[] = $text;
            }
            if (count($parts) >= 3) {
                break;
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
