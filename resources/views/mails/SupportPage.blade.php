@extends('layouts.plantilla')
@section('title','Soporte de usuario')
@section('content')
<body width="100%" style="margin: 0; padding: 0 !important; mso-line-height-rule: exactly; background-color: #f1f1f1;">
    <center style="width: 100%; background-color: #f1f1f1;">
        <div
            style="display: none; font-size: 1px;max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden; mso-hide: all; font-family: sans-serif;">
            &zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;
        </div>
        <div style="max-width: 600px; margin: 0 auto;" class="email-container">
            <!-- BEGIN BODY -->
            <table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
                style="margin: auto;">
                <tr>
                    <td valign="top" class="bg_white" style="padding: 2.5em 2.5em 0 2.5em; background-color: #ffffff;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td class="logo" style="text-align: left;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="width: 120px;" >
                                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="120" alt="Drbank" style="display: block;"/><!--Se cambio por Drbank-->
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr><!-- end tr -->
                <tr>
                    <td valign="middle" class="hero bg_white" style="padding: 2em 0 2em 0; background-color: #ffffff;">
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="padding: 0 2.5em; text-align: center; padding-bottom: 1.5em;">
                                    <div class="text">
                                        <p style="text-align: left;font-size: 20px;font-weight: 400;color: #000000;">{{$hello}} {{ $name }}</p>
                                        <h2 style="text-align: left;font-size: 18px;font-weight: 400;color: #000000;">{{$goodDay}}</h2>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0 2.5em; text-align: center; padding-bottom: 0em;">
                                    <p style="text-align: left;color: #000000;">{{$detailSupport}}</p>
                                    <ul style="text-align: left;color: #000000;">
                                        <li>{{$emailSupport}} <a href="mailto:jorge.limo@ourlimm.com" style="text-decoration:none; color: #0096FF;" >{{ $email }}</a></li>
                                        <li>{{$reason}} {{ $reasonAnswer }}</li>
                                        <li>{{$messageSupport}} {{ $description }}</li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 0 2.5em; text-align: center; padding-bottom: 0em;">
                                    <div class="text">
                                        <p style="text-align: left;font-size: 20px;font-weight: 400;color: #000000;margin-bottom: 10px;">{{$haveGoodDay}}</p>
                                        <h2 style="text-align: left;font-size: 18px;font-weight: 400;color: #000000;">{{$drbankTeam}}</h2> <!--Se cambio por Drbank-->
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr><!-- end tr -->
                <!-- 1 Column Text + Button : END -->
            </table>
            <table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
                style="margin: auto;">
                <tr>
                    <td valign="middle" class="bg_light footer email-section" style="background-color: #f8f9fa; padding: 20px;">
                        <table>
                            <tr>
                                <td valign="top" width="100%" style="padding-top: 20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="text-align: left; padding-right: 10px;">
                                                <div>
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 80px;" >
                                                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="80" alt="Drbank" style="display: block;"/><!--Se cambio por Drbank-->
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <p style="color: #777777; font-size: 13px;">{{$messageTo}} <a href="mailto:jorge.limo@ourlimm.com" style="color: #0096FF; font-weight: 600;text-decoration: none;">{{ $email }}</a>. {{$messageHaveQuestion}}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr><!-- end: tr -->
                <tr>
                    <td class="bg_light" style="text-align: center; background-color: #f8f9fa; padding: 15px;">
                        <p style="font-size: 12px; color: #999999;">Copyright © {{ date('Y') }} {{$drbank}}. {{$reserved}}</p><!--Se cambio por Drbank-->
                        <p style="font-size: 12px; color: #999999;">Powered by <a href="https://www.ourlimm.tech/" target="_blank" style="color: #999999;">Ourlimm Technologies</a></p>
                    </td>
                </tr>
            </table>

        </div>
    </center>
</body>
@endsection