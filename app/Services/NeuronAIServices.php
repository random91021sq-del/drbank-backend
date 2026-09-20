<?php

namespace App\Services;

use NeuronAI\Agent;
use NeuronAI\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\OpenAI\OpenAI;

class NeuronAIServices extends Agent
{
    /**
     * Configure the Groq provider.
     * Groq exposes an OpenAI-compatible API — we extend OpenAI
     * and point the base URI at Groq's endpoint.
     */
    protected function provider(): AIProviderInterface
    {
        return new class(
            key  : config('services.groq.key'),
            model: config('services.groq.model'),
        ) extends OpenAI {
            protected string $baseUri = 'https://api.groq.com/openai/v1';
        };
    }

    /**
     * System instructions that define the agent's role and tone.
     */
    public function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'You are an encouraging and supportive academic advisor for medical students preparing for their specialty exams.',
                'Your sole purpose is to produce personalized study and improvement recommendations based on exam performance data.',
                'You must respond ONLY with study recommendations. Do not greet, explain your process, comment on the data format, or produce any content that is not a direct recommendation for the student.',
                'You have deep knowledge of medical education and understand how knowledge gaps in specific areas and specialties affect exam outcomes.',
            ],
            steps: [
                'Read the exam data provided: score, and each failed or blank question with its area, specialty, theme, correct answer, student answer, and justification.',
                'Identify the areas and specialties where the student made the most errors or left answers blank.',
                'Group errors by area and specialty to avoid repeating the same item more than once.',
                'For each grouped area/specialty, compose one concrete, actionable study action tied to the specific themes that were missed.',
                'Order the numbered list from the most errors to the least.',
            ],
            output: [
                'Write exclusively in Spanish.',
                'DO NOT use any markdown formatting: no asterisks, no bold (**text**), no italic (*text*), no headers (##), no bullet dashes (-), no backticks. Plain text only.',
                'Follow this exact structure — no deviations:',
                '  PART 1: One short opening paragraph (2–3 sentences) stating that the evaluation reveals areas requiring focused study. Do not mention the score as a number.',
                '  PART 2: A numbered list. Each item must follow this exact pattern:',
                '    [Number]. [Area] – [Specialty]: [One or two sentences of specific, actionable study guidance for the themes missed in that area and specialty.]',
                '    Example item: "3. Ciencias Básicas – Histología: Dedica tiempo a comprender la estructura y función de las células neuroepiteliales y su papel en la percepción del olfato y el gusto. Investiga cómo infecciones como el COVID-19 pueden afectar estas células y provocar síntomas como anosmia y ageusia."',
                '  PART 3: One short closing sentence (plain, no label) encouraging the student to keep studying consistently.',
                'Each numbered item covers exactly one Area–Specialty pair. Do not combine multiple specialties into one item.',
                'Reference themes, specialties, and areas by name — never use raw numeric IDs.',
                'Keep the total response under 500 words.',
            ]
        );
    }
}
