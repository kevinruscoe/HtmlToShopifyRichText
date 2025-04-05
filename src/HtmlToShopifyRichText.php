<?php

namespace Webfoo\HtmlToShopifyRichText;

use Webfoo\HtmlToShopifyRichText\Exceptions\InvalidHtmlException;
use Webfoo\HtmlToShopifyRichText\Exceptions\ConversionException;
use Webfoo\HtmlToShopifyRichText\Exceptions\JsonEncodingException;
use Symfony\Component\DomCrawler\Crawler;
use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Class HtmlToShopifyRichText
 * 
 * This class provides functionality to convert HTML content to Shopify's Rich Text format.
 * It handles the conversion of common HTML elements to their Shopify Rich Text equivalents.
 */
readonly class HtmlToShopifyRichText
{
    private const array SUPPORTED_HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    private const array BLOCK_ELEMENTS = ['heading', 'list', 'paragraph'];
    private const array INLINE_ELEMENTS = ['text', 'link', 'bold', 'italic'];
    private const array SUPPORTED_INLINE_TAGS = ['strong', 'b', 'em', 'i', 'a'];
    private const array SUPPORTED_LIST_TAGS = ['ul', 'ol'];
    private const array SUPPORTED_BOLD_TAGS = ['strong', 'b'];
    private const array SUPPORTED_ITALIC_TAGS = ['em', 'i'];

    /**
     * Convert HTML content to Shopify Rich Text format
     *
     * @param string $html The HTML content to convert
     * @return string The converted Shopify Rich Text content as JSON
     * @throws InvalidHtmlException If the HTML is invalid or cannot be parsed
     * @throws ConversionException If the conversion process fails
     * @throws JsonEncodingException If the JSON encoding fails
     */
    public static function convert(string $html): string
    {
        if (empty($html)) {
            throw new InvalidHtmlException('HTML content cannot be empty');
        }

        // Remove comments before validation
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Basic malformed HTML checks
        if (substr_count($html, '<') !== substr_count($html, '>')) {
            throw new InvalidHtmlException('Malformed HTML: Unmatched angle brackets');
        }

        // Check for unclosed tags
        $stack = [];
        preg_match_all('/<(\/)?([a-zA-Z0-9]+)[^>]*>/', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $isClosing = $match[1] === '/';
            $tag = strtolower($match[2]);
            
            if (!$isClosing) {
                $stack[] = $tag;
            } else {
                if (empty($stack) || array_pop($stack) !== $tag) {
                    throw new InvalidHtmlException('Malformed HTML: Unclosed or mismatched tags');
                }
            }
        }
        
        if (!empty($stack)) {
            throw new InvalidHtmlException('Malformed HTML: Unclosed tags');
        }

        // Check for invalid tag syntax
        if (preg_match('/<[^a-zA-Z\/!]/', $html)) {
            throw new InvalidHtmlException('Malformed HTML: Invalid tag syntax');
        }

        // Check for links without href before other validations
        if (preg_match('/<a(?![^>]*href=)[^>]*>/', $html)) {
            throw new ConversionException('Link element missing href attribute');
        }

        $html = self::sanitizeHtml($html);
        
        try {
            $crawler = new Crawler($html);
            $document = [
                'type' => 'root',
                'children' => []
            ];

            $currentParagraph = null;

            // If there's no wrapping element, wrap the content in a paragraph
            if (!$crawler->filter('body > *')->count() && trim($crawler->filter('body')->text())) {
                $text = trim($crawler->filter('body')->text());
                $document['children'][] = [
                    'type' => 'paragraph',
                    'children' => [
                        ['type' => 'text', 'value' => $text]
                    ]
                ];
            } else {
                $crawler->filter('body > *')->each(function (Crawler $node) use (&$document, &$currentParagraph) {
                    $converted = self::convertElement($node);
                    if ($converted !== null) {
                        self::handleConvertedElement($converted, $document, $currentParagraph);
                    }
                });

                if ($currentParagraph && !empty($currentParagraph['children'])) {
                    $document['children'][] = $currentParagraph;
                }
            }

            // If no children were added, add an empty paragraph
            if (empty($document['children'])) {
                $document['children'][] = [
                    'type' => 'paragraph',
                    'children' => []
                ];
            }

            $json = json_encode($document, JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new JsonEncodingException(sprintf('Failed to encode document to JSON: %s', json_last_error_msg()));
            }
            
            if (!json_validate($json)) {
                throw new JsonEncodingException('Generated JSON is invalid');
            }
            
            return $json;
        } catch (\Throwable $e) {
            throw new ConversionException(sprintf('Failed to convert HTML: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Handle a converted element and update the document structure
     *
     * @param array|array[] $converted The converted element
     * @param array $document The document structure
     * @param array|null $currentParagraph Reference to the current paragraph
     * @throws ConversionException If the element cannot be handled
     */
    private static function handleConvertedElement($converted, array &$document, ?array &$currentParagraph): void
    {
        if (!is_array($converted)) {
            throw new ConversionException('Converted element must be an array');
        }

        if (isset($converted['type'])) {
            if (in_array($converted['type'], self::BLOCK_ELEMENTS)) {
                if ($currentParagraph && !empty($currentParagraph['children'])) {
                    $document['children'][] = $currentParagraph;
                    $currentParagraph = null;
                }
                $document['children'][] = $converted;
            } elseif (in_array($converted['type'], self::INLINE_ELEMENTS)) {
                if (!$currentParagraph) {
                    $currentParagraph = [
                        'type' => 'paragraph',
                        'children' => []
                    ];
                }
                $currentParagraph['children'][] = $converted;
            } else {
                throw new ConversionException(sprintf('Unsupported element type: %s', $converted['type']));
            }
        } else {
            if (!$currentParagraph) {
                $currentParagraph = [
                    'type' => 'paragraph',
                    'children' => []
                ];
            }
            foreach ($converted as $item) {
                if (!empty($item)) {
                    if (!is_array($item) || !isset($item['type'])) {
                        throw new ConversionException('Invalid converted item structure');
                    }
                    $currentParagraph['children'][] = $item;
                }
            }
        }
    }

    /**
     * Sanitize HTML content before conversion
     *
     * @param string $html The HTML content to sanitize
     * @return string The sanitized HTML content
     * @throws InvalidHtmlException If the HTML cannot be sanitized
     */
    private static function sanitizeHtml(string $html): string
    {
        try {
            // Basic malformed HTML checks
            if (substr_count($html, '<') !== substr_count($html, '>')) {
                throw new InvalidHtmlException('Malformed HTML: Unmatched angle brackets');
            }

            $config = HTMLPurifier_Config::createDefault();
            
            // Configure HTML Purifier to allow only the elements we support
            $config->set('HTML.Allowed', implode(',', [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6', // Headings
                'p', // Paragraphs
                'ul', 'ol', 'li', // Lists
                'a[href|title]', // Links with href and title attributes
                'strong', 'b', // Bold
                'em', 'i', // Italic
            ]));
            
            // Remove comments
            $config->set('Core.RemoveProcessingInstructions', true);
            
            // Remove script and style tags
            $config->set('HTML.ForbiddenElements', ['script', 'style']);

            // Require proper nesting
            $config->set('HTML.Strict', true);
            
            // Create HTML Purifier instance
            $purifier = new HTMLPurifier($config);
            
            // Ensure proper HTML structure
            $html = sprintf('<!DOCTYPE html><html><body>%s</body></html>', $html);
            
            // Purify the HTML
            $purified = $purifier->purify($html);

            // Extract the body content
            $crawler = new Crawler($purified);
            $bodyContent = $crawler->filter('body')->html();
            
            return trim($bodyContent);
        } catch (\Throwable $e) {
            throw new InvalidHtmlException(sprintf('Failed to sanitize HTML: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Convert a DOM node to Shopify Rich Text format
     *
     * @param \DOMNode $node The node to convert
     * @return array The converted content
     * @throws ConversionException If the node cannot be converted
     */
    private static function convertNode(Crawler $node): array
    {
        $result = [];

        try {
            // Handle text nodes
            if ($node->nodeName() === '#text') {
                $text = $node->getNode(0)->nodeValue;
                if (trim($text) !== '') {
                    // Normalize multiple spaces to a single space
                    $text = preg_replace('/\s+/', ' ', $text);
                    $result[] = ['type' => 'text', 'value' => $text];
                }
                return $result;
            }

            // Handle child nodes
            $childNodes = $node->getNode(0)->childNodes;
            $lastIndex = $childNodes->length - 1;

            for ($i = 0; $i < $childNodes->length; $i++) {
                $childNode = $childNodes->item($i);
                $childCrawler = new Crawler($childNode);
                $converted = self::convertElement($childCrawler);

                if ($converted !== null) {
                    if (is_array($converted) && !isset($converted['type'])) {
                        foreach ($converted as $item) {
                            $result[] = $item;
                        }
                    } else {
                        $result[] = $converted;
                    }
                }
            }

            // Normalize whitespace
            $normalizedResult = [];
            $lastTextIndex = -1;

            for ($i = 0; $i < count($result); $i++) {
                if ($result[$i]['type'] === 'text') {
                    $value = $result[$i]['value'];
                    
                    // Normalize multiple spaces
                    $value = preg_replace('/\s+/', ' ', $value);
                    
                    // Handle whitespace based on position
                    if ($i === 0) {
                        $value = ltrim($value);
                    }
                    if ($i === count($result) - 1) {
                        $value = rtrim($value);
                    }
                    
                    // Add space between text nodes if needed
                    if ($lastTextIndex !== -1 && trim($value) !== '') {
                        $lastValue = $normalizedResult[$lastTextIndex]['value'];
                        if (!str_ends_with($lastValue, ' ') && !str_starts_with($value, ' ')) {
                            $normalizedResult[$lastTextIndex]['value'] = rtrim($lastValue) . ' ';
                        }
                    }
                    
                    if (trim($value) !== '') {
                        $result[$i]['value'] = $value;
                        $normalizedResult[] = $result[$i];
                        $lastTextIndex = count($normalizedResult) - 1;
                    }
                } else {
                    // Add space before non-text nodes if needed
                    if ($lastTextIndex !== -1) {
                        $lastValue = $normalizedResult[$lastTextIndex]['value'];
                        if (!str_ends_with($lastValue, ' ')) {
                            $normalizedResult[$lastTextIndex]['value'] = rtrim($lastValue) . ' ';
                        }
                    }
                    $normalizedResult[] = $result[$i];
                    $lastTextIndex = -1;
                }
            }

            return $normalizedResult;
        } catch (\Throwable $e) {
            throw new ConversionException('Failed to convert node: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert a single DOM element to Shopify Rich Text format
     *
     * @param \DOMNode $element The element to convert
     * @return array|null The converted element
     * @throws ConversionException If the element cannot be converted
     */
    private static function convertElement(Crawler $node): ?array
    {
        $nodeName = $node->nodeName();

        // Handle text nodes
        if ($nodeName === '#text') {
            $text = $node->getNode(0)->nodeValue;
            if (trim($text) !== '') {
                return ['type' => 'text', 'value' => $text];
            }
            return null;
        }

        // Handle headings
        if (in_array($nodeName, self::SUPPORTED_HEADINGS)) {
            $level = (int) substr($nodeName, 1);
            $children = self::convertNode($node);
            if (!empty($children)) {
                return [
                    'type' => 'heading',
                    'level' => $level,
                    'children' => $children
                ];
            }
            return null;
        }

        // Handle lists
        if (in_array($nodeName, self::SUPPORTED_LIST_TAGS)) {
            $children = array_filter(array_map(function ($item) {
                if ($item instanceof \DOMText && trim($item->nodeValue) === '') {
                    return null;
                }
                if (!($item instanceof \DOMElement) || $item->nodeName !== 'li') {
                    return null;
                }
                $children = self::convertNode(new Crawler($item));
                if (!empty($children)) {
                    return [
                        'type' => 'list-item',
                        'children' => $children
                    ];
                }
                return null;
            }, iterator_to_array($node->getNode(0)->childNodes)));

            if (!empty($children)) {
                return [
                    'type' => 'list',
                    'listType' => $nodeName === 'ul' ? 'unordered' : 'ordered',
                    'children' => array_values($children)
                ];
            }
            return null;
        }

        // Handle list items
        if ($nodeName === 'li') {
            $children = self::convertNode($node);
            if (!empty($children)) {
                return [
                    'type' => 'list-item',
                    'children' => $children
                ];
            }
            return null;
        }

        // Handle links
        if ($nodeName === 'a') {
            $href = $node->attr('href');
            if (!$href) {
                throw new ConversionException('Link element missing href attribute');
            }
            $children = self::convertNode($node);
            if (!empty($children)) {
                return [
                    'type' => 'link',
                    'url' => $href,
                    'title' => $node->attr('title'),
                    'children' => $children
                ];
            }
            return null;
        }

        // Handle bold text
        if (in_array($nodeName, self::SUPPORTED_BOLD_TAGS)) {
            $children = self::convertNode($node);
            if (empty($children)) {
                return null;
            }
            
            foreach ($children as &$child) {
                if ($child['type'] === 'text') {
                    $child['bold'] = true;
                    if (isset($child['italic'])) {
                        // Ensure consistent attribute order
                        $value = $child['value'];
                        unset($child['value'], $child['italic'], $child['bold']);
                        $child['value'] = $value;
                        $child['bold'] = true;
                        $child['italic'] = true;
                    }
                }
            }
            return $children;
        }

        // Handle italic text
        if (in_array($nodeName, self::SUPPORTED_ITALIC_TAGS)) {
            $children = self::convertNode($node);
            if (empty($children)) {
                return null;
            }
            
            foreach ($children as &$child) {
                if ($child['type'] === 'text') {
                    $child['italic'] = true;
                    if (isset($child['bold'])) {
                        // Ensure consistent attribute order
                        $value = $child['value'];
                        unset($child['value'], $child['italic'], $child['bold']);
                        $child['value'] = $value;
                        $child['bold'] = true;
                        $child['italic'] = true;
                    }
                }
            }
            return $children;
        }

        // Handle paragraphs and other block elements
        if ($nodeName === 'p') {
            $children = self::convertNode($node);
            if (!empty($children)) {
                return [
                    'type' => 'paragraph',
                    'children' => $children
                ];
            }
            return null;
        }

        // Handle unsupported elements
        return self::convertNode($node);
    }
} 