<?php

declare(strict_types=1);

namespace TimAlexander\OnebrcPhp;

final class Main
{
    private const CHUNK_SIZE = 8192 * 1024; // 8MB chunks for efficient I/O
    
    public function run(): void
    {
        $file = __DIR__ . '/../measurements-1000000000.txt';
        
        if (!file_exists($file)) {
            throw new \RuntimeException("File not found: $file");
        }
        
        $startTime = microtime(true);
        echo "Processing file: $file\n";
        
        $stations = $this->processFile($file);
        $this->outputResults($stations);
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        echo "\nProcessing completed in {$duration} seconds\n";
    }
    
    private function processFile(string $filename): array
    {
        $handle = fopen($filename, 'rb');
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: $filename");
        }
        
        $stations = [];
        $buffer = '';
        $lineCount = 0;
        
        while (!feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            if ($chunk === false) {
                break;
            }
            
            $buffer .= $chunk;
            
            // Process complete lines from buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                if ($line !== '') {
                    $this->processLine($line, $stations);
                    $lineCount++;
                    
                    // Progress indicator for large files
                    if ($lineCount % 10000000 === 0) {
                        echo "Processed " . number_format($lineCount) . " lines...\n";
                    }
                }
            }
        }
        
        // Process any remaining line in buffer
        if ($buffer !== '') {
            $this->processLine(trim($buffer), $stations);
            $lineCount++;
        }
        
        fclose($handle);
        
        echo "Total lines processed: " . number_format($lineCount) . "\n";
        echo "Unique stations: " . count($stations) . "\n";
        
        return $stations;
    }
    
    private function processLine(string $line, array &$stations): void
    {
        $semicolonPos = strpos($line, ';');
        if ($semicolonPos === false) {
            return; // Skip invalid lines
        }
        
        $stationName = substr($line, 0, $semicolonPos);
        $temperatureStr = substr($line, $semicolonPos + 1);
        
        // Convert to fixed-point integer (multiply by 10) to avoid float precision issues
        $temperature = (int) round((float) $temperatureStr * 10);
        
        if (!isset($stations[$stationName])) {
            $stations[$stationName] = [
                'min' => $temperature,
                'max' => $temperature,
                'sum' => $temperature,
                'count' => 1
            ];
        } else {
            $station = &$stations[$stationName];
            $station['min'] = min($station['min'], $temperature);
            $station['max'] = max($station['max'], $temperature);
            $station['sum'] += $temperature;
            $station['count']++;
        }
    }
    
    private function outputResults(array $stations): void
    {
        // Sort stations by name
        ksort($stations);
        
        echo "\nResults (station=min/mean/max):\n";
        echo "{";
        
        $first = true;
        foreach ($stations as $stationName => $data) {
            if (!$first) {
                echo ", ";
            }
            
            // Convert back from fixed-point and format to 1 decimal place
            $min = $data['min'] / 10.0;
            $max = $data['max'] / 10.0;
            $mean = $data['sum'] / ($data['count'] * 10.0);
            
            echo sprintf(
                "%s=%.1f/%.1f/%.1f",
                $stationName,
                $min,
                $mean,
                $max
            );
            
            $first = false;
        }
        
        echo "}\n";
    }
}
