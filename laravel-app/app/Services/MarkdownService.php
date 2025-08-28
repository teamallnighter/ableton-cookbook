<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Output\RenderedContent;

class MarkdownService
{
    private MarkdownConverter $converter;
    
    /**
     * Allowed HTML tags for sanitization
     */
    private array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'strike', 'del',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span',
        'hr'
    ];
    
    /**
     * Allowed attributes for specific tags
     */
    private array $allowedAttributes = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'class'],
        'div' => ['class', 'id'],
        'span' => ['class', 'id'],
        'table' => ['class'],
        'th' => ['class', 'scope'],
        'td' => ['class', 'colspan', 'rowspan'],
        'code' => ['class'],
        'pre' => ['class'],
        'blockquote' => ['cite']
    ];

    public function __construct()
    {
        $this->converter = $this->createConverter();
    }

    /**
     * Parse markdown to HTML with security measures and rich media support
     */
    public function parseToHtml(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        // Pre-process for rich media embedding
        $markdown = $this->processRichMedia($markdown);

        // Parse markdown to HTML
        $result = $this->converter->convert($markdown);
        $html = (string) $result;

        // Additional security sanitization
        return $this->sanitizeHtml($html);
    }

    /**
     * Strip markdown formatting and return plain text
     */
    public function stripMarkdown(string $markdown): string
    {
        if (empty($markdown)) {
            return '';
        }

        // Convert to HTML first, then strip tags for cleaner text
        $html = $this->parseToHtml($markdown);
        $plainText = strip_tags($html);

        // Clean up extra whitespace
        return trim(preg_replace('/\s+/', ' ', $plainText));
    }

    /**
     * Get a preview of markdown content as plain text
     */
    public function getPreview(string $markdown, int $length = 200): string
    {
        $plainText = $this->stripMarkdown($markdown);
        
        if (strlen($plainText) <= $length) {
            return $plainText;
        }

        // Truncate at word boundary
        $truncated = substr($plainText, 0, $length);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false && $lastSpace > $length * 0.75) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    /**
     * Validate markdown content for potential issues
     */
    public function validateContent(string $markdown): array
    {
        $issues = [];

        // Check for extremely long content
        if (strlen($markdown) > 100000) { // 100KB limit
            $issues[] = 'Content is too long. Please keep articles under 100KB.';
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script\b/i' => 'Script tags are not allowed',
            '/<iframe\b/i' => 'Iframe tags are not allowed',
            '/javascript:/i' => 'JavaScript URLs are not allowed',
            '/on\w+\s*=/i' => 'Event handlers are not allowed',
        ];

        foreach ($suspiciousPatterns as $pattern => $message) {
            if (preg_match($pattern, $markdown)) {
                $issues[] = $message;
            }
        }

        return $issues;
    }

    /**
     * Create the markdown converter with appropriate extensions and configuration
     */
    private function createConverter(): MarkdownConverter
    {
        // Configure environment with security-focused settings
        $config = [
            'html_input' => 'escape', // Escape HTML input for security
            'allow_unsafe_links' => false, // Disallow unsafe links
            'max_nesting_level' => 50, // Prevent deeply nested structures
            'commonmark' => [
                'enable_em' => true,
                'enable_strong' => true,
                'use_asterisk' => true,
                'use_underscore' => true,
                'unordered_list_markers' => ['-', '*', '+'],
            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => ['class' => 'table-responsive'],
                ],
            ],
        ];

        // Create environment
        $environment = new Environment($config);
        
        // Add core markdown support
        $environment->addExtension(new CommonMarkCoreExtension());
        
        // Add GitHub Flavored Markdown for tables, strikethrough, etc.
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        return new MarkdownConverter($environment);
    }

    /**
     * Legacy sanitizeHtml method - now calls comprehensive sanitization
     */
    private function sanitizeHtml(string $html): string
    {
        return $this->comprehensiveSanitizeHtml($html);
    }

    /**
     * Comprehensive HTML sanitization using DOMDocument for better security
     */
    private function comprehensiveSanitizeHtml(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }
        
        // Create DOMDocument for safer HTML manipulation
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        
        // Suppress errors for invalid HTML
        libxml_use_internal_errors(true);
        
        // Load HTML with proper encoding handling
        $htmlToProcess = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($htmlToProcess, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Get the body element
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        
        // Sanitize all elements recursively
        $this->sanitizeElement($body, $dom);
        
        // Extract only the body content
        $sanitizedHtml = '';
        foreach ($body->childNodes as $child) {
            $sanitizedHtml .= $dom->saveHTML($child);
        }
        
        // Clear libxml errors
        libxml_clear_errors();
        
        return $sanitizedHtml;
    }
    
    /**
     * Recursively sanitize DOM elements
     */
    private function sanitizeElement(\DOMNode $node, \DOMDocument $dom): void
    {
        $nodesToRemove = [];
        $nodesToReplace = [];
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);
                
                // Check if tag is allowed
                if (!in_array($tagName, $this->allowedTags)) {
                    // Replace with text content
                    if ($child->textContent) {
                        $textNode = $dom->createTextNode($child->textContent);
                        $nodesToReplace[] = ['old' => $child, 'new' => $textNode];
                    } else {
                        $nodesToRemove[] = $child;
                    }
                    continue;
                }
                
                // Sanitize attributes
                $this->sanitizeElementAttributes($child, $tagName);
                
                // Recursively sanitize child elements
                $this->sanitizeElement($child, $dom);
            }
        }
        
        // Remove or replace nodes
        foreach ($nodesToRemove as $nodeToRemove) {
            if ($nodeToRemove->parentNode) {
                $nodeToRemove->parentNode->removeChild($nodeToRemove);
            }
        }
        
        foreach ($nodesToReplace as $replacement) {
            if ($replacement['old']->parentNode) {
                $replacement['old']->parentNode->replaceChild(
                    $replacement['new'], 
                    $replacement['old']
                );
            }
        }
    }

    /**
     * Check if a URL is allowed (basic security check)
     */
    private function isAllowedUrl(string $url): bool
    {
        // Block javascript:, data:, and other dangerous protocols
        $disallowedProtocols = ['javascript:', 'data:', 'vbscript:', 'file:', 'ftp:'];
        
        $lowerUrl = strtolower(trim($url));
        
        foreach ($disallowedProtocols as $protocol) {
            if (strpos($lowerUrl, $protocol) === 0) {
                return false;
            }
        }

        // Allow relative URLs, http, and https
        // Also allow YouTube and SoundCloud embeds
        if (preg_match('/^https?:\/\/(www\.)?(youtube\.com\/embed\/|soundcloud\.com\/)/i', $url)) {
            return true;
        }
        
        return preg_match('/^(https?:\/\/|\/|\?|#|[a-zA-Z0-9])/i', $url);
    }

    /**
     * Process rich media embedding before markdown parsing
     */
    private function processRichMedia(string $markdown): string
    {
        // Process YouTube embeds
        $markdown = $this->processYouTubeEmbeds($markdown);
        
        // Process SoundCloud embeds
        $markdown = $this->processSoundCloudEmbeds($markdown);
        
        // Process code blocks with syntax highlighting
        $markdown = $this->processCodeBlocks($markdown);
        
        return $markdown;
    }

    /**
     * Convert YouTube URLs to embedded iframes
     */
    private function processYouTubeEmbeds(string $markdown): string
    {
        // Pattern to match YouTube links in markdown link format
        $pattern = '/\[([^\]]+)\]\((https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)[^\)]*)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $videoId = $this->extractYouTubeId($matches[2]);
            if (!$videoId) {
                return $matches[0]; // Return original if can't extract ID
            }
            
            return $this->generateYouTubeEmbed($videoId);
        }, $markdown);
    }

    /**
     * Convert SoundCloud URLs to embedded players
     */
    private function processSoundCloudEmbeds(string $markdown): string
    {
        // Pattern to match SoundCloud links in markdown link format
        $pattern = '/\[SoundCloud\]\((https:\/\/soundcloud\.com\/[^\)]+)\)/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            return $this->generateSoundCloudEmbed($matches[1]);
        }, $markdown);
    }

    /**
     * Enhanced code block processing with syntax highlighting classes
     */
    private function processCodeBlocks(string $markdown): string
    {
        // This will be handled by the CommonMark extensions
        // but we can add additional processing here if needed
        return $markdown;
    }

    /**
     * Extract YouTube video ID from various YouTube URL formats
     */
    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/i',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/i',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }

    /**
     * Generate YouTube embed HTML
     */
    private function generateYouTubeEmbed(string $videoId): string
    {
        return sprintf(
            '<div class="youtube-embed my-4"><iframe width="560" height="315" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>',
            htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generate SoundCloud embed HTML
     * Note: This is a simplified version. In production, you'd want to use SoundCloud's oEmbed API
     */
    private function generateSoundCloudEmbed(string $url): string
    {
        // Encode the URL for the SoundCloud embed API
        $encodedUrl = urlencode($url);
        $embedUrl = "https://w.soundcloud.com/player/?url={$encodedUrl}&color=%23ff5500&auto_play=false&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true";
        
        return sprintf(
            '<div class="soundcloud-embed my-4"><iframe width="100%%" height="166" scrolling="no" frameborder="no" allow="autoplay" src="%s"></iframe></div>',
            htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate embedded media URLs for security
     */
    public function validateEmbeddedMedia(string $markdown): array
    {
        $issues = [];
        
        // Check for suspicious embedded content
        if (preg_match('/<iframe[^>]+src=["\']([^"\'>]+)["\'][^>]*>/i', $markdown, $matches)) {
            $src = $matches[1];
            if (!$this->isAllowedEmbedUrl($src)) {
                $issues[] = 'Embedded media from unauthorized domains detected';
            }
        }
        
        return $issues;
    }

    /**
     * Check if an embed URL is from an allowed domain
     */
    private function isAllowedEmbedUrl(string $url): bool
    {
        $allowedDomains = [
            'youtube.com',
            'www.youtube.com',
            'soundcloud.com',
            'w.soundcloud.com',
        ];
        
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return false;
        }
        
        return in_array(strtolower($parsed['host']), $allowedDomains);
    }

    /**
     * Sanitize element attributes based on allowed attributes for each tag
     */
    private function sanitizeElementAttributes(\DOMElement $element, string $tagName): void
    {
        $allowedAttrs = $this->allowedAttributes[$tagName] ?? [];
        
        // Get all attributes to check
        $attributesToRemove = [];
        foreach ($element->attributes as $attr) {
            $attrName = strtolower($attr->name);
            
            if (!in_array($attrName, $allowedAttrs)) {
                $attributesToRemove[] = $attrName;
                continue;
            }
            
            // Additional validation for specific attributes
            switch ($attrName) {
                case 'href':
                case 'src':
                    if (!$this->isAllowedUrl($attr->value)) {
                        $attributesToRemove[] = $attrName;
                    }
                    break;
                    
                case 'target':
                    // Only allow _blank for external links
                    if (!in_array($attr->value, ['_blank', '_self'])) {
                        $attributesToRemove[] = $attrName;
                    }
                    break;
                    
                case 'rel':
                    // Sanitize rel attribute values
                    $allowedRelValues = ['nofollow', 'noopener', 'noreferrer', 'external'];
                    $relValues = array_filter(explode(' ', strtolower($attr->value)));
                    $cleanRelValues = array_intersect($relValues, $allowedRelValues);
                    
                    if (empty($cleanRelValues)) {
                        $attributesToRemove[] = $attrName;
                    } else {
                        $element->setAttribute($attrName, implode(' ', $cleanRelValues));
                    }
                    break;
                    
                case 'class':
                    // Basic class name validation (alphanumeric, hyphens, underscores)
                    if (!preg_match('/^[a-zA-Z0-9\s_-]*$/', $attr->value)) {
                        $attributesToRemove[] = $attrName;
                    }
                    break;
            }
        }
        
        // Remove disallowed attributes
        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }
    
    /**
     * Get reading time estimation in minutes
     */
    public function getReadingTime(string $markdown): int
    {
        $plainText = $this->stripMarkdown($markdown);
        $wordCount = str_word_count($plainText);
        
        // Average reading speed: 200-250 words per minute
        $readingSpeed = 225;
        
        return max(1, ceil($wordCount / $readingSpeed));
    }

    /**
     * Extract headings from markdown for table of contents
     */
    public function extractHeadings(string $markdown): array
    {
        $headings = [];
        $lines = explode("\n", $markdown);
        
        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', trim($line), $matches)) {
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $slug = $this->generateSlug($text);
                
                $headings[] = [
                    'level' => $level,
                    'text' => $text,
                    'slug' => $slug
                ];
            }
        }
        
        return $headings;
    }

    /**
     * Generate URL-safe slug from text
     */
    private function generateSlug(string $text): string
    {
        // Remove special characters and convert to lowercase
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
        
        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        return trim($slug, '-');
    }
}