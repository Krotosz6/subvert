<?php

namespace App\Actions;

use App\Enums\Status;
use App\Models\Process;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateSummary
{
    public function handle(Process $process, \Closure $next)
    {
        $process->update([
            'status' => Status::PROCESSING_SUMMARY
        ]);

        $option = filter_var($process->options['summary'], FILTER_VALIDATE_BOOLEAN);
        if (!$option) {
            return $next($process);
        }

        try {
            $completedSummaryChunks = [];

            foreach($process->transcriptChunks as $chunk) {
                $completedSummaryChunks[] = $this->getSummary($chunk);
            }

            $completedSummary = $this->getCompiledSummary($completedSummaryChunks);

            $process->update([
                'summary' => $completedSummary
            ]);
        } catch (\Exception $e) {
            $process->update([
                'status' => Status::ERRORED,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        return $next($process);
    }

    private function getSummary($subtitles)
    {
        $summary = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Jesteś montażystą wideo. Otrzymasz napisy do filmu. Musisz streścić film w zwięzły sposób, w formie jednego akapitu, nie dłuższego niż 5 zdań. Prześlij tylko tekst podsumowania, bez żadnych dodatkowych informacji.'
                ],
                [
                    'role' => 'user',
                    'content' => implode("\n", $subtitles)
                ]
            ]
        ]);

        $completedSummary = '';
        foreach($summary->choices as $choice) {
            $completedSummary .= $choice->message->content;
        }

        return $completedSummary;
    }

    private function getCompiledSummary($summaryChunks)
    {
        $summary = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Jesteś montażystą wideo. Otrzymasz napisy do filmu. Musisz streścić film w zwięzły sposób, w formie jednego akapitu, nie dłuższego niż 5 zdań. Prześlij tylko tekst podsumowania, bez żadnych dodatkowych informacji.'
                ],
                [
                    'role' => 'user',
                    'content' => implode(' ', $summaryChunks)
                ]
            ]
        ]);

        $compiledSummary = '';
        foreach($summary->choices as $choice) {
            $compiledSummary .= $choice->message->content;
        }

        return $compiledSummary;
    }
}
