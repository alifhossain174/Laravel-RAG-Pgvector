<?php

namespace Tests\Unit;

use App\Services\LlmService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class LlmServiceTest extends TestCase
{
    public function test_answer_with_context_returns_gemini_answer(): void
    {
        config([
            'services.llm.provider' => 'gemini',
            'services.llm.temperature' => 0.2,
            'services.llm.max_output_tokens' => 1200,
            'services.gemini.api_key' => 'test-key',
            'services.gemini.chat_model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Use shorter title tags [SEO Report, page 2].'],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 100,
                    'candidatesTokenCount' => 12,
                ],
            ]),
        ]);

        $result = app(LlmService::class)->answerWithContext(
            question: 'What should I fix?',
            retrievedChunks: [
                [
                    'document_title' => 'SEO Report',
                    'page_start' => 2,
                    'page_end' => 2,
                    'content' => 'The title tag should be shortened.',
                    'score' => 0.91,
                ],
            ],
            conversationHistory: [
                ['role' => 'user', 'content' => 'Review SEO issues.'],
            ],
        );

        $this->assertSame('Use shorter title tags [SEO Report, page 2].', $result['answer']);
        $this->assertSame('gemini', $result['provider']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertSame('STOP', $result['raw']['finishReason']);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'];

            return $request->hasHeader('x-goog-api-key', 'test-key')
                && $request['generationConfig']['temperature'] === 0.2
                && $request['generationConfig']['maxOutputTokens'] === 1200
                && str_contains($prompt, 'Answer only from the selected document context')
                && str_contains($prompt, 'I could not find this information in the selected documents.')
                && str_contains($prompt, 'Document: SEO Report')
                && str_contains($prompt, 'Page range: page 2')
                && str_contains($prompt, 'Question:')
                && str_contains($prompt, 'What should I fix?');
        });
    }

    public function test_answer_with_context_continues_when_gemini_hits_max_tokens(): void
    {
        config([
            'services.llm.provider' => 'gemini',
            'services.llm.temperature' => 0.2,
            'services.llm.max_output_tokens' => 100,
            'services.llm.continuation_attempts' => 1,
            'services.gemini.api_key' => 'test-key',
            'services.gemini.chat_model' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => "**Performance:**\n- **Website Load Speed:**"],
                                ],
                            ],
                            'finishReason' => 'MAX_TOKENS',
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 100,
                        'candidatesTokenCount' => 100,
                    ],
                ])
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Needs improvement based on the audit findings [SEO Report, page 4].'],
                                ],
                            ],
                            'finishReason' => 'STOP',
                        ],
                    ],
                ]),
        ]);

        $result = app(LlmService::class)->answerWithContext(
            question: 'Summarize this document',
            retrievedChunks: [
                [
                    'document_title' => 'SEO Report',
                    'page_start' => 4,
                    'page_end' => 4,
                    'content' => 'Website load speed needs improvement.',
                    'score' => 0.91,
                ],
            ],
        );

        $this->assertStringContainsString('Website Load Speed', $result['answer']);
        $this->assertStringContainsString('Needs improvement', $result['answer']);
        $this->assertSame('MAX_TOKENS', $result['raw']['finishReason']);
        $this->assertSame('STOP', $result['raw']['continuationFinishReason']);
        $this->assertTrue($result['raw']['continuationUsed']);
        $this->assertFalse($result['raw']['truncated']);

        Http::assertSentCount(2);
    }

    public function test_answer_with_context_requires_api_key(): void
    {
        config([
            'services.llm.provider' => 'gemini',
            'services.gemini.api_key' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini API key is missing');

        app(LlmService::class)->answerWithContext('hello', []);
    }

    public function test_answer_with_context_requires_supported_provider(): void
    {
        config([
            'services.llm.provider' => 'openai',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported LLM provider');

        app(LlmService::class)->answerWithContext('hello', []);
    }
}
