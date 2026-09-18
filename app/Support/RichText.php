<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Comment and description bodies are written in an editor and stored as HTML, so everything
 * arriving from a browser passes through here first. The allow list is the editor's own output
 * and nothing else: anything a person can produce by typing is kept, anything else is dropped.
 */
class RichText
{
    public static function sanitize(string $html): string
    {
        return trim(self::sanitizer()->sanitize($html));
    }

    /**
     * The readable version, used for search, for excerpts, and for reading slash commands, which
     * are written one per line and stay that way once the markup is gone.
     */
    public static function toText(string $html): string
    {
        $withBreaks = preg_replace(
            '#</(p|li|h1|h2|h3|blockquote|pre)>|<br\s*/?>#i',
            "\n",
            $html
        ) ?? $html;

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace("/\n{2,}/", "\n", $text) ?? $text);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('s')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('blockquote')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('a', ['href'])
            ->allowElement('span', ['data-mention', 'data-id'])
            ->allowAttribute('data-mention', ['span'])
            ->allowAttribute('data-id', ['span'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks();

        return new HtmlSanitizer($config);
    }
}
