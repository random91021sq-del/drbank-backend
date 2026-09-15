@extends('layouts.plantilla')
@section('title','Activación de perfil')
@section('content')
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!-- Hidden Preheader Text -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{$messageActivation}} - Código de activación: {{ $code }}
    </div>
    
    <!-- Email Container -->
    <div style="max-width: 600px; margin: 20px auto;">
        <!-- Main White Container -->
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: #ffffff; box-shadow: 0 2px 15px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;">
            <!-- Header Section -->
            <tr>
                <td align="center" style="padding: 30px 25px 20px 25px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td align="center" style="padding-bottom: 15px;">
                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="120" alt="Drbank Logo" style="display: block; width: 120px; height: auto; margin: 0 auto;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <h1 style="margin: 0; color: #333333; font-size: 24px; font-weight: 700;">{{$drbank}}</h1>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            
            <!-- Greeting Section -->
            <tr>
                <td style="padding: 30px 30px 20px 30px;">
                    <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">{{$hello}}, {{ $name }} {{$lastname}}</h2>
                    <p style="margin: 0 0 25px 0; color: #555555; font-size: 15px; line-height: 1.5;">{{$messageActivation}}</p>
                    
                    <!-- Activation Code - Compact Version -->
                    <div style="text-align: center; margin-bottom: 25px;">
                        <div style="display: inline-block; background-color: #0096FF; color: #ffffff; font-size: 24px; font-weight: 700; padding: 8px 20px; border-radius: 4px; letter-spacing: 1px;">
                            {{ $code }}
                        </div>
                    </div>
                </td>
            </tr>
            
            <!-- Closing Section -->
            <tr>
                <td style="padding: 0 30px 30px 30px;">
                    <p style="margin: 0 0 10px 0; color: #555555; font-size: 15px; line-height: 1.5;">{{$goodDay}}</p>
                    <p style="margin: 0; color: #555555; font-size: 15px; line-height: 1.5;">{{$drbankTeam}}</p>
                </td>
            </tr>
            
            <!-- Footer Section -->
            <tr>
                <td style="padding: 25px 30px; background-color: #f8f9fa; border-top: 1px solid #e9ecef;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td align="center" style="padding-bottom: 20px;">
                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="80" alt="Drabnk Logo" style="display: block; width: 80px; height: auto; margin: 0 auto;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding-bottom: 15px;">
                                <p style="margin: 0; color: #777777; font-size: 13px; line-height: 1.5;">
                                    {{$messageTo}} <a href="mailto:jorge.limo@ourlimm.com" style="color: #0096FF; text-decoration: none; font-weight: 600;">{{ $email }}</a>. {{$messageHaveQuestion}}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <p style="margin: 0; color: #999999; font-size: 12px; line-height: 1.5;">
                                    Copyright © {{ date('Y') }} {{$drbank}}. {{$reserved}}<br>
                                    Powered by <a href="https://www.ourlimm.tech/" target="_blank" style="color: #999999; text-decoration: none;">Ourlimm Technologies</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
@endsection