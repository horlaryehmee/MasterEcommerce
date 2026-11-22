{{--
    Branded HTML email layout. Table-based with inline styles so it renders the
    same in Gmail, Outlook (Windows/Mac/web), Apple Mail and Yahoo, on phones and
    desktops. Sections: @section('preheader'), @section('content').
--}}
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subjectLine ?? $brand['name'] }}</title>
    <!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
        img { border: 0; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: {{ $brand['color'] }}; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px { padding-left: 20px !important; padding-right: 20px !important; }
            .stack { display: block !important; width: 100% !important; padding-left: 0 !important; padding-right: 0 !important; }
            .stack-gap { padding-bottom: 16px !important; }
            .h1 { font-size: 22px !important; line-height: 30px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5;">
    {{-- Preview text shown next to the subject in the inbox. --}}
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#f4f4f5; opacity:0;">
        @yield('preheader')&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">
                    {{-- Brand header --}}
                    <tr>
                        <td align="center" style="padding:0 0 20px 0;">
                            <a href="{{ $brand['website'] }}" target="_blank" style="text-decoration:none;">
                                @if ($brand['logo'])
                                    <img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" height="48" style="display:block; height:48px; width:auto; max-width:220px; border:0;">
                                @else
                                    <span style="font-family:Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold; color:#18181b; letter-spacing:-0.3px;">{{ $brand['name'] }}</span>
                                @endif
                            </a>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff; border-radius:12px; border-top:4px solid {{ $brand['color'] }};">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td class="px" style="padding:36px 40px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:24px; color:#3f3f46;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" class="px" style="padding:24px 40px 8px 40px; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:19px; color:#71717a;">
                            <strong style="color:#3f3f46;">{{ $brand['name'] }}</strong><br>
                            @if ($brand['address'])
                                {{ $brand['address'] }}<br>
                            @endif
                            @if ($brand['phone'])
                                {{ $brand['phone'] }}@if ($brand['email']) &nbsp;·&nbsp; @endif
                            @endif
                            @if ($brand['email'])
                                <a href="mailto:{{ $brand['email'] }}" style="color:#71717a; text-decoration:underline;">{{ $brand['email'] }}</a>
                            @endif
                            @if ($brand['phone'] || $brand['email'])<br>@endif
                            <a href="{{ $brand['website'] }}" target="_blank" style="color:#71717a; text-decoration:underline;">{{ $brand['website_label'] }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" class="px" style="padding:8px 40px 0 40px; font-family:Arial, Helvetica, sans-serif; font-size:11px; line-height:17px; color:#a1a1aa;">
                            @yield('footnote', 'You received this email because of an order placed on our website.')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
