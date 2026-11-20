<?php

namespace Tests\Unit;

use App\Models\BusinessSetting;
use App\Rules\SafeLink;
use App\Support\Color;
use App\Support\Markdown;
use App\Support\Money;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupportTest extends TestCase
{
    protected function settings(array $attrs): BusinessSetting
    {
        return new BusinessSetting($attrs);
    }

    public function test_money_formats_common_currencies(): void
    {
        $this->assertSame('₦1,250,000', Money::format(1250000, $this->settings([])));
        $this->assertSame('$1,250.50', Money::format(1250.5, $this->settings(['currency_symbol' => '$', 'decimal_places' => 2])));
        $this->assertSame('£19.99', Money::format('19.99', $this->settings(['currency_symbol' => '£', 'decimal_places' => 2])));
        $this->assertSame('1.250,50 €', Money::format(1250.5, $this->settings(['currency_symbol' => '€', 'currency_position' => 'after', 'decimal_places' => 2, 'thousands_separator' => '.', 'decimal_separator' => ','])));
        $this->assertSame('KSh 5,000', Money::format(5000, $this->settings(['currency_symbol' => 'KSh'])));
        $this->assertNull(Money::format(null, $this->settings([])));
    }

    #[DataProvider('numbers')]
    public function test_whatsapp_number_normalisation(string $input, ?string $expected): void
    {
        $this->assertSame($expected, WhatsApp::normalize($input));
    }

    public static function numbers(): array
    {
        return [
            ['+234 801 234 5678', '2348012345678'],
            ['(234) 801-234-5678', '2348012345678'],
            ['002348012345678', '2348012345678'],
            ['+44 7700 900123', '447700900123'],
            ['08012345678', null], // local format without country code
            ['12345', null],
            ['', null],
        ];
    }

    public function test_whatsapp_link_encodes_message(): void
    {
        $s = $this->settings(['whatsapp_number' => '2348000000000', 'business_name' => 'A & B']);
        $this->assertSame('https://wa.me/2348000000000?text=Hi%20A%20%26%20B%3F', WhatsApp::link('Hi A & B?', $s));
        $this->assertNull(WhatsApp::link('x', $this->settings(['whatsapp_number' => null])));
    }

    public function test_markdown_strips_html_and_unsafe_links(): void
    {
        $html = Markdown::toHtml("<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1)) **ok**");
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('<strong>ok</strong>', $html);
    }

    public function test_color_contrast(): void
    {
        $this->assertSame('#FFFFFF', Color::contrast('#111827'));
        $this->assertSame('#111111', Color::contrast('#F4F1EC'));
        $this->assertSame('#123456', Color::sanitize('red', '#123456'));
    }

    public function test_safe_link_rule(): void
    {
        $ok = ['/products', '/categories/phones?x=1', '#about', 'https://example.com/a', 'whatsapp', ''];
        $bad = ['javascript:alert(1)', '//evil.com', 'data:text/html,hi', 'ftp://x.com', 'products'];

        foreach ($ok as $v) {
            $this->assertTrue(Validator::make(['l' => $v], ['l' => [new SafeLink]])->passes(), $v);
        }
        foreach ($bad as $v) {
            $this->assertFalse(Validator::make(['l' => $v], ['l' => [new SafeLink]])->passes(), $v);
        }
    }
}
