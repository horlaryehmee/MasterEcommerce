<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * Brand fonts for the storefront (Website Settings → Branding).
 *
 * The built-in font (Plus Jakarta Sans) is bundled with the site. Any other
 * choice is loaded from Google Fonts, so nothing has to be installed. Only the
 * weights each family actually has are requested; Google refuses the whole
 * stylesheet if one of them doesn't exist.
 */
class Fonts
{
    public const DEFAULT = 'Plus Jakarta Sans';

    /** family => [category, weights] */
    public const FAMILIES = [
        // Sans serif
        'Inter' => ['sans', [400, 500, 600, 700, 800]],
        'Poppins' => ['sans', [400, 500, 600, 700, 800]],
        'Montserrat' => ['sans', [400, 500, 600, 700, 800]],
        'DM Sans' => ['sans', [400, 500, 600, 700, 800]],
        'Manrope' => ['sans', [400, 500, 600, 700, 800]],
        'Outfit' => ['sans', [400, 500, 600, 700, 800]],
        'Figtree' => ['sans', [400, 500, 600, 700, 800]],
        'Urbanist' => ['sans', [400, 500, 600, 700, 800]],
        'Lato' => ['sans', [400, 700, 900]],
        'Open Sans' => ['sans', [400, 500, 600, 700, 800]],
        'Nunito' => ['sans', [400, 500, 600, 700, 800]],
        'Raleway' => ['sans', [400, 500, 600, 700, 800]],
        'Work Sans' => ['sans', [400, 500, 600, 700, 800]],
        'Jost' => ['sans', [400, 500, 600, 700, 800]],
        'Rubik' => ['sans', [400, 500, 600, 700, 800]],
        'Mulish' => ['sans', [400, 500, 600, 700, 800]],
        'Karla' => ['sans', [400, 500, 600, 700, 800]],
        'Lexend' => ['sans', [400, 500, 600, 700, 800]],
        'Josefin Sans' => ['sans', [400, 500, 600, 700]],
        'Quicksand' => ['sans', [400, 500, 600, 700]],
        'Tenor Sans' => ['sans', [400]],
        // Serif
        'Cormorant Garamond' => ['serif', [400, 500, 600, 700]],
        'Cormorant' => ['serif', [400, 500, 600, 700]],
        'Playfair Display' => ['serif', [400, 500, 600, 700, 800]],
        'Lora' => ['serif', [400, 500, 600, 700]],
        'EB Garamond' => ['serif', [400, 500, 600, 700, 800]],
        'Libre Baskerville' => ['serif', [400, 700]],
        'Merriweather' => ['serif', [400, 700, 900]],
        'Fraunces' => ['serif', [400, 500, 600, 700, 800]],
        'Bodoni Moda' => ['serif', [400, 500, 600, 700, 800]],
        'Cinzel' => ['serif', [400, 500, 600, 700, 800]],
        'DM Serif Display' => ['serif', [400]],
        'Marcellus' => ['serif', [400]],
        'Prata' => ['serif', [400]],
        'Gilda Display' => ['serif', [400]],
        'Italiana' => ['serif', [400]],
        'Forum' => ['serif', [400]],
        // Display & script (titles)
        'Oswald' => ['display', [400, 500, 600, 700]],
        'Bebas Neue' => ['display', [400]],
        'Abril Fatface' => ['display', [400]],
        'Dancing Script' => ['handwriting', [400, 500, 600, 700]],
        'Great Vibes' => ['handwriting', [400]],
        'Pacifico' => ['handwriting', [400]],
    ];

    public const CATEGORY_LABELS = ['sans' => 'Sans serif', 'serif' => 'Serif', 'display' => 'Display', 'handwriting' => 'Script'];

    protected const FALLBACKS = [
        'sans' => "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif",
        'serif' => "ui-serif, Georgia, 'Times New Roman', serif",
        'display' => "ui-sans-serif, system-ui, sans-serif",
        'handwriting' => 'cursive',
    ];

    public const HEADING_WEIGHTS = [400 => 'Regular', 500 => 'Medium', 600 => 'Semibold', 700 => 'Bold', 800 => 'Extra bold'];

    public const BUTTON_SHAPES = ['pill' => 'Pill', 'rounded' => 'Rounded', 'square' => 'Square'];

    protected const BUTTON_RADIUS = ['pill' => '9999px', 'rounded' => '0.5rem', 'square' => '0'];

    /** A Google Fonts family name someone typed: letters, digits and spaces only (it ends up in CSS and a URL). */
    public static function validName(?string $name): bool
    {
        return is_string($name) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,59}$/', $name) === 1;
    }

    public static function isDefault(?string $family): bool
    {
        return blank($family) || $family === self::DEFAULT;
    }

    public static function category(string $family): string
    {
        return self::FAMILIES[$family][0] ?? 'sans';
    }

    /** CSS font-family value with sensible fallbacks. */
    public static function stack(string $family): string
    {
        $name = $family === self::DEFAULT ? 'Plus Jakarta Sans Variable' : $family;

        return "'{$name}', ".self::FALLBACKS[self::category($family)];
    }

    /**
     * Google Fonts stylesheet for the chosen families, or null when only the
     * built-in font is used. Unlisted (typed) families get the regular weight
     * only, since their weights aren't known.
     */
    public static function stylesheetUrl(array $families): ?string
    {
        $params = collect($families)
            ->filter(fn ($f) => ! self::isDefault($f) && self::validName($f))
            ->unique()
            ->map(function (string $f) {
                $family = str_replace(' ', '+', $f);
                $weights = self::FAMILIES[$f][1] ?? null;

                return 'family='.$family.($weights && $weights !== [400] ? ':wght@'.implode(';', $weights) : '');
            });

        return $params->isEmpty() ? null : 'https://fonts.googleapis.com/css2?'.$params->implode('&').'&display=swap';
    }

    /**
     * Everything the page <head> needs for this installation's branding: the
     * font stylesheet and the CSS variables (absent values keep the defaults).
     *
     * @return array{stylesheet: ?string, vars: array<string, string>}
     */
    public static function forSettings(BusinessSetting $s): array
    {
        $vars = [];
        if (! self::isDefault($s->body_font) && self::validName($s->body_font)) {
            $vars['--brand-font-body'] = self::stack($s->body_font);
        }
        if (! self::isDefault($s->heading_font) && self::validName($s->heading_font)) {
            $vars['--brand-font-heading'] = self::stack($s->heading_font);
        }
        if (isset(self::HEADING_WEIGHTS[(int) $s->heading_weight])) {
            $vars['--brand-heading-weight'] = (string) (int) $s->heading_weight;
        }
        if (isset(self::BUTTON_RADIUS[$s->button_shape])) {
            $vars['--btn-radius'] = self::BUTTON_RADIUS[$s->button_shape];
        }

        return ['stylesheet' => self::stylesheetUrl([$s->heading_font, $s->body_font]), 'vars' => $vars];
    }

    /** Font list for the admin picker. */
    public static function options(): array
    {
        return collect(self::FAMILIES)->map(fn ($f, $name) => [
            'value' => $name,
            'category' => self::CATEGORY_LABELS[$f[0]],
            'weights' => $f[1],
            'url' => self::stylesheetUrl([$name]),
        ])->values()->all();
    }
}
