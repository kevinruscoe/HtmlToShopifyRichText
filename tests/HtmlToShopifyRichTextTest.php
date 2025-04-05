<?php

namespace Webfoo\HtmlToShopifyRichText\Tests;

use Webfoo\HtmlToShopifyRichText\HtmlToShopifyRichText;
use Webfoo\HtmlToShopifyRichText\Exceptions\InvalidHtmlException;
use Webfoo\HtmlToShopifyRichText\Exceptions\ConversionException;
use Webfoo\HtmlToShopifyRichText\Exceptions\JsonEncodingException;
use PHPUnit\Framework\TestCase;

class HtmlToShopifyRichTextTest extends TestCase
{
    public function testConvertEmptyHtml()
    {
        $this->expectException(InvalidHtmlException::class);
        $this->expectExceptionMessage('HTML content cannot be empty');
        HtmlToShopifyRichText::convert('');
    }

    public function testConvertMalformedHtml()
    {
        $this->expectException(InvalidHtmlException::class);
        $this->expectExceptionMessage('Malformed HTML: Invalid tag syntax');
        HtmlToShopifyRichText::convert('<<>>');
    }

    public function testConvertInvalidTagSyntax()
    {
        $this->expectException(InvalidHtmlException::class);
        $this->expectExceptionMessage('Malformed HTML: Invalid tag syntax');
        HtmlToShopifyRichText::convert('<1>Invalid tag</1>');
    }

