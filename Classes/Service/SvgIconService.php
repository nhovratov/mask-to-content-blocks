<?php

declare(strict_types=1);

namespace NH\MaskToContentBlocks\Service;

readonly class SvgIconService
{
    public function generateSvgIcon(string $elementKey, string $elementColor, int $size = 40, int $radius = 1): string
    {
        $elementColor = trim($elementColor);
        if ($elementColor === '') {
            $elementColor = '#000000';
        }
        $validColor = $this->validateColor($elementColor);
        if ($validColor === false) {
            throw new \InvalidArgumentException('Invalid element color: ' . $elementColor, 1767376315);
        }
        $textColor = '#FFFFFF';

        $svgIcon = $this->createSvgIcon(
            $elementKey,
            $elementColor,
            $size,
            $radius,
            $textColor
        );
        return $svgIcon;
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
     role="img" aria-label="$label">
  <rect x="0" y="0" width="$size" height="$size" rx="$radius" fill="$bgColorEscaped" />
  <text x="$center" y="$baseline" text-anchor="middle"
        font-family="'Source Sans Pro', Verdana, Arial, Helvetica, sans-serif"
        font-size="$fontSize" font-weight="700" fill="$textColorEscaped">$label</text>
</svg>
SVG;
    }

    protected function validateColor(string $raw): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $raw) === 1;
    }
}
