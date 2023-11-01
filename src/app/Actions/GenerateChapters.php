<?php

namespace App\Actions;

use App\Enums\Status;
use App\Models\Process;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateChapters
{
    public function handle(Process $process, \Closure $next)
    {
        $process->update([
            'status' => Status::PROCESSING_CHAPTERS
        ]);

        $option = filter_var($process->options['chapters'], FILTER_VALIDATE_BOOLEAN);
        if (!$option) {
            return $next($process);
        }

        $chaptersAmount = $process->options['chapters_amount'];

        try {
            $completedChapterChunks = [];

            foreach($process->transcriptChunks as $chunk) {
                $completedChapterChunks[] = $this->getChapters($chunk, $chaptersAmount);
            }

            $completedChapters = $this->getCompiledChapters($completedChapterChunks, $chaptersAmount);

            $process->update([
                'chapters' => $completedChapters
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

    private function getChapters($subtitles, $chaptersAmount)
    {
        $chapters = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Jesteś montażystą wideo. Otrzymasz napisy do filmu. Musisz podsumować film jako listę {$chaptersAmount} zwięzłych rozdziałów, złożonych z nie więcej niż jednego krótkiego zdania. Każdy rozdział powinien być poprzedzony jednym znacznikiem czasu odnoszącym się do pozycji startowej w filmie. Prześlij tylko listę rozdziałów, niczego przed nimi i niczego po nich."
                ],
                [
                    'role' => 'user',
                    'content' => implode("\n", $subtitles)
                ]
            ]
        ]);

        $completedChapters = '';
        foreach($chapters->choices as $choice) {
            $completedChapters .= $choice->message->content;
        }

        return $completedChapters;
    }

    private function getCompiledChapters($chapterChunks, $chaptersAmount)
    {
        $chapters = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "Jesteś montażystą wideo. Otrzymasz napisy do filmu. Musisz podsumować film jako listę {$chaptersAmount} zwięzłych rozdziałów, złożonych z nie więcej niż jednego krótkiego zdania. Każdy rozdział powinien być poprzedzony jednym znacznikiem czasu odnoszącym się do pozycji startowej w filmie. Prześlij tylko listę rozdziałów, niczego przed nimi i niczego po nich."
                ],
                [
                    'role' => 'user',
                    'content' => implode(' ', $chapterChunks)
                ]
            ]
        ]);

        $completedChapters = '';
        foreach($chapters->choices as $choice) {
            $completedChapters .= $choice->message->content;
        }

        return $completedChapters;
    }
}