    public function testConvertSimpleText()
    {
        $result = HtmlToShopifyRichText::convert('Hello World');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"Hello World"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertParagraph()
    {
        $result = HtmlToShopifyRichText::convert('<p>Hello World</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"Hello World"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertMultipleParagraphs()
    {
        $result = HtmlToShopifyRichText::convert('<p>First paragraph</p><p>Second paragraph</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"First paragraph"}]},{"type":"paragraph","children":[{"type":"text","value":"Second paragraph"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertHeading()
    {
        $result = HtmlToShopifyRichText::convert('<h1>Main Heading</h1>');
        $expected = '{"type":"root","children":[{"type":"heading","level":1,"children":[{"type":"text","value":"Main Heading"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertBold()
    {
        $result = HtmlToShopifyRichText::convert('<p>This is <strong>bold</strong> text</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"This is "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" text"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertItalic()
    {
        $result = HtmlToShopifyRichText::convert('<p>This is <em>italic</em> text</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"This is "},{"type":"text","value":"italic","italic":true},{"type":"text","value":" text"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertLink()
    {
        $result = HtmlToShopifyRichText::convert('<p>Visit <a href="https://example.com">Example</a></p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"Visit "},{"type":"link","url":"https://example.com","title":null,"children":[{"type":"text","value":"Example"}]}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertLinkWithTitle()
    {
        $result = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="Link to example.com">This is a hyperlink</a></p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"Link to example.com","children":[{"type":"text","value":"This is a hyperlink"}]}]}]}';
        $this->assertEquals($expected, $result);

        // Test link with formatting inside
        $result2 = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="Link to example.com">This is a <strong>bold</strong> hyperlink</a></p>');
        $expected2 = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"Link to example.com","children":[{"type":"text","value":"This is a "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" hyperlink"}]}]}]}';
        $this->assertEquals($expected2, $result2);

        // Test link with multiple words in title
        $result3 = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="This is a link to example.com">Click here</a></p>');
        $expected3 = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"This is a link to example.com","children":[{"type":"text","value":"Click here"}]}]}]}';
        $this->assertEquals($expected3, $result3);
    }

    public function testConvertLinkWithoutHref()
    {
        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('Link element missing href attribute');
        HtmlToShopifyRichText::convert('<p>Visit <a>Example</a></p>');
    }

    public function testConvertUnorderedList()
    {
        $result = HtmlToShopifyRichText::convert('<ul><li>First item</li><li>Second item</li></ul>');
        $expected = '{"type":"root","children":[{"type":"list","listType":"unordered","children":[{"type":"list-item","children":[{"type":"text","value":"First item"}]},{"type":"list-item","children":[{"type":"text","value":"Second item"}]}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertOrderedList()
    {
        $result = HtmlToShopifyRichText::convert('<ol><li>This is the first list item.</li><li>This is the second list item.</li></ol>');
        $expected = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is the first list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is the second list item."}]}]}]}';
        $this->assertEquals($expected, $result);

        // Test ordered list with formatting
        $result2 = HtmlToShopifyRichText::convert('<ol><li>This is a <strong>bold</strong> list item.</li><li>This is an <em>italic</em> list item.</li></ol>');
        $expected2 = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is a "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is an "},{"type":"text","value":"italic","italic":true},{"type":"text","value":" list item."}]}]}]}';
        $this->assertEquals($expected2, $result2);

        // Test ordered list with links
        $result3 = HtmlToShopifyRichText::convert('<ol><li>This is a <a href="https://example.com">link</a> in a list item.</li><li>This is another list item.</li></ol>');
        $expected3 = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is a "},{"type":"link","url":"https://example.com","title":null,"children":[{"type":"text","value":"link"}]},{"type":"text","value":" in a list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is another list item."}]}]}]}';
        $this->assertEquals($expected3, $result3);
    }

    public function testConvertNestedElements()
    {
        $result = HtmlToShopifyRichText::convert('<p>This is <strong>bold and <em>italic</em></strong> text</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"This is "},{"type":"text","value":"bold and ","bold":true},{"type":"text","value":"italic","bold":true,"italic":true},{"type":"text","value":" text"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertWhitespace()
    {
        $result = HtmlToShopifyRichText::convert('<p>  Multiple   spaces  </p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"Multiple spaces"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertEmptyElements()
    {
        $result = HtmlToShopifyRichText::convert('<p></p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertUnsupportedElements()
    {
        $result = HtmlToShopifyRichText::convert('<p>This is <span>unsupported</span> text</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"This is unsupported text"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertScriptTags()
    {
        $result = HtmlToShopifyRichText::convert('<p>Hello<script>alert("world");</script>World</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"HelloWorld"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertStyleTags()
    {
        $result = HtmlToShopifyRichText::convert('<p>Hello<style>body { color: red; }</style>World</p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"HelloWorld"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertComments()
    {
        $result = HtmlToShopifyRichText::convert('<!-- Comment -->Hello World<!-- Another comment -->');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"Hello World"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testConvertComplexNestedTags()
    {
        $html = '
            <h1>Main Title</h1>
            <p>This is a <strong>bold text with <em>italic</em> inside</strong> and a 
            <a href="https://example.com" title="Example Link">link with <strong>bold</strong> text</a>.</p>
            <ul>
                <li><strong>First</strong> item with <em>emphasis</em></li>
                <li>Second item with <a href="https://test.com">nested <em>italic</em> link</a></li>
            </ul>
            <h2>Subsection</h2>
            <p>Final <em>paragraph</em> with <strong>mixed</strong> formatting.</p>
        ';

        $result = HtmlToShopifyRichText::convert($html);
        $expected = json_encode([
            'type' => 'root',
            'children' => [
                [
                    'type' => 'heading',
                    'level' => 1,
                    'children' => [
                        ['type' => 'text', 'value' => 'Main Title']
                    ]
                ],
                [
                    'type' => 'paragraph',
                    'children' => [
                        ['type' => 'text', 'value' => 'This is a '],
                        [
                            'type' => 'text',
                            'value' => 'bold text with ',
                            'bold' => true
                        ],
                        [
                            'type' => 'text',
                            'value' => 'italic',
                            'bold' => true,
                            'italic' => true
                        ],
                        [
                            'type' => 'text',
                            'value' => ' inside',
                            'bold' => true
                        ],
                        ['type' => 'text', 'value' => ' and a '],
                        [
                            'type' => 'link',
                            'url' => 'https://example.com',
                            'title' => 'Example Link',
                            'children' => [
                                ['type' => 'text', 'value' => 'link with '],
                                ['type' => 'text', 'value' => 'bold', 'bold' => true],
                                ['type' => 'text', 'value' => ' text']
                            ]
                        ],
                        ['type' => 'text', 'value' => '.']
                    ]
                ],
                [
                    'type' => 'list',
                    'listType' => 'unordered',
                    'children' => [
                        [
                            'type' => 'list-item',
                            'children' => [
                                ['type' => 'text', 'value' => 'First', 'bold' => true],
                                ['type' => 'text', 'value' => ' item with '],
                                ['type' => 'text', 'value' => 'emphasis', 'italic' => true]
                            ]
                        ],
                        [
                            'type' => 'list-item',
                            'children' => [
                                ['type' => 'text', 'value' => 'Second item with '],
                                [
                                    'type' => 'link',
                                    'url' => 'https://test.com',
                                    'title' => null,
                                    'children' => [
                                        ['type' => 'text', 'value' => 'nested '],
                                        ['type' => 'text', 'value' => 'italic', 'italic' => true],
                                        ['type' => 'text', 'value' => ' link']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'heading',
                    'level' => 2,
                    'children' => [
                        ['type' => 'text', 'value' => 'Subsection']
                    ]
                ],
                [
                    'type' => 'paragraph',
                    'children' => [
                        ['type' => 'text', 'value' => 'Final '],
                        ['type' => 'text', 'value' => 'paragraph', 'italic' => true],
                        ['type' => 'text', 'value' => ' with '],
                        ['type' => 'text', 'value' => 'mixed', 'bold' => true],
                        ['type' => 'text', 'value' => ' formatting.']
                    ]
                ]
            ]
        ], JSON_UNESCAPED_SLASHES);

        $this->assertEquals($expected, $result);
    }

    public function testShopifyDocumentationBoldAndItalic()
    {
        $result = HtmlToShopifyRichText::convert('<p><strong><em>This text is bolded and italicized.</em></strong></p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"text","value":"This text is bolded and italicized.","bold":true,"italic":true}]}]}';
        $this->assertEquals($expected, $result);

        // Test the reverse order (em inside strong)
        $result2 = HtmlToShopifyRichText::convert('<p><em><strong>This text is bolded and italicized.</strong></em></p>');
        $this->assertEquals($expected, $result2);
    }

    public function testShopifyDocumentationHeading()
    {
        $result = HtmlToShopifyRichText::convert('<h1>This is an H1 heading</h1>');
        $expected = '{"type":"root","children":[{"type":"heading","level":1,"children":[{"type":"text","value":"This is an H1 heading"}]}]}';
        $this->assertEquals($expected, $result);

        // Test all heading levels
        for ($level = 1; $level <= 6; $level++) {
            $html = sprintf('<h%d>This is an H%d heading</h%d>', $level, $level, $level);
            $result = HtmlToShopifyRichText::convert($html);
            $expected = sprintf(
                '{"type":"root","children":[{"type":"heading","level":%d,"children":[{"type":"text","value":"This is an H%d heading"}]}]}',
                $level,
                $level
            );
            $this->assertEquals($expected, $result, sprintf('Failed to convert H%d heading', $level));
        }

        // Test heading with formatting
        $result = HtmlToShopifyRichText::convert('<h1>This is a <strong>bold</strong> heading</h1>');
        $expected = '{"type":"root","children":[{"type":"heading","level":1,"children":[{"type":"text","value":"This is a "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" heading"}]}]}';
        $this->assertEquals($expected, $result);
    }

    public function testShopifyDocumentationLink()
    {
        $result = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="Link to example.com">This is a hyperlink</a></p>');
        $expected = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"Link to example.com","children":[{"type":"text","value":"This is a hyperlink"}]}]}]}';
        $this->assertEquals($expected, $result);

        // Test link with formatting inside
        $result2 = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="Link to example.com">This is a <strong>bold</strong> hyperlink</a></p>');
        $expected2 = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"Link to example.com","children":[{"type":"text","value":"This is a "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" hyperlink"}]}]}]}';
        $this->assertEquals($expected2, $result2);

        // Test link with multiple words in title
        $result3 = HtmlToShopifyRichText::convert('<p><a href="https://example.com" title="This is a link to example.com">Click here</a></p>');
        $expected3 = '{"type":"root","children":[{"type":"paragraph","children":[{"type":"link","url":"https://example.com","title":"This is a link to example.com","children":[{"type":"text","value":"Click here"}]}]}]}';
        $this->assertEquals($expected3, $result3);
    }

    public function testShopifyDocumentationOrderedList()
    {
        $result = HtmlToShopifyRichText::convert('<ol><li>This is the first list item.</li><li>This is the second list item.</li></ol>');
        $expected = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is the first list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is the second list item."}]}]}]}';
        $this->assertEquals($expected, $result);

        // Test ordered list with formatting
        $result2 = HtmlToShopifyRichText::convert('<ol><li>This is a <strong>bold</strong> list item.</li><li>This is an <em>italic</em> list item.</li></ol>');
        $expected2 = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is a "},{"type":"text","value":"bold","bold":true},{"type":"text","value":" list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is an "},{"type":"text","value":"italic","italic":true},{"type":"text","value":" list item."}]}]}]}';
        $this->assertEquals($expected2, $result2);

        // Test ordered list with links
        $result3 = HtmlToShopifyRichText::convert('<ol><li>This is a <a href="https://example.com">link</a> in a list item.</li><li>This is another list item.</li></ol>');
        $expected3 = '{"type":"root","children":[{"type":"list","listType":"ordered","children":[{"type":"list-item","children":[{"type":"text","value":"This is a "},{"type":"link","url":"https://example.com","title":null,"children":[{"type":"text","value":"link"}]},{"type":"text","value":" in a list item."}]},{"type":"list-item","children":[{"type":"text","value":"This is another list item."}]}]}]}';
        $this->assertEquals($expected3, $result3);
    }
} 