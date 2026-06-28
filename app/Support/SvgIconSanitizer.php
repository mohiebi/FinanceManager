<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SvgIconSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'svg',
        'g',
        'path',
        'circle',
        'rect',
        'line',
        'polyline',
        'polygon',
        'ellipse',
        'defs',
        'clipPath',
        'mask',
        'linearGradient',
        'radialGradient',
        'stop',
        'title',
        'desc',
    ];

    /** @var list<string> */
    private const ALLOWED_ATTRIBUTES = [
        'xmlns',
        'viewBox',
        'width',
        'height',
        'fill',
        'stroke',
        'stroke-width',
        'stroke-linecap',
        'stroke-linejoin',
        'stroke-miterlimit',
        'd',
        'points',
        'x',
        'y',
        'x1',
        'y1',
        'x2',
        'y2',
        'cx',
        'cy',
        'r',
        'rx',
        'ry',
        'opacity',
        'fill-rule',
        'clip-rule',
        'transform',
        'role',
        'aria-hidden',
        'focusable',
        'clip-path',
        'mask',
        'id',
        'offset',
        'stop-color',
        'stop-opacity',
    ];

    public function sanitize(?string $svg): ?string
    {
        $svg = trim((string) $svg);

        if ($svg === '') {
            return null;
        }

        if (strlen($svg) > 5000 || ! str_starts_with(strtolower($svg), '<svg')) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement instanceof DOMElement || $document->documentElement->tagName !== 'svg') {
            return null;
        }

        $this->sanitizeAttributes($document->documentElement);
        $this->sanitizeNode($document->documentElement);

        $sanitized = $document->saveXML($document->documentElement);

        return is_string($sanitized) && $sanitized !== '' ? $sanitized : null;
    }

    private function sanitizeNode(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                if (! in_array($child->tagName, self::ALLOWED_TAGS, true)) {
                    $node->removeChild($child);
                    $child = $next;

                    continue;
                }

                $this->sanitizeAttributes($child);
                $this->sanitizeNode($child);
            } elseif (! $child->isSameNode($node->ownerDocument?->documentElement) && ! in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $node->removeChild($child);
            }

            $child = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);

            if ($attribute === null) {
                continue;
            }

            $name = $attribute->nodeName;
            $value = trim($attribute->nodeValue ?? '');

            if (
                str_starts_with(strtolower($name), 'on')
                || ! in_array($name, self::ALLOWED_ATTRIBUTES, true)
                || str_contains(strtolower($value), 'javascript:')
                || str_contains(strtolower($value), 'data:')
            ) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
