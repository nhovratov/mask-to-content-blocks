<?php

declare(strict_types=1);

namespace NH\MaskToContentBlocks\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class SvgIconService
{
    public function generateSvgIcon(string $elementKey, string $elementColor, string $targetPath, int $size = 40, int $radius = 1): bool
    {
        $bgColor = $this->normalizeColor($elementColor);
        $textColor = '#FFFFFF';

        $svgIcon = $this->createSvgIcon(
            $elementKey,
            $bgColor,
            $size,
            $radius,
            $textColor
        );

        $directory = dirname($targetPath);
        if (!is_dir($directory) && !GeneralUtility::mkdir($directory)) {
            return false;
        }

        return file_put_contents($targetPath, $svgIcon) !== false;
    }

    protected function createSvgIcon(string $letter, string $bgColor, int $size, int $radius, string $textColor): string
    {
        $letter = strtoupper(mb_substr(trim($letter), 0, 1));
        if ($letter === '') {
            $letter = '?';
        }

        $fontSize = (int)round($size * 0.75);
        $center = $size / 2.2;
        $baseline = $center + ($fontSize * 0.35);

        $label = htmlspecialchars($letter, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $bgColorEscaped = htmlspecialchars($bgColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $textColorEscaped = htmlspecialchars($textColor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg"
     width="$size" height="$size" viewBox="0 0 $size $size"
     role="img" aria-label="{$label}">
  <rect x="0" y="0" width="$size" height="$size" rx="$radius" fill="{$bgColorEscaped}" />
  <text x="$center" y="$baseline" text-anchor="middle"
        font-family="'Source Sans Pro', Verdana, Arial, Helvetica, sans-serif"
        font-size="$fontSize" font-weight="700" fill="{$textColorEscaped}">{$label}</text>
</svg>
SVG;
    }

    protected function normalizeColor(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $hex = $this->normalizeHex($raw);
        if ($hex !== null) {
            return $hex;
        }

        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(0|1|0?\.\d+))?\s*\)$/i', $raw)) {
            return $raw;
        }

        return null;
    }

    protected function normalizeHex(string $hex): ?string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }

        if ($hex[0] === '#') {
            $hex = substr($hex, 1);
        }

        if (!preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $hex)) {
            return null;
        }
        return '#' . strtoupper($hex);
    }
}
