<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

/**
 * The delivered markup of a frontend response as a DOM.
 *
 * For assertions about structure - which input sits in which fieldset, which
 * element an "aria-controls" points at - that a regular expression over the
 * source can only approximate.
 */
trait DeliveredMarkupTrait
{
    /**
     * libxml does not know HTML5 elements such as "svg" or "header" and
     * reports each of them as an error. Those reports are collected and
     * discarded instead of surfacing as warnings, which the suite would fail
     * on; the tree is built all the same.
     */
    private function deliveredDocument(string $body): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $this->assertTrue($document->loadHTML($body), 'The delivered markup could not be parsed.');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new \DOMXPath($document);
    }

    /**
     * @return list<\DOMElement>
     */
    private function elementsMatching(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = $xpath->query($query, $context);
        $this->assertInstanceOf(\DOMNodeList::class, $nodes, sprintf('The query "%s" is invalid.', $query));

        $elements = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * An XPath predicate matching one class among several, the way a CSS
     * class selector does - "contains(@class, …)" would also match
     * "theme-settings__panel" for "theme-settings".
     */
    private function hasClass(string $class): string
    {
        return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
    }
}
