@extends('layouts.plantilla')
@section('title', $drbank.' - Resumen de Examen')
@section('content')
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {{ $examSummaryIntro }}
    </div>

    <div style="max-width: 700px; margin: 20px auto;">
        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: #ffffff; box-shadow: 0 2px 18px rgba(0,0,0,0.08); border-radius: 8px; overflow: hidden;">
            <!-- Header (same style as Activation/Recovery) -->
            <tr>
                <td align="center" style="padding: 28px 24px; background-color: #f8f9fa; border-bottom: 1px solid #e9ecef;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td align="center" style="padding-bottom: 12px;">
                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="120" alt="Drbank Logo" style="display: block; width: 120px; height: auto; margin: 0 auto;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center">
                                <h1 style="margin: 0; color: #0b6fbf; font-size: 22px; font-weight: 700;">{{ $drbank }}</h1>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

            <!-- Body -->
            <tr>
                <td style="padding: 28px 30px 20px 30px;">
                    <h2 style="margin: 0 0 12px 0; color: #0b6fbf; font-size: 18px; font-weight: 700;">{{ $examSummaryIntro }}</h2>
                    <p style="margin: 0 0 14px 0; color: #444444; font-size: 15px; line-height: 1.6;">Hola {{ $name ?? '' }},</p>
                    <p style="margin: 0 0 12px 0; color: #555555; font-size: 15px; line-height: 1.6;">Se han generado y adjuntado los archivos PDF con el resumen de los exámenes que solicitaste. Cada PDF contiene dos secciones: <strong>1) Examen listo para imprimir y completar</strong> y <strong>2) Respuestas con justificación, referencia y análisis de distractores</strong>.</p>
                    <p style="margin: 0; color: #555555; font-size: 15px; line-height: 1.6;">Abre los adjuntos para descargar o imprimir. Si necesitas otro formato, responde a este correo y te ayudamos.</p>
                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td style="padding: 18px 30px 24px 30px; background-color: #f8f9fa; border-top: 1px solid #e9ecef;">
                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td align="center" style="padding-bottom: 10px;">
                                <img src="https://drbank.startupdev.tech/assets/images/logo/logo.png" width="80" alt="Drbank Logo" style="display: block; width: 80px; height: auto; margin: 0 auto;">
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding-bottom: 8px; color: #777777; font-size: 13px;">
                                {{ $messageTo }} <a href="mailto:{{ $email }}" style="color: #0b6fbf; text-decoration: none; font-weight: 600;">{{ $email }}</a>. {{ $messageHaveQuestion }}
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="color: #999999; font-size: 12px;">
                                Copyright © {{ date('Y') }} {{ $drbank }}. {{ $reserved }}<br>
                                Powered by <a href="https://www.ourlimm.tech/" target="_blank" style="color: #999999; text-decoration: none;">Ourlimm Technologies</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
@endsection
