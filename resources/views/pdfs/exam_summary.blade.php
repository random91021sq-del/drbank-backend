<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 30mm 15mm; }
        body { font-family: 'Helvetica', Arial, sans-serif; color: #222; font-size: 12px; }
        .wrap { width: 100%; margin: 0 auto; }

        /* Header */
        .pdf-header { text-align: center; margin-bottom: 18px; }
        .pdf-header img { width: 120px; height: auto; display: block; margin: 0 auto 6px; }
        .pdf-title { color: #0b6fbf; font-size: 22px; font-weight: 700; margin: 0 0 6px; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 6px; vertical-align: top; }
        .meta-label { color: #666; width: 160px; font-weight: 700; }
        .meta-value { color: #222; }

        /* Section */
        .section-title { margin: 6px 0 8px 0; font-size: 16px; color: #0b6fbf; font-weight: 700; border-bottom: 1px solid #e6eef8; padding-bottom: 6px; }

        /* Question card */
        .question { background: #ffffff; border: 1px solid #e6eef8; border-radius: 8px; padding: 12px; margin: 10px 0; box-shadow: 0 2px 6px rgba(11,111,191,0.06); }
        .question-title { font-weight: 700; color: #0b6fbf; margin-bottom: 8px; font-size: 13px; }
        .question-text { color: #333; margin-bottom: 8px; text-align: justify; }
        .options { margin: 6px 0 10px 0; padding: 0; list-style: none; }
        .option { display: block; margin-bottom: 6px; }
        .marker { display: inline-block; color: #0b6fbf; font-weight: 700; margin-right: 6px; min-width: 18px; }
        .opt-text { color: #222; text-align: justify; display: inline-block; vertical-align: top; width: calc(100% - 28px); line-height: 1.4; }
        .option.correct .marker { color: #0b6fbf; background: transparent; padding: 0; border-radius: 0; }
        .option.correct .opt-text { font-weight: normal; }
        .answer-line { margin-top: 8px; padding: 10px; background: #f3f7fb; border-radius: 6px; color: #1b1b1b; }
        .fill-line { display: inline-block; width: 75%; border-bottom: 1px solid #c9d6e8; margin-left: 10px; height: 14px; vertical-align: middle; }

        /* Second part styling */
        .answer-block { padding: 10px; background: #fcfcfd; border-left: 4px solid #f3b000; border-radius: 4px; margin-top: 2px; }
        .answer-field { margin-bottom: 8px; }
        .answer-field strong { color: #333; display: inline; vertical-align: top; }
        .justified { text-align: justify; color: #222; }
        .correct-label { display: inline-block; background: #0b6fbf; color: #fff; padding: 2px 8px; border-radius: 4px; font-weight: 700; }

        /* Footer */
        .pdf-footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 10px; color: #888; }

        /* Page break rules */
        .question { page-break-inside: avoid; }
        .answer-block { page-break-inside: avoid; }

        /* Small screens / safety */
        @media print {
            .pdf-header img { width: 110px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="pdf-header">
            <img src="{{ $logo ?? 'https://drbank.startupdev.tech/assets/images/logo/logo.png' }}" alt="DrBank Logo">
            <div class="pdf-title">Resumen de Examen</div>
        </div>

        <table class="meta-table meta">
            <tr>
                <td class="meta-label">Título:</td>
                <td class="meta-value">{{ $exam['title'] ?? '-' }}</td>
                <td class="meta-label">Total preguntas:</td>
                <td class="meta-value">{{ $exam['total_questions'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Porcentaje:</td>
                <td class="meta-value">{{ $exam['score_percentage'] ?? '-' }}</td>
                <td class="meta-label">Tiempo (s):</td>
                <td class="meta-value">{{ $exam['time_spent'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Inicio:</td>
                <td class="meta-value">{{ $exam['started_at'] ?? '-' }}</td>
                <td class="meta-label">Finalización:</td>
                <td class="meta-value">{{ $exam['completed_at'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Estado:</td>
                <td class="meta-value">{{ $exam['status'] ?? '-' }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <div class="section-title">Examen</div>

        @php
            $summary = $exam['exam_summary'] ?? [];
            if (is_string($summary)) {
                $summary = json_decode($summary, true) ?: [];
            }
        @endphp

        @forelse ($summary as $index => $item)
            <div class="question">
                <div class="question-title">Pregunta {{ $index + 1 }}</div>
                <div class="question-text">{{ $item['question'] ?? '---' }}</div>

                @php
                    $correct = $item['correct'] ?? $item['answer'] ?? $item['correct_answer'] ?? null;
                @endphp

                <ul class="options">
                    @if (! empty($item['alt_a']))
                        <li class="option"><span class="marker">a)</span><div class="opt-text">{{ $item['alt_a'] }}</div></li>
                    @endif
                    @if (! empty($item['alt_b']))
                        <li class="option"><span class="marker">b)</span><div class="opt-text">{{ $item['alt_b'] }}</div></li>
                    @endif
                    @if (! empty($item['alt_c']))
                        <li class="option"><span class="marker">c)</span><div class="opt-text">{{ $item['alt_c'] }}</div></li>
                    @endif
                    @if (! empty($item['alt_d']))
                        <li class="option"><span class="marker">d)</span><div class="opt-text">{{ $item['alt_d'] }}</div></li>
                    @endif
                    @if (! empty($item['alt_e']))
                        <li class="option"><span class="marker">e)</span><div class="opt-text">{{ $item['alt_e'] }}</div></li>
                    @endif
                </ul>

                <div class="answer-line">Respuesta: <span class="fill-line"></span></div>

            </div>
        @empty
            <p>No hay preguntas registradas en el examen.</p>
        @endforelse

        <div style="page-break-before: always;"></div>
        <div class="section-title">Resolución del examen</div>

        @forelse ($summary as $index => $item)
            <div class="question">
                <div class="question-title">Pregunta {{ $index + 1 }}</div>
                <div class="question-text">{{ $item['question'] ?? '---' }}</div>

                @php
                    $correct = $item['correct'] ?? $item['answer'] ?? $item['correct_answer'] ?? null;
                    $userResp = $item['response'] ?? null;
                @endphp

                <div class="answer-block">
                    <div class="answer-field"><strong>Respuesta correcta:</strong> {{ $correct ?? '-' }}</div>

                    <div class="answer-field"><strong>Justificación:</strong>
                        <div class="justified">{{ $item['justification'] ?? '-' }}</div>
                    </div>

                    <div class="answer-field"><strong>Referencia:</strong>
                        <div class="justified">{{ $item['reference'] ?? '-' }}</div>
                    </div>

                    <div class="answer-field"><strong>Análisis de distractores:</strong>
                        <div class="justified">{{ $item['distractor_analysis'] ?? '-' }}</div>
                    </div>
                </div>
            </div>
        @empty
            <p>No hay respuestas registradas en el examen.</p>
        @endforelse

        <div class="pdf-footer">DrBank · Resumen de examen · Página <span class="pageNumber"></span></div>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $pdf->page_text(520, 820, "Página {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(136/255,136/255,136/255));
        }
    </script>
</body>
</html>
