<?php
/**
 * Markdown - простой и безопасный Markdown renderer
 * Поддерживает только базовый синтаксис, блокирует XSS
 */

declare(strict_types=1);

namespace App\Helpers;

class Markdown
{
    /**
     * Конвертация Markdown в HTML с санитизацией
     */
    public static function parse(string $text): string
    {
        // Экранируем весь HTML сначала
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Блокировка javascript: и on* атрибутов (на случай если кто-то найдёт способ их вставить)
        $text = self::blockDangerousPatterns($text);

        // Code blocks (должны быть до inline code)
        $text = self::parseCodeBlocks($text);

        // Blockquotes
        $text = self::parseBlockquotes($text);

        // Headings (должны быть перед списками)
        $text = self::parseHeadings($text);

        // Lists
        $text = self::parseLists($text);

        // Bold и Italic
        $text = self::parseBoldItalic($text);

        // Links
        $text = self::parseLinks($text);

        // Inline code
        $text = self::parseInlineCode($text);

        // Line breaks
        $text = self::parseLineBreaks($text);

        return $text;
    }

    /**
     * Блокировка опасных паттернов
     */
    private static function blockDangerousPatterns(string $text): string
    {
        // Блокируем javascript: в ссылках (дополнительная защита)
        $text = preg_replace('/&lt;a[^&]*href=[&quot;\']?javascript:[^&]*[&quot;\']?[^&]*&gt;/i', '', $text);
        
        // Блокируем on* атрибуты
        $text = preg_replace('/\s*on\w+\s*=\s*[\"\'\"][^\"\'<>]*[\"\'\"]/', '', $text);
        
        return $text;
    }

    /**
     * Парсинг code блоков ```code```
     */
    private static function parseCodeBlocks(string $text): string
    {
        return preg_replace_callback(
            '/```(.*?)```/s',
            function ($matches) {
                $code = htmlspecialchars($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return '<pre><code>' . $code . '</code></pre>';
            },
            $text
        );
    }

    /**
     * Парсинг blockquotes > text
     */
    private static function parseBlockquotes(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inQuote = false;

        foreach ($lines as $line) {
            if (preg_match('/^&gt;\s?(.*)$/', $line, $matches)) {
                if (!$inQuote) {
                    $result[] = '<blockquote>';
                    $inQuote = true;
                }
                $result[] = $matches[1];
            } else {
                if ($inQuote) {
                    $result[] = '</blockquote>';
                    $inQuote = false;
                }
                $result[] = $line;
            }
        }

        if ($inQuote) {
            $result[] = '</blockquote>';
        }

        return implode("\n", $result);
    }

    /**
     * Парсинг заголовков # ## ###
     */
    private static function parseHeadings(string $text): string
    {
        $text = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $text);
        return $text;
    }

    /**
     * Парсинг списков - и 1.
     */
    private static function parseLists(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inUnorderedList = false;
        $inOrderedList = false;

        foreach ($lines as $line) {
            // Unordered list
            if (preg_match('/^-\s+(.+)$/', $line, $matches)) {
                if (!$inUnorderedList) {
                    if ($inOrderedList) {
                        $result[] = '</ol>';
                        $inOrderedList = false;
                    }
                    $result[] = '<ul>';
                    $inUnorderedList = true;
                }
                $result[] = '<li>' . $matches[1] . '</li>';
            }
            // Ordered list
            elseif (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                if (!$inOrderedList) {
                    if ($inUnorderedList) {
                        $result[] = '</ul>';
                        $inUnorderedList = false;
                    }
                    $result[] = '<ol>';
                    $inOrderedList = true;
                }
                $result[] = '<li>' . $matches[1] . '</li>';
            } else {
                if ($inUnorderedList) {
                    $result[] = '</ul>';
                    $inUnorderedList = false;
                }
                if ($inOrderedList) {
                    $result[] = '</ol>';
                    $inOrderedList = false;
                }
                $result[] = $line;
            }
        }

        if ($inUnorderedList) {
            $result[] = '</ul>';
        }
        if ($inOrderedList) {
            $result[] = '</ol>';
        }

        return implode("\n", $result);
    }

    /**
     * Парсинг **bold** и *italic*
     */
    private static function parseBoldItalic(string $text): string
    {
        // Bold **text**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        
        // Italic *text* (но не внутри слов для избежания ложных срабатываний)
        $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
        
        return $text;
    }

    /**
     * Парсинг ссылок [text](url)
     */
    private static function parseLinks(string $text): string
    {
        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function ($matches) {
                $linkText = $matches[1];
                $url = htmlspecialchars($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                // Дополнительная проверка URL
                if (stripos($url, 'javascript:') !== false || stripos($url, 'data:') !== false) {
                    return $linkText; // Возвращаем просто текст без ссылки
                }
                
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $linkText . '</a>';
            },
            $text
        );
    }

    /**
     * Парсинг inline code `code`
     */
    private static function parseInlineCode(string $text): string
    {
        return preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    }

    /**
     * Парсинг переносов строк
     */
    private static function parseLineBreaks(string $text): string
    {
        // Double line break = paragraph
        $text = preg_replace('/\n\n+/', '</p><p>', $text);
        
        // Single line break = <br>
        $text = preg_replace('/(?<!<\/(?:p|ul|ol|li|h[1-6]|blockquote|pre|div)>)\n/', '<br>', $text);
        
        // Wrap in paragraphs if not already wrapped
        if (!str_starts_with($text, '<p>')) {
            $text = '<p>' . $text . '</p>';
        }
        
        return $text;
    }
}
