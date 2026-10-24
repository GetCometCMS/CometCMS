<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * Section shape dividers: fixed SVG paths, so layouts only
 * store a shape's name. The admin builder draws its shape pickers from the
 * same paths (see spec()).
 */
final class Shapes
{
    /**
     * Dividers along a section's top edge, filled from the edge inwards in a
     * 1200×120 box that stretches to the section's width and the chosen height.
     */
    public const DIVIDER_WIDTH = 1200;
    public const DIVIDER_HEIGHT = 120;

    private const DIVIDERS = [
        'wave' => 'M0,0V64C200,124 400,124 600,64S1000,4 1200,64V0Z',
        'curve' => 'M0,0Q600,240 1200,0Z',
        'slope' => 'M0,0V120L1200,0Z',
        'triangle' => 'M0,0L600,120L1200,0Z',
        'double_wave' => 'M0,0V60C100,-20 200,-20 300,60S500,140 600,60S800,-20 900,60S1100,140 1200,60V0Z',
        'asymmetric_curve' => 'M0,0V120C240,120 300,0 1200,0Z',
        'mountains' => 'M0,0V80L180,25L360,100L540,40L720,120L960,20L1200,80V0Z',
        'steps' => 'M0,0V120H240V96H480V72H720V48H960V24H1200V0Z',
        'scallops' => 'M0,0V20Q100,220 200,20Q300,220 400,20Q500,220 600,20Q700,220 800,20Q900,220 1000,20Q1100,220 1200,20V0Z',
        'arrow' => 'M0,0V35H440L600,120L760,35H1200V0Z',
    ];

    /** Teeth of the zigzag divider. */
    private const ZIGZAG_TEETH = 24;

    /** @return list<string> */
    public static function dividers(): array
    {
        return ['none', ...array_keys(self::DIVIDERS), 'zigzag'];
    }

    public static function dividerPath(string $shape): string
    {
        if ($shape !== 'zigzag') {
            return self::DIVIDERS[$shape] ?? '';
        }
        $tooth = self::DIVIDER_WIDTH / self::ZIGZAG_TEETH;
        $path = 'M0,0';
        for ($i = 0; $i < self::ZIGZAG_TEETH; $i++) {
            $path .= 'L' . ($i * $tooth + $tooth / 2) . ',' . self::DIVIDER_HEIGHT . 'L' . (($i + 1) * $tooth) . ',0';
        }

        return $path . 'Z';
    }

    /**
     * A divider's SVG, filled with the current color. Inverted dividers fill
     * the rest of the box instead, turned towards the edge; flipped ones are
     * mirrored sideways. The SVG mirrors the path itself: a mirrored element
     * would let browsers bleed its far edge into a hairline.
     */
    public static function dividerSvg(string $shape, string $edge, bool $flip, bool $invert): string
    {
        $path = self::dividerPath($shape);
        if ($path === '') {
            return '';
        }
        [$width, $height] = [self::DIVIDER_WIDTH, self::DIVIDER_HEIGHT];
        if ($invert) {
            $path = 'M0,0H' . $width . 'V' . $height . 'H0Z' . $path;
        }
        $x = $flip ? -1 : 1;
        $y = ($edge === 'bottom') !== $invert ? -1 : 1;
        $transform = $x === 1 && $y === 1 ? '' : ' transform="matrix(' . $x . ' 0 0 ' . $y . ' ' . ($x === -1 ? $width : 0) . ' ' . ($y === -1 ? $height : 0) . ')"';

        return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" focusable="false">'
            . '<path d="' . $path . '"' . $transform . ' fill="currentColor" fill-rule="evenodd"/></svg>';
    }

    /** Paths and boxes the admin builder draws its shape pickers with. */
    public static function spec(): array
    {
        $dividers = [];
        foreach (self::dividers() as $shape) {
            if ($shape !== 'none') $dividers[$shape] = self::dividerPath($shape);
        }

        return [
            'divider' => ['width' => self::DIVIDER_WIDTH, 'height' => self::DIVIDER_HEIGHT, 'paths' => $dividers],
        ];
    }
}
