# HtmlToShopifyRichText

A PHP package that converts HTML content to Shopify's Rich Text format. This package provides a simple and reliable way to transform HTML content into the JSON structure that Shopify's Rich Text editor expects.

## Features

- Converts common HTML elements to Shopify Rich Text format
- Supports headings (h1-h6)
- Handles paragraphs, lists (ordered and unordered)
- Processes inline elements (bold, italic, links)
- Sanitizes HTML input
- Validates HTML structure
- Handles whitespace normalization
- Provides detailed error handling

## Installation

You can install the package via Composer:

```bash
composer require webfoo/html-to-shopify-rich-text
```

## Usage

```php
use Webfoo\HtmlToShopifyRichText\HtmlToShopifyRichText;

$html = '<h1>Hello World</h1><p>This is a <strong>test</strong> paragraph.</p>';
$richText = HtmlToShopifyRichText::convert($html);

// $richText will contain the JSON representation of the Shopify Rich Text
```

### Supported HTML Elements

- Headings: `<h1>` through `<h6>`
- Paragraphs: `<p>`
- Lists: `<ul>`, `<ol>`, `<li>`
- Links: `<a href="...">`
- Bold: `<strong>`, `<b>`
- Italic: `<em>`, `<i>`

## Error Handling

The package throws specific exceptions for different error scenarios:

- `InvalidHtmlException`: Thrown when the HTML is invalid or cannot be parsed
- `ConversionException`: Thrown when the conversion process fails
- `JsonEncodingException`: Thrown when the JSON encoding fails

Example error handling:

```php
try {
    $richText = HtmlToShopifyRichText::convert($html);
} catch (InvalidHtmlException $e) {
    // Handle invalid HTML
} catch (ConversionException $e) {
    // Handle conversion errors
} catch (JsonEncodingException $e) {
    // Handle JSON encoding errors
}
```

## Requirements

- PHP 8.1 or higher
- Composer
- Required dependencies:
  - `symfony/dom-crawler`
  - `ezyang/htmlpurifier`

## License

This package is open-sourced software licensed under the MIT license.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. 