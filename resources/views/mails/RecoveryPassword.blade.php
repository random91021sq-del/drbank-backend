@extends('layouts.plantilla')
@section('title','Recuperar contraseña')
@section('content')
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <!-- Hidden Preheader Text -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{$newAccess}} - Enlace para restablecer tu contraseña
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
                    <h2 style="margin: 0 0 15px 0; color: #333333; font-size: 18px; font-weight: 600;">{{$hello}}, {{$name}} {{$last_name}}</h2>
                    <p style="margin: 0 0 20px 0; color: #555555; font-size: 15px; line-height: 1.5;">{{$newAccess}}</p>
                    
                    <!-- Reset Button -->
                    <table align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 25px auto;">
                        <tr>
                            <td align="center" style="border-radius: 4px; background: #0096FF;">
                                <a href="{{$url}}" target="_blank" style="display: inline-block; padding: 12px 30px; font-family: Helvetica, Arial, sans-serif; font-size: 16px; color: #ffffff; text-decoration: none; font-weight: 600; border-radius: 4px;">Restablecer contraseña</a>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Expiration Notice -->
                    <p style="margin: 20px 0 0 0; color: #777777; font-size: 14px; line-height: 1.5;">
                        {{$messageExpired}}
                    </p>
                </td>
            </tr>
            
            <!-- Security Warning -->
            <tr>
                <td style="padding: 0 30px 20px 30px;">
                    <div style="background-color: #E6F7FF; border-left: 4px solid #0096FF; padding: 15px; border-radius: 0 4px 4px 0;">
                        <p style="margin: 0; color: #006699; font-size: 14px; line-height: 1.5; font-weight: 500;">
                            Por seguridad, no compartas el enlace con nadie al seleccionar el botón de restablecer. Nuestro equipo nunca te pedirá tu contraseña.
                        </p>
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
                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="80" alt="drbank Logo" style="display: block; width: 80px; height: auto; margin: 0 auto;">
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