<?php

namespace App\Service;

class ArticleContentSanitizer
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'em',
        'h2',
        'h3',
        'img',
        'li',
        'ol',
        'p',
        'strong',
        'table',
        'tbody',
        'td',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];

    /**
     * @var list<string>
     */
    private const REMOVE_WITH_CONTENT_TAGS = [
        'applet',
        'embed',
        'form',
        'iframe',
        'input',
        'meta',
        'object',
        'script',
        'select',
        'style',
        'svg',
        'textarea',
    ];

    public function sanitize(?string $content): string
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);

        $wrappedContent = sprintf('<div data-sanitizer-root="1">%s</div>', $content);
        $document->loadHTML(
            '<?xml encoding="UTF-8">' . $wrappedContent,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $root = $document->documentElement;
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $this->sanitizeNode($root);

        $html = '';
        foreach ($root->childNodes as $childNode) {
            $html .= $document->saveHTML($childNode);
        }

        return trim($html);
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null; ) {
            $nextSibling = $child->nextSibling;

            if ($child instanceof \DOMElement) {
                $tagName = strtolower($child->tagName);

                if (in_array($tagName, self::REMOVE_WITH_CONTENT_TAGS, true)) {
                    $node->removeChild($child);
                    $child = $nextSibling;
                    continue;
                }

                if (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                    $this->unwrapNode($child);
                    $child = $nextSibling;
                    continue;
                }

                $this->sanitizeAttributes($child, $tagName);
                $this->sanitizeNode($child);
            }

            $child = $nextSibling;
        }
    }

    private function sanitizeAttributes(\DOMElement $element, string $tagName): void
    {
        $allowedAttributes = self::ALLOWED_ATTRIBUTES[$tagName] ?? [];

        for ($index = $element->attributes->length - 1; $index >= 0; --$index) {
            $attribute = $element->attributes->item($index);
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $attributeName = strtolower($attribute->name);
            if (!in_array($attributeName, $allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $sanitizedValue = $this->sanitizeAttributeValue($tagName, $attributeName, $attribute->value);
            if ($sanitizedValue === null) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $element->setAttribute($attributeName, $sanitizedValue);
        }

        if ($tagName === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function sanitizeAttributeValue(string $tagName, string $attributeName, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (($tagName === 'a' && $attributeName === 'href') || ($tagName === 'img' && $attributeName === 'src')) {
            return $this->sanitizeUrl($value, $tagName === 'a');
        }

        if ($attributeName === 'target') {
            return in_array($value, ['_blank', '_self'], true) ? $value : null;
        }

        if (in_array($attributeName, ['width', 'height', 'colspan', 'rowspan'], true)) {
            return ctype_digit($value) ? $value : null;
        }

        if ($attributeName === 'scope') {
            return in_array($value, ['col', 'row', 'colgroup', 'rowgroup'], true) ? $value : null;
        }

        return $value;
    }

    private function sanitizeUrl(string $value, bool $allowMailAndTel): ?string
    {
        if (preg_match('/^\s*javascript:/i', $value) === 1) {
            return null;
        }

        if (preg_match('/^\s*data:/i', $value) === 1) {
            return null;
        }

        if (preg_match('/^\s*vbscript:/i', $value) === 1) {
            return null;
        }

        if (
            str_starts_with($value, '/')
            || str_starts_with($value, './')
            || str_starts_with($value, '../')
            || str_starts_with($value, '#')
        ) {
            return $value;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        if (!is_string($scheme)) {
            return null;
        }

        $scheme = strtolower($scheme);
        $allowedSchemes = $allowMailAndTel ? ['http', 'https', 'mailto', 'tel'] : ['http', 'https'];

        return in_array($scheme, $allowedSchemes, true) ? $value : null;
    }

    private function unwrapNode(\DOMElement $node): void
    {
        $parentNode = $node->parentNode;
        if (!$parentNode instanceof \DOMNode) {
            return;
        }

        while ($node->firstChild !== null) {
            $parentNode->insertBefore($node->firstChild, $node);
        }

        $parentNode->removeChild($node);
    }
}
