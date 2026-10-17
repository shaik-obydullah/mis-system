<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

class CsvService
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string|int|float|null>> $rows
     */
    public function export(string $filename, array $headers, array $rows): Response
    {
        $handle = fopen('php://temp', 'w');

        if ($headers) {
            fputcsv($handle, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }
}
