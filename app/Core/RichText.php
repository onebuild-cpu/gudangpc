<?php
namespace App\Core;

use DOMDocument;
use DOMXPath;

/**
 * Cleans rich text HTML before it is printed in a public view.
 */
class RichText
{
    /**
     * Clean rich text HTML by stripping disallowed tags and attributes.
     */
    public static function clean(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td><a>';
        $html = strip_tags($html, $allowedTags);

        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*') as $element) {
            $isAnchor = $element->nodeName === 'a';
            $attributeNames = [];
            foreach ($element->attributes as $attribute) {
                $attributeNames[] = $attribute->nodeName;
            }
            foreach ($attributeNames as $attributeName) {
                if ($isAnchor && $attributeName === 'href') {
                    if (preg_match('~^https?://~i', $element->getAttribute('href')) !== 1) {
                        $element->removeAttribute($attributeName);
                    }
                } elseif (!$isAnchor || $attributeName !== 'href') {
                    $element->removeAttribute($attributeName);
                }
            }
            if ($isAnchor) {
                $element->setAttribute('rel', 'nofollow noopener');
            }
        }

        $rootDiv = $document->getElementsByTagName('div')->item(0);
        $innerHtml = '';
        foreach ($rootDiv->childNodes as $childNode) {
            $innerHtml .= $document->saveHTML($childNode);
        }

        return $innerHtml;
    }
}
