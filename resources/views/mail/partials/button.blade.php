{{-- Bulletproof button (VML for Outlook on Windows). Needs $url, $label, $brand. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px auto 4px auto;">
    <tr>
        <td align="center" bgcolor="{{ $brand['color'] }}" style="border-radius:8px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="{{ $url }}" style="height:46px; v-text-anchor:middle; width:240px;" arcsize="18%" stroke="f" fillcolor="{{ $brand['color'] }}">
                <center style="color:{{ $brand['on_color'] }}; font-family:Arial, sans-serif; font-size:15px; font-weight:bold;">{{ $label }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:20px; color:{{ $brand['on_color'] }}; text-decoration:none; border-radius:8px; background-color:{{ $brand['color'] }};">{{ $label }}</a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
