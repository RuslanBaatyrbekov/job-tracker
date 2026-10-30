<?php

namespace Tests\Feature;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected GeminiService $geminiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geminiService = new GeminiService();
    }

    public function test_it_filters_job_related_emails()
    {
        // Подготавливаем фейковый ответ от Gemini API
        $fakeJsonResponse = json_encode(['relevant_ids' => [1, 3]]);

        // Мокаем HTTP запрос
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $fakeJsonResponse]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $emails = [
            ['id' => 1, 'subject' => 'Interview request', 'from_name' => 'HR'],
            ['id' => 2, 'subject' => 'Buy our product', 'from_name' => 'Spam'],
            ['id' => 3, 'subject' => 'Application Update', 'from_name' => 'Tech Corp'],
        ];

        $result = $this->geminiService->filterJobRelatedEmails($emails);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals([1, 3], $result);
    }

    public function test_it_analyzes_email_body()
    {
        $fakeJsonResponse = json_encode([
            'company_name' => 'Beam',
            'position' => 'Backend Developer',
            'status' => 'rejected',
            'rejection_reason' => 'Looking for someone with AWS',
            'ai_advice' => 'Learn AWS basic services'
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $fakeJsonResponse]]]]]
            ], 200)
        ]);

        $result = $this->geminiService->analyzeEmailBody('Update on your application', 'Unfortunately, we are moving forward with other candidates...');

        $this->assertIsArray($result);
        $this->assertEquals('Beam', $result['company_name']);
        $this->assertEquals('rejected', $result['status']);
    }
}
